<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{Crypto,Principal};
/** Provider inspect is outside HTTP/idempotency transactions; current Identity is fenced again before evidence commit. */
final readonly class Inspector
{
    private \Hostider\Identity\IdentityService $identity;
    public function __construct(private Database $db,private Config $config){$this->identity=new \Hostider\Identity\IdentityService($db,$config);}
    private function actor(string $reference,string $customer,string $service,string $user): Principal
    {
        return $this->identity->withStepUp($reference,'admin',function(array $u,array $s)use($customer,$service,$user): Principal{if($u['id']!==$user){throw new HttpError(403,'INSPECTION_ACTOR_MISMATCH');}return $this->identity->authorize($u,$s,'services.reconcile',$customer,$service,privileged:true);});
    }
    public function request(string $id,string $customer,string $reference,string $user,string $trace): array
    {
        $this->db->requireTransaction();if($this->config->environment!=='test'){throw new HttpError(403,'F3_TEST_ONLY');}$actor=$this->actor($reference,$customer,$id,$user);$service=(new Repository($this->db,$this->config))->find($id,$customer);if($service['status']!=='UNKNOWN'){throw new HttpError(409,'SERVICE_NOT_RECONCILABLE');}$job=Id::uuid();$key=base64_decode($this->config->key,true);$this->db->execute('INSERT INTO svc_inspection_jobs(id,service_id,customer_id,requested_by,authorization_encrypted) VALUES (?,?,?,?,?)',[$job,$id,$customer,$actor->actorId,(new Crypto($key))->encrypt($reference,'svc-inspection-job:'.$job)]);(new \Hostider\Core\Queue\Outbox($this->db,new \Hostider\Core\Queue\Queue($this->db)))->append(Id::uuid(),'services.inspect',$job,['inspection_job_id'=>$job],$trace);return ['inspection_job_id'=>$job,'status'=>'PENDING'];
    }
    public function once(string $id): bool
    {
        if($this->config->environment!=='test'||$this->db->pdo->inTransaction()){throw new HttpError(403,'F3_TEST_ONLY');}$claim=$this->db->transaction(function()use($id): ?array{
            $row=$this->db->execute('SELECT *,lease_until<=UTC_TIMESTAMP(6) AS expired FROM svc_inspection_jobs WHERE id=? FOR UPDATE',[$id])->fetch();if(!$row){return null;}if($row['status']==='RUNNING'&&$row['expired']){$this->db->execute("UPDATE svc_inspection_jobs SET status='FAILED',safe_code='INSPECTION_LEASE_EXPIRED' WHERE id=?",[$id]);return null;}if($row['status']!=='PENDING'){return null;}$token=Id::uuid();$this->db->execute("UPDATE svc_inspection_jobs SET status='RUNNING',lease_token=?,lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND) WHERE id=?",[$token,$id]);return $row+['token'=>$token];
        });if(!$claim){return false;}
        try{
            $reference=(new Crypto(base64_decode($this->config->key,true)))->decrypt($claim['authorization_encrypted'],'svc-inspection-job:'.$id);$actor=$this->actor($reference,$claim['customer_id'],$claim['service_id'],$claim['requested_by']);$reauthorize=fn(): Principal=>$this->actor($reference,$claim['customer_id'],$claim['service_id'],$claim['requested_by']);$result=(new Reconciliation($this->db,$this->config))->inspect($claim['service_id'],$claim['customer_id'],$actor,Id::uuid(),$reauthorize);
            $this->db->transaction(function()use($id,$claim,$result): void{$row=$this->db->execute('SELECT *,lease_until>UTC_TIMESTAMP(6) AS live FROM svc_inspection_jobs WHERE id=? FOR UPDATE',[$id])->fetch();if($row['status']!=='RUNNING'||!$row['live']||!hash_equals($row['lease_token'],$claim['token'])){throw new HttpError(409,'STALE_INSPECTION_LEASE');}$this->db->execute("UPDATE svc_inspection_jobs SET status='SUCCEEDED',inspection_id=?,safe_code='INSPECTION_RECORDED' WHERE id=?",[$result['inspection_id'],$id]);});
        }catch(\Throwable $error){$code=$error instanceof HttpError&&in_array($error->status,[401,403],true)?'INSPECTION_AUTHORIZATION_REVOKED':'INSPECTION_REQUIRES_REVIEW';$this->db->transaction(fn()=>$this->db->execute("UPDATE svc_inspection_jobs SET status='FAILED',safe_code=? WHERE id=? AND status='RUNNING' AND lease_token=?",[$code,$id,$claim['token']]));}
        return true;
    }
}
