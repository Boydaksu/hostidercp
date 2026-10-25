<?php
declare(strict_types=1);
namespace Hostider\Cloud;
use Hostider\Core\{Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Identity\IdentityService;use Hostider\Providers\Mock\DurableVoyar;use Hostider\Core\Queue\{Outbox,Queue};
/** Only a live Identity admin session may authorize existing-resource harm containment. */
final readonly class Containment
{
    private Store $store;private IdentityService $identity;
    public function __construct(private Database $db,private Config $config){$this->store=new Store($db,$config);$this->identity=new IdentityService($db,$config);}
    private function authorized(string $reference,string $instance,\Closure $work): mixed
    {
        return $this->identity->withStepUp($reference,'admin',function(array $u,array $s)use($instance,$work): mixed{
            $i=$this->store->instance($instance);$actor=$this->identity->authorize($u,$s,'services.manage',$i['customer_id'],$i['service_id'],privileged:true);
            if(!$actor->staff||$actor->impersonating){throw new HttpError(403,'CLOUD_CONTAINMENT_STAFF_REQUIRED');}return $work($i,$u,$s,$actor);
        });
    }
    private function available(array $i): void
    {
        if(!in_array($i['status'],['ACTIVE','STOPPED'],true)||!$i['resource_id']||!$i['provider_resource_ref']){throw new HttpError(409,'CLOUD_CONTAINMENT_RESOURCE_REQUIRED');}
        if($i['current_operation_id']){$op=$this->store->operation($i['current_operation_id']);if(!in_array($op['status'],['SUCCEEDED','FAILED'],true)){throw new HttpError(409,'CLOUD_CONTAINMENT_BUSY');}}
    }
    private function proof(array $i): array
    {
        $r=(new DurableVoyar($this->db,$this->config,$i['account_id']))->inspectResource($i['resource_id']);
        if(!$r||$r['account_id']!==$i['account_id']||$r['id']!==$i['resource_id']||$r['provider_ref']!==$i['provider_resource_ref']||!in_array($r['state'],['RUNNING','STOPPED'],true)){throw new HttpError(409,'CLOUD_CONTAINMENT_PROOF_MISMATCH');}return $r;
    }
    public function prepare(string $instance,string $kind,string $reason,string $reference): string
    {
        if(!in_array($kind,['STOP','TERMINATE'],true)||strlen(trim($reason))<5||strlen($reason)>1000){throw new HttpError(422,'CLOUD_CONTAINMENT_INPUT_INVALID');}
        $before=$this->authorized($reference,$instance,function(array $i): array{$this->available($i);return $i;});$proof=$this->proof($before);
        return $this->authorized($reference,$instance,function(array $i,array $u,array $s,$actor)use($before,$proof,$kind,$reason): string{
            $this->available($i);if($i['version']!==$before['version']){throw new HttpError(409,'CLOUD_CONTAINMENT_STALE');}
            $policy=(new Catalog($this->db,$this->config))->find($i['catalog_id'])['policy'];(new Catalog($this->db,$this->config))->action($policy,$kind,[]);
            $id=Id::uuid();$data=['reason'=>$reason,'proof'=>$proof,'kind'=>$kind,'financial_exception'=>true];
            $this->db->execute('INSERT INTO cloud_safety_confirmations(id,instance_id,instance_version,kind,session_id,actor_id,identity_version,payload_encrypted,fingerprint,expires_at,not_before) VALUES (?,?,?,?,?,?,?,?,?,TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)),TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)))',[$id,$i['id'],$i['version'],$kind,$s['id'],$u['id'],$u['identity_version'],$this->store->encrypt($data,'cloud-safety:'.$id),$this->store->hash($data),($kind==='TERMINATE'?$policy['termination_wait_seconds']:0)+300,$kind==='TERMINATE'?$policy['termination_wait_seconds']:0]);
            $this->identity->confirmationEvent($u['id'],$id,false);return $id;
        });
    }
    public function execute(string $instance,string $confirmation,string $reference,bool $accepted): string
    {
        if(!$accepted){throw new HttpError(403,'CLOUD_EXPLICIT_CONFIRMATION_REQUIRED');}
        $before=$this->authorized($reference,$instance,function(array $i): array{$this->available($i);return $i;});$proof=$this->proof($before);
        return $this->authorized($reference,$instance,function(array $i,array $u,array $s,$actor)use($before,$proof,$confirmation,$reference): string{
            $this->available($i);$c=$this->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) AS fresh,not_before<=UTC_TIMESTAMP(6) AS ready FROM cloud_safety_confirmations WHERE id=? FOR UPDATE',[$confirmation])->fetch();
            if(!$c||$c['instance_id']!==$i['id']||$c['actor_id']!==$u['id']||$c['session_id']!==$s['id']||$c['identity_version']!==$u['identity_version']||(int)$c['instance_version']!==(int)$i['version']||$i['version']!==$before['version']||!$c['fresh']||!$c['ready']||$c['consumed']){throw new HttpError(409,'CLOUD_CONTAINMENT_CONFIRMATION_STALE');}
            $data=$this->store->decrypt($c['payload_encrypted'],$c['fingerprint'],'cloud-safety:'.$c['id']);if($this->store->hash($data['proof'])!==$this->store->hash($proof)){throw new HttpError(409,'CLOUD_CONTAINMENT_RESOURCE_CHANGED');}
            $op=Id::uuid();$request=['id'=>$op,'account_id'=>$i['account_id'],'instance_id'=>$i['id'],'kind'=>$c['kind'],'generation'=>(int)$i['generation']+1,'resource_id'=>$i['resource_id'],'provider_resource_ref'=>$i['provider_resource_ref'],'data'=>[],'financial_exception'=>true,'expected_resource_version'=>(int)$proof['version']];
            $context=['reason'=>$data['reason'],'proof'=>$proof,'reference'=>$reference,'financial_context'=>(new \Hostider\Services\CloudServices($this->db,$this->config))->financialContext($i['service_id'])];
            $this->db->execute('INSERT INTO cloud_operations(id,instance_id,kind,generation,request_encrypted,fingerprint,financial_exception) VALUES (?,?,?,?,?,?,TRUE)',[$op,$i['id'],$c['kind'],$request['generation'],$this->store->encrypt($request,'cloud-request:'.$op),$this->store->hash($request)]);
            $this->db->execute('INSERT INTO cloud_containments(operation_id,instance_id,actor_id,session_id,identity_version,context_encrypted,fingerprint,financial_exception) VALUES (?,?,?,?,?,?,?,TRUE)',[$op,$i['id'],$u['id'],$s['id'],$u['identity_version'],$this->store->encrypt($context,'cloud-containment:'.$op),$this->store->hash($context)]);
            $this->db->execute('UPDATE cloud_safety_confirmations SET consumed=TRUE WHERE id=?',[$confirmation]);$this->db->execute('UPDATE cloud_instances SET current_operation_id=?,generation=generation+1,version=version+1 WHERE id=?',[$op,$i['id']]);
            $this->identity->confirmationEvent($u['id'],$confirmation,true);$this->store->history($i,$op,'FINANCIAL_CONTAINMENT_'.$c['kind'],$actor);
            (new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'cloud.execute',$op,['operation_id'=>$op],Id::uuid());return $op;
        });
    }
    public function dispatchable(array $op,array $i): bool
    {
        try{
            $row=$this->db->execute('SELECT * FROM cloud_containments WHERE operation_id=?',[$op['id']])->fetch();if(!$row||!in_array($op['kind'],['STOP','TERMINATE'],true)||$row['instance_id']!==$i['id']){return false;}
            $context=$this->store->decrypt($row['context_encrypted'],$row['fingerprint'],'cloud-containment:'.$op['id']);$r=$this->store->request($op);
            if(($r['financial_exception']??false)!==true||$r['resource_id']!==$i['resource_id']||$r['account_id']!==$i['account_id']||$r['provider_resource_ref']!==$i['provider_resource_ref']||$context['proof']['id']!==$i['resource_id']||$context['proof']['account_id']!==$i['account_id']){return false;}
            return $this->identity->withStepUp($context['reference'],'admin',function(array $u,array $s)use($row,$i): bool{
                $actor=$this->identity->authorize($u,$s,'services.manage',$i['customer_id'],$i['service_id'],privileged:true);
                return $actor->staff&&!$actor->impersonating&&$u['id']===$row['actor_id']&&$s['id']===$row['session_id']&&$u['identity_version']===$row['identity_version'];
            });
        }catch(HttpError){return false;}
    }
}
