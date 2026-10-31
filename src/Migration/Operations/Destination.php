<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;
use Hostider\Core\Config;
use Hostider\Core\Database\{Database,Migrator};
use Hostider\Migration\Contract;
use Hostider\Migration\Synthetic\Sandbox;

/** Escrow clone, never a rebound native application database. */
final readonly class Destination
{
    private function __construct(private Database $db,private string $schema,private string $account,private string $secret,private Vault $files,private Database $witness) {}
    public static function open(int $port,string $schema,string $account,#[\SensitiveParameter] string $password,#[\SensitiveParameter] string $secret,Vault $files,#[\SensitiveParameter] ?Database $witness=null): self
    {
        Sandbox::flags();Contract::require(getenv('SYNTHETIC_BACKUP_ALLOWED')==='1'&&$port>0&&$port<=65535&&preg_match('/^f6d_[a-f0-9]{24}$/D',$schema)===1&&preg_match('/^f6d_[a-f0-9]{16}$/D',$account)===1&&preg_match('/^[a-f0-9]{64}$/D',$password)===1&&preg_match('/^[a-f0-9]{64}$/D',$secret)===1,'F6D_DESTINATION_DENIED');
        try{Contract::require($witness!==null,'F6D_DDL_WITNESS_REQUIRED');$db=Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),'mysql:host=127.0.0.1;port='.$port.';dbname='.$schema.';charset=utf8mb4',$account,$password));$self=new self($db,$schema,$account,$secret,$files,$witness);$self->fence();return $self;}catch(\Throwable){throw new \RuntimeException('F6D_DESTINATION_DENIED');}
    }
    private function fence(): void
    {
        Sandbox::flags();$this->files->fence();$host=$this->db->execute("SELECT SUBSTRING_INDEX(USER(),'@',-1)")->fetchColumn();
        Contract::require(getenv('SYNTHETIC_BACKUP_ALLOWED')==='1'&&$this->db->execute('SELECT DATABASE()')->fetchColumn()===$this->schema&&$this->db->execute('SELECT CURRENT_USER()')->fetchColumn()===$this->account.'@'.$host&&in_array($this->db->execute('SELECT CURRENT_ROLE()')->fetchColumn(),[null,'','NONE'],true),'F6D_DESTINATION_IDENTITY');
        $row=$this->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) AS fresh FROM f6d_restore_marker WHERE id=1')->fetch();
        Contract::require($row&&(int)$row['fresh']===1&&$row['schema_name']===$this->schema&&$row['account_name']===$this->account&&$row['vault_id']===$this->files->identity&&hash_equals($row['token_hash'],hash('sha256',$this->secret)),'F6D_DESTINATION_LEASE');
        $tables=$this->db->execute('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(\PDO::FETCH_COLUMN);$expected=[...Sandbox::TABLES,'f6d_restore_marker'];sort($expected);Contract::require($tables===$expected,'F6D_DESTINATION_TABLES');
        Contract::require($this->witness->execute('SELECT DATABASE()')->fetchColumn()===$this->schema,'F6D_WITNESS_SCHEMA');
        $visible=false;foreach($this->witness->execute('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN) as $grant){if(preg_match('/^GRANT (.+) ON (.+) TO /',$grant,$g)!==1){continue;}$scope=str_replace('`','',$g[2]);$exact=str_replace('_','\\_',$this->schema);if(($scope==='*.*'||in_array($scope,[$exact.'.*',str_replace('\\','\\\\',$exact).'.*'],true))&&($g[1]==='ALL PRIVILEGES'||in_array('TRIGGER',array_map('trim',explode(',',$g[1])),true))){$visible=true;}}
        Contract::require($visible&&(int)$this->witness->execute('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn()===0,'F6D_UNAPPROVED_TRIGGER_OR_WITNESS');
        $allowed=['USAGE'=>false,'SELECT'=>false];$inserts=[];$escaped=str_replace('_','\\_',$this->schema);
        foreach($this->db->execute('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN) as $grant){Contract::require(!str_contains($grant,'WITH GRANT OPTION')&&preg_match('/^GRANT (.+) ON (.+) TO /',$grant,$m)===1,'F6D_DESTINATION_GRANTS');$scope=str_replace('`','',$m[2]);
            if($scope==='*.*'){Contract::require($m[1]==='USAGE','F6D_DESTINATION_GLOBAL');$allowed['USAGE']=true;}
            elseif(in_array($scope,[$escaped.'.*',str_replace('\\','\\\\',$escaped).'.*'],true)){Contract::require($m[1]==='SELECT','F6D_DESTINATION_SCOPE');$allowed['SELECT']=true;}
            else{$prefix=$this->schema.'.';Contract::require(str_starts_with($scope,$prefix)&&$m[1]==='INSERT'&&in_array(substr($scope,strlen($prefix)),Sandbox::TABLES,true),'F6D_DESTINATION_DML');$inserts[substr($scope,strlen($prefix))]=true;}}
        Contract::require(!in_array(false,$allowed,true)&&count($inserts)===count(Sandbox::TABLES),'F6D_DESTINATION_GRANTS');
    }
    public function restore(#[\SensitiveParameter] AuthenticatedBackup $package,string $fault='NONE'): array
    {
        $data=$package->payload['data'];$backupId=$package->payload['logical_sha256'];
        $this->fence();Contract::require(in_array($fault,['NONE','CRASH_INSERT','CRASH_FILES','CRASH_COMMIT','FAIL_INSERT'],true),'F6D_FAULT');
        Contract::require(Contract::hash($data)===$backupId&&$data['contract']==='hostider.backup.v1','F6D_RESTORE_ID');
        $key='f6d:'.substr(hash('sha256',$this->schema),0,48);Contract::require((int)$this->db->execute('SELECT GET_LOCK(?,10)',[$key])->fetchColumn()===1,'F6D_BUSY');
        try{
            $schemas=[];foreach(Sandbox::TABLES as $table){$create=$this->db->execute('SHOW CREATE TABLE '.$table)->fetch(\PDO::FETCH_NUM);$schemas[$table]=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$create[1]);}
            Contract::require(Contract::hash($schemas)===Contract::hash($data['schema']),'F6D_SCHEMA_MISMATCH');
            $existing=$this->rows($data['tables']);
            $count=array_sum(array_map('count',$existing));$replay=$count>0;
            if($replay){Contract::require(Contract::hash($existing)===Contract::hash($data['tables']),'F6D_TARGET_NOT_EMPTY_OR_CONFLICT');$this->files->restoreFiles($data['files']);}
            else{$this->db->transaction(function()use($data,$fault): void{
                foreach(['f6b_environment','f6b_runs','f6b_entities','f6b_source_snapshots','f6b_mappings','f6b_claims','f6b_provenance','f6b_history','schema_migrations'] as $table){foreach($data['tables'][$table] as $row){$keys=array_keys($row);foreach($keys as $column){Contract::require(preg_match('/^[a-z_]+$/D',$column)===1,'F6D_COLUMNS');}$this->db->execute('INSERT INTO '.$table.' (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($row));if($fault==='CRASH_INSERT'){exit(73);}if($fault==='FAIL_INSERT'){throw new \RuntimeException('F6D_INJECTED_INSERT');}}}
                $this->files->restoreFiles($data['files']);if($fault==='CRASH_FILES'){exit(73);}(new Migrator($this->db,Sandbox::directory()))->assertReady();$this->fence();
            });}
            if($fault==='CRASH_COMMIT'){exit(73);}
            $rows=$this->rows($data['tables']);
            Contract::require(Contract::hash($rows)===Contract::hash($data['tables']),'F6D_RESTORE_DIGEST');$this->fence();
            return ['classification'=>'SYNTHETIC','escrow_restore'=>'VERIFIED','native_application_restore'=>'BLOCKED_IDENTITY_BOUND','f6c_activation'=>'BLOCKED_IDENTITY_BOUND','backup_id'=>$backupId,'logical_sha256'=>Contract::hash($rows),'finance'=>$data['report']['finance'],'files'=>count($data['files']),'file_contract'=>$data['file_contract'],'replay'=>$replay,'provider_create'=>0,'production_restore'=>'NOT_VERIFIED'];
        }finally{$this->db->execute('SELECT RELEASE_LOCK(?)',[$key]);}
    }
    private function rows(array $expected): array
    {
        $this->db->execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->db->execute('SET TRANSACTION READ ONLY');
        return $this->db->transaction(function()use($expected): array{$out=[];$total=0;foreach(Sandbox::TABLES as $table){$columns=array_keys($expected[$table][0]??[]);Contract::require($columns!==[]&&count($columns)<=32,'F6D_TARGET_COLUMNS');$quoted=[];foreach($columns as $column){Contract::require(preg_match('/^[a-z_]+$/D',$column)===1,'F6D_TARGET_COLUMNS');$quoted[]='`'.$column.'`';}$order=match($table){'f6b_entities'=>'target_id','f6b_mappings','f6b_provenance'=>'source_key','f6b_claims'=>'kind,claim_hash','f6b_source_snapshots'=>'snapshot_key','schema_migrations'=>'version',default=>'id'};$expression=implode('+',array_map(static fn(string $c): string=>'COALESCE(OCTET_LENGTH('.$c.'),0)',$quoted));$probe=$this->db->execute('SELECT COUNT(*) AS n,COALESCE(SUM(b),0) AS bytes,COALESCE(MAX(b),0) AS largest FROM(SELECT '.$expression.' AS b FROM '.$table.' ORDER BY '.$order.' LIMIT 513) bounded_rows')->fetch();$total+=(int)$probe['bytes'];Contract::require((int)$probe['n']<=512&&(int)$probe['largest']<=70000&&$total<=2097152,'F6D_TARGET_BOUND');$out[$table]=$this->db->execute('SELECT '.implode(',',$quoted).' FROM '.$table.' ORDER BY '.$order.' LIMIT 513')->fetchAll();}return $out;});
    }
}
