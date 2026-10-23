<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{Authorization,Principal};
/** Explicit mock debt policy only. G-T01 live transfer remains closed. */
final readonly class Transfers
{
    private Repository $repository;private \Hostider\Identity\IdentityService $identity;private \Hostider\Commerce\Services $commerce;
    public function __construct(private Database $db,private Config $config){$this->repository=new Repository($db,$config);$this->identity=new \Hostider\Identity\IdentityService($db,$config);$this->commerce=new \Hostider\Commerce\Services($db,$config);}
    private function guard(): void{if($this->config->environment!=='test'){throw new HttpError(403,'G_T01_LIVE_TRANSFER_CLOSED');}}
    private function lock(string $id,string $customer): array
    {
        $identity=$this->repository->find($id,$customer,false);$this->db->execute('SELECT id FROM orders WHERE id=? FOR UPDATE',[$identity['order_id']]);$this->db->execute('SELECT id FROM order_items WHERE id=? FOR UPDATE',[$identity['order_item_id']]);$service=$this->repository->find($id,$customer);
        if($service['origin_environment']!=='test'||$service['imported']||!in_array($service['status'],['ACTIVE','SUSPENDED'],true)){throw new HttpError(409,'SERVICE_NOT_TRANSFERABLE');}
        if($service['current_operation_id']!==null&&$this->db->execute("SELECT 1 FROM svc_operations o WHERE o.id=? AND (o.status IN ('PENDING','RUNNING') OR (o.status='UNKNOWN' AND NOT EXISTS(SELECT 1 FROM svc_resolutions r WHERE r.operation_id=o.id)))",[$service['current_operation_id']])->fetchColumn()){throw new HttpError(409,'SERVICE_OPERATION_IN_FLIGHT');}
        if($this->db->execute("SELECT 1 FROM svc_renewals WHERE service_id=? AND status IN ('AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN')",[$id])->fetchColumn()){throw new HttpError(409,'RENEWAL_TRANSFER_CONFLICT');}if($this->db->execute("SELECT 1 FROM svc_package_changes WHERE service_id=? AND status IN ('AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN')",[$id])->fetchColumn()){throw new HttpError(409,'PACKAGE_TRANSFER_CONFLICT');}return $service;
    }
    private function policy(array $service): array
    {
        $policy=(new Policies($this->db,$this->config))->resolve($service);if(Policies::required($policy,'allow_transfer')!==true){throw new HttpError(409,'SERVICE_TRANSFER_DISABLED');}Policies::required($policy,'transfer_debt_scope');$ttl=Policies::required($policy,'transfer_ttl_seconds');if($ttl>300){throw new HttpError(409,'TRANSFER_SECURITY_TTL_EXCEEDS_FIVE_MINUTES');}return $policy;
    }
    private function debt(array $service,string $scope): void
    {
        $ids=$this->db->execute('SELECT r.id FROM billing_requests r WHERE r.customer_id=?'.($scope==='SERVICE'?' AND (r.order_id=? OR EXISTS(SELECT 1 FROM svc_periods p WHERE p.service_id=? AND p.billing_request_id=r.id))':'').' ORDER BY r.id LIMIT 1001',$scope==='SERVICE'?[$service['customer_id'],$service['order_id'],$service['id']]:[$service['customer_id']])->fetchAll();if(count($ids)>1000){throw new HttpError(409,'TRANSFER_DEBT_MANUAL_REVIEW_REQUIRED');}
        foreach($ids as $row){$bill=$this->commerce->billing->repository->request($row['id'],$service['customer_id'],true);if($bill['status']==='VOIDED'){continue;}if($bill['refund_status']!=='NONE'||$this->commerce->finance->repository->allocated($bill['id'],true)!==(string)$bill['total_minor']){throw new HttpError(409,'TRANSFER_OUTSTANDING_OR_DISPUTED_BALANCE');}}
    }
    private function owner(Principal $actor,string $customer): void
    {
        if($actor->staff||$actor->impersonating||!$this->db->execute('SELECT 1 FROM identity_memberships WHERE user_id=? AND customer_id=? AND is_owner=TRUE',[$actor->actorId,$customer])->fetchColumn()){throw new HttpError(403,'TRANSFER_REQUIRES_ACTUAL_CUSTOMER_OWNER');}
    }
    private function currency(array $service,string $target): void
    {
        $price=$this->db->execute('SELECT currency FROM svc_prices WHERE id=?',[$service['current_price_id']])->fetchColumn();$account=$this->identity->repository->customer($target);if($price!==$account['currency']){throw new HttpError(409,'TRANSFER_CURRENCY_POLICY_REQUIRES_REVIEW');}
    }
    public function start(string $id,string $customer,string $target,string $reference,string $trace): array
    {
        $this->guard();return $this->identity->withStepUp($reference,'client',function(array $u,array $s)use($id,$customer,$target,$reference,$trace): array{
            $actor=$this->identity->authorize($u,$s,'services.transfer',$customer,$id,privileged:true);$this->owner($actor,$customer);if($target===$customer){throw new HttpError(422,'TRANSFER_REQUIRES_DIFFERENT_CUSTOMER');}$customers=[$customer,$target];sort($customers);foreach($customers as $account){$this->identity->repository->customer($account,true);}$service=$this->lock($id,$customer);$policy=$this->policy($service);$this->currency($service,$target);$this->debt($service,$policy['transfer_debt_scope']);
            if($this->db->execute("SELECT 1 FROM svc_transfers WHERE service_id=? AND status='PENDING' AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE",[$id])->fetchColumn()){throw new HttpError(409,'TRANSFER_ALREADY_PENDING');}
            $transfer=Id::uuid();$token=bin2hex(random_bytes(32));$key=base64_decode($this->config->key,true);$hash=hash_hmac('sha256',$token,$key);$this->db->execute('INSERT INTO svc_transfers(id,service_id,from_customer_id,to_customer_id,sender_user_id,sender_session_reference,sender_identity_version,service_version,token_hmac,policy_hmac,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)))',[$transfer,$id,$customer,$target,$actor->actorId,$reference,$u['identity_version'],$service['version'],$hash,hash_hmac('sha256',CanonicalJson::encode($policy),$key),$policy['transfer_ttl_seconds']]);$this->repository->event($service,'TRANSFER_SENDER_APPROVED',$actor,$trace);return ['transfer_id'=>$transfer,'recipient_token'=>$token,'expires_in'=>$policy['transfer_ttl_seconds']];
        });
    }
    public function accept(string $transfer,string $customer,#[\SensitiveParameter] string $token,string $reference,string $trace): array
    {
        $this->guard();return $this->identity->withStepUp($reference,'client',function(array $u,array $s)use($transfer,$customer,$token,$trace): array{
            $actor=$this->identity->authorize($u,$s,'services.transfer.accept',$customer,privileged:true);$this->owner($actor,$customer);$identity=$this->db->execute('SELECT * FROM svc_transfers WHERE id=? AND to_customer_id=?',[$transfer,$customer])->fetch();$key=base64_decode($this->config->key,true);if(!$identity||!preg_match('/^[a-f0-9]{64}$/D',$token)||!hash_equals($identity['token_hmac'],hash_hmac('sha256',$token,$key))){throw new HttpError(404,'TRANSFER_NOT_FOUND');}
            $sender=$this->identity->repository->user($identity['sender_user_id'],true);$senderSession=$this->identity->repository->session($identity['sender_session_reference'],'client',true);$this->identity->repository->validate($sender,$senderSession);if($sender['identity_version']!==$identity['sender_identity_version']||$senderSession['user_id']!==$sender['id']){throw new HttpError(409,'TRANSFER_SENDER_IDENTITY_CHANGED');}$senderPrincipal=$this->identity->principal($sender,$senderSession);$this->owner($senderPrincipal,$identity['from_customer_id']);
            $this->identity->repository->owns($identity['service_id'],$identity['from_customer_id']);$customers=[$identity['from_customer_id'],$customer];sort($customers);foreach($customers as $account){$this->identity->repository->customer($account,true);}$service=$this->lock($identity['service_id'],$identity['from_customer_id']);$row=$this->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) AS live FROM svc_transfers WHERE id=? FOR UPDATE',[$transfer])->fetch();if($row['status']!=='PENDING'||!$row['live']||$row['service_version']!==$service['version']){throw new HttpError(409,'TRANSFER_EXPIRED_USED_OR_SERVICE_CHANGED');}(new Authorization())->require($senderPrincipal,'services.transfer',$service['customer_id'],privileged:true,service:$service['id']);$policy=$this->policy($service);if(!hash_equals($row['policy_hmac'],hash_hmac('sha256',CanonicalJson::encode($policy),$key))){throw new HttpError(409,'TRANSFER_POLICY_CHANGED');}$this->currency($service,$customer);$this->debt($service,$policy['transfer_debt_scope']);
            $price=$this->db->execute('SELECT * FROM svc_prices WHERE id=?',[$service['current_price_id']])->fetch();$copy=Id::uuid();$this->db->execute('INSERT INTO svc_prices(id,service_id,customer_id,currency,net_minor,tax_minor,total_minor,snapshot) VALUES (?,?,?,?,?,?,?,?)',[$copy,$service['id'],$customer,$price['currency'],$price['net_minor'],$price['tax_minor'],$price['total_minor'],$price['snapshot']]);$this->identity->reassignServiceResource($service['id'],$service['customer_id'],$customer);$this->db->execute('UPDATE svc_services SET customer_id=?,current_price_id=?,version=version+1 WHERE id=?',[$customer,$copy,$service['id']]);$this->db->execute('UPDATE svc_preferences SET customer_id=?,auto_wallet=FALSE,version=version+1 WHERE service_id=?',[$customer,$service['id']]);
            $this->db->execute("INSERT INTO svc_ownership_history(id,service_id,from_customer_id,to_customer_id,reason,actor_id,trace_id) VALUES (?,?,?,?,'MOCK_TRANSFER',?,?)",[Id::uuid(),$service['id'],$service['customer_id'],$customer,$actor->actorId,$trace]);$this->db->execute("UPDATE svc_transfers SET status='COMPLETED',recipient_user_id=?,completed_at=UTC_TIMESTAMP(6) WHERE id=?",[$actor->actorId,$transfer]);$this->repository->event($this->repository->find($service['id']),'TRANSFER_COMPLETED',$actor,$trace);return ['service_id'=>$service['id'],'status'=>$service['status'],'ownership_changed'=>true];
        });
    }
}
