<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\{CanonicalJson,Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;
use Hostider\Providers\Contract\{HostingProvider,Operation,ProviderResult};use Hostider\Catalog\BillingCycle;
/** Persistent synthetic external world, deliberately separate from caller transactions. */
final readonly class DurableCpanel implements HostingProvider
{
    public function __construct(private Database $db,private Config $config,private string $server,private string $scenario='SUCCESS')
    {
        if($config->environment!=='test'||!in_array($scenario,['SUCCESS','TIMEOUT_BEFORE_CREATE','TIMEOUT_AFTER_CREATE','DEFINITIVE_FAILURE','UNSUPPORTED'],true)){throw new HttpError(403,'F3_TEST_ONLY');}
    }
    public function capabilities(): array{return ['createAccount','inspectAccount','suspendAccount','unsuspendAccount','changePackage','terminateAccount'];}
    public function createAccount(Operation $operation): ProviderResult{return $this->action($operation,'createAccount');}
    public function inspectAccount(Operation $operation): ProviderResult{return $this->action($operation,'inspectAccount');}
    public function suspendAccount(Operation $operation): ProviderResult{return $this->action($operation,'suspendAccount');}
    public function unsuspendAccount(Operation $operation): ProviderResult{return $this->action($operation,'unsuspendAccount');}
    public function changePackage(Operation $operation): ProviderResult{return $this->action($operation,'changePackage');}
    public function terminateAccount(Operation $operation): ProviderResult{return $this->action($operation,'terminateAccount');}
    public function execute(Operation $operation): ProviderResult{return $this->action($operation,$operation->action);}
    private function action(Operation $operation,string $action): ProviderResult
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('Provider calls forbidden inside application transaction');}
        if($operation->action!==$action||!in_array($action,$this->capabilities(),true)){throw new HttpError(422,'INVALID_HOSTING_ACTION');}
        return $this->db->transaction(function()use($operation,$action): ProviderResult{
            // Serialize by server even across independent processes; persisted request ID enforces replay identity.
            $server=$this->db->execute("SELECT id FROM hosting_servers WHERE id=? AND environment='mock' FOR UPDATE",[$this->server])->fetchColumn();if(!$server){throw new HttpError(409,'MOCK_SERVER_NOT_FOUND');}
            $fingerprint=hash('sha256',CanonicalJson::encode(['action'=>$action,'resource'=>$operation->resourceId,'config'=>$operation->configuration]));
            $old=$this->db->execute('SELECT * FROM mock_hosting_calls WHERE operation_id=?',[$operation->id])->fetch();
            if($old){if($old['server_id']!==$this->server||$old['action']!==$action||!hash_equals($old['fingerprint'],$fingerprint)){throw new HttpError(409,'MOCK_OPERATION_REUSED');}return new ProviderResult($operation->id,'cpanel',$old['status'],$old['resource_id'],$old['safe_code']);}
            $status='SUCCESS';$resource=null;$code='MOCK_VERIFIED';
            if($action==='createAccount'){
                $status=match($this->scenario){'TIMEOUT_BEFORE_CREATE','TIMEOUT_AFTER_CREATE'=>'UNKNOWN','DEFINITIVE_FAILURE'=>'DEFINITIVE_FAILURE','UNSUPPORTED'=>'UNSUPPORTED',default=>'SUCCESS'};
                if(in_array($this->scenario,['SUCCESS','TIMEOUT_AFTER_CREATE'],true)){
                    $resource=Id::uuid();$cfg=$operation->configuration;$now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));$cycle=$cfg['cycle'];$end=$cycle['kind']==='ONCE'?null:BillingCycle::next($now,$cycle['kind'],(int)$cycle['interval_count']);
                    $this->db->execute("INSERT INTO mock_hosting_accounts(id,server_id,service_id,create_operation_id,package_name,status,starts_at,ends_at) VALUES (?,?,?,?,?,'ACTIVE',?,?)",[$resource,$this->server,$cfg['service_id'],$operation->id,$cfg['package_name'],$now->format('Y-m-d H:i:s.u'),$end?->format('Y-m-d H:i:s.u')]);
                }
                if($status!=='SUCCESS'){$code='MOCK_'.$this->scenario;}
                if($status==='UNKNOWN'){$resource=null;}
            }else{
                $row=$operation->resourceId!==null?$this->db->execute('SELECT * FROM mock_hosting_accounts WHERE id=? AND server_id=? FOR UPDATE',[$operation->resourceId,$this->server])->fetch():$this->db->execute('SELECT * FROM mock_hosting_accounts WHERE create_operation_id=? AND server_id=? FOR UPDATE',[$operation->configuration['create_operation_id']??'', $this->server])->fetch();
                if(!$row){$status='DEFINITIVE_FAILURE';$code='MOCK_NOT_FOUND';}
                else{$resource=$row['id'];if($action!=='inspectAccount'){
                    if($row['status']==='TERMINATED'){$status='DEFINITIVE_FAILURE';$code='MOCK_TERMINAL';}
                    else{$next=match($action){'suspendAccount'=>'SUSPENDED','unsuspendAccount'=>'ACTIVE','terminateAccount'=>'TERMINATED',default=>$row['status']};$package=$action==='changePackage'?($operation->configuration['package_name']??''):$row['package_name'];if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$package)){throw new HttpError(422,'INVALID_MOCK_PACKAGE');}$this->db->execute('UPDATE mock_hosting_accounts SET status=?,package_name=?,version=version+1 WHERE id=?',[$next,$package,$resource]);}
                }}
            }
            $this->db->execute('INSERT INTO mock_hosting_calls(operation_id,server_id,action,fingerprint,status,resource_id,safe_code) VALUES (?,?,?,?,?,?,?)',[$operation->id,$this->server,$action,$fingerprint,$status,$resource,$code]);return new ProviderResult($operation->id,'cpanel',$status,$resource,$code);
        });
    }
    /** Synthetic inspect evidence, not a claim about the real WHM response schema. */
    public function evidence(string $resource): ?array
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('Inspect outside application transaction');}
        $row=$this->db->execute('SELECT id,server_id,service_id,create_operation_id,package_name,status,starts_at,ends_at,version FROM mock_hosting_accounts WHERE id=? AND server_id=?',[$resource,$this->server])->fetch();return $row?:null;
    }
}
