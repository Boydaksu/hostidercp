<?php
declare(strict_types=1);
namespace Hostider\Migration\Synthetic;
use Hostider\Core\CanonicalJson;
use Hostider\Migration\{Contract, Entity, MappingEvidence, SourceEnvelope, SourceIdentity};

/** Isolated facts only: no native service/queue/provider/finance adapters. */
final readonly class Loader
{
    public function __construct(public Effects $effects = new Effects()) {}
    public function dryRun(Fixture $fixture,?Sandbox $sandbox=null): array
    {
        Sandbox::flags();$plan=Plan::build($fixture);
        try{if($sandbox!==null){$sandbox->assertReady();$reasons=$this->conflicts($plan,$sandbox);}else{$reasons=[];}}
        catch(\Throwable){$reasons=['F6B_STORAGE_UNAVAILABLE'];}
        $result=$plan->manifest('DRY_RUN',$reasons);$result['effects']=$this->effects->counts();return $result;
    }
    public function load(Fixture $fixture,Sandbox $sandbox,int $batchSize=64,?array $expected=null,Fault $fault=Fault::None): array
    {
        Sandbox::flags();Contract::require($batchSize>0 && $batchSize<=256,'F6B_BATCH_SIZE');$plan=Plan::build($fixture);$locked=false;
        try{
            $sandbox->assertReady();$db=$sandbox->db;
            Contract::require((int)$db->execute('SELECT GET_LOCK(?,10)',[$sandbox->mutex()])->fetchColumn()===1,'F6B_BUSY');$locked=true;
            $run=$db->transaction(function()use($plan,$sandbox,$expected): array{
                $row=$sandbox->db->execute('SELECT * FROM f6b_runs WHERE id=? FOR UPDATE',[$plan->hash])->fetch();
                if($row){$this->verifyRun($row,$plan,$sandbox);$this->expected($row,$expected,$sandbox);return $row;}
                Contract::require($expected===null,'F6B_STALE_CHECKPOINT');
                $row=['id'=>$plan->hash,'plan_hash'=>$plan->hash,'target_snapshot'=>$sandbox->snapshot,'cursor'=>0,'version'=>0,'state'=>'PLANNED','last_event_hash'=>str_repeat('0',64),'checkpoint_hash'=>str_repeat('0',64),'safe_code'=>null];
                $sandbox->db->execute('INSERT INTO f6b_runs VALUES (?,?,?,?,?,?,?,?,?)',array_values($row));
                $this->event($row,$sandbox,'PLANNED');
                if($plan->reasons!==[]){$row['safe_code']=$plan->reasons[0];$this->event($row,$sandbox,'REVIEW_REQUIRED');}
                else{
                    $snapshotReasons=$this->snapshotConflicts($plan,$sandbox);
                    if($snapshotReasons!==[]){$row['safe_code']=$snapshotReasons[0];$this->event($row,$sandbox,'REVIEW_REQUIRED');}
                    else{foreach($plan->snapshots() as $key=>$root){if(!$sandbox->db->execute('SELECT 1 FROM f6b_source_snapshots WHERE snapshot_key=?',[$key])->fetchColumn()){$sandbox->db->execute('INSERT INTO f6b_source_snapshots VALUES (?,?,?)',[$key,$root,$plan->hash]);}}$this->event($row,$sandbox,'VALIDATED');}
                }
                $this->saveRun($row,$sandbox);return $row;
            });
            if($run['state']==='REVIEW_REQUIRED' && $plan->reasons!==[]){return $this->manifest($plan,$run,$sandbox,false,$plan->reasons);}
            if($run['state']==='APPLIED'){Contract::require($this->conflicts($plan,$sandbox)===[],'F6B_REPLAY_CONFLICT');return $this->manifest($plan,$run,$sandbox,true);}
            return $db->transaction(function()use($plan,$sandbox,$batchSize,$fault): array{
                $row=$sandbox->db->execute('SELECT * FROM f6b_runs WHERE id=? FOR UPDATE',[$plan->hash])->fetch();
                $this->verifyRun($row,$plan,$sandbox);$sandbox->assertBound();
                $reasons=[...$plan->reasons,...$this->conflicts($plan,$sandbox)];
                if($reasons!==[]){$row['safe_code']=$reasons[0];$row['version']=(int)$row['version']+1;$this->event($row,$sandbox,'REVIEW_REQUIRED');$this->saveRun($row,$sandbox);$sandbox->assertReady();return $this->manifest($plan,$row,$sandbox,false,$reasons);}
                $row['version']=(int)$row['version']+1;$this->event($row,$sandbox,'APPLYING');
                $end=min(count($plan->items),(int)$row['cursor']+$batchSize);
                for($position=(int)$row['cursor'];$position<$end;++$position){
                    $this->apply($plan->items[$position],$plan,$sandbox);
                    if($position===(int)$row['cursor']){
                        if($fault===Fault::AfterInsert){throw new \RuntimeException('F6B_SYNTHETIC_FAILURE');}
                        if($fault===Fault::Crash){exit(73);}
                        if($fault===Fault::Disconnect){$id=(int)$sandbox->db->execute('SELECT CONNECTION_ID()')->fetchColumn();$sandbox->db->execute('KILL CONNECTION '.$id);throw new \RuntimeException('F6B_STORAGE_UNAVAILABLE');}
                    }
                }
                $row['cursor']=$end;$row['safe_code']=null;$this->event($row,$sandbox,$end===count($plan->items)?'APPLIED':'APPLYING');$this->saveRun($row,$sandbox);
                if($fault===Fault::AfterCheckpoint){throw new \RuntimeException('F6B_SYNTHETIC_FAILURE');}
                $sandbox->assertBound();$sandbox->assertReady();return $this->manifest($plan,$row,$sandbox,false);
            });
        }catch(\Throwable $error){
            $code=$error instanceof \InvalidArgumentException && preg_match('/^F6B_[A-Z0-9_]+$/D',$error->getMessage())===1 ? $error->getMessage() : ($error->getMessage()==='F6B_SYNTHETIC_FAILURE'?'F6B_SYNTHETIC_FAILURE':'F6B_STORAGE_UNAVAILABLE');
            if($code==='F6B_SYNTHETIC_FAILURE' && $locked){
                try{$sandbox->db->transaction(function()use($plan,$sandbox,$code): void{$sandbox->assertBound();$row=$sandbox->db->execute('SELECT * FROM f6b_runs WHERE id=? FOR UPDATE',[$plan->hash])->fetch();$this->verifyRun($row,$plan,$sandbox);Contract::require($row['state']!=='APPLIED','F6B_ALREADY_APPLIED');$row['version']=(int)$row['version']+1;$row['safe_code']=$code;$this->event($row,$sandbox,'FAILED');$this->saveRun($row,$sandbox);});}catch(\Throwable){/* Never claim that an unavailable failure checkpoint committed. */}
            }
            $result=$plan->manifest('SYNTHETIC_LOAD',[$code]);$result['status']='FAILED';$result['effects']=$this->effects->counts();return $result;
        }finally{if($locked){try{$sandbox->db->execute('SELECT RELEASE_LOCK(?)',[$sandbox->mutex()]);}catch(\Throwable){/* Connection loss releases its DB mutex. */}}}
    }
    private function conflicts(Plan $plan,Sandbox $sandbox): array
    {
        $reasons=$this->snapshotConflicts($plan,$sandbox);
        foreach($plan->items as $e){
            $key=$e->identity->key();$target=Plan::target($e);
            $existing=$sandbox->db->execute('SELECT * FROM f6b_mappings WHERE source_key=?',[$key])->fetch();
            if($existing){
                $mapping=MappingEvidence::fromArray(json_decode($existing['evidence_json'],true,32,JSON_THROW_ON_ERROR));
                Contract::require($existing['target_id']===$target && hash_equals($existing['evidence_hash'],$mapping->data['evidence_sha256']),'F6B_MAPPING_INTEGRITY');
                $comparison=MappingEvidence::compare($e,$e->identity->entity->value,$target,$mapping);
                if($comparison['status']!=='NOOP_REPLAY'){$reasons[]=$comparison['manual_review_reason'];}
                $entity=$sandbox->db->execute('SELECT * FROM f6b_entities WHERE target_id=?',[$target])->fetch();
                $provenance=$sandbox->db->execute('SELECT * FROM f6b_provenance WHERE source_key=?',[$key])->fetch();
                Contract::require($entity && $provenance && $entity['source_key']===$key && $entity['payload_hash']===Contract::hash(json_decode($entity['payload_json'],true,32,JSON_THROW_ON_ERROR)) && $entity['payload_hash']===$mapping->data['content_sha256'] && $provenance['envelope_hash']===$mapping->data['envelope_sha256'] && $provenance['evidence_hash']===$mapping->data['evidence_sha256'],'F6B_PROVENANCE_INTEGRITY');
            }elseif($sandbox->db->execute('SELECT 1 FROM f6b_entities WHERE target_id=? OR source_key=?',[$target,$key])->fetchColumn()){$reasons[]='F6B_TARGET_IDENTITY_CONFLICT';}
            foreach(Plan::claims($e) as $kind=>$hash){$owner=$sandbox->db->execute('SELECT source_key FROM f6b_claims WHERE kind=? AND claim_hash=?',[$kind,$hash])->fetchColumn();if($owner!==false && $owner!==$key){$reasons[]=$kind.'_CONFLICT';}}
        }
        return array_values(array_unique($reasons));
    }
    private function snapshotConflicts(Plan $plan,Sandbox $sandbox): array
    {
        foreach($plan->snapshots() as $key=>$root){$stored=$sandbox->db->execute('SELECT snapshot_hash FROM f6b_source_snapshots WHERE snapshot_key=?',[$key])->fetchColumn();if($stored!==false && !hash_equals($stored,$root)){return ['SOURCE_SNAPSHOT_CONFLICT'];}}
        return [];
    }
    private function apply(SourceEnvelope $e,Plan $plan,Sandbox $sandbox): void
    {
        $db=$sandbox->db;$key=$e->identity->key();
        if($db->execute('SELECT 1 FROM f6b_mappings WHERE source_key=?',[$key])->fetchColumn()){return;}
        foreach(Plan::dependencies($e) as $dependency){Contract::require((bool)$db->execute('SELECT 1 FROM f6b_mappings WHERE source_key=?',[$dependency])->fetchColumn(),'F6B_PARENT_NOT_APPLIED');}
        $owner=isset($e->payload['customer'])?(new SourceIdentity($e->identity->instance,Entity::Customer,$e->payload['customer']))->key():null;
        $target=Plan::target($e);$mapping=MappingEvidence::propose($e,$e->identity->entity->value,$target);
        $db->execute('INSERT INTO f6b_entities VALUES (?,?,?,?,?,?)',[$target,$key,$e->identity->entity->value,$owner,CanonicalJson::encode($e->payload),$e->contentHash]);
        $db->execute('INSERT INTO f6b_mappings VALUES (?,?,?,?)',[$key,$target,CanonicalJson::encode($mapping->data),$mapping->data['evidence_sha256']]);
        foreach(Plan::claims($e) as $kind=>$hash){$db->execute('INSERT INTO f6b_claims VALUES (?,?,?)',[$kind,$hash,$key]);}
        $db->execute('INSERT INTO f6b_provenance VALUES (?,?,?,?,?)',[$key,$plan->hash,$e->metadata['snapshot'],$e->fingerprint,$mapping->data['evidence_sha256']]);
    }
    private function verifyRun(array $row,Plan $plan,Sandbox $sandbox): void
    {
        Contract::require($row['id']===$plan->hash && $row['plan_hash']===$plan->hash && $row['target_snapshot']===$sandbox->snapshot && (int)$row['cursor']<=count($plan->items),'F6B_RUN_CONTEXT');
        Contract::require(hash_equals($row['checkpoint_hash'],$this->signature($row,$sandbox)),'F6B_CHECKPOINT_INTEGRITY');
        $last=$sandbox->db->execute('SELECT * FROM f6b_history WHERE run_id=? ORDER BY id DESC LIMIT 1',[$row['id']])->fetch();
        Contract::require($last && $last['event_hash']===$row['last_event_hash'] && $last['state']===$row['state'] && (int)$last['cursor']===(int)$row['cursor'] && (int)$last['version']===(int)$row['version'],'F6B_HISTORY_INTEGRITY');
        for($i=0;$i<(int)$row['cursor'];++$i){Contract::require((bool)$sandbox->db->execute('SELECT 1 FROM f6b_mappings WHERE source_key=?',[$plan->items[$i]->identity->key()])->fetchColumn(),'F6B_CHECKPOINT_GAP');}
    }
    private function expected(array $row,?array $expected,Sandbox $sandbox): void
    {
        if($expected===null){return;}
        Contract::require(Contract::hash($expected)===Contract::hash($this->checkpoint($row,$sandbox)),'F6B_STALE_CHECKPOINT');
    }
    private function signature(array $row,Sandbox $sandbox): string
    {
        return $sandbox->sign([$row['id'],$row['plan_hash'],$row['target_snapshot'],(int)$row['cursor'],(int)$row['version'],$row['state'],$row['last_event_hash'],$row['safe_code']]);
    }
    private function saveRun(array &$row,Sandbox $sandbox): void
    {
        $row['checkpoint_hash']=$this->signature($row,$sandbox);
        $sandbox->db->execute('UPDATE f6b_runs SET `cursor`=?,version=?,state=?,last_event_hash=?,checkpoint_hash=?,safe_code=? WHERE id=?',[$row['cursor'],$row['version'],$row['state'],$row['last_event_hash'],$row['checkpoint_hash'],$row['safe_code'],$row['id']]);
    }
    private function event(array &$row,Sandbox $sandbox,string $state): void
    {
        $previous=$row['last_event_hash'];$row['state']=$state;
        $hash=$sandbox->sign([$row['id'],(int)$row['version'],$state,(int)$row['cursor'],$previous]);
        $sandbox->db->execute('INSERT INTO f6b_history(run_id,version,state,`cursor`,previous_hash,event_hash) VALUES (?,?,?,?,?,?)',[$row['id'],$row['version'],$state,$row['cursor'],$previous,$hash]);$row['last_event_hash']=$hash;
    }
    private function checkpoint(array $row,Sandbox $sandbox): array
    {
        return ['run_id'=>$row['id'],'target_snapshot'=>$sandbox->snapshot,'cursor'=>(int)$row['cursor'],'version'=>(int)$row['version'],'hash'=>$row['checkpoint_hash']];
    }
    private function manifest(Plan $plan,array $row,Sandbox $sandbox,bool $replay,array $reasons=[]): array
    {
        $result=$plan->manifest('SYNTHETIC_LOAD',$reasons);$result['status']=$row['state'];$result['imported_records']=(int)$row['cursor'];$result['replay']=$replay;
        $result['checkpoint']=$this->checkpoint($row,$sandbox);$result['effects']=$this->effects->counts();return $result;
    }
    /** Test-only projection; no native route, session or attachment download capability. */
    public function publicArchive(Sandbox $sandbox,SourceIdentity $customer,SourceIdentity $ticket): array
    {
        $sandbox->assertReady();Contract::require($customer->entity===Entity::Customer && $ticket->entity===Entity::Ticket && $customer->instance===$ticket->instance,'F6B_ARCHIVE_SCOPE');
        $row=$sandbox->db->execute('SELECT * FROM f6b_entities WHERE source_key=? AND owner_key=? AND entity_type=?',[$ticket->key(),$customer->key(),'ticket'])->fetch();
        Contract::require((bool)$row,'F6B_ARCHIVE_SCOPE');$messages=[];$allowed=[];$attachments=[];
        foreach($sandbox->db->execute('SELECT source_key,payload_json FROM f6b_entities WHERE owner_key=? AND entity_type=?',[$customer->key(),'message'])->fetchAll() as $item){$p=json_decode($item['payload_json'],true,32,JSON_THROW_ON_ERROR);if($p['ticket']!==$ticket->id || $p['kind']!=='PUBLIC_REPLY' || $p['visibility']!==['state'=>'KNOWN','value'=>'PUBLIC']){continue;}$allowed[$p['sequence']]=$p;$messages[]=['id'=>$item['source_key'],'sequence'=>$p['sequence'],'created_at'=>$p['created_at'],'body'=>$p['body']];}
        usort($messages,static fn(array $a,array $b): int=>$a['sequence']<=>$b['sequence']);
        foreach($sandbox->db->execute('SELECT source_key,payload_json FROM f6b_entities WHERE owner_key=? AND entity_type=? ORDER BY source_key',[$customer->key(),'attachment'])->fetchAll() as $item){$p=json_decode($item['payload_json'],true,32,JSON_THROW_ON_ERROR);if($p['ticket']!==$ticket->id){continue;}$message=$sandbox->db->execute('SELECT payload_json FROM f6b_entities WHERE source_key=? AND owner_key=? AND entity_type=?',[(new SourceIdentity($ticket->instance,Entity::Message,$p['message']))->key(),$customer->key(),'message'])->fetchColumn();if(!$message){continue;}$m=json_decode($message,true,32,JSON_THROW_ON_ERROR);if($m['kind']!=='PUBLIC_REPLY'||$m['visibility']['state']!=='KNOWN'||$m['visibility']['value']!=='PUBLIC'){continue;}$attachments[]=['id'=>$item['source_key'],'sha256'=>$p['sha256'],'size'=>$p['size'],'mime'=>$p['mime']];}
        return ['classification'=>'SYNTHETIC','messages'=>$messages,'attachments'=>$attachments,'effects_allowed'=>false];
    }
}
