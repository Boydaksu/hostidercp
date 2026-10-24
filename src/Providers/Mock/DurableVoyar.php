<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;

/** Synthetic normalized protocol, NOT a decoder for unverified live Voyar task responses. */
final readonly class DurableVoyar implements \Hostider\Providers\Contract\AsyncComputeProvider
{
    private string $key;
    public function __construct(private Database $db,Config $config,private string $account)
    {if($config->environment!=='test'){throw new HttpError(403,'G_V02_G_V03_LIVE_DISPATCH_CLOSED');}$this->key=base64_decode($config->key,true);}
    private function outside(): void{if($this->db->pdo->inTransaction()){throw new \LogicException('Cloud provider I/O outside application transaction required');}}
    public function mutate(array $request,string $fingerprint,string $scenario='SUCCESS'): array
    {
        $this->outside();if(!in_array($scenario,['SUCCESS','TIMEOUT_BEFORE_EFFECT','TIMEOUT_AFTER_EFFECT','BODY_LOST','AUTH_AFTER_EFFECT','DEFINITIVE_FAILURE'],true)){throw new \InvalidArgumentException('Invalid synthetic scenario');}
        if(($request['account_id']??null)!==$this->account||!hash_equals($fingerprint,hash_hmac('sha256',CanonicalJson::encode($request),$this->key))){throw new HttpError(409,'CLOUD_MOCK_REQUEST_INTEGRITY');}
        return $this->db->transaction(function()use($request,$fingerprint,$scenario): array{
            $account=$this->db->execute('SELECT * FROM cloud_accounts WHERE id=? AND enabled=TRUE FOR UPDATE',[$this->account])->fetch();
            $dispatch=$this->db->execute('SELECT * FROM cloud_dispatches WHERE operation_id=?',[$request['id']])->fetch();
            if(!$account||!$dispatch||!hash_equals($dispatch['fingerprint'],$fingerprint)){throw new HttpError(409,'CLOUD_DISPATCH_NOT_ADMITTED');}
            $old=$this->db->execute('SELECT * FROM mock_voyar_calls WHERE operation_id=?',[$request['id']])->fetch();
            if($old){if($old['account_id']!==$this->account||!hash_equals($old['fingerprint'],$fingerprint)){throw new HttpError(409,'CLOUD_MOCK_REQUEST_REUSED');}return ['outcome'=>$old['outcome'],'task_id'=>$old['task_id'],'resource_id'=>$old['resource_id']];}
            $resource=null;$task=null;$outcome='ACCEPTED';
            if($scenario==='TIMEOUT_BEFORE_EFFECT'){$outcome='UNKNOWN';}
            elseif($scenario==='DEFINITIVE_FAILURE'){$outcome='DEFINITIVE_FAILURE';}
            else{
                if($request['kind']==='CREATE'){
                    $resource=Id::uuid();$this->db->execute("INSERT INTO mock_voyar_resources(id,account_id,provider_ref,create_operation_id,last_operation_id,spec_json,state) VALUES (?,?,?,?,?,?,'BUILDING')",[$resource,$this->account,'MOCK-VM-'.$resource,$request['id'],$request['id'],CanonicalJson::encode($request['data'])]);
                }else{
                    $r=$this->db->execute('SELECT * FROM mock_voyar_resources WHERE id=? AND account_id=? FOR UPDATE',[$request['resource_id'],$this->account])->fetch();
                    if(!$r||$r['state']==='TERMINATED'||$r['provider_ref']!==$request['provider_resource_ref']){throw new HttpError(409,'CLOUD_MOCK_RESOURCE_MISMATCH');}$resource=$r['id'];
                }
                $task=Id::uuid();$this->db->execute('INSERT INTO mock_voyar_tasks(id,account_id,provider_ref,operation_id,resource_id,fingerprint,kind,payload_json) VALUES (?,?,?,?,?,?,?,?)',[$task,$this->account,'MOCK-TASK-'.$task,$request['id'],$resource,$fingerprint,$request['kind'],CanonicalJson::encode($request['data'])]);
                if(in_array($scenario,['TIMEOUT_AFTER_EFFECT','BODY_LOST','AUTH_AFTER_EFFECT'],true)){$outcome='UNKNOWN';}
            }
            $this->db->execute('INSERT INTO mock_voyar_calls(operation_id,account_id,fingerprint,kind,task_id,resource_id,outcome) VALUES (?,?,?,?,?,?,?)',[$request['id'],$this->account,$fingerprint,$request['kind'],$task,$resource,$outcome]);
            return ['outcome'=>$outcome,'task_id'=>$outcome==='UNKNOWN'?null:$task,'resource_id'=>$outcome==='UNKNOWN'?null:$resource];
        });
    }
    /** Explicit mock scheduler step. Never interprets arbitrary real task status strings as success. */
    public function advance(string $taskId,bool $fail=false): void
    {
        $this->outside();$this->db->transaction(function()use($taskId,$fail): void{
            $task=$this->db->execute('SELECT * FROM mock_voyar_tasks WHERE id=? AND account_id=? FOR UPDATE',[$taskId,$this->account])->fetch();
            if(!$task||$task['state']!=='MOCK_PENDING'){return;}
            if($fail){$this->db->execute("UPDATE mock_voyar_tasks SET state='MOCK_FAILED' WHERE id=?",[$taskId]);return;}
            $resource=$this->db->execute('SELECT * FROM mock_voyar_resources WHERE id=? FOR UPDATE',[$task['resource_id']])->fetch();
            if(!$resource||$resource['state']==='TERMINATED'){throw new HttpError(409,'CLOUD_MOCK_TASK_RESOURCE_CHANGED');}
            $spec=json_decode($resource['spec_json'],true,32,JSON_THROW_ON_ERROR);$data=json_decode($task['payload_json'],true,32,JSON_THROW_ON_ERROR);
            if($task['kind']==='REINSTALL'){$spec['osid']=$data['osid'];}if($task['kind']==='UPGRADE'){$spec['pid']=$data['pid'];}
            $state=match($task['kind']){'STOP'=>'STOPPED','TERMINATE'=>'TERMINATED',default=>'RUNNING'};
            $this->db->execute('UPDATE mock_voyar_resources SET state=?,spec_json=?,last_operation_id=?,version=version+1 WHERE id=?',[$state,CanonicalJson::encode($spec),$task['operation_id'],$resource['id']]);
            $this->db->execute("UPDATE mock_voyar_tasks SET state='MOCK_SUCCEEDED' WHERE id=?",[$taskId]);
        });
    }
    public function observe(string $operation): array
    {
        $this->outside();$call=$this->db->execute('SELECT * FROM mock_voyar_calls WHERE operation_id=? AND account_id=?',[$operation,$this->account])->fetch();
        if(!$call){return ['call'=>null,'task'=>null,'resource'=>null];}
        $task=$this->db->execute('SELECT id,account_id,provider_ref,operation_id,resource_id,fingerprint,kind,state FROM mock_voyar_tasks WHERE id=? AND account_id=?',[$call['task_id'],$this->account])->fetch();
        $resource=$this->db->execute('SELECT id,account_id,provider_ref,create_operation_id,last_operation_id,state,version,spec_json FROM mock_voyar_resources WHERE id=? AND account_id=?',[$call['resource_id'],$this->account])->fetch();
        return ['call'=>$call,'task'=>$task?:null,'resource'=>$resource?:null];
    }
    public function inspectResource(string $id): ?array
    {
        $this->outside();$row=$this->db->execute('SELECT id,account_id,provider_ref,state,version,spec_json FROM mock_voyar_resources WHERE id=? AND account_id=?',[$id,$this->account])->fetch();return $row?:null;
    }
}
