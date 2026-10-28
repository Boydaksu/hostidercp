<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;
use Hostider\Core\Config;
use Hostider\Core\Database\{Database,Migrator};
use Hostider\Migration\Contract;
use Hostider\Migration\Reconciliation\Report;
use Hostider\Migration\Synthetic\Sandbox;

/** Actual restored base tables, fixed SELECTs only. Never an original Sandbox. */
final readonly class RecoveryInspector
{
    private function __construct(private RecoveryContext $context,private RecoveryTargetPolicy $policy,private Database $db) {}
    public static function open(RecoveryContext $context,RecoveryTargetPolicy $policy,#[\SensitiveParameter] string $password): self
    {
        $context->fence();$policy->check($context->verified['backup_id']);Contract::require(preg_match('/^[a-f0-9]{64}$/D',$password)===1,'R2_PASSWORD');$b=$policy->binding;
        try{$db=Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),'mysql:host=127.0.0.1;port='.$b['port'].';dbname='.$b['schema'].';charset=utf8mb4',$b['account'],$password));$self=new self($context,$policy,$db);$self->fence();return $self;}
        catch(\Throwable){throw new \RuntimeException('R2_TARGET_DENIED');}
    }
    private function fence(): void
    {
        $this->context->fence();$this->policy->check($this->context->verified['backup_id']);$b=$this->policy->binding;$db=$this->db;$host=$db->execute("SELECT SUBSTRING_INDEX(USER(),'@',-1)")->fetchColumn();
        Contract::require(($host==='localhost'||filter_var($host,FILTER_VALIDATE_IP)!==false)&&$db->execute('SELECT DATABASE()')->fetchColumn()===$b['schema']&&$db->execute('SELECT CURRENT_USER()')->fetchColumn()===$b['account'].'@'.$host&&in_array($db->execute('SELECT CURRENT_ROLE()')->fetchColumn(),[null,'','NONE'],true),'R2_READER_IDENTITY');
        $select=false;$schema=str_replace('_','\\_',$b['schema']);foreach($db->execute('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN) as $grant){Contract::require(!str_contains($grant,'WITH GRANT OPTION')&&preg_match('/^GRANT (.+) ON (.+) TO /',$grant,$m)===1,'R2_GRANT');$scope=str_replace('`','',$m[2]);if($scope==='*.*'){Contract::require($m[1]==='USAGE','R2_GLOBAL_GRANT');continue;}
            Contract::require($m[1]==='SELECT'&&in_array($scope,[$schema.'.*',str_replace('\\','\\\\',$schema).'.*'],true),'R2_SCOPE_GRANT');$select=true;}Contract::require($select,'R2_SELECT_REQUIRED');
        $rows=$db->execute('SELECT TABLE_NAME,TABLE_TYPE,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME LIMIT 11')->fetchAll();$expected=[...Sandbox::TABLES,'f6d_restore_marker'];sort($expected);Contract::require(array_column($rows,'TABLE_NAME')===$expected,'R2_TABLE_SET');foreach($rows as $r){Contract::require($r['TABLE_TYPE']==='BASE TABLE'&&$r['ENGINE']==='InnoDB','R2_BASE_TABLE_ONLY');}
        $create=$db->execute('SHOW CREATE TABLE f6d_restore_marker')->fetch(\PDO::FETCH_NUM);Contract::require(Contract::hash($create[1])===$b['marker_schema_sha256'],'R2_MARKER_SCHEMA');$this->schema();
        $marker=$db->execute('SELECT id,schema_name,account_name,token_hash,vault_id,expires_at FROM f6d_restore_marker WHERE id=1')->fetch();$fresh=$db->execute('SELECT expires_at>UTC_TIMESTAMP(6) FROM f6d_restore_marker WHERE id=1')->fetchColumn();
        Contract::require($marker&&(int)$fresh===1&&$marker['schema_name']===$b['schema']&&Contract::hash($marker)===$b['marker_sha256']&&(int)$db->execute('SELECT COUNT(*) FROM(SELECT id FROM f6d_restore_marker LIMIT 2) marker_rows')->fetchColumn()===1,'R2_MARKER_OR_LEASE');
        (new Migrator($db,Sandbox::directory()))->assertReady();
    }
    private function schema(): void
    {
        $schemas=[];foreach(Sandbox::TABLES as $table){$c=$this->db->execute('SHOW CREATE TABLE '.$table)->fetch(\PDO::FETCH_NUM);$schemas[$table]=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$c[1]);
            $cols=$this->db->execute('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION LIMIT 32',[$table])->fetchAll(\PDO::FETCH_COLUMN);$expected=$this->context->columns($table);sort($cols);sort($expected);Contract::require($cols===$expected,'R2_COLUMNS');}
        Contract::require(Contract::hash($schemas)===$this->context->schemaDigest(),'R2_SCHEMA_OR_INDEX');
    }
    public function inspect(string $fault='NONE'): Report
    {
        $locked=false;$lock='f6d:'.substr(hash('sha256',$this->policy->binding['schema']),0,48);
        try{$this->fence();Contract::require(in_array($fault,['NONE','CRASH_BEFORE','CRASH_CAPTURE','CRASH_REPORT'],true),'R2_FAULT');if($fault==='CRASH_BEFORE'){exit(73);}
            Contract::require((int)$this->db->execute('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()===1,'R2_BUSY');$locked=true;$this->db->execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->db->execute('SET TRANSACTION READ ONLY');
            $tables=$this->db->transaction(function()use($fault): array{$out=[];$bytes=0;foreach(Sandbox::TABLES as $table){$cols=$this->context->columns($table);$q=[];foreach($cols as $c){Contract::require(preg_match('/^[a-z_]+$/D',$c)===1,'R2_COLUMNS');$q[]='`'.$c.'`';}$order=match($table){'f6b_entities'=>'target_id','f6b_mappings','f6b_provenance'=>'source_key','f6b_claims'=>'kind,claim_hash','f6b_source_snapshots'=>'snapshot_key','schema_migrations'=>'version',default=>'id'};
                $sum=implode('+',array_map(static fn(string $c): string=>'COALESCE(OCTET_LENGTH('.$c.'),0)',$q));$probe=$this->db->execute('SELECT COUNT(*) n,COALESCE(SUM(b),0) bytes,COALESCE(MAX(b),0) largest FROM(SELECT '.$sum.' b FROM '.$table.' ORDER BY '.$order.' LIMIT 513) bounded_rows')->fetch();$bytes+=(int)$probe['bytes'];Contract::require((int)$probe['n']<=512&&(int)$probe['largest']<=65536&&$bytes<=2097152,'R2_DATA_BOUND');$out[$table]=$this->db->execute('SELECT '.implode(',',$q).' FROM '.$table.' ORDER BY '.$order.' LIMIT 513')->fetchAll();if($fault==='CRASH_CAPTURE'){exit(73);}}return $out;});
            $this->fence();$content=$this->context->compare($tables,$this->policy);$this->fence();if($fault==='CRASH_REPORT'){exit(73);}
            $content['status']=match($content['status']){'MATCHED_REFERENCE'=>'PASS','REFERENCE_DIFFERENCES'=>'REVIEW_REQUIRED',default=>'BLOCKED'};
            $content['verification_scope']='ACTUAL_RESTORED_DB_READ_ONLY';$content['target_identity_sha256']=$content['target_policy_sha256'];$content['observed_target_evidence_sha256']=$content['candidate_evidence_sha256'];unset($content['candidate_evidence_sha256'],$content['target_policy_sha256']);
            return new Report([...$content,'schema_verification'=>'MATCHED_BASE_TABLE_DEFINITIONS','migration'=>'READY','authorization'=>'R1_AND_TARGET_FENCES_PASSED','consistency'=>'REPEATABLE_READ_POINT_IN_TIME','executable_object_inventory'=>'NOT_VERIFIED_SELECT_ONLY','routines_triggers'=>'NOT_CALLED_NO_DML_OR_EXECUTE']);
        }catch(\Throwable $e){$code=preg_match('/^R[12]_[A-Z0-9_]+$/D',$e->getMessage())===1?$e->getMessage():'R2_UNVERIFIABLE';return new Report(['classification'=>'SYNTHETIC','contract'=>'hostider.readonly-recovery.v1','status'=>'BLOCKED','reasons'=>[$code],'effects_allowed'=>false,'runtime_authority'=>'NOT_GRANTED','original_hmac'=>'NOT_RECOMPUTED','production_restore'=>'NOT_VERIFIED']);}
        finally{if($locked){try{$this->db->execute('SELECT RELEASE_LOCK(?)',[$lock]);}catch(\Throwable){/* Connection loss releases advisory lock. */}}}
    }
}
