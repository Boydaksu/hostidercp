<?php
declare(strict_types=1);
namespace Hostider\CloudCosts;
use Hostider\Core\{Config,Id,CanonicalJson};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Identity\IdentityService;
final readonly class Tariffs
{
    private Store $store;
    public function __construct(private Database $db,private Config $config){$this->store=new Store($db,$config);}
    public function publish(string $account,array $policy,string $session): string
    {
        Calculator::policy($policy);$identity=new IdentityService($this->db,$this->config);
        return $identity->withStepUp($session,'admin',function(array $u,array $s)use($identity,$account,$policy): string{
            $actor=$identity->authorize($u,$s,'finance.manage',privileged:true);if($policy['fx']!==null){$identity->authorize($u,$s,'pricing.manage',privileged:true);}if(!$actor->staff||$actor->impersonating){throw new HttpError(403,'CLOUD_COST_STAFF_REQUIRED');}
            if(!$this->db->execute("SELECT id FROM cloud_accounts WHERE id=? AND enabled=TRUE AND environment='mock' FOR UPDATE",[$account])->fetchColumn()){throw new HttpError(409,'CLOUD_TARIFF_ACCOUNT_INVALID');}
            $id=Id::uuid();$this->db->execute("INSERT INTO cloud_meter_tariffs(id,account_id,policy_json,fingerprint,actor_id,provenance) VALUES (?,?,?,?,?,'MOCK')",[$id,$account,CanonicalJson::encode($policy),$this->store->hash(['account'=>$account,'policy'=>$policy]),$u['id']]);$identity->confirmationEvent($u['id'],$id,true);return $id;
        });
    }
    public function find(string $id): ?array
    {
        $r=$this->db->execute('SELECT * FROM cloud_meter_tariffs WHERE id=?',[$id])->fetch();if(!$r){return null;}$r['policy']=json_decode($r['policy_json'],true,64,JSON_THROW_ON_ERROR);if(!hash_equals($r['fingerprint'],$this->store->hash(['account'=>$r['account_id'],'policy'=>$r['policy']]))){throw new HttpError(409,'CLOUD_TARIFF_INTEGRITY');}return $r;
    }
}
