<?php
declare(strict_types=1);
namespace Hostider\ProviderPanel;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Security\Crypto;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue,Worker};
/** Durable read-only inspection uses the existing outbox/queue; it never dispatches a mutation. */
final readonly class Inspections
{
    public function __construct(private Services $c){}
    public function request(array $u,array $s,string $customer,string $service,string $operation,string $trace): string
    {
        $db=$this->c->db;$db->requireTransaction();$cmd=new Commands($this->c);$cmd->authorize($u,$s,'inspect',['service_id'=>$service,'operation_id'=>$operation],$customer);$v=(new Access($this->c))->service($u,$s,$customer,$service);$state=$cmd->state(['service_id'=>$service],$customer);$id=Id::uuid();
        $db->execute('INSERT INTO panel_inspections(id,service_id,customer_id,operation_id,kind,actor_id,session_id,identity_version,authorization_encrypted,state_hmac) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$service,$customer,$operation,$v['product_type'],$u['id'],$s['id'],$u['identity_version'],(new Crypto(base64_decode($this->c->config->key,true)))->encrypt($s['native_reference'],'panel-inspection:'.$id),$cmd->hash($state)]);
        (new Outbox($db,new Queue($db)))->append(Id::uuid(),'panel.inspect',$id,['inspection_id'=>$id],$trace);return $id;
    }
    public function register(Worker $worker): void
    {
        $worker->register('panel.inspect',function(array $p): string{
            if(array_keys($p)!==['inspection_id']||!is_string($p['inspection_id'])){return 'FAILED';}
            $rows=$this->c->db->execute("SELECT payload FROM outbox_events WHERE event_type='panel.inspect' AND aggregate_id=?",[$p['inspection_id']])->fetchAll();if(!array_filter($rows,static fn(array $r): bool=>CanonicalJson::fromJson($r['payload'])===CanonicalJson::encode($p))){return 'FAILED';}
            $this->once($p['inspection_id']);return 'SUCCEEDED';
        });
    }
    private function authorization(array $r,string $reference): \Hostider\Core\Security\Principal
    {
        return $this->c->identity->withStepUp($reference,'admin',function(array $u,array $s)use($r): \Hostider\Core\Security\Principal{
            if($r['actor_id']!==$u['id']||$r['session_id']!==$s['id']||(string)$r['identity_version']!==(string)$u['identity_version']){throw new HttpError(403,'PANEL_INSPECTION_SESSION_CHANGED');}
            $cmd=new Commands($this->c);$a=$cmd->authorize($u,$s,'inspect',['service_id'=>$r['service_id'],'operation_id'=>$r['operation_id']],$r['customer_id']);if(!hash_equals($r['state_hmac'],$cmd->hash($cmd->state(['service_id'=>$r['service_id']],$r['customer_id'])))){throw new HttpError(409,'PANEL_INSPECTION_STATE_CHANGED');}return $a;
        });
    }
    public function once(?string $id=null): bool
    {
        if($this->c->config->environment!=='test'||$this->c->db->pdo->inTransaction()){throw new HttpError(403,'F4_TEST_ONLY');}$db=$this->c->db;
        $claim=$db->transaction(function()use($id,$db): ?array{
            $db->execute("UPDATE panel_inspections SET status='REVIEW',safe_code='INSPECTION_LEASE_EXPIRED' WHERE status='RUNNING' AND lease_until<=UTC_TIMESTAMP(6) LIMIT 100");
            $r=$db->execute("SELECT * FROM panel_inspections WHERE status='PENDING'".($id?' AND id=?':'').' ORDER BY created_at,id LIMIT 1 FOR UPDATE SKIP LOCKED',$id?[$id]:[])->fetch();if(!$r){return null;}$token=Id::uuid();$db->execute("UPDATE panel_inspections SET status='RUNNING',lease_token=?,lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND) WHERE id=?",[$token,$r['id']]);return $r+['token'=>$token];
        });if(!$claim){return false;}
        try{
            $ref=(new Crypto(base64_decode($this->c->config->key,true)))->decrypt($claim['authorization_encrypted'],'panel-inspection:'.$claim['id']);$a=$this->authorization($claim,$ref);
            if($claim['kind']==='CLOUD'){$result=(new \Hostider\Cloud\Engine($db,$this->c->config))->observe($claim['operation_id'],$a);}else{
                $account=$db->execute('SELECT a.account_id FROM domain_operations o JOIN domain_registrar_attempts a ON a.id=o.active_attempt_id WHERE o.id=?',[$claim['operation_id']])->fetchColumn();
                $result=(new \Hostider\Domains\DomainReconciliation($db,$this->c->config))->inspect($claim['operation_id'],new \Hostider\Providers\Mock\DurableRegistrar($db,$this->c->config,$account),$a,Id::uuid())['inspection_id'];
            }
            $this->c->identity->withStepUp($ref,'admin',function(array $u,array $s)use($claim,$result,$db): void{
                if($claim['actor_id']!==$u['id']||$claim['session_id']!==$s['id']||(string)$claim['identity_version']!==(string)$u['identity_version']){throw new HttpError(403,'PANEL_INSPECTION_SESSION_CHANGED');}
                (new Access($this->c))->service($u,$s,$claim['customer_id'],$claim['service_id'],'services.reconcile',true);
                $n=$db->execute("UPDATE panel_inspections SET status='SUCCEEDED',result_id=?,safe_code='INSPECTION_RECORDED' WHERE id=? AND status='RUNNING' AND lease_token=? AND lease_until>UTC_TIMESTAMP(6)",[$result,$claim['id'],$claim['token']])->rowCount();if($n!==1){throw new HttpError(409,'STALE_PANEL_INSPECTION_LEASE');}
            });
        }catch(\Throwable){$db->execute("UPDATE panel_inspections SET status='REVIEW',safe_code='INSPECTION_REQUIRES_REVIEW' WHERE id=? AND status='RUNNING' AND lease_token=?",[$claim['id'],$claim['token']]);}
        return true;
    }
    public function run(): array
    {
        $n=0;$end=hrtime(true)+20_000_000_000;while($n<100&&hrtime(true)<$end&&memory_get_usage(true)<64*1024*1024&&$this->once()){++$n;}return ['worked'=>$n,'limit'=>100,'budget_seconds'=>20];
    }
}
