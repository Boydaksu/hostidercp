<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Domains\{DomainName,TldPolicy};
use Hostider\Providers\Contract\{DomainManagement,Operation,ProviderResult};

/** Synthetic external registrar world. Never uses a real endpoint or auth payload. */
final readonly class DurableRegistrar implements DomainManagement
{
    private string $key;
    public function __construct(private Database $db,Config $config,private string $account,private string $scenario='SUCCESS')
    {
        if($config->environment!=='test'||!in_array($scenario,['SUCCESS','DEFINITIVE_FAILURE','TIMEOUT_BEFORE_EFFECT','TIMEOUT_AFTER_EFFECT'],true)){throw new HttpError(403,'F4_TEST_ONLY');}
        $this->key=base64_decode($config->key,true);
    }
    public function capabilities(): array{return ['actions'=>array_values(array_diff(TldPolicy::ACTIONS,['dns','epp'])),'liveValidated'=>false,'environment'=>'mock'];}
    public function availability(Operation $operation): ProviderResult{return $this->action($operation,'availability');}
    public function register(Operation $operation): ProviderResult{return $this->action($operation,'register');}
    public function renew(Operation $operation): ProviderResult{return $this->action($operation,'renew');}
    public function initiateTransfer(Operation $operation): ProviderResult{return $this->action($operation,'initiateTransfer');}
    public function status(Operation $operation): ProviderResult{return $this->action($operation,'status');}
    public function nameservers(Operation $operation): ProviderResult{return $this->action($operation,'nameservers');}
    public function dns(Operation $operation): ProviderResult{return $this->action($operation,'dns');}
    public function epp(Operation $operation): ProviderResult{return $this->action($operation,'epp');}
    public function lock(Operation $operation): ProviderResult{return $this->action($operation,'lock');}
    public function privacy(Operation $operation): ProviderResult{return $this->action($operation,'privacy');}
    public function execute(Operation $operation): ProviderResult{return $this->action($operation,$operation->action);}
    private function action(Operation $operation,string $action): ProviderResult
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('Provider outside application transaction required');}
        if($operation->action!==$action||!in_array($action,TldPolicy::ACTIONS,true)){throw new HttpError(422,'DOMAIN_ACTION_INVALID');}
        $name=new DomainName($operation->configuration['domain']??'');
        $fingerprint=hash_hmac('sha256',CanonicalJson::encode(['account'=>$this->account,'action'=>$action,'resource'=>$operation->resourceId,'configuration'=>$operation->configuration]),$this->key);
        return $this->db->transaction(function()use($operation,$action,$name,$fingerprint): ProviderResult{
            $account=$this->db->execute("SELECT * FROM domain_registrar_accounts WHERE id=? AND environment='mock' AND enabled=TRUE FOR UPDATE",[$this->account])->fetch();
            if(!$account){throw new HttpError(409,'MOCK_REGISTRAR_UNAVAILABLE');}
            $old=$this->db->execute('SELECT * FROM mock_registrar_calls WHERE operation_id=?',[$operation->id])->fetch();
            if($old){if(!hash_equals($old['fingerprint'],$fingerprint)){throw new HttpError(409,'DOMAIN_OPERATION_REUSED');}return new ProviderResult($operation->id,$account['provider'],$old['status'],$old['resource_id'],$old['safe_code']);}
            $rule=$this->db->execute('SELECT * FROM domain_tld_provider_rules WHERE id=? AND account_id=?',[$operation->configuration['rule_id']??'',$this->account])->fetch();
            $status='SUCCESS';$code='MOCK_OK';$resource=null;$noEffect=false;
            $policy=$rule?TldPolicy::fromSnapshot(json_decode($rule['policy_json'],true,32,JSON_THROW_ON_ERROR)):null;
            if(!$rule||!$name->under($rule['tld'])||!$policy||!in_array($action,$policy->actions,true)){$status='UNSUPPORTED';$code='MOCK_TLD_UNSUPPORTED';$noEffect=true;}
            elseif(in_array($action,['dns','epp'],true)){
                // No DNS record model or safe one-time EPP delivery yet; never manufacture support.
                $status='UNSUPPORTED';$code='MOCK_SECURE_DELIVERY_PENDING';$noEffect=true;
            }else{
                $domain=$this->db->execute('SELECT * FROM mock_registrar_domains WHERE name_ascii=? FOR UPDATE',[$name->ascii])->fetch();
                if($action==='availability'){$code=$domain?'MOCK_UNAVAILABLE':'MOCK_AVAILABLE_NOT_RESERVED';$noEffect=true;}
                elseif($action==='status'){
                    if($domain&&$domain['account_id']===$this->account){$resource=$domain['id'];}else{$status='DEFINITIVE_FAILURE';$code='MOCK_NOT_FOUND';}$noEffect=true;
                }elseif($this->scenario==='TIMEOUT_BEFORE_EFFECT'){$status='UNKNOWN';$code='MOCK_TIMEOUT';}
                elseif($this->scenario==='DEFINITIVE_FAILURE'){$status='DEFINITIVE_FAILURE';$code='MOCK_NO_EFFECT';$noEffect=true;}
                elseif(in_array($action,['register','initiateTransfer'],true)){
                    $policy->validateContact($operation->configuration['contact']??[]);
                    $years=$operation->configuration['years']??null;
                    if(!is_int($years)||$years<1||$years>10){throw new HttpError(422,'DOMAIN_TERM_INVALID');}
                    $ns=$this->nameserverList($operation->configuration['nameservers']??[]);
                    if($domain){$status='DEFINITIVE_FAILURE';$code='MOCK_ALREADY_EXISTS';$noEffect=true;}
                    else{$resource=Id::uuid();$this->db->execute('INSERT INTO mock_registrar_domains(id,account_id,name_ascii,create_operation_id,status,term_years,nameservers_json) VALUES (?,?,?,?,?,?,?)',[$resource,$this->account,$name->ascii,$operation->id,$action==='register'?'REGISTERED':'TRANSFER_PENDING',$years,CanonicalJson::encode($ns)]);}
                }else{
                    if(!$domain||$domain['account_id']!==$this->account||$domain['id']!==$operation->resourceId){$status='DEFINITIVE_FAILURE';$code='MOCK_RESOURCE_MISMATCH';$noEffect=true;}
                    elseif($domain['status']!=='REGISTERED'){$status='DEFINITIVE_FAILURE';$code='MOCK_TRANSFER_PENDING';$noEffect=true;}
                    else{
                        $resource=$domain['id'];
                        if($action==='renew'){$years=$operation->configuration['years']??null;if(!is_int($years)||$years<1||$years>10||$years+(int)$domain['term_years']>10){throw new HttpError(422,'DOMAIN_TERM_INVALID');}$this->db->execute('UPDATE mock_registrar_domains SET term_years=term_years+?,version=version+1 WHERE id=?',[$years,$resource]);}
                        elseif($action==='nameservers'){$ns=$this->nameserverList($operation->configuration['nameservers']??[]);$this->db->execute('UPDATE mock_registrar_domains SET nameservers_json=?,version=version+1 WHERE id=?',[CanonicalJson::encode($ns),$resource]);}
                        elseif(in_array($action,['lock','privacy'],true)){$enabled=$operation->configuration['enabled']??null;if(!is_bool($enabled)){throw new HttpError(422,'DOMAIN_BOOLEAN_REQUIRED');}$column=$action==='lock'?'locked':'privacy_enabled';$this->db->execute('UPDATE mock_registrar_domains SET '.$column.'=?,version=version+1 WHERE id=?',[(int)$enabled,$resource]);}
                    }
                }
                if(!in_array($action,['availability','status'],true)&&$this->scenario==='TIMEOUT_AFTER_EFFECT'&&$status==='SUCCESS'){$status='UNKNOWN';$code='MOCK_TIMEOUT';$resource=null;}
            }
            $this->db->execute('INSERT INTO mock_registrar_calls(operation_id,account_id,action,fingerprint,status,resource_id,safe_code,no_effect) VALUES (?,?,?,?,?,?,?,?)',[$operation->id,$this->account,$action,$fingerprint,$status,$resource,$code,(int)$noEffect]);
            return new ProviderResult($operation->id,$account['provider'],$status,$resource,$code);
        });
    }
    private function nameserverList(array $values): array
    {
        if(!array_is_list($values)||count($values)<2||count($values)>13){throw new HttpError(422,'DOMAIN_NAMESERVERS_INVALID');}
        $result=array_map(static fn(string $v): string=>(new DomainName($v))->ascii,$values);
        if(count(array_unique($result))!==count($result)){throw new HttpError(422,'DOMAIN_NAMESERVERS_INVALID');}return $result;
    }
    public function evidence(string $name): ?array
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('Provider outside application transaction required');}
        $name=new DomainName($name);$row=$this->db->execute('SELECT id,account_id,name_ascii,create_operation_id,status,term_years,version FROM mock_registrar_domains WHERE account_id=? AND name_ascii=?',[$this->account,$name->ascii])->fetch();return $row?:null;
    }
}
