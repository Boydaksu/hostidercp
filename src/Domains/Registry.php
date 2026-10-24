<?php
declare(strict_types=1);
namespace Hostider\Domains;
use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Core\Security\{Audit,AuditEvent,Authorization,Crypto,Principal};

/** Trusted in-process test model. Public mutation requires the F4-F confirmation boundary. */
final readonly class Registry
{
    private Crypto $crypto;
    private string $key;
    public function __construct(private Database $db,private Config $config)
    {
        if($config->environment!=='test'){throw new HttpError(403,'F4_TEST_ONLY');}
        $this->key=base64_decode($config->key,true);$this->crypto=new Crypto($this->key);
    }
    public function configure(string $tld,string $provider,TldPolicy $policy): string
    {
        $name=new DomainName('policy.'.$tld);
        if($name->ascii!=='policy.'.$tld||!in_array($provider,['resellerclub','domainnameapi'],true)){throw new HttpError(422,'DOMAIN_RULE_INVALID');}
        return $this->db->transaction(function()use($tld,$provider,$policy): string{
            $account=Id::uuid();$rule=Id::uuid();
            $this->db->execute("INSERT INTO domain_registrar_accounts(id,provider,environment,enabled) VALUES (?,?,'mock',TRUE)",[$account,$provider]);
            $this->db->execute('INSERT INTO domain_tlds(name) VALUES (?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$tld]);
            $this->db->execute('INSERT INTO domain_tld_provider_rules(id,tld,account_id,version,policy_json) VALUES (?,?,?,1,?)',[$rule,$tld,$account,CanonicalJson::encode($policy->snapshot())]);
            return $rule;
        });
    }
    public function policy(string $rule): array
    {
        $row=$this->db->execute('SELECT r.*,a.provider,a.enabled,a.environment FROM domain_tld_provider_rules r JOIN domain_registrar_accounts a ON a.id=r.account_id WHERE r.id=?',[$rule])->fetch();
        if(!$row||!$row['enabled']||$row['environment']!=='mock'){throw new HttpError(409,'DOMAIN_RULE_UNAVAILABLE');}
        return $row;
    }
    public function attach(string $serviceId,string $customer,string $name,string $ruleId,array $contact,array $nameservers,Principal $actor,string $trace): string
    {
        $this->db->requireTransaction();
        (new Authorization())->require($actor,'services.manage',$customer,privileged:true,service:$serviceId);
        if($actor->impersonating){throw new HttpError(403,'DOMAIN_IMPERSONATION_FORBIDDEN');}
        $service=(new \Hostider\Services\RegistrationEligibility($this->db,$this->config))->lock($serviceId,$customer,'DOMAIN');
        $product=$this->db->execute('SELECT product_type FROM catalog_products WHERE id=?',[$service['product_id']])->fetchColumn();
        if($product!=='DOMAIN'||$service['origin_environment']!=='test'||$service['imported']){throw new HttpError(409,'DOMAIN_SERVICE_REQUIRED');}
        $name=new DomainName($name);$rule=$this->policy($ruleId);$policy=TldPolicy::fromSnapshot(json_decode($rule['policy_json'],true,32,JSON_THROW_ON_ERROR));
        if(!$name->under($rule['tld'])||substr($name->ascii,0,-strlen($rule['tld'])-1)===''||str_contains(substr($name->ascii,0,-strlen($rule['tld'])-1),'.')){throw new HttpError(422,'DOMAIN_TLD_MISMATCH');}
        $policy->requireAction('register');$policy->validateContact($contact);
        if(!array_is_list($nameservers)||count($nameservers)<2||count($nameservers)>13){throw new HttpError(422,'DOMAIN_NAMESERVERS_INVALID');}
        $ns=array_map(static fn(string $value): string=>(new DomainName($value))->ascii,$nameservers);
        if(count(array_unique($ns))!==count($ns)){throw new HttpError(422,'DOMAIN_NAMESERVERS_INVALID');}
        $existing=$this->db->execute('SELECT * FROM domains WHERE service_id=?',[$serviceId])->fetch();
        if($existing){
            $storedContact=$this->db->execute("SELECT fingerprint FROM domain_contacts WHERE domain_id=? AND role='REGISTRANT' ORDER BY version DESC LIMIT 1",[$existing['id']])->fetchColumn();
            $storedNs=$this->db->execute('SELECT nameservers_json FROM domain_nameserver_snapshots WHERE domain_id=? ORDER BY version DESC LIMIT 1',[$existing['id']])->fetchColumn();
            if($existing['name_ascii']!==$name->ascii||$existing['rule_id']!==$ruleId||!is_string($storedContact)||!hash_equals($storedContact,hash_hmac('sha256',CanonicalJson::encode($contact),$this->key))||$storedNs!==CanonicalJson::encode($ns)){throw new HttpError(409,'DOMAIN_BINDING_CONFLICT');}
            return $existing['id'];
        }
        if($this->db->execute('SELECT 1 FROM domains WHERE name_ascii=?',[$name->ascii])->fetchColumn()){throw new HttpError(409,'DOMAIN_NAME_CONFLICT');}
        $id=Id::uuid();$contactId=Id::uuid();$json=CanonicalJson::encode($contact);
        $this->db->execute('INSERT INTO domains(id,service_id,customer_id,name_ascii,tld,rule_id) VALUES (?,?,?,?,?,?)',[$id,$serviceId,$customer,$name->ascii,$rule['tld'],$ruleId]);
        $this->db->execute("INSERT INTO domain_contacts(id,domain_id,customer_id,role,version,contact_encrypted,fingerprint) VALUES (?,?,?,'REGISTRANT',1,?,?)",[$contactId,$id,$customer,$this->crypto->encrypt($json,'domain-contact:'.$contactId),hash_hmac('sha256',$json,$this->key)]);
        $this->db->execute('INSERT INTO domain_nameserver_snapshots(id,domain_id,version,nameservers_json) VALUES (?,?,1,?)',[Id::uuid(),$id,CanonicalJson::encode($ns)]);
        $this->event($id,$customer,'ATTACHED',$actor,$trace);
        return $id;
    }
    public function find(string $id,string $customer): array
    {
        $row=$this->db->execute('SELECT * FROM domains WHERE id=? AND customer_id=?',[$id,$customer])->fetch();
        if(!$row){throw new HttpError(404,'DOMAIN_NOT_FOUND');}return $row;
    }
    public function view(string $id,string $customer,Principal $actor): array
    {
        $row=$this->find($id,$customer);(new Authorization())->require($actor,'services.view',$customer,service:$row['service_id']);
        // Explicit client projection never exposes provider mapping, contacts or credentials.
        return array_intersect_key($row,array_flip(['id','service_id','name_ascii','status','expires_at','locked','privacy_enabled','version']));
    }
    public function contact(string $id,string $customer,Principal $actor): array
    {
        $row=$this->find($id,$customer);(new Authorization())->require($actor,'services.manage',$customer,privileged:true,service:$row['service_id']);
        if($actor->impersonating){throw new HttpError(403,'DOMAIN_IMPERSONATION_FORBIDDEN');}
        $contact=$this->db->execute("SELECT * FROM domain_contacts WHERE domain_id=? AND customer_id=? AND role='REGISTRANT' ORDER BY version DESC LIMIT 1",[$id,$customer])->fetch();
        if(!$contact){throw new HttpError(409,'DOMAIN_CONTACT_MISSING');}
        $json=$this->crypto->decrypt($contact['contact_encrypted'],'domain-contact:'.$contact['id']);
        if(!hash_equals($contact['fingerprint'],hash_hmac('sha256',$json,$this->key))){throw new HttpError(409,'DOMAIN_CONTACT_INTEGRITY');}
        return json_decode($json,true,32,JSON_THROW_ON_ERROR);
    }
    private function event(string $id,string $customer,string $kind,Principal $actor,string $trace): void
    {
        $event=Id::uuid();$this->db->execute('INSERT INTO domain_history(id,domain_id,customer_id,kind,actor_id,trace_id) VALUES (?,?,?,?,?,?)',[$event,$id,$customer,$kind,$actor->actorId,$trace]);
        (new Audit($this->db))->record($actor,AuditEvent::DomainChanged,$id,$trace);
        (new Outbox($this->db,new Queue($this->db)))->append($event,'domains.changed',$id,['domain_id'=>$id],$trace);
    }
}
