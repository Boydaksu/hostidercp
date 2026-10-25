<?php
declare(strict_types=1);
namespace Hostider\Cloud;
use Hostider\Core\{CanonicalJson,Config,Id,Validation};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;

/** Explicit synthetic capability/configuration snapshots; no live Voyar pricing inference. */
final readonly class Catalog
{
    public function __construct(private Database $db,private Config $config){if($config->environment!=='test'){throw new HttpError(403,'F4_TEST_ONLY');}}
    public function configure(string $product,array $snapshot): string
    {
        Validation::fields($snapshot,['pids','osids','locations','actions','custom','custom_limits','free_upgrade_targets','termination_wait_seconds'],['pids','osids','locations','actions','custom','free_upgrade_targets','termination_wait_seconds']);
        foreach(['pids','osids','locations','free_upgrade_targets'] as $field){if(!array_is_list($snapshot[$field])){throw new HttpError(422,'CLOUD_CATALOG_INVALID');}foreach($snapshot[$field] as $v){if(!is_int($v)||$v<1){throw new HttpError(422,'CLOUD_CATALOG_INVALID');}}}
        if(!is_int($snapshot['termination_wait_seconds'])||$snapshot['termination_wait_seconds']<1||$snapshot['termination_wait_seconds']>86400||!is_bool($snapshot['custom'])){throw new HttpError(422,'CLOUD_CATALOG_INVALID');}
        if(!array_is_list($snapshot['actions'])){throw new HttpError(422,'CLOUD_CAPABILITY_INVALID');}
        foreach($snapshot['actions'] as $action){if(!in_array($action,['CREATE','START','STOP','RESTART','REINSTALL','UPGRADE','TERMINATE'],true)){throw new HttpError(422,'CLOUD_CAPABILITY_INVALID');}}
        if($snapshot['custom']){
            $keys=['group_id','cpu_min','cpu_max','cpu_step','ram_min','ram_max','ram_per_core_max','disk_min','disk_max','disk_per_ram_max','ipv4_max'];
            Validation::fields($snapshot['custom_limits']??[],$keys,$keys);foreach($keys as $key){if(!is_int($snapshot['custom_limits'][$key])||$snapshot['custom_limits'][$key]<1||$snapshot['custom_limits'][$key]>100000){throw new HttpError(422,'CLOUD_CUSTOM_LIMITS_INVALID');}}
        }
        elseif(array_key_exists('custom_limits',$snapshot)){throw new HttpError(422,'CLOUD_CUSTOM_LIMITS_NOT_ALLOWED');}
        return $this->db->transaction(function()use($product,$snapshot): string{$account=Id::uuid();$id=Id::uuid();$this->db->execute("INSERT INTO cloud_accounts(id,environment) VALUES (?,'mock')",[$account]);$this->db->execute('INSERT INTO cloud_catalog(id,account_id,product_id,snapshot) VALUES (?,?,?,?)',[$id,$account,$product,CanonicalJson::encode($snapshot)]);return $id;});
    }
    public function find(string $id): array
    {
        $row=$this->db->execute('SELECT c.*,a.enabled FROM cloud_catalog c JOIN cloud_accounts a ON a.id=c.account_id WHERE c.id=?',[$id])->fetch();if(!$row||!$row['enabled']){throw new HttpError(409,'CLOUD_CATALOG_UNAVAILABLE');}return $row+['policy'=>json_decode($row['snapshot'],true,32,JSON_THROW_ON_ERROR)];
    }
    public function validate(array $policy,array $spec): void
    {
        Validation::fields($spec,['pid','custom_package','osid','locationid','type'],['osid','locationid','type']);
        if(!in_array($spec['osid'],$policy['osids'],true)||!in_array($spec['locationid'],$policy['locations'],true)||!in_array($spec['type'],['ondemand','prepaid'],true)||isset($spec['pid'])===isset($spec['custom_package'])){throw new HttpError(422,'CLOUD_SPEC_INVALID');}
        if(isset($spec['pid'])){if(!in_array($spec['pid'],$policy['pids'],true)){throw new HttpError(422,'CLOUD_PLAN_NOT_ALLOWED');}return;}
        // These are explicit Hostider mock policy limits, not invented fields of a live Voyar response.
        if(!$policy['custom']){throw new HttpError(409,'G_V04_CUSTOM_CONFIGURATION_UNVERIFIED');}
        $c=$spec['custom_package'];$keys=['product_group_id','cpu_cores','ram_gb','disk_gb','ipv4_count'];Validation::fields($c,$keys,$keys);
        foreach($keys as $key){if(!is_int($c[$key])||$c[$key]<($key==='ipv4_count'?0:1)||$c[$key]>100000){throw new HttpError(422,'CLOUD_CUSTOM_SPEC_INVALID');}}
        $l=$policy['custom_limits'];
        if($c['product_group_id']!==$l['group_id']||$c['cpu_cores']<$l['cpu_min']||$c['cpu_cores']>$l['cpu_max']||($c['cpu_cores']-$l['cpu_min'])%$l['cpu_step']!==0||$c['ram_gb']<$l['ram_min']||$c['ram_gb']>$l['ram_max']||$c['ram_gb']>$c['cpu_cores']*$l['ram_per_core_max']||$c['disk_gb']<$l['disk_min']||$c['disk_gb']>$l['disk_max']||$c['disk_gb']>$c['ram_gb']*$l['disk_per_ram_max']||$c['ipv4_count']>$l['ipv4_max']){throw new HttpError(422,'CLOUD_CUSTOM_SPEC_INVALID');}
    }
    public function action(array $policy,string $kind,array $payload): void
    {
        if(!in_array($kind,$policy['actions'],true)){throw new HttpError(409,'CLOUD_ACTION_UNSUPPORTED');}
        $fields=match($kind){'REINSTALL'=>['osid'],'UPGRADE'=>['pid'],default=>[]};Validation::fields($payload,$fields,$fields);
        if($kind==='REINSTALL'&&!in_array($payload['osid'],$policy['osids'],true)){throw new HttpError(422,'CLOUD_OS_NOT_ALLOWED');}
        if($kind==='UPGRADE'&&(!in_array($payload['pid'],$policy['pids'],true)||!in_array($payload['pid'],$policy['free_upgrade_targets'],true))){throw new HttpError(409,'CLOUD_PLAN_FINANCIAL_REVIEW_REQUIRED');}
    }
}
