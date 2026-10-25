<?php
declare(strict_types=1);
namespace Hostider\ProviderPanel;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,Validation,CanonicalJson};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
/** Local application composition only. No provider reads or mutations in HTTP transactions. */
final readonly class Commands
{
    public Access $access;
    public function __construct(public Services $c){$this->access=new Access($c);}
    public function authorize(array $u,array $s,string $action,array $p,string $customer,bool $replay=false): Principal
    {
        $fields=match($action){'cloud-action'=>['service_id','kind','data'],'inspect'=>['service_id','operation_id'],'resolve'=>['service_id','operation_id','evidence_id','outcome'],'proposal-approve','proposal-publish'=>['service_id','proposal_id'],default=>throw new HttpError(422,'UNSUPPORTED_PANEL_ACTION')};
        Validation::fields($p,$fields,$fields);foreach($p as $key=>$val){if(str_ends_with($key,'_id')){Access::id($val);}}
        $a=$this->c->identity->principal($u,$s);$permission=$action==='cloud-action'?($a->staff?'services.manage':'cloud.power'):(str_starts_with($action,'proposal-')?'finance.approve':'services.reconcile');
        $v=$this->access->service($u,$s,$customer,$p['service_id'],$permission,true);
        if($action!=='cloud-action'){$this->access->staff($a);}
        if($action==='cloud-action'){
            if($v['product_type']!=='CLOUD'||!in_array($p['kind'],['START','STOP','RESTART','REINSTALL','TERMINATE'],true)||!is_array($p['data'])){throw new HttpError(422,'CLOUD_PANEL_ACTION_INVALID');}
            $i=$this->instance($p['service_id'],$customer);$store=new \Hostider\Cloud\Store($this->c->db,$this->c->config);$policy=(new \Hostider\Cloud\Catalog($this->c->db,$this->c->config))->find($i['catalog_id'])['policy'];(new \Hostider\Cloud\Catalog($this->c->db,$this->c->config))->action($policy,$p['kind'],$p['data']);(new \Hostider\Cloud\Entitlements($this->c->db,$this->c->config))->action($i,$p['kind'],$p['data']);
            if(!$store->source($i)||!in_array($v['status'],['ACTIVE'],true)){throw new HttpError(409,'CLOUD_SOURCE_OR_SERVICE_BLOCKED');}
            $op=$i['current_operation_id']?$store->operation($i['current_operation_id'],false):null;if(!$replay&&(!in_array($i['status'],['ACTIVE','STOPPED'],true)||!$i['resource_id']||($op&&!in_array($op['status'],['SUCCEEDED','FAILED'],true)))){throw new HttpError(409,'CLOUD_OPERATION_IN_FLIGHT');}
        }elseif(str_starts_with($action,'proposal-')){
            if($v['product_type']!=='CLOUD'){throw new HttpError(404,'CLOUD_SERVICE_NOT_FOUND');}$this->c->identity->authorize($u,$s,'pricing.manage',privileged:true);$this->c->identity->authorize($u,$s,'finance.view',$customer,$p['service_id'],privileged:true);
            if(!$this->c->db->execute('SELECT 1 FROM catalog_cloud_cost_links l JOIN cloud_instances i ON i.id=l.instance_id WHERE l.proposal_id=? AND i.service_id=? AND i.customer_id=?',[$p['proposal_id'],$p['service_id'],$customer])->fetchColumn()){throw new HttpError(404,'CLOUD_PROPOSAL_NOT_FOUND');}
            if($action==='proposal-publish'){$this->c->identity->authorize($u,$s,'catalog.manage',privileged:true);}
        }else{
            $this->c->identity->authorize($u,$s,'services.manage',$customer,$p['service_id'],privileged:true);$op=$this->operation($v['product_type'],$p['operation_id'],$p['service_id'],$customer);
            if(!$replay&&!in_array($op['status'],['UNKNOWN','MANUAL_REVIEW'],true)){throw new HttpError(409,'OPERATION_NOT_UNKNOWN');}
            if($action==='resolve'&&!in_array($p['outcome'],['CONFIRMED_SUCCESS','CONFIRMED_FAILURE'],true)){throw new HttpError(422,'INVALID_RECONCILIATION_OUTCOME');}
            if($action==='resolve'){$table=$v['product_type']==='CLOUD'?'cloud_observations':'domain_inspections';if(!$this->c->db->execute('SELECT 1 FROM '.$table.' WHERE id=? AND operation_id=?',[$p['evidence_id'],$p['operation_id']])->fetchColumn()){throw new HttpError(404,'INSPECTION_NOT_FOUND');}}
        }
        return $a;
    }
    public function instance(string $service,string $customer): array
    {
        $i=$this->c->db->execute('SELECT * FROM cloud_instances WHERE service_id=? AND customer_id=? FOR UPDATE',[$service,$customer])->fetch();if(!$i){throw new HttpError(404,'CLOUD_INSTANCE_NOT_FOUND');}return $i;
    }
    public function operation(string $kind,string $operation,string $service,string $customer): array
    {
        $sql=$kind==='CLOUD'?'SELECT o.* FROM cloud_operations o JOIN cloud_instances i ON i.id=o.instance_id WHERE o.id=? AND i.service_id=? AND i.customer_id=?':'SELECT o.* FROM domain_operations o JOIN domains d ON d.id=o.domain_id WHERE o.id=? AND o.service_id=? AND d.customer_id=?';
        $r=$this->c->db->execute($sql.' FOR UPDATE',[$operation,$service,$customer])->fetch();if(!$r){throw new HttpError(404,'OPERATION_NOT_FOUND');}return $r;
    }
    public function state(array $p,string $customer): array
    {
        $db=$this->c->db;$db->requireTransaction();$state=(new \Hostider\Services\Commands($this->c))->lockState('panel',$p,$customer);
        $i=$db->execute('SELECT * FROM cloud_instances WHERE service_id=? AND customer_id=? FOR UPDATE',[$p['service_id'],$customer])->fetch();$d=$db->execute('SELECT * FROM domains WHERE service_id=? AND customer_id=? FOR UPDATE',[$p['service_id'],$customer])->fetch();$state['instance']=$i;$state['domain']=$d;
        $state['cloud_catalog']=$i?$db->execute('SELECT * FROM cloud_catalog WHERE id=? FOR UPDATE',[$i['catalog_id']])->fetch():null;
        $state['cloud_operation']=$i&&$i['current_operation_id']?$db->execute('SELECT * FROM cloud_operations WHERE id=? FOR UPDATE',[$i['current_operation_id']])->fetch():null;
        $state['domain_operations']=$d?$db->execute('SELECT * FROM domain_operations WHERE domain_id=? ORDER BY id FOR UPDATE',[$d['id']])->fetchAll():[];
        if(isset($p['proposal_id'])){$state['proposal']=$db->execute('SELECT * FROM catalog_cloud_cost_links WHERE proposal_id=? FOR UPDATE',[$p['proposal_id']])->fetch();$state['approval']=$db->execute('SELECT * FROM catalog_cloud_cost_approvals WHERE proposal_id=?',[$p['proposal_id']])->fetch();$state['publication']=$db->execute('SELECT * FROM catalog_cloud_cost_publications WHERE proposal_id=?',[$p['proposal_id']])->fetch();}
        return $state;
    }
    public function nativePrepare(string $action,array $p,string $customer,Principal $a,string $reference): ?string
    {
        if($action==='cloud-action'){return (new \Hostider\Cloud\Engine($this->c->db,$this->c->config))->prepare($this->instance($p['service_id'],$customer)['id'],$p['kind'],$p['data'],$a);}
        if($action==='resolve'){$v=$this->c->db->execute('SELECT product_type FROM catalog_products p JOIN svc_services s ON s.product_id=p.id WHERE s.id=?',[$p['service_id']])->fetchColumn();if($v==='CLOUD'){if($p['outcome']!=='CONFIRMED_SUCCESS'){throw new HttpError(409,'CLOUD_FAILURE_REQUIRES_MANUAL_REVIEW');}return (new \Hostider\Cloud\Engine($this->c->db,$this->c->config))->prepare($this->instance($p['service_id'],$customer)['id'],'RESOLVE',['observation_id'=>$p['evidence_id']],$a);}}
        if($action==='proposal-publish'){return (new \Hostider\Catalog\CloudCostProposals($this->c->db,$this->c->config))->preparePublish($p['proposal_id'],$reference)['id'];}return null;
    }
    public function execute(string $action,array $p,string $customer,Principal $a,array $u,array $s,?string $native,string $trace): array
    {
        $db=$this->c->db;$cfg=$this->c->config;
        if($action==='cloud-action'){return ['operation_id'=>(new \Hostider\Cloud\Engine($db,$cfg))->enqueue($this->instance($p['service_id'],$customer)['id'],$p['kind'],$p['data'],$a,$native),'status'=>'PENDING'];}
        if($action==='inspect'){return ['inspection_job_id'=>(new Inspections($this->c))->request($u,$s,$customer,$p['service_id'],$p['operation_id'],$trace),'status'=>'PENDING'];}
        if($action==='resolve'){$v=$db->execute('SELECT product_type FROM catalog_products p JOIN svc_services s ON s.product_id=p.id WHERE s.id=?',[$p['service_id']])->fetchColumn();if($v==='CLOUD'){(new \Hostider\Cloud\Engine($db,$cfg))->resolve($p['operation_id'],$p['evidence_id'],$native,$a);}else{(new \Hostider\Domains\DomainReconciliation($db,$cfg))->resolve($p['operation_id'],$p['evidence_id'],$p['outcome'],$a,$trace);}return ['recorded'=>true];}
        $proposals=new \Hostider\Catalog\CloudCostProposals($db,$cfg);if($action==='proposal-approve'){$proposals->approve($p['proposal_id'],$s['native_reference'],true);return ['approved'=>true,'published'=>false];}
        if($action==='proposal-publish'){return ['price_id'=>$proposals->publish($p['proposal_id'],$s['native_reference'],['id'=>$native,'accepted'=>true])];}throw new HttpError(422,'UNSUPPORTED_PANEL_ACTION');
    }
    public function hash(array $value): string{return $this->c->identity->digest(CanonicalJson::encode($value));}
}
