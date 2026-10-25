<?php
declare(strict_types=1);
namespace Hostider\ProviderPanel;
use Hostider\Commerce\Services;
use Hostider\Core\Http\HttpError;
final readonly class Views
{
    private Access $access;
    public function __construct(private Services $c){$this->access=new Access($c);}
    public function list(array $u,array $s,string $customer,string $kind,int $offset): array
    {
        if(!in_array($kind,['DOMAIN','CLOUD'],true)){throw new HttpError(422,'INVALID_PANEL_KIND');}
        $rows=$this->c->db->execute('SELECT s.id FROM svc_services s JOIN catalog_products p ON p.id=s.product_id WHERE s.customer_id=? AND p.product_type=? ORDER BY s.created_at DESC,s.id LIMIT 50 OFFSET '.$offset,[$customer,$kind])->fetchAll();$out=[];
        foreach($rows as $r){try{$out[]=$this->detail($u,$s,$customer,$r['id'],false);}catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}}}return $out;
    }
    public function detail(array $u,array $s,string $customer,string $id,bool $full=true): array
    {
        $v=$this->access->service($u,$s,$customer,$id);$a=$this->c->identity->principal($u,$s);$db=$this->c->db;
        $out=['id'=>$id,'name'=>$v['name'],'kind'=>$v['product_type'],'service_state'=>$v['status'],'version'=>(string)$v['version'],'cancel_at'=>$v['cancel_at'],'provenance'=>'MOCK','transfer_supported'=>false,'credential_retrieval_supported'=>false];
        $out['period']=$db->execute('SELECT starts_at,ends_at,status FROM svc_periods WHERE id=? AND customer_id=?',[$v['current_period_id'],$customer])->fetch()?:null;
        $out['sale_price']=$db->execute('SELECT currency,CAST(total_minor AS CHAR) total_minor FROM svc_prices WHERE id=? AND customer_id=?',[$v['current_price_id'],$customer])->fetch()?:null;
        $out['sale_period_label']=$v['product_type']==='CLOUD'?'30 DAYS prepaid customer sale':'Purchased domain term';
        if($v['product_type']==='DOMAIN'){
            $d=$db->execute('SELECT id,name_ascii,status,expires_at,locked,version FROM domains WHERE service_id=? AND customer_id=?',[$id,$customer])->fetch();
            $ns=$d?$db->execute('SELECT nameservers_json FROM domain_nameserver_snapshots WHERE domain_id=? ORDER BY version DESC LIMIT 1',[$d['id']])->fetchColumn():false;
            $out['domain']=$d?['name'=>$d['name_ascii'],'state'=>$d['status'],'expires_at'=>$d['expires_at'],'nameservers'=>$ns?json_decode($ns,true,32,JSON_THROW_ON_ERROR):[],'locked'=>(bool)$d['locked']]:null;
            $out['operations']=$d?$db->execute('SELECT id,kind,status,created_at,updated_at'.($a->staff?',runner_checked_at,runner_hold_code,lease_until,active_attempt_id':'').' FROM domain_operations WHERE domain_id=? ORDER BY created_at DESC,id LIMIT 30',[$d['id']])->fetchAll():[];
            if($full&&$a->staff&&$d){$out['attempts']=$db->execute('SELECT a.id,a.operation_id,a.account_id,a.status,a.safe_code,a.finished_at FROM domain_registrar_attempts a JOIN domain_operations o ON o.id=a.operation_id WHERE o.domain_id=? ORDER BY a.id DESC LIMIT 50',[$d['id']])->fetchAll();$out['inspections']=$db->execute('SELECT i.id,i.operation_id,i.found,i.created_at FROM domain_inspections i JOIN domain_operations o ON o.id=i.operation_id WHERE o.domain_id=? ORDER BY i.created_at DESC LIMIT 20',[$d['id']])->fetchAll();}
            $out['actions']=[];$out['unsupported']=['transfer','EPP','contact export','DNS mutation'];
        }else{
            $i=$db->execute('SELECT * FROM cloud_instances WHERE service_id=? AND customer_id=?',[$id,$customer])->fetch();$out['vm']=$i?['state'=>$i['status'],'imported'=>(bool)$i['imported'],'version'=>(string)$i['version']]:null;$out['actions']=[];
            if($i){$store=new \Hostider\Cloud\Store($db,$this->c->config);$out['purchased_configuration']=$store->decrypt($i['spec_encrypted'],$i['fingerprint'],'cloud-spec:'.$i['id']);$policy=(new \Hostider\Cloud\Catalog($db,$this->c->config))->find($i['catalog_id'])['policy'];
                $out['operations']=$db->execute('SELECT id,kind,status,created_at'.($a->staff?',task_id,resource_id,generation,lease_until,runner_checked_at,safe_code,financial_exception':'').' FROM cloud_operations WHERE instance_id=? ORDER BY created_at DESC,id LIMIT 30',[$i['id']])->fetchAll();
                try{$this->access->service($u,$s,$customer,$id,$a->staff?'services.manage':'cloud.power');$busy=$i['current_operation_id']?$store->operation($i['current_operation_id'],false):null;
                    if(in_array($i['status'],['ACTIVE','STOPPED'],true)&&(!$busy||in_array($busy['status'],['SUCCEEDED','FAILED'],true))&&$store->source($i)){$out['actions']=array_values(array_intersect($policy['actions'],['START','STOP','RESTART','REINSTALL','TERMINATE']));}
                }catch(HttpError $e){if($e->status!==403){throw $e;}}
                if($full&&$a->staff){$out['resource']=['account_id'=>$i['account_id'],'resource_id'=>$i['resource_id'],'provider_reference'=>$i['provider_resource_ref']];$out['financial_source_valid']=$store->source($i);$out['observations']=$db->execute('SELECT o.id,o.operation_id,o.created_at FROM cloud_observations o JOIN cloud_operations p ON p.id=o.operation_id WHERE p.instance_id=? ORDER BY o.created_at DESC LIMIT 20',[$i['id']])->fetchAll();}
            }else{$out['operations']=[];}
            $out['cost_state']='NOT_PROVEN';$out['stop_ends_provider_cost']=false;$out['unsupported']=['paid plan/custom change','credential retrieval','transfer'];
        }
        if($full){$out['renewals']=$db->execute('SELECT id,status,CAST(generation AS CHAR) generation FROM svc_renewals WHERE service_id=? AND customer_id=? ORDER BY generation DESC LIMIT 20',[$id,$customer])->fetchAll();if($a->staff){$out['inspection_jobs']=$db->execute('SELECT id,operation_id,kind,status,result_id,safe_code,created_at FROM panel_inspections WHERE service_id=? AND customer_id=? ORDER BY created_at DESC,id LIMIT 20',[$id,$customer])->fetchAll();}}
        return $out;
    }
    public function costs(array $u,array $s,string $customer,string $id): array
    {
        $this->access->staff($this->c->identity->principal($u,$s));$v=$this->access->service($u,$s,$customer,$id,'finance.view');if($v['product_type']!=='CLOUD'){throw new HttpError(404,'CLOUD_SERVICE_NOT_FOUND');}$db=$this->c->db;
        $i=$db->execute('SELECT id FROM cloud_instances WHERE service_id=? AND customer_id=?',[$id,$customer])->fetchColumn();$out=['provenance'=>'MOCK','provider_invoice_is_paid'=>false,'stop_ends_provider_cost'=>false,'work'=>[],'costs'=>[],'reconciliations'=>[],'proposals'=>[]];if(!$i){return $out;}
        $out['work']=$db->execute('SELECT id,status,safe_code,created_at FROM cloud_cost_work WHERE instance_id=? ORDER BY created_at DESC,id LIMIT 50',[$i])->fetchAll();$store=new \Hostider\CloudCosts\Store($db,$this->c->config);
        foreach($db->execute('SELECT * FROM cloud_usage_costs WHERE instance_id=? ORDER BY created_at DESC,id LIMIT 50',[$i])->fetchAll() as $r){$calc=(new \Hostider\CloudCosts\Engine($db,$this->c->config))->cost($r['id'])['calculation'];$out['costs'][]=['id'=>$r['id'],'currency'=>$r['currency'],'total_minor'=>(string)$r['total_minor'],'starts_at'=>$r['starts_at'],'ends_at'=>$r['ends_at'],'net_minor'=>$calc['net_minor'],'tax_minor'=>$calc['tax_minor'],'reporting'=>$calc['reporting'],'fx'=>$calc['fx']===null?null:array_intersect_key($calc['fx'],array_flip(['source','target','rate','spread','observed_at','rounding'])),'provenance'=>'MOCK'];}
        foreach($db->execute('SELECT * FROM cloud_cost_reconciliations WHERE instance_id=? ORDER BY created_at DESC,id LIMIT 50',[$i])->fetchAll() as $r){$e=$store->decrypt($r['evidence_encrypted'],$r['fingerprint'],'cloud-invoice:'.$r['id']);$out['reconciliations'][]=['id'=>$r['id'],'status'=>$r['status'],'reason'=>$e['reason'],'difference_minor'=>$e['difference_minor'],'cost_total_minor'=>$e['cost_total_minor'],'invoice'=>array_intersect_key($e['invoice'],array_flip(['currency','total_minor','starts_at','ends_at','observed_at','final','provenance']))];}
        foreach($db->execute('SELECT * FROM catalog_cloud_cost_links WHERE instance_id=? ORDER BY proposal_id DESC LIMIT 30',[$i])->fetchAll() as $r){$snap=$store->decrypt($r['snapshot_encrypted'],$r['fingerprint'],'cloud-price:'.$r['proposal_id']);$out['proposals'][]=['id'=>$r['proposal_id'],'cost_id'=>$r['cost_id'],'margin'=>$snap['margin'],'forecast_days'=>$snap['forecast_days'],'forecast_cost'=>$snap['forecast_cost'],'forecast_currency'=>$snap['forecast_currency'],'proposed_price'=>$snap['payload'],'review_reason'=>$snap['review_reason'],'auto_publish_allowed'=>false,'approved'=>(bool)$db->execute('SELECT 1 FROM catalog_cloud_cost_approvals WHERE proposal_id=?',[$r['proposal_id']])->fetchColumn(),'published'=>(bool)$db->execute('SELECT 1 FROM catalog_cloud_cost_publications WHERE proposal_id=?',[$r['proposal_id']])->fetchColumn()];}
        return $out;
    }
}
