<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{Authorization,Crypto,Principal};
final readonly class Actions
{
    private Repository $repository;
    public function __construct(private Database $db,private Config $config){$this->repository=new Repository($db,$config);}
    private function authorize(string $id,string $customer,string $permission,Principal $actor,bool $staff): void
    {
        if($this->config->environment!=='test'||($staff&&!$actor->staff)){throw new HttpError(403,'F3_TEST_ONLY');}(new Authorization())->require($actor,$permission,$customer,privileged:true,service:$id);
    }
    private function transaction(\Closure $work): mixed{return $this->db->pdo->inTransaction()?$work():$this->db->transaction($work);}
    private function lock(string $id,string $customer): array
    {
        $identity=$this->repository->find($id,$customer,false);$this->db->execute('SELECT id FROM orders WHERE id=? FOR UPDATE',[$identity['order_id']]);$this->db->execute('SELECT id FROM order_items WHERE id=? FOR UPDATE',[$identity['order_item_id']]);$service=$this->repository->find($id,$customer);
        if($service['origin_environment']!=='test'||$service['imported']){throw new HttpError(409,'SERVICE_ACTION_ORIGIN_FORBIDDEN');}
        if($service['current_operation_id']!==null&&$this->db->execute("SELECT 1 FROM svc_operations o WHERE o.id=? AND (o.status IN ('PENDING','RUNNING') OR (o.status='UNKNOWN' AND NOT EXISTS(SELECT 1 FROM svc_resolutions r WHERE r.operation_id=o.id)))",[$service['current_operation_id']])->fetchColumn()){throw new HttpError(409,'SERVICE_OPERATION_IN_FLIGHT');}return $service;
    }
    public function scheduleCancel(string $id,string $customer,Principal $actor,string $trace): array
    {
        $this->authorize($id,$customer,'services.cancel',$actor,false);return $this->transaction(function()use($id,$customer,$actor,$trace): array{
            $service=$this->lock($id,$customer);if(!in_array($service['status'],['ACTIVE','SUSPENDED'],true)){throw new HttpError(409,'SERVICE_NOT_CANCELLABLE');}$policy=(new Policies($this->db,$this->config))->resolve($service);if(Policies::required($policy,'allow_cancel')!==true){throw new HttpError(409,'SERVICE_CANCELLATION_DISABLED');}$wait=Policies::required($policy,'termination_wait_seconds');
            if($this->db->execute("SELECT 1 FROM svc_renewals WHERE service_id=? AND status IN ('AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN')",[$id])->fetchColumn()){throw new HttpError(409,'RENEWAL_CANCELLATION_CONFLICT');}$period=$this->db->execute('SELECT ends_at FROM svc_periods WHERE id=?',[$service['current_period_id']])->fetch();$cancel=Id::uuid();
            $this->db->execute('INSERT INTO svc_cancellations(id,service_id,actor_id,previous_status,execute_after,trace_id) VALUES (?,?,?,?,GREATEST(COALESCE(?,UTC_TIMESTAMP(6)),TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6))),?)',[$cancel,$id,$actor->actorId,$service['status'],$period['ends_at'],$wait,$trace]);$this->db->execute('UPDATE svc_services SET cancel_at=(SELECT execute_after FROM svc_cancellations WHERE id=?) WHERE id=?',[$cancel,$id]);$this->repository->transition($service,'CANCEL_SCHEDULED',$actor,$trace);return ['cancellation_id'=>$cancel,'status'=>'CANCEL_SCHEDULED'];
        });
    }
    public function undoCancel(string $id,string $customer,Principal $actor,string $trace): void
    {
        $this->authorize($id,$customer,'services.cancel',$actor,false);$this->transaction(function()use($id,$customer,$actor,$trace): void{$service=$this->lock($id,$customer);$cancel=$this->db->execute("SELECT * FROM svc_cancellations WHERE service_id=? AND status='PENDING' ORDER BY requested_at DESC,id DESC LIMIT 1 FOR UPDATE",[$id])->fetch();if(!$cancel||$service['status']!=='CANCEL_SCHEDULED'){throw new HttpError(409,'CANCELLATION_NOT_PENDING');}$this->db->execute("UPDATE svc_cancellations SET status='CANCELLED' WHERE id=?",[$cancel['id']]);$this->db->execute('UPDATE svc_services SET cancel_at=NULL WHERE id=?',[$id]);$this->repository->transition($service,$cancel['previous_status'],$actor,$trace);});
    }
    public function request(string $id,string $customer,string $kind,Principal $actor,string $trace,bool $safeguard=false): string
    {
        $permission=match($kind){'SUSPEND'=>'services.suspend','UNSUSPEND'=>'services.unsuspend','TERMINATE'=>'services.terminate',default=>throw new HttpError(422,'UNSUPPORTED_LIFECYCLE_ACTION')};$this->authorize($id,$customer,$permission,$actor,true);
        return $this->enqueue($id,$customer,$kind,$actor,$trace,$safeguard);
    }
    /** Trusted cron entry; persisted explicit policy and elapsed verified period supply authorization. */
    public function suspendDue(string $id,Principal $actor,string $trace): string
    {
        if($this->config->environment!=='test'||$actor->impersonating){throw new HttpError(403,'F3_TEST_ONLY');}
        return $this->transaction(function()use($id,$actor,$trace): string{
            $identity=$this->repository->find($id,lock:false);$service=$this->lock($id,$identity['customer_id']);$policy=(new Policies($this->db,$this->config))->resolve($service);$grace=Policies::required($policy,'grace_seconds');if(Policies::required($policy,'auto_suspend')!==true){throw new HttpError(409,'AUTOMATIC_SUSPENSION_DISABLED');}
            $period=$this->db->execute("SELECT status,ends_at IS NOT NULL AND TIMESTAMPADD(SECOND,?,ends_at)<=UTC_TIMESTAMP(6) AS due FROM svc_periods WHERE id=?",[$grace,$service['current_period_id']])->fetch();if(!$period||$period['status']!=='VERIFIED'||!$period['due']){throw new HttpError(409,'SERVICE_GRACE_NOT_ELAPSED');}
            if($this->db->execute("SELECT 1 FROM svc_renewals r JOIN orders o ON o.id=r.order_id JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE r.service_id=? AND b.status='PAID' AND r.status IN ('AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN')",[$id])->fetchColumn()){throw new HttpError(409,'PAID_RENEWAL_REQUIRES_PROVIDER_REVIEW');}
            return $this->enqueue($id,$service['customer_id'],'SUSPEND',$actor,$trace,false);
        });
    }
    private function enqueue(string $id,string $customer,string $kind,Principal $actor,string $trace,bool $safeguard): string
    {
        return $this->transaction(function()use($id,$customer,$kind,$actor,$trace,$safeguard): string{
            $service=$this->lock($id,$customer);$expected=match($kind){'SUSPEND'=>'ACTIVE','UNSUSPEND'=>'SUSPENDED','TERMINATE'=>'CANCEL_SCHEDULED',default=>throw new HttpError(422,'UNSUPPORTED_LIFECYCLE_ACTION')};if($service['status']!==$expected){throw new HttpError(409,'SERVICE_ACTION_STATE_CONFLICT');}
            if($kind==='TERMINATE'){
                $cancel=$this->db->execute("SELECT *,execute_after<=UTC_TIMESTAMP(6) AS due FROM svc_cancellations WHERE service_id=? AND status='PENDING' ORDER BY requested_at DESC,id DESC LIMIT 1 FOR UPDATE",[$id])->fetch();if(!$safeguard||!$cancel||!$cancel['due']){throw new HttpError(409,'TERMINATION_WAIT_AND_EXPLICIT_SAFEGUARD_REQUIRED');}
                if($this->db->execute("SELECT 1 FROM svc_renewals WHERE service_id=? AND status IN ('AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN')",[$id])->fetchColumn()){throw new HttpError(409,'RENEWAL_TERMINATION_CONFLICT');}
            }
            if($kind==='UNSUSPEND'){$period=$this->db->execute("SELECT status,ends_at>UTC_TIMESTAMP(6) AS current FROM svc_periods WHERE id=?",[$service['current_period_id']])->fetch();if($period['status']!=='VERIFIED'||!$period['current']){throw new HttpError(409,'CURRENT_PAID_VERIFIED_PERIOD_REQUIRED');}}
            $resource=$this->db->execute("SELECT * FROM svc_resources WHERE service_id=? AND resource_role='PRIMARY'",[$id])->fetch();if(!$resource){throw new HttpError(409,'SERVICE_RESOURCE_MISSING');}$method=match($kind){'SUSPEND'=>'suspendAccount','UNSUSPEND'=>'unsuspendAccount','TERMINATE'=>'terminateAccount'};$server=$this->db->execute('SELECT capabilities FROM hosting_servers WHERE id=?',[$resource['server_id']])->fetch();if(!$server||!in_array($method,json_decode($server['capabilities'],true,32,JSON_THROW_ON_ERROR),true)){throw new HttpError(409,'PROVIDER_CAPABILITY_REQUIRED');}
            $operation=Id::uuid();$provider=Id::uuid();$generation=1+(int)$this->db->execute('SELECT COALESCE(MAX(generation),0) FROM svc_operations WHERE order_item_id=? AND kind=?',[$service['order_item_id'],$kind])->fetchColumn();$request=CanonicalJson::encode(['kind'=>$kind,'method'=>$method,'service_id'=>$id,'resource_id'=>$resource['external_id'],'server_id'=>$resource['server_id'],'expected_status'=>$service['status']]);$key=base64_decode($this->config->key,true);$hash=hash_hmac('sha256',$request,$key);
            $this->db->execute('INSERT INTO svc_operations(id,service_id,order_item_id,kind,generation,request_fingerprint) VALUES (?,?,?,?,?,?)',[$operation,$id,$service['order_item_id'],$kind,$generation,$hash]);$this->db->execute('INSERT INTO svc_provider_operations(id,lifecycle_operation_id,server_id,request_encrypted,request_fingerprint) VALUES (?,?,?,?,?)',[$provider,$operation,$resource['server_id'],(new Crypto($key))->encrypt($request,'svc-operation:'.$provider),$hash]);$this->db->execute('UPDATE svc_services SET current_operation_id=?,version=version+1 WHERE id=?',[$operation,$id]);
            if($kind==='TERMINATE'){$this->repository->transition($this->repository->find($id),'TERMINATING',$actor,$trace);}(new \Hostider\Core\Queue\Outbox($this->db,new \Hostider\Core\Queue\Queue($this->db)))->append(Id::uuid(),'services.action',$operation,['operation_id'=>$operation],$trace);$this->repository->event($service,$kind.'_QUEUED',$actor,$trace);return $operation;
        });
    }
}
