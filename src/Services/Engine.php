<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Core\Security\{Crypto,Principal};

/** Canonical test-only discovery. Queue delivery is a hint, never the source of truth. */
final readonly class Engine
{
    public Repository $repository;
    public function __construct(private Database $db,private Config $config)
    {
        $this->repository=new Repository($db,$config);
    }
    private function guard(Principal $actor): void
    {
        if($this->config->environment!=='test'||$actor->impersonating){throw new HttpError(403,'F3_TEST_ONLY');}
    }
    public function discoverBatch(Principal $actor,string $trace): int
    {
        $this->guard($actor);$count=0;
        $ids=$this->db->execute("SELECT o.id FROM orders o JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE o.origin_environment='test' AND o.status IN ('READY_FOR_PROVISIONING','REQUIRES_APPROVAL') AND o.refund_status='NONE' AND b.status='PAID' AND b.refund_status='NONE' AND EXISTS (SELECT 1 FROM order_items i JOIN catalog_products p ON p.id=i.product_id WHERE i.order_id=o.id AND p.product_type='HOSTING' AND (SELECT COUNT(*) FROM svc_services s WHERE s.order_item_id=i.id)<CAST(JSON_UNQUOTE(JSON_EXTRACT(i.snapshot,'$.quantity')) AS UNSIGNED)) ORDER BY o.created_at,o.id LIMIT 100")->fetchAll();
        foreach($ids as $row){try{$this->discover($row['id'],$actor,$trace);++$count;}catch(HttpError $e){if($e->status!==409){throw $e;}}}return $count;
    }
    /** Internal scheduler entry point; authorization of user commands belongs to the HTTP application service. */
    public function discover(string $orderId,Principal $actor,string $trace): array
    {
        $this->guard($actor);
        return $this->db->transaction(function()use($orderId,$actor,$trace): array{
            $order=$this->db->execute('SELECT * FROM orders WHERE id=? FOR UPDATE',[$orderId])->fetch();
            if(!$order){throw new HttpError(404,'ORDER_NOT_FOUND');}
            $bill=$this->db->execute('SELECT r.currency,s.status,s.refund_status FROM billing_requests r JOIN billing_request_states s ON s.request_id=r.id WHERE r.id=? AND r.order_id=? AND r.customer_id=? FOR UPDATE',[$order['payment_request_id'],$orderId,$order['customer_id']])->fetch();
            if(!$bill||$order['origin_environment']!=='test'||!in_array($order['status'],['READY_FOR_PROVISIONING','REQUIRES_APPROVAL'],true)||$order['refund_status']!=='NONE'||$bill['status']!=='PAID'||$bill['refund_status']!=='NONE'){throw new HttpError(409,'SERVICE_ORDER_NOT_ELIGIBLE');}
            $order['currency']=$bill['currency'];
            $items=$this->db->execute('SELECT * FROM order_items WHERE order_id=? ORDER BY id FOR UPDATE',[$orderId])->fetchAll();$result=[];
            foreach($items as $item){
                $product=$this->db->execute('SELECT product_type,category_id FROM catalog_products WHERE id=?',[$item['product_id']])->fetch();
                if(!$product||$product['product_type']!=='HOSTING'){continue;}
                $line=json_decode($item['snapshot'],true,64,JSON_THROW_ON_ERROR);
                for($unit=1;$unit<=$line['quantity'];++$unit){
                    $service=$this->repository->materialize($order,$item,$line,$unit,$product['category_id'],$actor,$trace);
                    if($service['status']==='QUEUED'){$this->enqueueCreate($service,$actor,$trace);}
                    $result[]=$service['id'];
                }
            }
            return $result;
        });
    }
    private function enqueueCreate(array $service,Principal $actor,string $trace): void
    {
        $this->db->requireTransaction();
        if($service['imported']||$service['origin_environment']!=='test'||$service['order_item_id']===null){throw new HttpError(409,'SERVICE_CREATE_FORBIDDEN');}
        if($service['current_operation_id']!==null){return;}
        $id=Id::uuid();$provider=Id::uuid();$request=CanonicalJson::encode(['service_id'=>$service['id'],'kind'=>'CREATE','unit'=>(int)$service['unit_no'],'config'=>$this->repository->configuration($service['id'])]);$key=base64_decode($this->config->key,true);$fingerprint=hash_hmac('sha256',$request,$key);
        $this->db->execute("INSERT INTO svc_operations(id,service_id,order_item_id,kind,generation,request_fingerprint) VALUES (?,?,?,'CREATE',?,?)",[$id,$service['id'],$service['order_item_id'],$service['unit_no'],$fingerprint]);
        $this->db->execute('INSERT INTO svc_provider_operations(id,lifecycle_operation_id,request_encrypted,request_fingerprint) VALUES (?,?,?,?)',[$provider,$id,(new Crypto($key))->encrypt($request,'svc-operation:'.$provider),$fingerprint]);
        $this->db->execute('UPDATE svc_services SET current_operation_id=?,version=version+1 WHERE id=?',[$id,$service['id']]);
        (new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'services.execute',$id,['operation_id'=>$id],$trace);
        $this->repository->event($service,'CREATE_QUEUED',$actor,$trace);
    }
    /** Claim transaction commits before a provider adapter may be invoked. */
    public function claim(Principal $actor,string $trace,?string $id=null): ?array
    {
        $this->guard($actor);
        return $this->db->transaction(function()use($actor,$trace,$id): ?array{
            $candidate=$this->db->execute("SELECT service_id,id FROM svc_operations WHERE status='PENDING'".($id!==null?' AND id=?':'').' ORDER BY created_at,id LIMIT 1',$id!==null?[$id]:[])->fetch();
            if(!$candidate){return null;}
            // Same order -> service -> operation order as discovery; cancellation cannot race dispatch acceptance.
            $sourceOrder=$this->db->execute('SELECT order_id FROM svc_services WHERE id=?',[$candidate['service_id']])->fetchColumn();
            $this->db->execute('SELECT id FROM orders WHERE id=? FOR UPDATE',[$sourceOrder]);
            $service=$this->db->execute('SELECT * FROM svc_services WHERE id=? FOR UPDATE SKIP LOCKED',[$candidate['service_id']])->fetch();
            if(!$service){return null;}
            $operation=$this->db->execute('SELECT * FROM svc_operations WHERE id=? FOR UPDATE',[$candidate['id']])->fetch();
            if($operation['status']!=='PENDING'){return null;}
            if($service['status']!=='QUEUED'||$service['imported']||$service['origin_environment']!=='test'||$service['current_operation_id']!==$operation['id']){throw new HttpError(409,'SERVICE_CLAIM_CONFLICT');}
            // Recheck cancellation/refund/payment at dispatch, not merely at discovery.
            $source=$this->db->execute('SELECT o.status,o.refund_status,s.status AS bill_status,s.refund_status AS bill_refund FROM orders o JOIN billing_request_states s ON s.request_id=o.payment_request_id WHERE o.id=?',[$service['order_id']])->fetch();
            if(!$source||$source['status']!=='READY_FOR_PROVISIONING'||$source['refund_status']!=='NONE'||$source['bill_status']!=='PAID'||$source['bill_refund']!=='NONE'){throw new HttpError(409,'SERVICE_SOURCE_CHANGED');}
            $token=Id::uuid();$this->db->execute("UPDATE svc_operations SET status='RUNNING',lease_token=?,lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND),updated_at=UTC_TIMESTAMP(6) WHERE id=?",[$token,$operation['id']]);
            $this->db->execute("UPDATE svc_provider_operations SET status='RUNNING' WHERE lifecycle_operation_id=?",[$operation['id']]);
            $this->db->execute("INSERT INTO svc_attempt_events(operation_id,lease_token,kind,safe_code) VALUES (?,?,'STARTED','DISPATCH_CLAIMED')",[$operation['id'],$token]);
            $this->repository->transition($service,'PROVISIONING',$actor,$trace);
            return $this->db->execute('SELECT * FROM svc_operations WHERE id=?',[$operation['id']])->fetch();
        });
    }
    /** Expired unsafe work becomes UNKNOWN, never available for a second create. */
    public function expire(Principal $actor,string $trace,?string $id=null): int
    {
        $this->guard($actor);$count=0;
        $candidates=$this->db->execute("SELECT id,service_id FROM svc_operations WHERE status='RUNNING' AND lease_until<=UTC_TIMESTAMP(6)".($id!==null?' AND id=?':'').' ORDER BY lease_until,id LIMIT 100',$id!==null?[$id]:[])->fetchAll();
        foreach($candidates as $candidate){$count+=$this->db->transaction(function()use($candidate,$actor,$trace): int{
            $service=$this->repository->find($candidate['service_id']);$op=$this->db->execute("SELECT * FROM svc_operations WHERE id=? AND status='RUNNING' AND lease_until<=UTC_TIMESTAMP(6) FOR UPDATE",[$candidate['id']])->fetch();
            if(!$op){return 0;}$this->unknown($service,$op,$actor,$trace,'LEASE_EXPIRED');return 1;
        });}return $count;
    }
    private function unknown(array $service,array $op,Principal $actor,string $trace,string $code): void
    {
        $this->db->execute("UPDATE svc_operations SET status='UNKNOWN',updated_at=UTC_TIMESTAMP(6) WHERE id=?",[$op['id']]);
        $this->db->execute("UPDATE svc_provider_operations SET status='UNKNOWN' WHERE lifecycle_operation_id=?",[$op['id']]);
        $this->db->execute("INSERT INTO svc_attempt_events(operation_id,lease_token,kind,safe_code) VALUES (?,?,'FINISHED',?)",[$op['id'],$op['lease_token'],$code]);
        $this->repository->transition($service,'UNKNOWN',$actor,$trace);
    }
    public function markUnknown(string $id,string $token,Principal $actor,string $trace): void
    {
        $this->guard($actor);$this->db->transaction(function()use($id,$token,$actor,$trace): void{
            $serviceId=$this->db->execute('SELECT service_id FROM svc_operations WHERE id=?',[$id])->fetchColumn();if(!$serviceId){throw new HttpError(404,'OPERATION_NOT_FOUND');}
            $service=$this->repository->find($serviceId);$op=$this->db->execute('SELECT *,lease_until>UTC_TIMESTAMP(6) AS live FROM svc_operations WHERE id=? FOR UPDATE',[$id])->fetch();
            if($op['status']!=='RUNNING'||!hash_equals((string)$op['lease_token'],$token)||!$op['live']){throw new HttpError(409,'STALE_SERVICE_LEASE');}
            $this->unknown($service,$op,$actor,$trace,'PROVIDER_OUTCOME_UNKNOWN');
        });
    }
}
