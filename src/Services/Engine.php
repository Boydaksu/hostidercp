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
        $this->guard($actor);$count=0;$deadline=hrtime(true)+20_000_000_000;
        $ids=$this->db->execute("SELECT o.id FROM orders o JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE o.origin_environment='test' AND o.status IN ('READY_FOR_PROVISIONING','REQUIRES_APPROVAL') AND o.refund_status='NONE' AND b.status='PAID' AND b.refund_status='NONE' AND NOT EXISTS(SELECT 1 FROM svc_renewals r WHERE r.order_id=o.id) AND NOT EXISTS(SELECT 1 FROM svc_package_changes c WHERE c.order_id=o.id) AND EXISTS (SELECT 1 FROM order_items i JOIN catalog_products p ON p.id=i.product_id WHERE i.order_id=o.id AND p.product_type='HOSTING' AND (SELECT COUNT(*) FROM svc_services s WHERE s.order_item_id=i.id)<CAST(JSON_UNQUOTE(JSON_EXTRACT(i.snapshot,'$.quantity')) AS UNSIGNED)) ORDER BY o.created_at,o.id LIMIT 100")->fetchAll();
        foreach($ids as $row){if(hrtime(true)>$deadline){break;}try{$this->discover($row['id'],$actor,$trace);++$count;}catch(HttpError $e){if($e->status!==409){throw $e;}}}return $count;
    }
    /** Internal scheduler entry point; authorization of user commands belongs to the HTTP application service. */
    public function discover(string $orderId,Principal $actor,string $trace): array
    {
        $this->guard($actor);
        return $this->db->transaction(function()use($orderId,$actor,$trace): array{
            $order=$this->db->execute('SELECT * FROM orders WHERE id=? FOR UPDATE',[$orderId])->fetch();
            if(!$order){throw new HttpError(404,'ORDER_NOT_FOUND');}
            if($this->db->execute('SELECT 1 FROM svc_renewals WHERE order_id=?',[$orderId])->fetchColumn()){throw new HttpError(409,'RENEWAL_IS_NOT_A_NEW_SERVICE');}
            if($this->db->execute('SELECT 1 FROM svc_package_changes WHERE order_id=?',[$orderId])->fetchColumn()){throw new HttpError(409,'PACKAGE_CHANGE_IS_NOT_A_NEW_SERVICE');}
            $bill=$this->db->execute('SELECT r.currency,s.status,s.refund_status FROM billing_requests r JOIN billing_request_states s ON s.request_id=r.id WHERE r.id=? AND r.order_id=? AND r.customer_id=? FOR UPDATE',[$order['payment_request_id'],$orderId,$order['customer_id']])->fetch();
            if(!$bill||$order['origin_environment']!=='test'||!in_array($order['status'],['READY_FOR_PROVISIONING','REQUIRES_APPROVAL'],true)||$order['refund_status']!=='NONE'||$bill['status']!=='PAID'||$bill['refund_status']!=='NONE'){throw new HttpError(409,'SERVICE_ORDER_NOT_ELIGIBLE');}
            $order['currency']=$bill['currency'];
            $items=$this->db->execute('SELECT * FROM order_items WHERE order_id=? ORDER BY id FOR UPDATE',[$orderId])->fetchAll();$result=[];$created=0;$deadline=hrtime(true)+5_000_000_000;
            foreach($items as $item){
                $product=$this->db->execute('SELECT product_type,category_id FROM catalog_products WHERE id=?',[$item['product_id']])->fetch();
                if(!$product||$product['product_type']!=='HOSTING'){continue;}
                $line=json_decode($item['snapshot'],true,64,JSON_THROW_ON_ERROR);
                $existing=[];foreach($this->db->execute('SELECT * FROM svc_services WHERE order_item_id=? ORDER BY unit_no FOR UPDATE',[$item['id']])->fetchAll() as $row){$existing[(int)$row['unit_no']]=$row;}
                for($unit=1;$unit<=$line['quantity'];++$unit){
                    if($created>=100||hrtime(true)>$deadline){return $result;}
                    $service=$existing[$unit]??$this->repository->materialize($order,$item,$line,$unit,$product['category_id'],$actor,$trace);if(!isset($existing[$unit])){++$created;}
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
    public function approve(string $id,string $customer,Principal $actor,string $trace): void
    {
        $this->guard($actor);(new \Hostider\Core\Security\Authorization())->require($actor,'services.approve',$customer,privileged:true,service:$id);if(!$actor->staff){throw new HttpError(403,'STAFF_REQUIRED');}
        $work=function()use($id,$customer,$actor,$trace): void{
            $identity=$this->repository->find($id,$customer,false);$this->db->execute('SELECT id FROM orders WHERE id=? FOR UPDATE',[$identity['order_id']]);$this->db->execute('SELECT id FROM order_items WHERE id=? FOR UPDATE',[$identity['order_item_id']]);$service=$this->repository->find($id,$customer);
            if($service['status']!=='PENDING_APPROVAL'||$service['imported']){throw new HttpError(409,'SERVICE_NOT_AWAITING_APPROVAL');}
            $source=$this->db->execute('SELECT o.status,o.refund_status,b.status AS bill_status,b.refund_status AS bill_refund FROM orders o JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE o.id=? FOR UPDATE',[$service['order_id']])->fetch();if(!$source||!in_array($source['status'],['REQUIRES_APPROVAL','READY_FOR_PROVISIONING'],true)||$source['refund_status']!=='NONE'||$source['bill_status']!=='PAID'||$source['bill_refund']!=='NONE'){throw new HttpError(409,'SERVICE_SOURCE_CHANGED');}
            $this->db->execute('INSERT INTO svc_approvals(service_id,actor_id,trace_id) VALUES (?,?,?)',[$id,$actor->actorId,$trace]);$service=$this->repository->transition($service,'QUEUED',$actor,$trace);$this->enqueueCreate($service,$actor,$trace);
        };if($this->db->pdo->inTransaction()){$work();}else{$this->db->transaction($work);}
    }
    private function sourceDispatchable(?array $source,array $service): bool
    {
        if(!$source||$source['refund_status']!=='NONE'||$source['bill_status']!=='PAID'||$source['bill_refund']!=='NONE'){return false;}
        return $source['status']==='READY_FOR_PROVISIONING'||($source['status']==='REQUIRES_APPROVAL'&&(bool)$this->db->execute('SELECT 1 FROM svc_approvals WHERE service_id=?',[$service['id']])->fetchColumn());
    }
    /** Claim transaction commits before a provider adapter may be invoked. */
    public function claim(Principal $actor,string $trace,?string $id=null,bool $reserve=false): ?array
    {
        $this->guard($actor);
        return $this->db->transaction(function()use($actor,$trace,$id,$reserve): ?array{
            $candidate=$this->db->execute("SELECT service_id,id FROM svc_operations WHERE status='PENDING' AND kind='CREATE'".($id!==null?' AND id=?':'').' ORDER BY created_at,id LIMIT 1',$id!==null?[$id]:[])->fetch();
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
            if(!$this->sourceDispatchable($source?:null,$service)){throw new HttpError(409,'SERVICE_SOURCE_CHANGED');}
            if($reserve){$provider=$this->db->execute('SELECT * FROM svc_provider_operations WHERE lifecycle_operation_id=? FOR UPDATE',[$operation['id']])->fetch();$key=base64_decode($this->config->key,true);$json=(new Crypto($key))->decrypt($provider['request_encrypted'],'svc-operation:'.$provider['id']);if(!hash_equals($operation['request_fingerprint'],$provider['request_fingerprint'])||!hash_equals($provider['request_fingerprint'],hash_hmac('sha256',$json,$key))){throw new HttpError(409,'SERVICE_REQUEST_INTEGRITY');}$frozen=json_decode($json,true,64,JSON_THROW_ON_ERROR);$reservation=(new \Hostider\Hosting\Pools($this->db,$this->config))->reserve($service,$operation['id'],$frozen['config']['selection']);$this->db->execute('UPDATE svc_provider_operations SET server_id=? WHERE lifecycle_operation_id=?',[$reservation['server_id'],$operation['id']]);}
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
        if($op['kind']==='RENEW'){$this->db->execute("UPDATE svc_periods p JOIN svc_renewals r ON r.period_id=p.id SET p.status='UNKNOWN',p.version=p.version+1 WHERE r.operation_id=?",[$op['id']]);$this->db->execute("UPDATE svc_renewals SET status='UNKNOWN' WHERE operation_id=?",[$op['id']]);}
        if($op['kind']==='CHANGE_PACKAGE'){$this->db->execute("UPDATE svc_package_changes SET status='UNKNOWN' WHERE operation_id=?",[$op['id']]);}
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
    public function finishMock(string $id,string $token,\Hostider\Providers\Contract\ProviderResult $result,?array $proof,Principal $actor,string $trace): void
    {
        $this->guard($actor);$this->db->transaction(function()use($id,$token,$result,$proof,$actor,$trace): void{
            $identity=$this->db->execute('SELECT o.service_id,s.order_id FROM svc_operations o JOIN svc_services s ON s.id=o.service_id WHERE o.id=?',[$id])->fetch();if(!$identity){throw new HttpError(404,'OPERATION_NOT_FOUND');}
            $source=$this->db->execute('SELECT o.status,o.refund_status,b.status AS bill_status,b.refund_status AS bill_refund FROM orders o JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE o.id=? FOR UPDATE',[$identity['order_id']])->fetch();
            $service=$this->repository->find($identity['service_id']);$op=$this->db->execute('SELECT *,lease_until>UTC_TIMESTAMP(6) AS live FROM svc_operations WHERE id=? FOR UPDATE',[$id])->fetch();$provider=$this->db->execute('SELECT * FROM svc_provider_operations WHERE lifecycle_operation_id=? FOR UPDATE',[$id])->fetch();
            if($op['status']!=='RUNNING'||!hash_equals((string)$op['lease_token'],$token)||!$op['live']||$service['current_operation_id']!==$id){throw new HttpError(409,'STALE_SERVICE_LEASE');}
            if($result->operationId!==$provider['id']||$result->provider!=='cpanel'||$result->environment!=='mock'){throw new HttpError(409,'PROVIDER_RESULT_BINDING');}
            if(!$this->sourceDispatchable($source?:null,$service)){$this->unknown($service,$op,$actor,$trace,'SOURCE_CHANGED_AFTER_DISPATCH');return;}
            if($result->status==='UNKNOWN'){$this->unknown($service,$op,$actor,$trace,'PROVIDER_OUTCOME_UNKNOWN');return;}
            $success=$result->status==='SUCCESS';
            if($success){
                $period=$this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE',[$service['current_period_id']])->fetch();
                $reservation=$this->db->execute('SELECT package_name FROM hosting_reservations WHERE operation_id=?',[$id])->fetch();
                if(!$proof||!$reservation||$proof['package_name']!==$reservation['package_name']||$result->externalResourceId!==$proof['id']||$proof['server_id']!==$provider['server_id']||$proof['service_id']!==$service['id']||$proof['create_operation_id']!==$provider['id']||$proof['status']!=='ACTIVE'||$period['status']!=='PENDING_PROVIDER'){$this->unknown($service,$op,$actor,$trace,'INSPECT_PROOF_MISMATCH');return;}
                $cycle=json_decode($period['cycle_snapshot'],true,32,JSON_THROW_ON_ERROR);$start=new \DateTimeImmutable($proof['starts_at'],new \DateTimeZone('UTC'));$end=$cycle['kind']==='ONCE'?null:\Hostider\Catalog\BillingCycle::next($start,$cycle['kind'],(int)$cycle['interval_count'])->format('Y-m-d H:i:s.u');
                if($proof['ends_at']!==$end){$this->unknown($service,$op,$actor,$trace,'INSPECT_PERIOD_MISMATCH');return;}
                $key=base64_decode($this->config->key,true);$json=CanonicalJson::encode($proof);$hash=hash_hmac('sha256',$json,$key);$evidence=Id::uuid();
                $this->db->execute('INSERT INTO svc_resources(id,service_id,server_id,external_id,operation_id,evidence_hash) VALUES (?,?,?,?,?,?)',[Id::uuid(),$service['id'],$provider['server_id'],$proof['id'],$id,$hash]);
                $this->db->execute('INSERT INTO svc_period_evidence(id,period_id,operation_id,evidence_encrypted,fingerprint) VALUES (?,?,?,?,?)',[$evidence,$period['id'],$id,(new Crypto($key))->encrypt($json,'svc-evidence:'.$evidence),$hash]);
                $this->db->execute("UPDATE svc_periods SET starts_at=?,ends_at=?,status='VERIFIED',version=version+1 WHERE id=?",[$proof['starts_at'],$proof['ends_at'],$period['id']]);
            }else{(new \Hostider\Hosting\Pools($this->db,$this->config))->releaseDefinitiveFailure($id);}
            $state=$success?'SUCCEEDED':'FAILED';$this->db->execute('UPDATE svc_operations SET status=?,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$state,$id]);$this->db->execute('UPDATE svc_provider_operations SET status=? WHERE id=?',[$state,$provider['id']]);
            $this->db->execute("INSERT INTO svc_attempt_events(operation_id,lease_token,kind,safe_code) VALUES (?,?,'FINISHED',?)",[$id,$token,$success?'MOCK_INSPECT_VERIFIED':'MOCK_DEFINITIVE_FAILURE']);$this->repository->transition($service,$success?'ACTIVE':'FAILED',$actor,$trace);
        });
    }
}
