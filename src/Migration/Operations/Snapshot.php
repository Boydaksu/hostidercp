<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;
use Hostider\Core\{Config,CanonicalJson};
use Hostider\Core\Database\Database;
use Hostider\Migration\{Contract,Entity,SourceIdentity};
use Hostider\Migration\Synthetic\{Sandbox,Fixture,Plan};
use Hostider\Migration\Reconciliation\Inspector;

final readonly class Snapshot
{
    private function __construct(private Sandbox $authority,private Database $db,private Inspector $inspector,public Vault $samples) {}
    public static function open(Sandbox $authority,int $port,string $user,#[\SensitiveParameter] string $password,?Vault $samples=null): self
    {
        $reader=Inspector::open($authority,$port,$user,$password);
        $db=Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),'mysql:host=127.0.0.1;port='.$port.';dbname='.$authority->schema.';charset=utf8mb4',$user,$password));
        $new=$samples===null;$samples??=Vault::create();$self=new self($authority,$db,$reader,$samples);if($new){$samples->installSamples(self::fileCatalog(),$authority->snapshot);}return $self;
    }
    public function capture(string $fault='NONE'): array
    {
        $before=$this->inspector->inspect(Fixture::named('baseline'))->jsonSerialize();Contract::require($before['content']['status']==='PASS','F6D_SOURCE_NOT_READY');
        Contract::require((int)$this->db->execute('SELECT GET_LOCK(?,10)',[$this->authority->mutex()])->fetchColumn()===1,'F6D_BUSY');
        try{
            $this->db->execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->db->execute('SET TRANSACTION READ ONLY');
            $data=$this->db->transaction(function()use($fault): array{
                $tables=[];$schema=[];$total=0;
                foreach(Sandbox::TABLES as $table){
                    $columns=$this->db->execute('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION LIMIT 32',[$table])->fetchAll(\PDO::FETCH_COLUMN);
                    $fields=[];foreach($columns as $column){Contract::require(preg_match('/^[a-z_]+$/D',$column)===1,'F6D_COLUMNS');$fields[]=in_array($column,['payload_json','evidence_json'],true)?'LEFT(`'.$column.'`,65537) AS `'.$column.'`':'`'.$column.'`';}
                    $order=$table==='f6b_claims'?'kind,claim_hash':($columns[0]??'id');
                    $query=$this->db->execute('SELECT '.implode(',',$fields).' FROM '.$table.' ORDER BY '.$order.' LIMIT 513');$rows=[];
                    while($row=$query->fetch()){$n=strlen(CanonicalJson::encode($row));$total+=$n;Contract::require($n<=70000&&$total<=2097152&&count($rows)<512,'F6D_BOUND');$rows[]=$row;}
                    $tables[$table]=$rows;$create=$this->db->execute('SHOW CREATE TABLE '.$table)->fetch(\PDO::FETCH_NUM);$schema[$table]=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$create[1]);if($fault==='CRASH_DB'){exit(73);}
                }return ['tables'=>$tables,'schema'=>$schema];
            });$this->authority->assertReady();
        }finally{$this->db->execute('SELECT RELEASE_LOCK(?)',[$this->authority->mutex()]);}
        $after=$this->inspector->inspect(Fixture::named('baseline'))->jsonSerialize();
        Contract::require($after['content']['status']==='PASS'&&$before['fingerprint']===$after['fingerprint']&&Contract::hash($data['tables'])===$before['content']['observed_target_evidence_sha256'],'F6D_SNAPSHOT_CHANGED');
        $files=$this->samples->readSamples(self::fileCatalog(),$this->authority->snapshot);if($fault==='CRASH_FILES_CAPTURE'){exit(73);}
        return [...$data,'files'=>$files,'report'=>$before['content'],'classification'=>'SYNTHETIC','contract'=>'hostider.backup.v1','file_contract'=>'F6D_DERIVED_SYNTHETIC_SAMPLES_NOT_ORIGINAL_ARCHIVE_BYTES'];
    }
    private static function fileCatalog(): array
    {
        $files=[];foreach(Plan::build(Fixture::named('baseline'))->items as $e){if(!in_array($e->identity->entity,[Entity::Attachment,Entity::Document],true)){continue;}
            $name=$e->identity->entity->value.'-'.substr($e->identity->key(),0,16).'.pdf';$bytes="%PDF-1.4\n% SYNTHETIC F6-D SAMPLE ONLY\n".$e->fingerprint."\n%%EOF\n";
            $files[$name]=['bytes'=>base64_encode($bytes),'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes),'source_key'=>$e->identity->key(),'customer_key'=>(new SourceIdentity($e->identity->instance,Entity::Customer,$e->payload['customer']))->key(),'source_metadata_sha256'=>$e->contentHash];}
        ksort($files,SORT_STRING);return $files;
    }
}
