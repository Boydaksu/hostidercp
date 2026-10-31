<?php
declare(strict_types=1);
namespace Hostider\Migration\Synthetic;
use Hostider\Core\Config;
use Hostider\Core\Database\{Database, Migrator};
use Hostider\Migration\Contract;

final readonly class Sandbox
{
    public const INSERT_TABLES=['f6b_runs','f6b_source_snapshots','f6b_entities','f6b_mappings','f6b_claims','f6b_provenance','f6b_history'];
    public const TABLES=['f6b_environment','f6b_runs','f6b_source_snapshots','f6b_entities','f6b_mappings','f6b_claims','f6b_provenance','f6b_history','schema_migrations'];
    private function __construct(public Database $db, public string $schema, private string $account, private string $secret, public string $snapshot) {}

    public static function directory(): string { return dirname(__DIR__,3).'/tests/fixtures/f6b-schema'; }
    public static function flags(): void
    {
        Contract::require(getenv('APP_ENV')==='test' && getenv('SYNTHETIC_IMPORT_ALLOWED')==='1','F6B_ENVIRONMENT_DENIED');
    }
    public static function open(int $port,string $schema,string $account,string $password,string $secret): self
    {
        self::flags();
        Contract::require($port>0 && $port<=65535 && preg_match('/^f6b_[a-f0-9]{24}$/D',$schema)===1 && preg_match('/^f6b_[a-f0-9]{16}$/D',$account)===1
            && preg_match('/^[a-f0-9]{64}$/D',$password)===1 && preg_match('/^[a-f0-9]{64}$/D',$secret)===1,'F6B_CONNECTION_DENIED');
        try {
            $db=Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(hex2bin($secret)),
                'mysql:host=127.0.0.1;port='.$port.';dbname='.$schema.';charset=utf8mb4',$account,$password));
            $row=$db->execute('SELECT target_snapshot FROM f6b_environment WHERE id=1')->fetch();
            Contract::require((bool)$row,'F6B_SANDBOX_MARKER');
            $sandbox=new self($db,$schema,$account,$secret,Contract::sha($row['target_snapshot'])); $sandbox->assertReady(); return $sandbox;
        } catch (\Throwable $error) {
            $code=preg_match('/^F6B_[A-Z0-9_]+$/D',$error->getMessage())===1 ? $error->getMessage() : 'F6B_SANDBOX_DENIED';
            throw new \RuntimeException($code);
        }
    }
    public function assertReady(): void
    {
        $this->assertBound(); $this->grants();
        $tables=$this->db->execute('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(\PDO::FETCH_COLUMN);
        $expected=self::TABLES;sort($expected,SORT_STRING);Contract::require($tables===$expected,'F6B_TABLE_SET');
        (new Migrator($this->db,self::directory()))->assertReady();
    }
    public function assertBound(): void
    {
        self::flags();
        Contract::require($this->db->execute('SELECT DATABASE()')->fetchColumn()===$this->schema,'F6B_SCHEMA_MISMATCH');
        $user=$this->db->execute('SELECT CURRENT_USER()')->fetchColumn();
        $host=(string)$this->db->execute("SELECT SUBSTRING_INDEX(USER(),'@',-1)")->fetchColumn();
        Contract::require(($host==='localhost' || filter_var($host,FILTER_VALIDATE_IP)!==false) && $user===$this->account.'@'.$host,'F6B_ACCOUNT_MISMATCH');
        Contract::require(in_array(strtoupper((string)$this->db->execute('SELECT CURRENT_ROLE()')->fetchColumn()),['','NONE'],true),'F6B_ROLE_DENIED');
        $row=$this->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) AS fresh FROM f6b_environment WHERE id=1')->fetch();
        Contract::require($row && (int)$row['fresh']===1 && $row['schema_name']===$this->schema && $row['account_name']===$this->account
            && $row['target_snapshot']===$this->snapshot && hash_equals($row['token_hash'],hash('sha256',$this->secret)),'F6B_SANDBOX_MARKER');
    }
    private function grants(): void
    {
        $select=false;$inserts=[];$update=false;
        foreach($this->db->execute('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN) as $grant){
            Contract::require(!str_contains($grant,'WITH GRANT OPTION') && preg_match('/^GRANT (.+) ON (.+) TO /',$grant,$m)===1,'F6B_GRANT_FORMAT');
            $rights=array_map('trim',explode(',',$m[1]));sort($rights,SORT_STRING);$scope=str_replace('`','',$m[2]);
            if($scope==='*.*'){Contract::require($rights===['USAGE'],'F6B_GLOBAL_PRIVILEGE');continue;}
            $exactSchema=str_replace('_','\\_',$this->schema);
            if(in_array($scope,[$exactSchema.'.*',str_replace('\\','\\\\',$exactSchema).'.*'],true)){Contract::require($rights===['SELECT'],'F6B_SCHEMA_GRANT');$select=true;continue;}
            $prefix=$this->schema.'.';Contract::require(str_starts_with($scope,$prefix),'F6B_SCOPE_GRANT');$table=substr($scope,strlen($prefix));
            Contract::require(in_array($table,self::INSERT_TABLES,true),'F6B_TABLE_GRANT');
            foreach($rights as $right){
                if($right==='INSERT'){$inserts[$table]=true;}
                elseif($right==='UPDATE' && $table==='f6b_runs'){$update=true;}
                else{throw new \InvalidArgumentException('F6B_TABLE_PRIVILEGE');}
            }
        }
        Contract::require($select && $update && count($inserts)===count(self::INSERT_TABLES),'F6B_GRANT_INCOMPLETE');
    }
    public function sign(array $data): string { return hash_hmac('sha256',\Hostider\Core\CanonicalJson::encode($data),$this->secret); }
    public function mutex(): string { return 'f6b:'.substr(hash('sha256',$this->schema),0,48); }
}
