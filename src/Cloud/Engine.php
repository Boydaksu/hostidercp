<?php
declare(strict_types=1);
namespace Hostider\Cloud;
use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Providers\VoyarMutationFence;
use Hostider\Providers\Mock\DurableVoyar;
use Hostider\Services\CloudServices;

final readonly class Engine
{
    public Store $store;private CloudServices $services;private Catalog $catalog;
    public function __construct(private Database $db,private Config $config){$this->store=new Store($db,$config);$this->services=new CloudServices($db,$config);$this->catalog=new Catalog($db,$config);}
    public function attach(string $service,string $customer,string $catalog,array $spec,Principal $actor): string
    {
        return $this->db->transaction(function()use($service,$customer,$catalog,$spec,$actor): string{
            $s=$this->services->source($service,$customer);$mapping=$this->catalog->find($catalog);$this->catalog->validate($mapping['policy'],$spec);
            $purchase=$this->services->purchase($service,$customer);
            if($purchase['offer']['catalog_id']!==$catalog||$this->store->hash($purchase['offer']['spec'])!==$this->store->hash($spec)){throw new HttpError(409,'CLOUD_SPEC_NOT_PURCHASED');}
            $this->store->authorize(['service_id'=>$service,'customer_id'=>$customer],$actor);
            if($s['product_id']!==$mapping['product_id']){throw new HttpError(409,'CLOUD_PRODUCT_MAPPING_MISMATCH');}
            $old=$this->db->execute('SELECT * FROM cloud_instances WHERE service_id=? FOR UPDATE',[$service])->fetch();
            if($old){if($old['catalog_id']!==$catalog||!hash_equals($old['fingerprint'],$this->store->hash($spec))){throw new HttpError(409,'CLOUD_ATTACH_CONFLICT');}return $old['id'];}
            $id=Id::uuid();$this->db->execute('INSERT INTO cloud_instances(id,service_id,customer_id,account_id,catalog_id,spec_encrypted,fingerprint) VALUES (?,?,?,?,?,?,?)',[$id,$service,$customer,$mapping['account_id'],$catalog,$this->store->encrypt($spec,'cloud-spec:'.$id),$this->store->hash($spec)]);
            (new Entitlements($this->db,$this->config))->bind($this->store->instance($id),$purchase,$spec);$this->store->history($this->store->instance($id),null,'ATTACHED',$actor);return $id;
        });
    }
    public function prepare(string $instance,string $kind,array $payload,Principal $actor): string
    {
        return $this->db->transaction(function()use($instance,$kind,$payload,$actor): string{
            $i=$this->store->instance($instance);$this->store->authorize($i,$actor,true);$policy=$this->catalog->find($i['catalog_id'])['policy'];
            if($kind!=='RESOLVE'){$this->catalog->action($policy,$kind,$payload);}else{if(array_keys($payload)!==['observation_id']){throw new HttpError(422,'CLOUD_RESOLUTION_INVALID');}}
            $id=Id::uuid();$wait=$kind==='TERMINATE'?$policy['termination_wait_seconds']:0;
            $this->db->execute('INSERT INTO cloud_confirmations(id,instance_id,instance_version,kind,payload_hash,actor_id,expires_at,not_before) VALUES (?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE),TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)))',[$id,$instance,$i['version'],$kind,$this->store->hash($payload),$actor->actorId,$wait]);return $id;
        });
    }
    public function bindImported(string $service,string $customer,string $catalog,string $resource,Principal $actor): string
    {
        $mapping=$this->catalog->find($catalog);$proof=(new DurableVoyar($this->db,$this->config,$mapping['account_id']))->inspectResource($resource);
        if(!$proof||!in_array($proof['state'],['RUNNING','STOPPED'],true)){throw new HttpError(409,'CLOUD_IMPORT_RESOURCE_NOT_VERIFIED');}
        $spec=json_decode($proof['spec_json'],true,32,JSON_THROW_ON_ERROR);$this->catalog->validate($mapping['policy'],$spec);
        return $this->db->transaction(function()use($service,$customer,$catalog,$mapping,$proof,$spec,$actor): string{
            $s=$this->services->imported($service,$customer);$this->store->authorize(['service_id'=>$service,'customer_id'=>$customer],$actor,true);
            if($s['product_id']!==$mapping['product_id']){throw new HttpError(409,'CLOUD_PRODUCT_MAPPING_MISMATCH');}
            $id=Id::uuid();$state=$proof['state']==='RUNNING'?'ACTIVE':'STOPPED';
            $this->db->execute('INSERT INTO cloud_instances(id,service_id,customer_id,account_id,catalog_id,spec_encrypted,fingerprint,imported,status,resource_id,provider_resource_ref) VALUES (?,?,?,?,?,?,?,TRUE,?,?,?)',[$id,$service,$customer,$mapping['account_id'],$catalog,$this->store->encrypt($spec,'cloud-spec:'.$id),$this->store->hash($spec),$state,$proof['id'],$proof['provider_ref']]);
            $this->services->state($service,'ACTIVE');$this->store->history($this->store->instance($id),null,'IMPORTED_RESOURCE_VERIFIED',$actor);return $id;
        });
    }
    private function confirmation(array $i,string $kind,array $payload,Principal $actor,?string $id): void
    {
        if($id===null){throw new HttpError(403,'CLOUD_EXPLICIT_CONFIRMATION_REQUIRED');}
        $c=$this->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) AS fresh,not_before<=UTC_TIMESTAMP(6) AS ready FROM cloud_confirmations WHERE id=? FOR UPDATE',[$id])->fetch();
        if(!$c||$c['instance_id']!==$i['id']||(int)$c['instance_version']!==(int)$i['version']||$c['kind']!==$kind||$c['actor_id']!==$actor->actorId||$c['consumed']||!$c['fresh']||!$c['ready']||!hash_equals($c['payload_hash'],$this->store->hash($payload))){throw new HttpError(409,'CLOUD_CONFIRMATION_STALE_OR_WAITING');}
        $this->db->execute('UPDATE cloud_confirmations SET consumed=TRUE WHERE id=?',[$id]);
    }
    public function enqueue(string $instance,string $kind,array $payload,Principal $actor,?string $confirmation=null): string
    {
        return $this->db->transaction(function()use($instance,$kind,$payload,$actor,$confirmation): string{
            $i=$this->store->instance($instance);$this->store->authorize($i,$actor,$kind!=='CREATE');
            if(!$this->store->source($i)){throw new HttpError(409,'CLOUD_SOURCE_CHANGED');}
            if($i['current_operation_id']){$old=$this->store->operation($i['current_operation_id']);if($kind==='CREATE'&&$old['kind']==='CREATE'){if($i['imported']){throw new HttpError(409,'CLOUD_IMPORTED_CREATE_FORBIDDEN');}return $old['id'];}if(!in_array($old['status'],['SUCCEEDED','FAILED'],true)){throw new HttpError(409,'CLOUD_OPERATION_IN_FLIGHT');}}
            if($kind==='CREATE'){
                if($i['imported']||$i['resource_id']!==null||$i['status']!=='PENDING'){throw new HttpError(409,'CLOUD_CREATE_FORBIDDEN');}
                $payload=$this->store->decrypt($i['spec_encrypted'],$i['fingerprint'],'cloud-spec:'.$i['id']);
            }else{
                if(!in_array($i['status'],['ACTIVE','STOPPED'],true)||!$i['resource_id']){throw new HttpError(409,'CLOUD_INSTANCE_NOT_ACTIONABLE');}
                $original=$this->store->decrypt($i['spec_encrypted'],$i['fingerprint'],'cloud-spec:'.$i['id']);
                if($kind==='UPGRADE'&&isset($original['custom_package'])){throw new HttpError(409,'CLOUD_CUSTOM_UPGRADE_UNSUPPORTED');}
                $this->confirmation($i,$kind,$payload,$actor,$confirmation);
            }
            $policy=$this->catalog->find($i['catalog_id'])['policy'];if($kind==='CREATE'){$this->catalog->validate($policy,$payload);if(!in_array('CREATE',$policy['actions'],true)){throw new HttpError(409,'CLOUD_ACTION_UNSUPPORTED');}}else{$this->catalog->action($policy,$kind,$payload);}
            (new Entitlements($this->db,$this->config))->action($i,$kind,$payload);
            $id=Id::uuid();$request=['id'=>$id,'account_id'=>$i['account_id'],'instance_id'=>$instance,'kind'=>$kind,'generation'=>(int)$i['generation']+1,'resource_id'=>$i['resource_id'],'provider_resource_ref'=>$i['provider_resource_ref'],'data'=>$payload];
            $this->db->execute('INSERT INTO cloud_operations(id,instance_id,kind,generation,request_encrypted,fingerprint) VALUES (?,?,?,?,?,?)',[$id,$instance,$kind,$request['generation'],$this->store->encrypt($request,'cloud-request:'.$id),$this->store->hash($request)]);
            $this->db->execute('UPDATE cloud_instances SET current_operation_id=?,generation=generation+1,version=version+1 WHERE id=?',[$id,$instance]);
            (new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'cloud.execute',$id,['operation_id'=>$id],Id::uuid());$this->store->history($i,$id,'QUEUED_'.$kind,$actor);return $id;
        });
    }
    public function claim(?string $id=null): ?array
    {
        return $this->db->transaction(function()use($id): ?array{
            $rows=$this->db->execute("SELECT * FROM cloud_operations WHERE status IN ('PENDING','WAITING_TASK')".($id?' AND id=?':'').' ORDER BY runner_checked_at,created_at,id LIMIT 100 FOR UPDATE SKIP LOCKED',$id?[$id]:[])->fetchAll();
            foreach($rows as $op){$i=$this->store->instance($op['instance_id']);$poll=$op['status']==='WAITING_TASK';
                if($i['current_operation_id']!==$op['id']||(!$poll&&!$this->dispatchable($op,$i))){$this->db->execute("UPDATE cloud_operations SET runner_checked_at=UTC_TIMESTAMP(6),safe_code='CLOUD_SOURCE_CHANGED' WHERE id=?",[$op['id']]);continue;}
                if(!$poll&&$this->db->execute('SELECT 1 FROM cloud_dispatches WHERE operation_id=?',[$op['id']])->fetchColumn()){$this->unknown($op,$i,'DISPATCH_ALREADY_CONSUMED');continue;}
                $request=$this->store->request($op);$token=Id::uuid();
                $this->db->execute('UPDATE cloud_operations SET status=?,lease_token=?,lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND),runner_checked_at=UTC_TIMESTAMP(6) WHERE id=?',[$poll?'POLLING':'RUNNING',$token,$op['id']]);
                if($op['kind']==='CREATE'){$this->services->state($i['service_id'],'PROVISIONING',$op['id']);if(!$poll){$this->db->execute("UPDATE cloud_instances SET status='PROVISIONING',version=version+1 WHERE id=?",[$i['id']]);}}
                return ['id'=>$op['id'],'token'=>$token,'poll'=>$poll,'account_id'=>$i['account_id'],'instance_id'=>$i['id'],'request'=>$request,'fingerprint'=>$op['fingerprint'],'task_id'=>$op['task_id']];
            }return null;
        });
    }
    private function dispatchable(array $op,array $instance): bool
    {
        if(!$op['financial_exception']){return $this->store->source($instance);}
        return (new Containment($this->db,$this->config))->dispatchable($op,$instance);
    }
    private function lease(string $id,string $token,string $status): array
    {
        $op=$this->store->operation($id);$i=$this->store->instance($op['instance_id']);if($op['status']!==$status||!$op['live']||!hash_equals((string)$op['lease_token'],$token)||$i['current_operation_id']!==$id){throw new HttpError(409,'STALE_CLOUD_LEASE');}return [$op,$i];
    }
    public function fence(array $claim): VoyarMutationFence
    {
        return new VoyarMutationFence($this->config,function()use($claim): void{
            $this->db->transaction(function()use($claim): void{
                [$op,$i]=$this->lease($claim['id'],$claim['token'],'RUNNING');
                if(!$this->dispatchable($op,$i)){throw new HttpError(409,'CLOUD_SOURCE_CHANGED');}
                if($this->db->execute('SELECT 1 FROM cloud_dispatches WHERE operation_id=?',[$op['id']])->fetchColumn()){throw new HttpError(409,'CLOUD_DURABLE_REPLAY_BLOCKED');}
                $this->db->execute('INSERT INTO cloud_dispatches(operation_id,lease_token,fingerprint) VALUES (?,?,?)',[$op['id'],$claim['token'],$op['fingerprint']]);
            });
        });
    }
    public function accepted(array $claim,array $result): void
    {
        $this->db->transaction(function()use($claim,$result): void{
            [$op,$i]=$this->lease($claim['id'],$claim['token'],'RUNNING');
            $call=$this->db->execute('SELECT * FROM mock_voyar_calls WHERE operation_id=?',[$op['id']])->fetch();
            if(!$call||$call['account_id']!==$i['account_id']||!hash_equals($call['fingerprint'],$op['fingerprint'])||$result['outcome']!==$call['outcome']||!in_array($result['outcome'],['ACCEPTED','UNKNOWN','DEFINITIVE_FAILURE'],true)){$this->unknown($op,$i,'CLOUD_CALL_INTEGRITY');return;}
            if($result['outcome']==='UNKNOWN'){$this->unknown($op,$i,'PROVIDER_OUTCOME_UNKNOWN');return;}
            if($result['outcome']==='DEFINITIVE_FAILURE'){
                if($call['task_id']!==null||$call['resource_id']!==null){$this->unknown($op,$i,'CLOUD_FAILURE_WITH_EFFECT');return;}
                $this->db->execute("UPDATE cloud_operations SET status='FAILED',safe_code='MOCK_DEFINITIVE_FAILURE' WHERE id=?",[$op['id']]);
                if($op['kind']==='CREATE'){$this->db->execute("UPDATE cloud_instances SET status='FAILED',version=version+1 WHERE id=?",[$i['id']]);$this->services->state($i['service_id'],'FAILED');}return;
            }
            if(!$result['task_id']||$result['task_id']!==$call['task_id']||$result['resource_id']!==$call['resource_id']){$this->unknown($op,$i,'CLOUD_TASK_BINDING');return;}
            $this->db->execute("UPDATE cloud_operations SET status='WAITING_TASK',task_id=?,resource_id=?,lease_token=NULL,lease_until=NULL,safe_code='TASK_PENDING' WHERE id=?",[$result['task_id'],$result['resource_id'],$op['id']]);
        });
    }
    public function lost(array $claim): void{$this->db->transaction(function()use($claim): void{[$op,$i]=$this->lease($claim['id'],$claim['token'],'RUNNING');$this->unknown($op,$i,'TRANSPORT_OR_PROCESS_LOSS');});}
    private function unknown(array $op,array $i,string $code): void
    {
        $this->db->execute("UPDATE cloud_operations SET status='UNKNOWN',safe_code=? WHERE id=?",[$code,$op['id']]);$this->db->execute("UPDATE cloud_instances SET status='UNKNOWN',version=version+1 WHERE id=?",[$i['id']]);if(!$op['financial_exception']){$this->services->state($i['service_id'],'UNKNOWN');}$this->store->history($i,$op['id'],$code,new Principal('cloud-worker',[]));
    }
    public function expire(): int
    {
        return $this->db->transaction(function(): int{$rows=$this->db->execute("SELECT * FROM cloud_operations WHERE status IN ('RUNNING','POLLING') AND lease_until<=UTC_TIMESTAMP(6) ORDER BY lease_until,id LIMIT 100 FOR UPDATE SKIP LOCKED")->fetchAll();foreach($rows as $op){$this->unknown($op,$this->store->instance($op['instance_id']),'LEASE_EXPIRED');}return count($rows);});
    }
    /** Provider observation is obtained outside TX; only a bound, encrypted snapshot is recorded here. */
    public function observe(string $opId,?Principal $actor=null): string
    {
        $snapshot=$this->db->transaction(function()use($opId,$actor): array{$op=$this->store->operation($opId);$i=$this->store->instance($op['instance_id']);if($actor){$this->store->authorize($i,$actor,true);}if(!in_array($op['status'],['POLLING','UNKNOWN','MANUAL_REVIEW'],true)){throw new HttpError(409,'CLOUD_NOT_INSPECTABLE');}return [$op,$i];});
        [$op,$i]=$snapshot;$evidence=(new DurableVoyar($this->db,$this->config,$i['account_id']))->observe($opId);
        return $this->db->transaction(function()use($op,$i,$evidence): string{$current=$this->store->operation($op['id']);$instance=$this->store->instance($i['id']);if($current['status']!==$op['status']||$current['lease_token']!==$op['lease_token']||$instance['version']!==$i['version']||$instance['current_operation_id']!==$op['id']){throw new HttpError(409,'STALE_CLOUD_OBSERVATION');}
            $id=Id::uuid();$envelope=['operation_id'=>$op['id'],'generation'=>$op['generation'],'fingerprint'=>$op['fingerprint'],'lease_token'=>$op['lease_token'],'evidence'=>$evidence];$this->db->execute('INSERT INTO cloud_observations(id,operation_id,instance_version,evidence_encrypted,fingerprint) VALUES (?,?,?,?,?)',[$id,$op['id'],$i['version'],$this->store->encrypt($envelope,'cloud-observation:'.$id),$this->store->hash($envelope)]);return $id;});
    }
    public function finish(array $claim,string $observation): void
    {
        $this->db->transaction(function()use($claim,$observation): void{[$op,$i]=$this->lease($claim['id'],$claim['token'],'POLLING');$this->apply($op,$i,$observation,false);});
    }
    public function resolve(string $operation,string $observation,string $confirmation,Principal $actor): void
    {
        $this->db->transaction(function()use($operation,$observation,$confirmation,$actor): void{$op=$this->store->operation($operation);$i=$this->store->instance($op['instance_id']);$this->store->authorize($i,$actor,true);if(!in_array($op['status'],['UNKNOWN','MANUAL_REVIEW'],true)){throw new HttpError(409,'CLOUD_NOT_UNKNOWN');}$this->confirmation($i,'RESOLVE',['observation_id'=>$observation],$actor,$confirmation);$this->apply($op,$i,$observation,true);});
    }
    private function apply(array $op,array $i,string $observation,bool $manual): void
    {
        $o=$this->db->execute('SELECT *,created_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE) AS fresh FROM cloud_observations WHERE id=? AND operation_id=?',[$observation,$op['id']])->fetch();
        if(!$o||!$o['fresh']||(int)$o['instance_version']!==(int)$i['version']){throw new HttpError(409,'STALE_CLOUD_OBSERVATION');}
        $envelope=$this->store->decrypt($o['evidence_encrypted'],$o['fingerprint'],'cloud-observation:'.$observation);$e=$envelope['evidence'];$call=$e['call'];$task=$e['task'];$resource=$e['resource'];
        $valid=$envelope['generation']===$op['generation']&&hash_equals($envelope['fingerprint'],$op['fingerprint'])&&$envelope['lease_token']===$op['lease_token']
            &&$call&&$task&&$resource&&in_array($call['outcome'],['ACCEPTED','UNKNOWN'],true)&&$call['account_id']===$i['account_id']&&$task['account_id']===$i['account_id']&&$resource['account_id']===$i['account_id']
            &&$call['operation_id']===$op['id']&&$task['operation_id']===$op['id']&&$task['kind']===$op['kind']&&$call['kind']===$op['kind']
            &&hash_equals($call['fingerprint'],$op['fingerprint'])&&hash_equals($task['fingerprint'],$op['fingerprint'])&&$call['task_id']===$task['id']
            &&$task['resource_id']===$resource['id']&&$call['resource_id']===$resource['id']&&($op['task_id']===null||$op['task_id']===$task['id']);
        if(!$valid){if($manual){throw new HttpError(409,'CLOUD_INSUFFICIENT_EVIDENCE');}$this->unknown($op,$i,'CLOUD_INSUFFICIENT_EVIDENCE');return;}
        if($task['state']==='MOCK_PENDING'){if($manual){throw new HttpError(409,'CLOUD_TASK_NOT_COMPLETE');}$this->db->execute("UPDATE cloud_operations SET status='WAITING_TASK',lease_token=NULL,lease_until=NULL WHERE id=?",[$op['id']]);return;}
        $target=match($op['kind']){'STOP'=>'STOPPED','TERMINATE'=>'TERMINATED',default=>'RUNNING'};
        $request=$this->store->request($op);$spec=json_decode($resource['spec_json'],true,32,JSON_THROW_ON_ERROR);
        $effect=$task['state']==='MOCK_SUCCEEDED'&&$resource['state']===$target&&$resource['last_operation_id']===$op['id']
            &&($op['kind']==='CREATE'?$resource['create_operation_id']===$op['id']:$request['resource_id']===$resource['id'])
            &&($op['kind']==='CREATE'||$request['provider_resource_ref']===$resource['provider_ref'])
            &&($op['kind']!=='CREATE'||$this->store->hash($spec)===$this->store->hash($request['data']))
            &&($op['kind']!=='REINSTALL'||$spec['osid']===$request['data']['osid'])&&($op['kind']!=='UPGRADE'||$spec['pid']===$request['data']['pid']);
        if(!$effect){if($manual){throw new HttpError(409,'CLOUD_TASK_RESULT_UNVERIFIED');}$this->unknown($op,$i,'CLOUD_TASK_RESULT_UNVERIFIED');return;}
        $funded=$this->store->source($i);$state=match($target){'RUNNING'=>'ACTIVE','STOPPED'=>'STOPPED','TERMINATED'=>'TERMINATED'};
        $this->db->execute("INSERT INTO cloud_effects(operation_id,instance_id,account_id,task_id,resource_id,resource_version,kind,state,provider_task_ref,provider_resource_ref,provenance) VALUES (?,?,?,?,?,?,?,?,?,?,'MOCK')",[$op['id'],$i['id'],$i['account_id'],$task['id'],$resource['id'],$resource['version'],$op['kind'],$state,$task['provider_ref'],$resource['provider_ref']]);
        $this->db->execute("UPDATE cloud_operations SET status='SUCCEEDED',task_id=?,resource_id=?,safe_code='TASK_RESOURCE_VERIFIED',lease_token=NULL,lease_until=NULL WHERE id=?",[$task['id'],$resource['id'],$op['id']]);
        $this->db->execute('UPDATE cloud_instances SET status=?,resource_id=?,provider_resource_ref=?,version=version+1 WHERE id=?',[$state,$resource['id'],$resource['provider_ref'],$i['id']]);
        $serviceState=$state==='TERMINATED'?'TERMINATED':($funded?'ACTIVE':'SUSPENDED');
        if($op['financial_exception']&&$state!=='TERMINATED'){
            // Historical financial_context is audit only. Never restore authorization from it.
            $current=$this->services->status($i['service_id']);
            $serviceState=in_array($current,['SUSPENDED','CANCEL_SCHEDULED','TERMINATING','TERMINATED'],true)
                ?$current:((!$funded||$current==='UNKNOWN')?'SUSPENDED':$current);
        }
        $this->services->state($i['service_id'],$serviceState,$op['id']);
        $this->store->history($i,$op['id'],'TASK_RESOURCE_VERIFIED',new Principal('cloud-evidence-worker',[]));
    }
    public function view(string $instance,Principal $actor): array
    {
        $i=$this->store->instance($instance,false);$this->store->authorize($i,$actor);return ['id'=>$i['id'],'service_id'=>$i['service_id'],'status'=>$i['status'],'imported'=>(bool)$i['imported'],'version'=>(int)$i['version']];
    }
}
