<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Audit,AuditEvent,Crypto,Principal};
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Finance\{Unsigned,Money,Currency};
final readonly class Repository
{
    public function __construct(public Database $db,private Config $config) {}
    public function find(string $id,?string $customer=null,bool $lock=true): array
    {
        if($lock){$this->db->requireTransaction();}$row=$this->db->execute('SELECT * FROM svc_services WHERE id=?'.($customer!==null?' AND customer_id=?':'').($lock?' FOR UPDATE':''),$customer!==null?[$id,$customer]:[$id])->fetch();if(!$row){throw new HttpError(404,'SERVICE_NOT_FOUND');}return $row;
    }
    public function event(array $service,string $kind,Principal $actor,string $trace): void
    {
        $this->db->requireTransaction();$id=Id::uuid();$this->db->execute('INSERT INTO svc_events(id,service_id,customer_id,kind,actor_id,trace_id) VALUES (?,?,?,?,?,?)',[$id,$service['id'],$service['customer_id'],$kind,$actor->actorId,$trace]);(new Audit($this->db))->record($actor,AuditEvent::ServiceChanged,$service['id'],$trace);(new Outbox($this->db,new Queue($this->db)))->append($id,'services.changed',$service['id'],['service_id'=>$service['id']],$trace);
    }
    public function transition(array $row,string $to,Principal $actor,string $trace): array
    {
        $this->db->requireTransaction();State::assertTransition($row['status'],$to);$this->db->execute('UPDATE svc_services SET status=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?',[$to,$row['id'],$row['version']]);$current=$this->find($row['id']);if((int)$current['version']!==(int)$row['version']+1){throw new HttpError(409,'SERVICE_VERSION_CONFLICT');}$this->event($current,'STATE_'.$to,$actor,$trace);return $current;
    }
    private function share(string $amount,int $quantity,int $unit): string
    {
        [$part,$remainder]=Unsigned::divide($amount,(string)$quantity);return $unit<=(int)$remainder?Unsigned::add($part,'1'):$part;
    }
    /** Trusted canonical-order materialization, not a public command. Authorization/eligibility belongs to claim. */
    public function materialize(array $order,array $item,array $line,int $unit,?string $category,Principal $actor,string $trace): array
    {
        $this->db->requireTransaction();if($actor->impersonating){throw new HttpError(403,'SERVICE_IMPERSONATION_FORBIDDEN');}if($this->config->environment!=='test'||($order['origin_environment']??null)!=='test'){throw new HttpError(403,'F3_TEST_ONLY');}
        $canonical=$this->db->execute('SELECT * FROM orders WHERE id=? AND customer_id=? FOR UPDATE',[$order['id'],$order['customer_id']])->fetch();$bill=$this->db->execute('SELECT r.*,s.status,s.refund_status FROM billing_requests r JOIN billing_request_states s ON s.request_id=r.id WHERE r.id=? AND r.customer_id=? FOR UPDATE',[$order['payment_request_id'],$order['customer_id']])->fetch();$source=$this->db->execute('SELECT * FROM order_items WHERE id=? AND order_id=? FOR UPDATE',[$item['id'],$order['id']])->fetch();
        if(!$canonical||!$bill||!$source||!in_array($canonical['status'],['READY_FOR_PROVISIONING','REQUIRES_APPROVAL'],true)||$canonical['origin_environment']!=='test'||$canonical['refund_status']!=='NONE'||$bill['status']!=='PAID'||$bill['refund_status']!=='NONE'||$canonical['payment_request_id']!==$bill['id']||$bill['order_id']!==$canonical['id']||$bill['currency']!==$order['currency']||CanonicalJson::fromJson($source['snapshot'])!==CanonicalJson::encode($line)){throw new HttpError(409,'SERVICE_ORDER_NOT_ELIGIBLE');}
        $order=$canonical+['currency'=>$bill['currency']];$item=$source;
        $quantity=$line['quantity']??0;if(!is_int($quantity)||$quantity<1||$quantity>1000||$unit<1||$unit>$quantity||$item['order_id']!==$order['id']){throw new HttpError(422,'INVALID_SERVICE_UNIT');}
        $old=$this->db->execute('SELECT id FROM svc_services WHERE order_item_id=? AND unit_no=? FOR UPDATE',[$item['id'],$unit])->fetchColumn();if($old){return $this->find($old);}
        $id=Id::uuid();$status=$line['activation_policy']==='MANUAL'?'PENDING_APPROVAL':'QUEUED';$this->db->execute('INSERT INTO svc_services(id,customer_id,order_id,order_item_id,unit_no,product_id,category_id,name,origin_environment,status) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$order['customer_id'],$order['id'],$item['id'],$unit,$item['product_id'],$category,$line['name'],$order['origin_environment'],$status]);
        $this->saveConfig($id,['original_line'=>$line,'selection'=>array_replace($line['selection'],['quantity'=>1])]);$net=$this->share($line['net_minor'],$quantity,$unit);$tax=$this->share($line['tax_minor'],$quantity,$unit);$total=(new Money($net,new Currency($order['currency'])))->add(new Money($tax,new Currency($order['currency'])))->minor;$price=Id::uuid();
        $this->db->execute('INSERT INTO svc_prices(id,service_id,customer_id,currency,net_minor,tax_minor,total_minor,snapshot) VALUES (?,?,?,?,?,?,?,?)',[$price,$id,$order['customer_id'],$order['currency'],$net,$tax,$total,CanonicalJson::encode($line)]);$period=Id::uuid();$this->db->execute("INSERT INTO svc_periods(id,service_id,customer_id,generation,billing_request_id,price_id,cycle_snapshot,status) VALUES (?,?,?,1,?,?,?,'PENDING_PROVIDER')",[$period,$id,$order['customer_id'],$order['payment_request_id'],$price,CanonicalJson::encode($line['cycle'])]);$this->db->execute('UPDATE svc_services SET current_period_id=? WHERE id=?',[$period,$id]);
        $this->db->execute("INSERT INTO svc_ownership_history(id,service_id,to_customer_id,reason,actor_id,trace_id) VALUES (?,?,?,'INITIAL',?,?)",[Id::uuid(),$id,$order['customer_id'],$actor->actorId,$trace]);$row=$this->find($id);$this->event($row,'MATERIALIZED',$actor,$trace);return $row;
    }
    public function saveConfig(string $service,array $snapshot): string
    {
        $this->db->requireTransaction();$this->find($service);$version=1+(int)$this->db->execute('SELECT COALESCE(MAX(version),0) FROM svc_configs WHERE service_id=?',[$service])->fetchColumn();$id=Id::uuid();$json=CanonicalJson::encode($snapshot);$key=base64_decode($this->config->key,true);$this->db->execute('INSERT INTO svc_configs(id,service_id,version,snapshot_encrypted,fingerprint) VALUES (?,?,?,?,?)',[$id,$service,$version,(new Crypto($key))->encrypt($json,'svc-config:'.$id),hash_hmac('sha256',$json,$key)]);return $id;
    }
    public function configuration(string $service): array
    {
        $row=$this->db->execute('SELECT * FROM svc_configs WHERE service_id=? ORDER BY version DESC LIMIT 1',[$service])->fetch();if(!$row){throw new HttpError(409,'SERVICE_CONFIG_MISSING');}$key=base64_decode($this->config->key,true);$json=(new Crypto($key))->decrypt($row['snapshot_encrypted'],'svc-config:'.$row['id']);if(!hash_equals($row['fingerprint'],hash_hmac('sha256',$json,$key))){throw new HttpError(409,'SERVICE_CONFIG_INTEGRITY');}return json_decode($json,true,64,JSON_THROW_ON_ERROR);
    }
}
