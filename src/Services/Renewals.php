<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{Authorization,Crypto,Principal};
use Hostider\Finance\{Currency,Money};use Hostider\Pricing\{PriceSnapshot,RenewalPricePolicy};
/** New immutable F2 order/bill for each service generation; payment never extends a period. */
final readonly class Renewals
{
    private Repository $repository;
    private \Hostider\Commerce\Services $commerce;
    public function __construct(private Database $db,private Config $config){$this->repository=new Repository($db,$config);$this->commerce=new \Hostider\Commerce\Services($db,$config);}
    private function authorize(Principal $actor,string $customer,string $service): void
    {
        if($this->config->environment!=='test'){throw new HttpError(403,'F3_TEST_ONLY');}(new Authorization())->require($actor,'services.renew',$customer,privileged:true,service:$service);
    }
    private function transaction(\Closure $work): mixed{return $this->db->pdo->inTransaction()?$work():$this->db->transaction($work);}
    private function locked(string $id,string $customer): array
    {
        $identity=$this->repository->find($id,$customer,false);$this->db->execute('SELECT id FROM orders WHERE id=? FOR UPDATE',[$identity['order_id']]);$this->db->execute('SELECT id FROM order_items WHERE id=? FOR UPDATE',[$identity['order_item_id']]);return $this->repository->find($id,$customer);
    }
    public function plan(string $id,string $customer,Principal $actor,\DateTimeImmutable $now): PriceSnapshot
    {
        $this->authorize($actor,$customer,$id);$service=$this->repository->find($id,$customer,false);
        if(!in_array($service['status'],['ACTIVE','SUSPENDED'],true)||$service['imported']){throw new HttpError(409,'SERVICE_NOT_RENEWABLE');}
        $period=$this->db->execute('SELECT * FROM svc_periods WHERE id=?',[$service['current_period_id']])->fetch();if(!$period||$period['status']!=='VERIFIED'||$period['ends_at']===null){throw new HttpError(409,'VERIFIED_RECURRING_PERIOD_REQUIRED');}
        $policy=(new Policies($this->db,$this->config))->resolve($service);$protected=Policies::required($policy,'price_protected');$notice=Policies::required($policy,'price_notice_required');$selection=$this->repository->configuration($id)['selection'];$price=$this->db->execute('SELECT * FROM svc_prices WHERE id=?',[$service['current_price_id']??$period['price_id']])->fetch();$current=$this->commerce->quotes->plan($customer,new Currency($price['currency']),[$selection],[],$now,true);$frozen=null;
        if($protected){$old=json_decode($price['snapshot'],true,64,JSON_THROW_ON_ERROR);foreach($old['sources'] as $source){if($source['charge_kind']==='ONCE'){throw new HttpError(409,'PROTECTED_RECURRING_COMPONENT_PRICE_REQUIRED');}}$data=$current->jsonSerialize();$line=array_replace($data['lines'][0],['net_minor'=>(string)$price['net_minor'],'tax_minor'=>(string)$price['tax_minor'],'total_minor'=>(string)$price['total_minor'],'original_minor'=>(string)$price['net_minor'],'discount_minor'=>'0','promotions'=>[],'sources'=>$old['sources']]);$data=array_replace($data,['net_minor'=>$line['net_minor'],'tax_minor'=>$line['tax_minor'],'total_minor'=>$line['total_minor'],'lines'=>[$line],'promotions'=>[]]);$frozen=new PriceSnapshot($data);}
        // A held mock notification is not delivery proof. Required notices remain fail-closed.
        return (new RenewalPricePolicy())->resolve($current,$frozen,$protected,$notice,false);
    }
    public function request(string $id,string $customer,Principal $actor,string $trace,\DateTimeImmutable $now): array
    {
        $this->authorize($actor,$customer,$id);return $this->transaction(function()use($id,$customer,$actor,$trace,$now): array{
            $service=$this->locked($id,$customer);$period=$this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE',[$service['current_period_id']])->fetch();$generation=(int)$period['generation']+1;$old=$this->db->execute('SELECT * FROM svc_renewals WHERE service_id=? AND generation=? FOR UPDATE',[$id,$generation])->fetch();if($old){return $old;}
            $busy=$service['current_operation_id']===null?false:$this->db->execute("SELECT 1 FROM svc_operations o WHERE o.id=? AND (o.status IN ('PENDING','RUNNING') OR (o.status='UNKNOWN' AND NOT EXISTS(SELECT 1 FROM svc_resolutions r WHERE r.operation_id=o.id)))",[$service['current_operation_id']])->fetchColumn();if($busy){throw new HttpError(409,'SERVICE_OPERATION_IN_FLIGHT');}
            $plan=$this->plan($id,$customer,$actor,$now);$quote=$this->commerce->quotes->issue($plan);$order=$this->commerce->orders->submit($quote['id'],$customer,['currency'=>$quote['currency'],'name'=>'Service renewal'],$actor,$trace,$now);$line=$quote['lines'][0];$price=Id::uuid();$next=Id::uuid();$renewal=Id::uuid();
            $this->db->execute('INSERT INTO svc_prices(id,service_id,customer_id,currency,net_minor,tax_minor,total_minor,snapshot) VALUES (?,?,?,?,?,?,?,?)',[$price,$id,$customer,$quote['currency'],$line['net_minor'],$line['tax_minor'],$line['total_minor'],CanonicalJson::encode($line)]);
            $this->db->execute("INSERT INTO svc_periods(id,service_id,customer_id,generation,billing_request_id,price_id,cycle_snapshot,status) VALUES (?,?,?,?,?,?,?,'AWAITING_PAYMENT')",[$next,$id,$customer,$generation,$order['payment_request_id'],$price,CanonicalJson::encode($line['cycle'])]);$this->db->execute('INSERT INTO svc_renewals(id,service_id,customer_id,generation,order_id,period_id) VALUES (?,?,?,?,?,?)',[$renewal,$id,$customer,$generation,$order['id'],$next]);
            $this->db->execute("INSERT INTO svc_notices(id,service_id,customer_id,kind,reference_id) VALUES (?,?,?,'RENEWAL_PAYMENT_REQUIRED',?)",[Id::uuid(),$id,$customer,$renewal]);$this->repository->event($service,'RENEWAL_REQUESTED',$actor,$trace);return $this->db->execute('SELECT * FROM svc_renewals WHERE id=?',[$renewal])->fetch();
        });
    }
    public function walletOptIn(string $id,string $customer,bool $enabled,Principal $actor,string $trace): void
    {
        $this->authorize($actor,$customer,$id);$this->transaction(function()use($id,$customer,$enabled,$actor,$trace): void{$service=$this->locked($id,$customer);$this->db->execute('INSERT INTO svc_preferences(service_id,customer_id,auto_wallet) VALUES (?,?,?) ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id),auto_wallet=VALUES(auto_wallet),version=version+1,updated_at=UTC_TIMESTAMP(6)',[$id,$customer,$enabled?1:0]);$this->repository->event($service,'WALLET_OPT_IN_CHANGED',$actor,$trace);});
    }
    /** Explicit opt-in is checked again while locked; F2 owns all wallet/journal writes. */
    public function autoFund(string $renewal,Principal $actor,string $trace): array
    {
        return $this->transaction(function()use($renewal,$actor,$trace): array{
            $identity=$this->db->execute('SELECT * FROM svc_renewals WHERE id=?',[$renewal])->fetch();if(!$identity){throw new HttpError(404,'RENEWAL_NOT_FOUND');}$this->authorize($actor,$identity['customer_id'],$identity['service_id']);$service=$this->locked($identity['service_id'],$identity['customer_id']);$row=$this->db->execute('SELECT * FROM svc_renewals WHERE id=? FOR UPDATE',[$renewal])->fetch();$preference=$this->db->execute('SELECT * FROM svc_preferences WHERE service_id=? FOR UPDATE',[$service['id']])->fetch();if(!$preference||!$preference['auto_wallet']||$preference['customer_id']!==$service['customer_id']){throw new HttpError(409,'AUTO_WALLET_EXPLICIT_OPT_IN_REQUIRED');}
            if(!in_array($service['status'],['ACTIVE','SUSPENDED'],true)||$row['customer_id']!==$service['customer_id']){throw new HttpError(409,'RENEWAL_FUNDING_SERVICE_CHANGED');}
            $order=$this->commerce->orders->repository->find($row['order_id'],$row['customer_id'],true);$bill=$this->commerce->billing->detail($order['payment_request_id'],$row['customer_id']);if($bill['status']==='PAID'){return $row;} // Idempotent after an earlier full debit.
            $request=$this->commerce->billing->repository->request($order['payment_request_id'],$row['customer_id'],true);$remaining=(string)(new \Hostider\Finance\Decimal((string)$request['total_minor']))->subtract(new \Hostider\Finance\Decimal($this->commerce->finance->repository->allocated($request['id'],true)));
            $this->commerce->orders->fund($order['id'],$row['customer_id'],new Money($remaining,new Currency($request['currency'])),'WALLET',null,'renewal-wallet:'.$renewal,$actor,$trace);$this->repository->event($service,'RENEWAL_WALLET_FUNDED',$actor,$trace);return $row;
        });
    }
    public function preparePaid(string $renewal,Principal $actor,string $trace): string
    {
        $identity=$this->db->execute('SELECT * FROM svc_renewals WHERE id=?',[$renewal])->fetch();if(!$identity){throw new HttpError(404,'RENEWAL_NOT_FOUND');}$this->authorize($actor,$identity['customer_id'],$identity['service_id']);
        return $this->transaction(function()use($identity,$renewal,$actor,$trace): string{
            $service=$this->locked($identity['service_id'],$identity['customer_id']);$row=$this->db->execute('SELECT * FROM svc_renewals WHERE id=? FOR UPDATE',[$renewal])->fetch();if($row['operation_id']!==null){return $row['operation_id'];}
            if(!in_array($service['status'],['ACTIVE','SUSPENDED'],true)||$row['status']!=='AWAITING_PAYMENT'){throw new HttpError(409,'RENEWAL_NOT_DISPATCHABLE');}
            $busy=$service['current_operation_id']===null?false:$this->db->execute("SELECT 1 FROM svc_operations o WHERE o.id=? AND (o.status IN ('PENDING','RUNNING') OR (o.status='UNKNOWN' AND NOT EXISTS(SELECT 1 FROM svc_resolutions r WHERE r.operation_id=o.id)))",[$service['current_operation_id']])->fetchColumn();if($busy){throw new HttpError(409,'SERVICE_OPERATION_IN_FLIGHT');}
            $order=$this->commerce->orders->repository->find($row['order_id'],$row['customer_id'],true);$bill=$this->commerce->billing->repository->request($order['payment_request_id'],$row['customer_id'],true);if($bill['status']!=='PAID'||$bill['refund_status']!=='NONE'||$order['refund_status']!=='NONE'||$order['status']==='CANCELLED'){throw new HttpError(409,'FULL_RENEWAL_PAYMENT_REQUIRED');}
            $previous=$this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE',[$service['current_period_id']])->fetch();$period=$this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE',[$row['period_id']])->fetch();$resource=$this->db->execute("SELECT * FROM svc_resources WHERE service_id=? AND resource_role='PRIMARY'",[$service['id']])->fetch();if(!$resource||(int)$previous['generation']+1!==(int)$row['generation']||$previous['ends_at']===null){throw new HttpError(409,'RENEWAL_PERIOD_BINDING');}
            $id=Id::uuid();$provider=Id::uuid();$generation=1+(int)$this->db->execute("SELECT COALESCE(MAX(generation),0) FROM svc_operations WHERE order_item_id=? AND kind='RENEW'",[$service['order_item_id']])->fetchColumn();$request=CanonicalJson::encode(['kind'=>'RENEW','service_id'=>$service['id'],'resource_id'=>$resource['external_id'],'server_id'=>$resource['server_id'],'renewal_id'=>$renewal,'period_id'=>$period['id'],'previous_period_id'=>$previous['id'],'expected_end'=>$previous['ends_at'],'cycle'=>json_decode($period['cycle_snapshot'],true,32,JSON_THROW_ON_ERROR)]);$key=base64_decode($this->config->key,true);$hash=hash_hmac('sha256',$request,$key);
            $this->db->execute("INSERT INTO svc_operations(id,service_id,order_item_id,kind,generation,request_fingerprint) VALUES (?,?,?,'RENEW',?,?)",[$id,$service['id'],$service['order_item_id'],$generation,$hash]);$this->db->execute('INSERT INTO svc_provider_operations(id,lifecycle_operation_id,server_id,request_encrypted,request_fingerprint) VALUES (?,?,?,?,?)',[$provider,$id,$resource['server_id'],(new Crypto($key))->encrypt($request,'svc-operation:'.$provider),$hash]);$this->db->execute("UPDATE svc_renewals SET operation_id=?,status='PENDING_PROVIDER' WHERE id=?",[$id,$renewal]);$this->db->execute("UPDATE svc_periods SET status='PENDING_PROVIDER',version=version+1 WHERE id=?",[$period['id']]);$this->db->execute('UPDATE svc_services SET current_operation_id=?,version=version+1 WHERE id=?',[$id,$service['id']]);
            (new \Hostider\Core\Queue\Outbox($this->db,new \Hostider\Core\Queue\Queue($this->db)))->append(Id::uuid(),'services.renew',$id,['operation_id'=>$id],$trace);$this->repository->event($service,'RENEWAL_PROVIDER_QUEUED',$actor,$trace);return $id;
        });
    }
}
