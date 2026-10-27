<?php
declare(strict_types=1);
namespace Hostider\Migration\Reconciliation;
use Hostider\Core\Config;
use Hostider\Core\Database\Database;
use Hostider\Migration\{Contract,Entity,MappingEvidence,SnapshotValidator,SourceEnvelope,SourceIdentity};
use Hostider\Migration\Synthetic\{Fixture,Plan,Sandbox};

/** Controlled synthetic source + exact SELECT-only connection. No repair or native adapter. */
final readonly class Inspector
{
    private const ROWS=512;
    private const BYTES=2097152;
    private function __construct(private Sandbox $authority,private Database $db,private string $account) {}
    public static function open(Sandbox $authority,int $port,string $account,string $password): self
    {
        Sandbox::flags();$authority->assertReady();
        Contract::require($port>0 && $port<=65535 && preg_match('/^f6c_[a-f0-9]{16}$/D',$account)===1 && preg_match('/^[a-f0-9]{64}$/D',$password)===1,'F6C_READER_INPUT');
        try{
            $db=Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),
                'mysql:host=127.0.0.1;port='.$port.';dbname='.$authority->schema.';charset=utf8mb4',$account,$password));
            $self=new self($authority,$db,$account);$self->fence();return $self;
        }catch(\Throwable){throw new \RuntimeException('F6C_READER_DENIED');}
    }
    private function fence(): void
    {
        $this->authority->assertReady();
        $db=$this->db;$host=(string)$db->execute("SELECT SUBSTRING_INDEX(USER(),'@',-1)")->fetchColumn();
        Contract::require($db->execute('SELECT DATABASE()')->fetchColumn()===$this->authority->schema && ($host==='localhost'||filter_var($host,FILTER_VALIDATE_IP)!==false)
            && $db->execute('SELECT CURRENT_USER()')->fetchColumn()===$this->account.'@'.$host,'F6C_READER_IDENTITY');
        Contract::require(in_array(strtoupper((string)$db->execute('SELECT CURRENT_ROLE()')->fetchColumn()),['','NONE'],true),'F6C_READER_ROLE');
        $select=false;$schema=str_replace('_','\\_',$this->authority->schema);
        foreach($db->execute('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN) as $grant){
            Contract::require(!str_contains($grant,'WITH GRANT OPTION')&&preg_match('/^GRANT (.+) ON (.+) TO /',$grant,$m)===1,'F6C_READER_GRANT');
            $scope=str_replace('`','',$m[2]);
            if($scope==='*.*'){Contract::require($m[1]==='USAGE','F6C_READER_GLOBAL');continue;}
            Contract::require($m[1]==='SELECT' && in_array($scope,[$schema.'.*',str_replace('\\','\\\\',$schema).'.*'],true),'F6C_READER_SCOPE');$select=true;
        }
        Contract::require($select,'F6C_READER_SELECT');
        $marker=$db->execute('SELECT schema_name,target_snapshot,expires_at>UTC_TIMESTAMP(6) AS fresh FROM f6b_environment WHERE id=1')->fetch();
        Contract::require($marker && (int)$marker['fresh']===1 && $marker['schema_name']===$this->authority->schema && $marker['target_snapshot']===$this->authority->snapshot,'F6C_READER_MARKER');
    }
    public function inspect(Fixture $fixture,?array $expectedCheckpoint=null): Report
    {
        $locked=false;
        try{
            $this->fence();$items=$fixture->envelopes();Contract::require(count($items)<=256,'F6C_SOURCE_LIMIT');
            $plan=Plan::build($fixture);
            if($plan->reasons!==[]){return $this->unavailable('REVIEW_REQUIRED',$plan->reasons);}
            Contract::require((int)$this->db->execute('SELECT GET_LOCK(?,10)',[$this->authority->mutex()])->fetchColumn()===1,'F6C_BUSY');$locked=true;
            $this->db->execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->db->execute('SET TRANSACTION READ ONLY');
            $report=$this->db->transaction(function()use($plan,$expectedCheckpoint): Report{return $this->compare($plan,$this->capture(),$expectedCheckpoint);});
            $this->fence();return $report;
        }catch(\Throwable $e){
            $code=preg_match('/^F6C_[A-Z0-9_]+$/D',$e->getMessage())===1?$e->getMessage():'F6C_STORAGE_UNAVAILABLE';
            return $this->unavailable(in_array($code,['F6C_SOURCE_LIMIT','F6C_TABLE_LIMIT','F6C_BYTE_LIMIT','F6C_BUSY'],true)?'INCONCLUSIVE':'BLOCKED',[$code]);
        }finally{if($locked){try{$this->db->execute('SELECT RELEASE_LOCK(?)',[$this->authority->mutex()]);}catch(\Throwable){/* Session loss releases the mutex. */}}}
    }
    private function unavailable(string $status,array $reasons): Report
    {
        $reasons=array_values(array_unique($reasons));sort($reasons,SORT_STRING);
        return new Report(['classification'=>'SYNTHETIC','contract'=>'hostider.reconciliation.v1','status'=>$status,'reasons'=>$reasons,
            'source_target_counts'=>null,'finance'=>['status'=>'NOT_AVAILABLE'],'integrity'=>'NOT_VERIFIED','real_whmcs'=>'NOT_VERIFIED','file_bytes'=>'NOT_VERIFIED','effects_allowed'=>false]);
    }
    private function capture(): array
    {
        $out=[];$bytes=0;
        foreach(Sandbox::TABLES as $table){
            $fields=match($table){
                'f6b_environment'=>['id','schema_name','account_name','token_hash','target_snapshot','expires_at'],
                'f6b_runs'=>['id','plan_hash','target_snapshot','cursor','version','state','last_event_hash','checkpoint_hash','safe_code'],
                'f6b_entities'=>['target_id','source_key','entity_type','owner_key','payload_json','payload_hash'],
                'f6b_source_snapshots'=>['snapshot_key','snapshot_hash','run_id'],
                'f6b_mappings'=>['source_key','target_id','evidence_json','evidence_hash'],
                'f6b_claims'=>['kind','claim_hash','source_key'],
                'f6b_provenance'=>['source_key','run_id','snapshot_id','envelope_hash','evidence_hash'],
                'f6b_history'=>['id','run_id','version','state','cursor','previous_hash','event_hash'],
                'schema_migrations'=>['version','checksum','status','applied_at']};
            $columns=$this->db->execute('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION LIMIT 32',[$table])->fetchAll(\PDO::FETCH_COLUMN);
            Contract::require($columns===$fields,'F6C_SCHEMA_COLUMNS');
            $indexes=$this->db->execute('SELECT INDEX_NAME,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND NON_UNIQUE=0 ORDER BY INDEX_NAME,SEQ_IN_INDEX LIMIT 32',[$table])->fetchAll();
            $unique=[];foreach($indexes as $index){$unique[$index['INDEX_NAME']][]=$index['COLUMN_NAME'];}
            $primary=$table==='f6b_claims'?['kind','claim_hash']:[$fields[0]];
            Contract::require(($unique['PRIMARY']??null)===$primary,'F6C_SCHEMA_PRIMARY_KEY');
            if(in_array($table,['f6b_entities','f6b_mappings'],true)){$required=$table==='f6b_entities'?['source_key']:['target_id'];Contract::require(in_array($required,array_values($unique),true),'F6C_SCHEMA_UNIQUENESS');}
            $quoted=array_map(static fn(string $field): string=>'`'.$field.'`',$fields);
            $expression=implode('+',array_map(static fn(string $field): string=>'COALESCE(OCTET_LENGTH('.$field.'),0)',$quoted));
            $order=match($table){'f6b_entities'=>'target_id','f6b_mappings','f6b_provenance'=>'source_key','f6b_claims'=>'kind,claim_hash','f6b_source_snapshots'=>'snapshot_key','schema_migrations'=>'version',default=>'id'};
            $probe=$this->db->execute('SELECT COUNT(*) AS n,COALESCE(SUM(b),0) AS bytes,COALESCE(MAX(b),0) AS largest FROM (SELECT '.$expression.' AS b FROM '.$table.' ORDER BY '.$order.' LIMIT 513) bounded_rows')->fetch();
            Contract::require((int)$probe['n']<=self::ROWS,'F6C_TABLE_LIMIT');$bytes+=(int)$probe['bytes'];
            Contract::require((int)$probe['largest']<=65536 && $bytes<=self::BYTES,'F6C_BYTE_LIMIT');
            $out[$table]=$this->db->execute('SELECT '.implode(',',$quoted).' FROM '.$table.' ORDER BY '.$order.' LIMIT 513')->fetchAll();
        }
        return $out;
    }
    private function compare(Plan $plan,array $tables,?array $expected): Report
    {
        $reasons=[];$issues=[];$source=[];$facts=[];$targetFacts=[];$targetEnvelopes=[];$counts=[];$matched=[];$financialUnknown=false;
        foreach(Entity::cases() as $type){$counts[$type->value]=['source'=>0,'target'=>0,'matched'=>0,'unmatched'=>0,'extra'=>0];}
        foreach($plan->items as $e){$key=$e->identity->key();$source[$key]=$e;++$counts[$e->identity->entity->value]['source'];$facts[$key]=['type'=>$e->identity->entity->value,'payload'=>$e->payload,'instance'=>$e->identity->instance,'id'=>$e->identity->id];}
        $entities=[];$mappings=[];$provenance=[];$claims=[];$runs=[];$history=[];$roots=[];
        foreach($tables['f6b_mappings'] as $row){$mappings[$row['source_key']]=$row;}
        foreach($tables['f6b_provenance'] as $row){$provenance[$row['source_key']]=$row;}
        foreach($tables['f6b_claims'] as $row){$claims[$row['kind'].':'.$row['claim_hash']]=$row['source_key'];}
        foreach($tables['f6b_runs'] as $row){$runs[$row['id']]=$row;}
        foreach($tables['f6b_history'] as $row){$history[$row['run_id']][]=$row;}
        foreach($tables['f6b_source_snapshots'] as $row){$roots[$row['snapshot_key']]=$row;}
        foreach($tables['f6b_entities'] as $row){
            $key=$row['source_key'];$type=$row['entity_type'];$entities[$key]=$row;
            if(!isset($counts[$type])){$this->issue($issues,'UNEXPECTED_ENTITY_TYPE');$financialUnknown=true;continue;}++$counts[$type]['target'];
            if(!isset($source[$key])){++$counts[$type]['extra'];$this->issue($issues,'EXTRA_TARGET');if(in_array($type,['order','payment','allocation','refund','wallet','document'],true)){$financialUnknown=true;}continue;}
            $e=$source[$key];$owner=isset($e->payload['customer'])?(new SourceIdentity($e->identity->instance,Entity::Customer,$e->payload['customer']))->key():null;
            if($row['target_id']!==Plan::target($e)||$type!==$e->identity->entity->value){$this->issue($issues,'TARGET_IDENTITY_CONFLICT');}
            if($row['owner_key']!==$owner){$this->issue($issues,'CUSTOMER_OWNERSHIP_CONFLICT');}
            try{
                $p=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);Contract::require(is_array($p),'F6C_TARGET_PAYLOAD');
                if(!hash_equals($row['payload_hash'],Contract::hash($p))){$this->issue($issues,'TARGET_CONTENT_INTEGRITY');}
                if($row['payload_hash']!==$e->contentHash || Contract::hash($p)!==$e->contentHash){$this->issue($issues,'CONTENT_MISMATCH');$this->differences($e,$p,$issues);}
                elseif($row['target_id']===Plan::target($e) && $type===$e->identity->entity->value && $row['owner_key']===$owner){$matched[$key]=true;}
                $targetFacts[$key]=['type'=>$type,'payload'=>$p,'instance'=>$e->identity->instance,'id'=>$e->identity->id];
                $targetEnvelopes[]=SourceEnvelope::fromArray([...$e->metadata,'payload'=>$p,'content_sha256'=>Contract::hash($p)]);
            }catch(\Throwable){$this->issue($issues,'TARGET_PAYLOAD_UNVERIFIABLE');}
        }
        foreach($source as $key=>$e){
            $before=array_sum($issues);
            if(!isset($entities[$key])){$this->issue($issues,'MISSING_TARGET');}
            $type=$e->identity->entity->value;
            foreach(Plan::dependencies($e) as $parent){if(!isset($entities[$parent])){$this->issue($issues,'MISSING_PARENT_OR_ORPHAN');}}
            $proposal=MappingEvidence::propose($e,$type,Plan::target($e));
            if(!isset($mappings[$key])){$this->issue($issues,'MISSING_MAPPING');}
            else{try{$m=$mappings[$key];$decoded=MappingEvidence::fromArray(json_decode($m['evidence_json'],true,32,JSON_THROW_ON_ERROR));Contract::require($m['target_id']===Plan::target($e) && $m['evidence_hash']===$proposal->data['evidence_sha256'] && Contract::hash($decoded->data)===Contract::hash($proposal->data),'F6C_MAPPING_EVIDENCE');}catch(\Throwable){$this->issue($issues,'MAPPING_EVIDENCE_INVALID');}}
            if(!isset($provenance[$key])){$this->issue($issues,'MISSING_PROVENANCE');}
            else{$p=$provenance[$key];if($p['run_id']!==$plan->hash || $p['snapshot_id']!==$e->metadata['snapshot'] || $p['envelope_hash']!==$e->fingerprint || $p['evidence_hash']!==$proposal->data['evidence_sha256']){$this->issue($issues,'PROVENANCE_INVALID');}}
            foreach(Plan::claims($e) as $kind=>$hash){if(($claims[$kind.':'.$hash]??null)!==$key){$this->issue($issues,'CLAIM_MISSING_OR_OWNER_CONFLICT');}}
            if(array_sum($issues)>$before){unset($matched[$key]);}
        }
        foreach($matched as $key=>$flag){++$counts[$source[$key]->identity->entity->value]['matched'];}
        foreach($counts as &$count){$count['unmatched']=$count['source']-$count['matched'];}unset($count);
        foreach([$mappings,$provenance] as $index){foreach($index as $key=>$row){if(!isset($source[$key])){$this->issue($issues,'UNEXPLAINED_EVIDENCE');}}}
        $expectedClaims=[];$observedClaims=[];
        foreach($source as $key=>$e){foreach(Plan::claims($e) as $kind=>$hash){$expectedClaims[$kind.':'.$hash]=$key;}}
        foreach($claims as $claim=>$key){if(($expectedClaims[$claim]??null)!==$key){$this->issue($issues,'UNEXPLAINED_CLAIM');}}
        foreach($targetEnvelopes as $e){try{foreach(Plan::claims($e) as $kind=>$hash){$key=$kind.':'.$hash;if(isset($observedClaims[$key]) && $observedClaims[$key]!==$e->identity->key()){$this->issue($issues,'DUPLICATE_EXTERNAL_CLAIM');}$observedClaims[$key]=$e->identity->key();if(($claims[$key]??null)!==$e->identity->key()){$this->issue($issues,'TARGET_CLAIM_CONFLICT');}}}catch(\Throwable){$this->issue($issues,'TARGET_CLAIM_UNVERIFIABLE');}}
        if(count($targetEnvelopes)===count($source)){
            try{$validation=(new SnapshotValidator())->validate($targetEnvelopes);foreach($validation->reasons as $reason){$this->issue($issues,'TARGET_'.$reason);}}
            catch(\Throwable $e){$this->issue($issues,'TARGET_'.$this->safeCode($e));}
        }
        foreach($plan->snapshots() as $key=>$hash){if(!isset($roots[$key]) || $roots[$key]['snapshot_hash']!==$hash || $roots[$key]['run_id']!==$plan->hash || !isset($runs[$roots[$key]['run_id']])){$this->issue($issues,'SOURCE_SNAPSHOT_ROOT_INVALID');}}
        foreach($roots as $key=>$row){if(!array_key_exists($key,$plan->snapshots())){$this->issue($issues,'UNEXPLAINED_SOURCE_SNAPSHOT');}}
        foreach($runs as $id=>$row){if($id!==$plan->hash){$this->issue($issues,'UNEXPLAINED_RUN');}if(!$this->runValid($row,$history[$id]??[])){$this->issue($issues,'RUN_HISTORY_CHECKPOINT_INVALID');}}
        foreach($history as $id=>$events){if(!isset($runs[$id])){$this->issue($issues,'ORPHAN_HISTORY');}}
        $run=$runs[$plan->hash]??null;$complete=$run && $run['state']==='APPLIED' && (int)$run['cursor']===count($source);
        if(!$run){$reasons[]='RUN_NOT_FOUND';}
        else{
            if($run['target_snapshot']!==$this->authority->snapshot || $run['plan_hash']!==$plan->hash){$this->issue($issues,'RUN_CONTEXT_INVALID');}
            if((int)$run['cursor']>count($source)){$this->issue($issues,'CURSOR_OUT_OF_SCOPE');}
            for($i=0;$i<min((int)$run['cursor'],count($plan->items));++$i){$key=$plan->items[$i]->identity->key();if(!isset($entities[$key],$mappings[$key],$provenance[$key])){$this->issue($issues,'CHECKPOINT_PREFIX_GAP');}}
            if($expected!==null){$actual=['run_id'=>$run['id'],'target_snapshot'=>$run['target_snapshot'],'cursor'=>(int)$run['cursor'],'version'=>(int)$run['version'],'hash'=>$run['checkpoint_hash']];if(Contract::hash($actual)!==Contract::hash($expected)){$this->issue($issues,'STALE_OR_WRONG_CHECKPOINT');}}
        }
        if(!$complete){$reasons[]='RUN_INCOMPLETE';}
        $hardIssues=$issues;foreach(['MISSING_TARGET','MISSING_MAPPING','MISSING_PROVENANCE','CLAIM_MISSING_OR_OWNER_CONFLICT','MISSING_PARENT_OR_ORPHAN'] as $missing){unset($hardIssues[$missing]);}if(!$run){unset($hardIssues['SOURCE_SNAPSHOT_ROOT_INVALID']);}
        $status=$complete?($issues===[]?'PASS':'REVIEW_REQUIRED'):($hardIssues===[]?'INCONCLUSIVE':'REVIEW_REQUIRED');
        if($run && $run['state']==='REVIEW_REQUIRED'){$status='REVIEW_REQUIRED';$reasons[]='RUN_REVIEW_REQUIRED';}
        foreach(array_keys($issues) as $code){$reasons[]=$code;}sort($reasons,SORT_STRING);ksort($issues,SORT_STRING);ksort($counts,SORT_STRING);
        try{Contract::require(!$financialUnknown,'F6C_FINANCE_UNKNOWN_SCOPE');$finance=['source'=>Finance::totals($facts),'target'=>Finance::totals($targetFacts)];$finance['differences_by_currency']=Finance::differences($finance['source'],$finance['target']);$finance['equal']=Contract::hash($finance['source'])===Contract::hash($finance['target']);if(!$finance['equal']){$reasons[]='FINANCE_TOTALS_MISMATCH';if($complete){$status='REVIEW_REQUIRED';}}}catch(\Throwable){$finance=['status'=>'NOT_AVAILABLE'];$reasons[]='FINANCE_UNVERIFIABLE';$status='REVIEW_REQUIRED';}
        $reasons=array_values(array_unique($reasons));sort($reasons,SORT_STRING);
        return new Report(['classification'=>'SYNTHETIC','contract'=>'hostider.reconciliation.v1','status'=>$status,'source_contract'=>Contract::VERSION,'transform'=>Contract::TRANSFORM,
            'plan_version'=>'f6b.v1','plan_hash'=>$plan->hash,'source_snapshot_roots'=>$plan->snapshots(),'target_snapshot'=>$this->authority->snapshot,
            'run'=>$run?['id'=>$run['id'],'state'=>$run['state'],'cursor'=>(int)$run['cursor'],'version'=>(int)$run['version']]:null,
            'source_target_counts'=>$counts,'integrity'=>$issues===[]?'MATCHED':'DIFFERENCES','differences'=>$issues,'reasons'=>$reasons,'finance'=>$finance,
            'providers'=>$this->providers($facts,$targetFacts),'support'=>$this->support($facts,$targetFacts),'documents'=>$this->documents($facts,$targetFacts),
            'bounds'=>['source'=>256,'rows_per_table'=>self::ROWS,'total_bytes'=>self::BYTES,'data_queries'=>18,'metadata_queries'=>18],
            'real_whmcs'=>'NOT_VERIFIED','real_provider'=>'NOT_VERIFIED','real_support_archive'=>'NOT_VERIFIED','file_bytes'=>'NOT_VERIFIED',
            'av'=>'NOT_VERIFIED','mime_bytes'=>'NOT_VERIFIED','retention'=>'NOT_VERIFIED','native_auth'=>'NOT_APPLICABLE_NO_ENDPOINT',
            'effects_allowed'=>false,'provider_operations_dispatched'=>0,'native_writes'=>0]);
    }
    private function issue(array &$issues,string $code): void {$issues[$code]=($issues[$code]??0)+1;}
    private function safeCode(\Throwable $e): string {return preg_match('/^[A-Z0-9_]{1,80}$/D',$e->getMessage())===1?$e->getMessage():'CONTRACT_UNVERIFIABLE';}
    private function differences(SourceEnvelope $e,array $p,array &$issues): void
    {
        $type=$e->identity->entity;
        if(in_array($type,[Entity::Resource,Entity::Service],true)){$this->issue($issues,'PROVIDER_RESOURCE_METADATA_MISMATCH');}
        if(in_array($type,[Entity::Payment,Entity::Allocation,Entity::Refund,Entity::Wallet,Entity::Order],true)){$this->issue($issues,'FINANCIAL_FACT_MISMATCH');}
        if($type===Entity::Document){$this->issue($issues,'DOCUMENT_METADATA_MISMATCH');if(($p['sha256']??null)!==$e->payload['sha256']){$this->issue($issues,'DOCUMENT_CHECKSUM_METADATA_MISMATCH');}}
        if($type===Entity::Message){$this->issue($issues,'SUPPORT_MESSAGE_METADATA_MISMATCH');}
        if($type===Entity::Attachment){$this->issue($issues,'ATTACHMENT_METADATA_MISMATCH');}
    }
    private function runValid(array $row,array $events): bool
    {
        $signature=$this->authority->sign([$row['id'],$row['plan_hash'],$row['target_snapshot'],(int)$row['cursor'],(int)$row['version'],$row['state'],$row['last_event_hash'],$row['safe_code']]);
        if(!hash_equals($row['checkpoint_hash'],$signature)||$events===[]){return false;}
        usort($events,static fn(array $a,array $b): int=>(int)$a['id']<=>(int)$b['id']);$previous=str_repeat('0',64);
        foreach($events as $event){if($event['previous_hash']!==$previous || $event['event_hash']!==$this->authority->sign([$row['id'],(int)$event['version'],$event['state'],(int)$event['cursor'],$previous])){return false;}$previous=$event['event_hash'];}
        $last=$events[count($events)-1];return $previous===$row['last_event_hash'] && $last['state']===$row['state'] && (int)$last['cursor']===(int)$row['cursor'] && (int)$last['version']===(int)$row['version'];
    }
    private function providers(array $source,array $target): array
    {
        $out=[];foreach(['CPANEL','VOYAR','RESELLERCLUB','DOMAINNAMEAPI'] as $provider){foreach(['source'=>$source,'target'=>$target] as $side=>$facts){$out[$provider][$side]=count(array_filter($facts,static fn(array $f): bool=>$f['type']==='resource'&&($f['payload']['provider']??null)===$provider));}$out[$provider]['operations']=['CREATE'=>0,'RENEW'=>0,'TERMINATE'=>0,'TRANSFER'=>0,'UPGRADE'=>0,'DOWNGRADE'=>0];}return $out;
    }
    private function support(array $source,array $target): array
    {
        $out=[];foreach(['source'=>$source,'target'=>$target] as $side=>$facts){$order=[];$public=0;$private=0;$attachments=0;
            foreach($facts as $key=>$f){$p=$f['payload'];if($f['type']==='message'){$order[]=[Contract::hash([$f['instance'],$p['ticket']??null]),$p['sequence']??null,$key,$p['created_at']??null];if(($p['kind']??null)==='PUBLIC_REPLY'&&($p['visibility']??null)===['state'=>'KNOWN','value'=>'PUBLIC']){++$public;}else{++$private;}}
                if($f['type']==='attachment'){++$attachments;}}
            sort($order,SORT_REGULAR);$out[$side]=['public_messages'=>$public,'private_or_unverified_messages'=>$private,'attachments'=>$attachments,'message_order_sha256'=>Contract::hash($order)];}
        $out['projection']='AGGREGATE_ONLY_NO_BODY_AUTHOR_PATH';$out['native_client_api']=false;return $out;
    }
    private function documents(array $source,array $target): array
    {
        $out=[];foreach(['source'=>$source,'target'=>$target] as $side=>$facts){$documents=[];foreach($facts as $key=>$f){if($f['type']==='document'){$documents[$key]=Contract::hash($f['payload']);}}ksort($documents,SORT_STRING);$out[$side]=['count'=>count($documents),'metadata_sha256'=>Contract::hash($documents),'byte_checksum'=>'NOT_VERIFIED'];}return $out;
    }
}
