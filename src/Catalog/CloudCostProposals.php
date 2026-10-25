<?php
declare(strict_types=1);
namespace Hostider\Catalog;
use Hostider\Core\{Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;
use Hostider\CloudCosts\{Store,Engine,LocalTransactions};use Hostider\Finance\{Currency,Decimal,Money,Rounding};use Hostider\Identity\IdentityService;use Hostider\Commerce\{Services,Commands,Confirmations};
/** FIN-010 uses existing catalog_cost_proposals and existing Commerce publication confirmations. */
final readonly class CloudCostProposals
{
    private Store $store;
    public function __construct(private Database $db,private Config $config){$this->store=new Store($db,$config);}
    public function suggest(string $costId,string $margin): ?string
    {
        $m=new Decimal($margin);$margin=(string)$m;if($m->compare(new Decimal('0'))<0||$m->compare(new Decimal('0.9'))>0){throw new HttpError(422,'CLOUD_MARGIN_INVALID');}
        return LocalTransactions::run($this->db,function()use($costId,$margin,$m): ?string{
            $work=$this->db->execute('SELECT * FROM cloud_cost_work WHERE id=? FOR UPDATE',[$costId])->fetch();if(!$work||$work['status']!=='COSTED_MOCK'){throw new HttpError(409,'CLOUD_COST_UNVERIFIED');}
            $c=(new Engine($this->db,$this->config))->cost($costId);$i=$c['instance'];$terms=(new \Hostider\Services\CloudServices($this->db,$this->config))->costTerms($i['service_id']);
            $mapping=$this->db->execute('SELECT product_id FROM cloud_catalog WHERE id=?',[$i['catalog_id']])->fetch();$product=(new Repository($this->db))->product($mapping['product_id']);
            $price=(new Repository($this->db))->price($mapping['product_id'],$terms['cycle']['id'],$product['source_currency']);if(!$price){throw new HttpError(409,'CLOUD_BASE_PRICE_REQUIRED');}
            $old=$this->db->execute('SELECT * FROM catalog_cloud_cost_links WHERE cost_id=? AND base_price_id=?',[$costId,$price['id']])->fetch();
            if($old){$snapshot=$this->store->decrypt($old['snapshot_encrypted'],$old['fingerprint'],'cloud-price:'.$old['proposal_id']);if($snapshot['margin']!==$margin){throw new HttpError(409,'CLOUD_PROPOSAL_REFERENCE_CONFLICT');}return $old['proposal_id'];}
            $calc=$c['calculation'];$report=$calc['usage_reporting'];$currency=new Currency($report['currency']);
            // An explicit synthetic 30-day forecast, not a customer charge or a supplier invoice.
            $forecast=(new Money($report['minor'],$currency))->decimal()->multiply(new Decimal('2592000'))->divide(new Decimal((string)$calc['seconds']),10,Rounding::HalfUp);
            $same=$currency->code===$price['currency'];$recurring=$calc['fee_minor']==='0';$target=$same&&$recurring?$forecast->divide((new Decimal('1'))->subtract($m),$currency->exponent,Rounding::HalfUp):null;
            if($target!==null&&$target->compare(new Decimal($price['amount']))<=0){return null;}
            $payload=['item_id'=>$mapping['product_id'],'cost_currency'=>$currency->code,'cost_amount'=>(string)$forecast,'source_reference'=>'cloud-mock-cost:'.$costId];
            if($target!==null){$payload+=['proposed_sale_amount'=>(string)$target,'proposed_sale_currency'=>$currency->code];}
            $id=(new CatalogService($this->db))->execute('cost-propose',$payload,new \Hostider\Core\Security\Principal('cloud-price-worker',[]),Id::uuid())['id'];
            $snapshot=['cost_id'=>$costId,'instance_id'=>$i['id'],'base_price'=>$price,'margin'=>$margin,'forecast_days'=>30,'forecast_cost'=>(string)$forecast,'forecast_currency'=>$currency->code,'payload'=>$target===null?null:['item_id'=>$price['item_id'],'cycle_id'=>$price['cycle_id'],'currency'=>$price['currency'],'amount'=>(string)$target],'provenance'=>'MOCK','auto_publish_allowed'=>false,'review_reason'=>!$same?'FX_REQUIRED':(!$recurring?'ONE_OFF_FEES_REQUIRE_REVIEW':'MOCK_COST_MANUAL_REVIEW')];
            $this->db->execute("INSERT INTO catalog_cloud_cost_links(proposal_id,cost_id,instance_id,base_price_id,snapshot_encrypted,fingerprint,auto_publish_allowed,provenance) VALUES (?,?,?,?,?,?,FALSE,'MOCK')",[$id,$costId,$i['id'],$price['id'],$this->store->encrypt($snapshot,'cloud-price:'.$id),$this->store->hash($snapshot)]);return $id;
        });
    }
    private function read(string $id): array
    {
        $r=$this->db->execute('SELECT * FROM catalog_cloud_cost_links WHERE proposal_id=? FOR UPDATE',[$id])->fetch();if(!$r){throw new HttpError(404,'CLOUD_PROPOSAL_NOT_FOUND');}$r['snapshot']=$this->store->decrypt($r['snapshot_encrypted'],$r['fingerprint'],'cloud-price:'.$id);return $r;
    }
    private function ready(array $r): array
    {
        $snapshot=$r['snapshot'];if($snapshot['payload']===null||$r['auto_publish_allowed']){throw new HttpError(409,'CLOUD_PROPOSAL_REVIEW_REQUIRED');}
        $p=$snapshot['base_price'];$latest=(new Repository($this->db))->price($p['item_id'],$p['cycle_id'],$p['currency']);if(!$latest||$latest['id']!==$p['id']){throw new HttpError(409,'CLOUD_PROPOSAL_BASE_PRICE_CHANGED');}
        $c=(new Engine($this->db,$this->config))->cost($r['cost_id']);$seen=Store::date($c['calculation']['source']['observed_at']);$now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
        if($seen>$now||$seen->getTimestamp()<$now->getTimestamp()-$c['calculation']['policy']['max_age_seconds']){throw new HttpError(409,'CLOUD_PROPOSAL_STALE_EVIDENCE');}
        return $snapshot['payload'];
    }
    private function staff(string $reference,\Closure $work): mixed
    {
        $identity=new IdentityService($this->db,$this->config);
        return $identity->withStepUp($reference,'admin',function(array $u,array $s)use($identity,$work): mixed{
            $a=$identity->authorize($u,$s,'finance.approve',privileged:true);$identity->authorize($u,$s,'pricing.manage',privileged:true);
            if(!$a->staff||$a->impersonating){throw new HttpError(403,'CLOUD_PROPOSAL_STAFF_REQUIRED');}return $work($u,$s,$identity);
        });
    }
    public function approve(string $id,string $session,bool $accepted): void
    {
        if(!$accepted){throw new HttpError(403,'EXPLICIT_MOCK_APPROVAL_REQUIRED');}
        $this->staff($session,function(array $u,array $s,IdentityService $identity)use($id): void{
            $r=$this->read($id);$this->ready($r);$old=$this->db->execute('SELECT * FROM catalog_cloud_cost_approvals WHERE proposal_id=?',[$id])->fetch();
            if($old){if($old['actor_id']!==$u['id']||$old['session_id']!==$s['id']||$old['identity_version']!==$u['identity_version']){throw new HttpError(409,'CLOUD_APPROVAL_CONFLICT');}return;}
            $this->db->execute('INSERT INTO catalog_cloud_cost_approvals(proposal_id,actor_id,session_id,identity_version,fingerprint) VALUES (?,?,?,?,?)',[$id,$u['id'],$s['id'],$u['identity_version'],$r['fingerprint']]);$identity->confirmationEvent($u['id'],$id,true);
        });
    }
    private function approved(array $r,array $u,array $s): void
    {
        $a=$this->db->execute('SELECT * FROM catalog_cloud_cost_approvals WHERE proposal_id=?',[$r['proposal_id']])->fetch();
        if(!$a||$a['actor_id']!==$u['id']||$a['session_id']!==$s['id']||$a['identity_version']!==$u['identity_version']||!hash_equals($a['fingerprint'],$r['fingerprint'])){throw new HttpError(403,'CLOUD_APPROVAL_REQUIRED');}
        if($this->db->execute('SELECT 1 FROM catalog_cloud_cost_publications WHERE proposal_id=?',[$r['proposal_id']])->fetchColumn()){throw new HttpError(409,'CLOUD_PROPOSAL_ALREADY_PUBLISHED');}
    }
    public function preparePublish(string $id,string $session): array
    {
        return $this->staff($session,function(array $u,array $s)use($id): array{
            $r=$this->read($id);$this->approved($r,$u,$s);$payload=$this->ready($r);$commerce=new Services($this->db,$this->config);
            return (new Confirmations($commerce,new Commands($commerce)))->prepare($u,$s,'price-publish',$payload,null);
        });
    }
    public function publish(string $id,string $session,array $confirmation): string
    {
        return $this->staff($session,function(array $u,array $s)use($id,$confirmation): string{
            $r=$this->read($id);$this->approved($r,$u,$s);$payload=$this->ready($r);$commerce=new Services($this->db,$this->config);
            $price=(new Confirmations($commerce,new Commands($commerce)))->execute($u,$s,'price-publish',$payload,null,$confirmation,Id::uuid())['id'];
            (new CloudOffers($this->db))->clonePrice($r['base_price_id'],$price);
            $this->db->execute('INSERT INTO catalog_cloud_cost_publications(proposal_id,price_id,actor_id,confirmation_id) VALUES (?,?,?,?)',[$id,$price,$u['id'],$confirmation['id']]);return $price;
        });
    }
    public function run(int $maximum=100,int $seconds=20,?string $margin=null): array
    {
        if($margin===null){throw new HttpError(409,'CLOUD_EXPLICIT_MOCK_MARGIN_REQUIRED');}$m=new Decimal($margin);if($m->compare(new Decimal('0'))<0||$m->compare(new Decimal('0.9'))>0){throw new HttpError(422,'CLOUD_MARGIN_INVALID');}
        if($maximum<1||$maximum>100||$seconds<1||$seconds>20){throw new \InvalidArgumentException('Invalid proposal worker budget');}$start=hrtime(true);$n=0;$review=0;$before=$this->db->execute('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
        while($n<$maximum&&hrtime(true)-$start<$seconds*1_000_000_000&&memory_get_usage(true)<64*1024*1024){
            $id=LocalTransactions::run($this->db,function()use($before): ?string{$w=$this->db->execute("SELECT id FROM cloud_cost_work FORCE INDEX(ix_cloud_cost_proposal_admission) WHERE status='COSTED_MOCK' AND (proposal_checked_at IS NULL OR proposal_checked_at<?) ORDER BY proposal_checked_at,created_at,id LIMIT 1 FOR UPDATE SKIP LOCKED",[$before])->fetchColumn();if(!$w){return null;}$this->db->execute('UPDATE cloud_cost_work SET proposal_checked_at=UTC_TIMESTAMP(6) WHERE id=?',[$w]);return $w;});
            if($id===null){break;}try{$this->suggest($id,$margin);}catch(HttpError){++$review;}++$n;
        }return ['checked'=>$n,'review'=>$review,'budget_seconds'=>$seconds,'db_retry_counts'=>LocalTransactions::counters()];
    }
}
