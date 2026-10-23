<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config,Validation};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{Authorization,Principal};
/** Nullable values inherit; missing commercial decisions do not receive invented defaults. */
final readonly class Policies
{
    private const FIELDS=['grace_seconds','termination_wait_seconds','transfer_ttl_seconds','renewal_lead_seconds','auto_suspend','auto_wallet','price_protected','price_notice_required','allow_cancel','allow_transfer','transfer_debt_scope','package_proration'];
    public function __construct(private Database $db,private Config $config){}
    public function set(string $scope,string $id,array $policy,int $version,Principal $actor): void
    {
        $this->db->requireTransaction();if($this->config->environment!=='test'){throw new HttpError(403,'F3_TEST_ONLY');}
        (new Authorization())->require($actor,'services.policy',privileged:true,administrative:true);
        if(!in_array($scope,['GLOBAL','CATEGORY','PRODUCT','SERVICE'],true)||($scope==='GLOBAL'?$id!=='GLOBAL':!preg_match('/^[a-f0-9-]{36}$/D',$id))){throw new HttpError(422,'INVALID_POLICY_SCOPE');}
        Validation::fields($policy,self::FIELDS,[]);foreach($policy as $key=>$value){if($value===null){continue;}if(str_ends_with($key,'_seconds')){if(!is_int($value)||$value<1||$value>31536000){throw new HttpError(422,'INVALID_POLICY_INTERVAL');}}elseif($key==='package_proration'){if(!in_array($value,['BLOCKED','EXACT_SECONDS'],true)){throw new HttpError(422,'INVALID_PRORATION_POLICY');}}elseif($key==='transfer_debt_scope'){if(!in_array($value,['SERVICE','CUSTOMER'],true)){throw new HttpError(422,'INVALID_TRANSFER_DEBT_SCOPE');}}elseif(!is_bool($value)){throw new HttpError(422,'INVALID_POLICY_BOOLEAN');}}
        $old=$this->db->execute('SELECT * FROM svc_policies WHERE scope_kind=? AND scope_id=? FOR UPDATE',[$scope,$id])->fetch();if(($old?(int)$old['version']:0)!==$version){throw new HttpError(409,'POLICY_VERSION_CONFLICT');}
        if($old){$this->db->execute('UPDATE svc_policies SET policy_json=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE scope_kind=? AND scope_id=?',[CanonicalJson::encode($policy),$scope,$id]);}else{$this->db->execute('INSERT INTO svc_policies(scope_kind,scope_id,policy_json) VALUES (?,?,?)',[$scope,$id,CanonicalJson::encode($policy)]);}
        (new \Hostider\Core\Security\Audit($this->db))->record($actor,\Hostider\Core\Security\AuditEvent::ServiceChanged,$id,\Hostider\Core\Id::uuid());
    }
    public function resolve(array $service): array
    {
        $result=array_fill_keys(self::FIELDS,null);
        foreach([['GLOBAL','GLOBAL'],['CATEGORY',$service['category_id']],['PRODUCT',$service['product_id']],['SERVICE',$service['id']]] as [$scope,$id]){if($id===null){continue;}$row=$this->db->execute('SELECT policy_json FROM svc_policies WHERE scope_kind=? AND scope_id=?',[$scope,$id])->fetchColumn();if(!$row){continue;}foreach(json_decode($row,true,32,JSON_THROW_ON_ERROR) as $key=>$value){if($value!==null){$result[$key]=$value;}}}return $result;
    }
    public static function required(array $policy,string $key): mixed
    {
        return $policy[$key]??throw new HttpError(409,'EXPLICIT_SERVICE_POLICY_REQUIRED');
    }
}
