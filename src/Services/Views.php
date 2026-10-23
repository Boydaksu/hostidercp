<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{Authorization,Principal};
final readonly class Views
{
    public function __construct(private \Hostider\Commerce\Services $c){}
    public function list(array $u,array $s,Principal $actor,string $customer,int $offset): array
    {
        (new Authorization())->require($actor,'services.view');$rows=$this->c->db->execute('SELECT id FROM svc_services WHERE customer_id=? ORDER BY created_at DESC,id LIMIT 50 OFFSET '.$offset,[$customer])->fetchAll();$result=[];
        foreach($rows as $row){try{$this->c->identity->authorize($u,$s,'services.view',$customer,$row['id']);$result[]=$this->summary($row['id'],$customer);}catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}}}return $result;
    }
    private function summary(string $id,string $customer): array
    {
        $row=$this->c->db->execute('SELECT s.id,s.name,s.status,CAST(s.version AS CHAR) version,s.cancel_at,p.starts_at,p.ends_at FROM svc_services s LEFT JOIN svc_periods p ON p.id=s.current_period_id WHERE s.id=? AND s.customer_id=?',[$id,$customer])->fetch();if(!$row){throw new HttpError(404,'SERVICE_NOT_FOUND');}return $row;
    }
    public function detail(array $u,array $s,Principal $actor,string $customer,string $id): array
    {
        $this->c->identity->authorize($u,$s,'services.view',$customer,$id);$result=$this->summary($id,$customer);if($actor->impersonating){return $result;}
        $db=$this->c->db;$result['price']=$db->execute('SELECT p.currency,CAST(p.net_minor AS CHAR) net_minor,CAST(p.tax_minor AS CHAR) tax_minor,CAST(p.total_minor AS CHAR) total_minor FROM svc_services s JOIN svc_prices p ON p.id=s.current_price_id WHERE s.id=? AND s.customer_id=? AND p.customer_id=?',[$id,$customer,$customer])->fetch()?:null;
        $source=(new Repository($db,$this->c->config))->find($id,$customer,false);$result['imported']=(bool)$source['imported'];if(!$source['imported']){$result['selection']=array_intersect_key((new Repository($db,$this->c->config))->configuration($id)['selection'],array_flip(['product_id','variant_id','cycle_id','quantity','options','addons']));}
        $result['periods']=$db->execute('SELECT CAST(generation AS CHAR) generation,starts_at,ends_at,status FROM svc_periods WHERE service_id=? AND customer_id=? ORDER BY generation DESC LIMIT 50',[$id,$customer])->fetchAll();$result['renewals']=$db->execute('SELECT id,order_id,status,CAST(generation AS CHAR) generation FROM svc_renewals WHERE service_id=? AND customer_id=? ORDER BY generation DESC LIMIT 20',[$id,$customer])->fetchAll();$result['package_changes']=$db->execute('SELECT id,order_id,status FROM svc_package_changes WHERE service_id=? AND customer_id=? ORDER BY created_at DESC,id LIMIT 20',[$id,$customer])->fetchAll();$result['preferences']=$db->execute('SELECT auto_wallet,CAST(version AS CHAR) version FROM svc_preferences WHERE service_id=? AND customer_id=?',[$id,$customer])->fetch()?:['auto_wallet'=>false,'version'=>'0'];$result['policy']=(new Policies($db,$this->c->config))->resolve((new Repository($db,$this->c->config))->find($id,$customer,false));
        $result['preferences']['auto_wallet']=(bool)$result['preferences']['auto_wallet'];$storedPolicy=$db->execute("SELECT policy_json,version FROM svc_policies WHERE scope_kind='SERVICE' AND scope_id=?",[$id])->fetch();$result['policy_version']=$storedPolicy?(string)$storedPolicy['version']:'0';$result['policy_overrides']=$storedPolicy?json_decode($storedPolicy['policy_json'],true,32,JSON_THROW_ON_ERROR):[];if($actor->staff){$result['operations']=$db->execute('SELECT id,kind,status,CAST(generation AS CHAR) generation,created_at,updated_at FROM svc_operations WHERE service_id=? ORDER BY created_at DESC,id LIMIT 50',[$id])->fetchAll();$result['attempts']=$db->execute('SELECT a.operation_id,a.kind,a.safe_code,a.created_at FROM svc_attempt_events a JOIN svc_operations o ON o.id=a.operation_id WHERE o.service_id=? ORDER BY a.id DESC LIMIT 100',[$id])->fetchAll();$result['inspections']=$db->execute('SELECT id,found,actor_id,expires_at,created_at FROM svc_inspections WHERE service_id=? ORDER BY created_at DESC,id LIMIT 20',[$id])->fetchAll();$result['inspection_jobs']=$db->execute('SELECT id,status,inspection_id,safe_code,created_at FROM svc_inspection_jobs WHERE service_id=? ORDER BY created_at DESC,id LIMIT 20',[$id])->fetchAll();$result['resources']=$db->execute('SELECT server_id,external_id,resource_role,imported FROM svc_resources WHERE service_id=?',[$id])->fetchAll();}
        return $result;
    }
    public function pools(Principal $actor): array
    {
        (new Authorization())->require($actor,'services.pools',administrative:true);$db=$this->c->db;return ['pools'=>$db->execute('SELECT id,name,enabled FROM hosting_pools ORDER BY id LIMIT 100')->fetchAll(),'servers'=>$db->execute("SELECT s.id,s.pool_id,s.environment,s.capacity,s.weight,s.healthy,s.observed_at,s.fresh_seconds,s.enabled,s.capabilities,(SELECT COUNT(*) FROM hosting_reservations r WHERE r.server_id=s.id AND r.status='HELD') AS reserved FROM hosting_servers s ORDER BY s.id LIMIT 100")->fetchAll(),'mappings'=>$db->execute('SELECT id,product_id,pool_id,package_name,reseller_scope,enabled FROM hosting_mappings ORDER BY id LIMIT 100')->fetchAll()];
    }
}
