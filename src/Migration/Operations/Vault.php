<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;
use Hostider\Core\{CanonicalJson};
use Hostider\Core\Security\Crypto;
use Hostider\Migration\Contract;
use Hostider\Migration\Synthetic\Sandbox;

/** Private test artifacts only; no scheduler, network destination or compression parser. */
final readonly class Vault
{
    private function __construct(private string $root,public string $identity,private string $token) {}
    public static function create(): self
    {
        Sandbox::flags();Contract::require(getenv('SYNTHETIC_BACKUP_ALLOWED')==='1','F6D_AUTH');
        $base=str_replace('\\','/',dirname(__DIR__,3)).'/storage';Contract::require(!is_link($base)&&str_replace('\\','/',(string)realpath($base))===$base,'F6D_ROOT');
        $id=bin2hex(random_bytes(12));$token=bin2hex(random_bytes(32));$root=$base.'/f6d-'.$id;
        Contract::require(mkdir($root,0700),'F6D_STORAGE');$self=new self($root,$id,$token);
        $self->write('lease.json',CanonicalJson::encode(['token_hash'=>hash('sha256',$token),'expires'=>time()+1800,'id'=>$id]));return $self;
    }
    public static function open(string $id,#[\SensitiveParameter] string $token): self
    {
        Contract::require(preg_match('/^[a-f0-9]{24}$/D',$id)===1&&preg_match('/^[a-f0-9]{64}$/D',$token)===1,'F6D_VAULT_INPUT');
        $self=new self(str_replace('\\','/',dirname(__DIR__,3)).'/storage/f6d-'.$id,$id,$token);$self->fence();return $self;
    }
    public function capability(): array {return [$this->identity,$this->token];}
    private function path(string $name): string
    {
        Contract::require(preg_match('/^[a-z0-9][a-z0-9.-]{0,100}$/D',$name)===1&&!str_contains($name,'..'),'F6D_PATH');
        $path=$this->root.'/'.$name;Contract::require(!is_link($path),'F6D_SYMLINK');return $path;
    }
    private function write(string $name,string $bytes): void
    {
        $path=$this->path($name);$file=fopen($path,'x+b');Contract::require(is_resource($file),'F6D_STORAGE');
        try{Contract::require(chmod($path,0600)&&fwrite($file,$bytes)===strlen($bytes)&&fflush($file),'F6D_STORAGE_WRITE');if(function_exists('fsync')){Contract::require(fsync($file),'F6D_STORAGE_SYNC');}}finally{fclose($file);}
    }
    public function fence(): void
    {
        Sandbox::flags();Contract::require(getenv('SYNTHETIC_BACKUP_ALLOWED')==='1','F6D_AUTH');
        Contract::require(!is_link($this->root)&&realpath($this->root)===$this->root || (!is_link($this->root)&&str_replace('\\','/',(string)realpath($this->root))===$this->root),'F6D_ROOT');
        if(PHP_OS_FAMILY!=='Windows'){Contract::require((fileperms($this->root)&0077)===0,'F6D_PERMISSIONS');}
        $raw=$this->read('lease.json',1024);$lease=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        Contract::require($lease['id']===$this->identity&&$lease['expires']>time()&&hash_equals($lease['token_hash'],hash('sha256',$this->token)),'F6D_LEASE');
    }
    private function read(string $name,int $limit=4194304): string
    {
        $path=$this->path($name);clearstatcache(true,$path);Contract::require(is_file($path)&&filesize($path)<=$limit,'F6D_PART_MISSING_OR_OVERSIZED');
        if(PHP_OS_FAMILY!=='Windows'){Contract::require((fileperms($path)&0077)===0,'F6D_PERMISSIONS');}
        $value=file_get_contents($path);Contract::require(is_string($value),'F6D_READ');return $value;
    }
    public function backup(Snapshot $source,#[\SensitiveParameter] Crypto $crypto,string $keyId,string $fault='NONE'): array
    {
        $this->fence();Contract::require(in_array($fault,['NONE','CRASH_BEFORE','CRASH_DB','CRASH_FILES_CAPTURE','CRASH_CAPTURE','CRASH_MANIFEST','CRASH_ENCRYPT','CRASH_PUBLISH','DISK_FULL','EXPIRE'],true),'F6D_FAULT');
        $lock=fopen($this->path('operation.lock'),'c+b');Contract::require(is_resource($lock),'F6D_LOCK');
        $deadline=hrtime(true)+10000000000;while(!flock($lock,LOCK_EX|LOCK_NB)){if(hrtime(true)>=$deadline){fclose($lock);throw new \RuntimeException('F6D_BUSY');}usleep(10000);}
        try{
            $this->fence();
            if($fault==='CRASH_BEFORE'){exit(73);}$data=$source->capture($fault);if($fault==='CRASH_CAPTURE'){exit(73);}
            self::validateData($data);$id=Contract::hash($data);$name=$id.'.sealed';
            if(is_file($this->path($name))){$existing=$this->verify($id,$crypto,$keyId);return [...$existing,'replay'=>true];}
            $plain=CanonicalJson::encode(['data'=>$data,'logical_sha256'=>$id,'created_at'=>gmdate('Y-m-d\TH:i:s\Z'),'key_id'=>$keyId,'encoding'=>'json-base64-no-compression','issuer_id'=>$this->identity,'issuer_mac'=>hash_hmac('sha256','f6d-issued:'.$id,$this->token)]);
            if($fault==='CRASH_MANIFEST'){exit(73);}$cipher=$crypto->encrypt($plain,'f6d-backup:'.$id);Contract::require(str_starts_with($cipher,$keyId.':'),'F6D_KEY_ID');
            Contract::require($this->status()['logical_stored_bytes']+strlen($cipher)<=16777216,'F6D_STORAGE_QUOTA');$part=bin2hex(random_bytes(16)).'.partial';
            if($fault==='DISK_FULL'){$this->write($part,substr($cipher,0,20));throw new \RuntimeException('F6D_STORAGE_WRITE');}
            $this->write($part,$cipher);if($fault==='CRASH_ENCRYPT'){exit(73);}
            $decoded=$this->decode($id,$this->read($part),$crypto,$keyId);
            if($fault==='EXPIRE'){throw new \RuntimeException('F6D_LEASE');}$this->fence();if($fault==='CRASH_PUBLISH'){exit(73);}
            Contract::require(!file_exists($this->path($name))&&rename($this->path($part),$this->path($name)),'F6D_PUBLISH');
            return [...$this->manifest($decoded),'replay'=>false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function decode(string $id,string $cipher,#[\SensitiveParameter] Crypto $crypto,string $keyId): array
    {
        return AuthenticatedBackup::open($id,$cipher,$crypto,$keyId,$this)->payload;
    }
    public static function validateData(#[\SensitiveParameter] array $data): void
    {
        Contract::fields($data,['tables','schema','files','report','classification','contract','file_contract']);
        Contract::require($data['classification']==='SYNTHETIC'&&$data['contract']==='hostider.backup.v1'&&$data['report']['status']==='PASS'&&Contract::hash($data['tables'])===$data['report']['observed_target_evidence_sha256'],'F6D_LOGICAL_INTEGRITY');
        $keys=array_keys($data['tables']);$expected=Sandbox::TABLES;sort($keys);sort($expected);Contract::require($keys===$expected&&count($data['files'])===4,'F6D_INVENTORY');
        $total=0;foreach($data['tables'] as $table=>$rows){Contract::require(is_array($rows)&&count($rows)<=512,'F6D_TABLE_BOUND');$total+=strlen(CanonicalJson::encode($rows));}Contract::require($total<=2097152,'F6D_BYTE_BOUND');
        $catalog=[];foreach(\Hostider\Migration\Synthetic\Plan::build(\Hostider\Migration\Synthetic\Fixture::named('baseline'))->items as $e){if(in_array($e->identity->entity->value,['attachment','document'],true)){$catalog[$e->identity->entity->value.'-'.substr($e->identity->key(),0,16).'.pdf']=['source_key'=>$e->identity->key(),'customer_key'=>(new \Hostider\Migration\SourceIdentity($e->identity->instance,\Hostider\Migration\Entity::Customer,$e->payload['customer']))->key(),'source_metadata_sha256'=>$e->contentHash];}}
        $seen=[];foreach($data['files'] as $name=>$file){Contract::require(preg_match('/^(attachment|document)-[a-f0-9]{16}\.pdf$/D',$name)===1&&!isset($seen[strtolower($name)])&&isset($catalog[$name]),'F6D_FILE_NAME');$seen[strtolower($name)]=true;
            foreach($catalog[$name] as $field=>$expectedValue){Contract::require(($file[$field]??null)===$expectedValue,'F6D_FILE_OWNERSHIP');}
            $bytes=base64_decode($file['bytes'],true);Contract::require(is_string($bytes)&&strlen($bytes)<=65536&&strlen($bytes)===$file['size']&&hash('sha256',$bytes)===$file['sha256']&&str_contains($bytes,'SYNTHETIC F6-D SAMPLE ONLY'),'F6D_FILE_INTEGRITY');}
    }
    public function verify(string $id,#[\SensitiveParameter] Crypto $crypto,string $keyId,bool $revoked=false): array
    {
        $this->fence();Contract::require(!$revoked,'F6D_KEY_REVOKED');return $this->manifest($this->decode($id,$this->read($id.'.sealed'),$crypto,$keyId));
    }
    public function restore(string $id,#[\SensitiveParameter] Crypto $crypto,string $keyId,Destination $target,string $fault='NONE'): array
    {
        $this->fence();$package=AuthenticatedBackup::open($id,$this->read($id.'.sealed'),$crypto,$keyId,$this);return $target->restore($package,$fault);
    }
    private function manifest(array $value): array
    {
        $d=$value['data'];$counts=[];$bytes=0;foreach($d['tables'] as $table=>$rows){$counts[$table]=count($rows);}foreach($d['files'] as $f){$bytes+=$f['size'];}
        $inventory=[];foreach($d['files'] as $file){$inventory[]=['sha256'=>$file['sha256'],'bytes'=>$file['size'],'source_identity'=>$file['source_key']];}
        $migrations=[];foreach($d['tables']['schema_migrations'] as $row){$migrations[]=['version'=>$row['version'],'checksum'=>$row['checksum'],'status'=>$row['status']];}
        return ['classification'=>'SYNTHETIC','contract'=>'hostider.backup.v1','backup_id'=>$value['logical_sha256'],'state'=>'VERIFIED','logical_sha256'=>Contract::hash($d['tables']),'schema_sha256'=>Contract::hash($d['schema']),
            'migration_versions'=>$migrations,'file_inventory'=>$inventory,'logical_total_bytes'=>strlen(CanonicalJson::encode($d['tables']))+$bytes,'mapping_provenance_checkpoint_sha256'=>Contract::hash([$d['tables']['f6b_mappings'],$d['tables']['f6b_provenance'],$d['tables']['f6b_runs'],$d['tables']['f6b_history']]),'consistency'=>'VERIFIED_DERIVED_SYNTHETIC_FILE_CONTRACT','restore_eligibility'=>'ESCROW_ONLY_NATIVE_BLOCKED',
            'source_snapshot'=>$d['report']['target_snapshot'],'source_reconciliation'=>$d['report']['status'],'counts'=>$counts,'finance'=>$d['report']['finance'],'file_count'=>count($d['files']),'file_bytes'=>$bytes,'file_inventory_sha256'=>Contract::hash($d['files']),
            'algorithm'=>'XCHACHA20-POLY1305-IETF','key_id'=>$value['key_id'],'created_at'=>$value['created_at'],'file_contract'=>$d['file_contract'],'native_restore'=>'BLOCKED_IDENTITY_BOUND','original_archive_bytes'=>'NOT_VERIFIED','production_backup'=>'NOT_VERIFIED'];
    }
    public function status(): array
    {
        $this->fence();$sealed=0;$partial=0;$latest=null;$n=0;$bytes=0;foreach(new \DirectoryIterator($this->root) as $entry){if($entry->isDot()){continue;}Contract::require(++$n<=128,'F6D_STORAGE_BUDGET');Contract::require(!$entry->isLink()&&!$entry->isDir(),'F6D_SYMLINK');$bytes+=$entry->getSize();if($entry->getExtension()==='sealed'){++$sealed;$latest=max($latest??0,$entry->getMTime());}if($entry->getExtension()==='partial'){++$partial;}}
        return ['classification'=>'SYNTHETIC','sealed_candidates'=>$sealed,'partial_or_failed'=>$partial,'failed_before_write'=>'NOT_AVAILABLE_NO_AUDIT_BINDING','logical_stored_bytes'=>$bytes,'quota_bytes'=>16777216,'last_artifact_time'=>$latest,'age_seconds'=>$latest===null?null:max(0,time()-$latest),'verification'=>'REQUIRES_KEY_AUTHENTICATION','last_verified_time'=>'NOT_AVAILABLE_REQUIRES_KEY','last_restore'=>'NOT_AVAILABLE_NO_PERSISTED_RECEIPT','queue_oldest_pending'=>'NOT_AVAILABLE','dlq'=>'NOT_AVAILABLE','production_capacity'=>'BLOCKED_EXTERNAL'];
    }
    public function restoreFiles(array $files): void
    {
        $this->fence();Contract::require(count($files)===4,'F6D_INVENTORY');
        foreach($files as $name=>$file){Contract::require(preg_match('/^(attachment|document)-[a-f0-9]{16}\.pdf$/D',$name)===1,'F6D_FILE_NAME');
            $bytes=base64_decode($file['bytes'],true);Contract::require(is_string($bytes)&&strlen($bytes)<=65536&&strlen($bytes)===$file['size']&&hash('sha256',$bytes)===$file['sha256'],'F6D_FILE_INTEGRITY');
            if(!is_file($this->path($name))){$this->write($name,$bytes);}Contract::require($this->read($name,65536)===$bytes,'F6D_RESTORE_FILE_CONFLICT');}
    }
    public function installSamples(array $files,string $source): void
    {
        $this->restoreFiles($files);$this->write('source-snapshot.json',CanonicalJson::encode(['snapshot'=>$source,'inventory'=>Contract::hash($files)]));
    }
    public function acceptIssued(string $id,string $issuer,#[\SensitiveParameter] string $mac): bool
    {
        $this->fence();return $issuer===$this->identity&&hash_equals(hash_hmac('sha256','f6d-issued:'.$id,$this->token),$mac);
    }
    public function readSamples(array $files,string $source): array
    {
        $this->fence();$marker=json_decode($this->read('source-snapshot.json',1024),true,8,JSON_THROW_ON_ERROR);Contract::require($marker['snapshot']===$source&&$marker['inventory']===Contract::hash($files),'F6D_FILE_SNAPSHOT');
        foreach($files as $name=>$file){$bytes=$this->read($name,65536);Contract::require(strlen($bytes)===$file['size']&&hash('sha256',$bytes)===$file['sha256'],'F6D_FILE_INTEGRITY');$files[$name]['bytes']=base64_encode($bytes);}return $files;
    }
}
