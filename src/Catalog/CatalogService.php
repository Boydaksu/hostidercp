<?php
declare(strict_types=1);
namespace Hostider\Catalog;
use Hostider\Core\{Id,Validation};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Core\Security\{Audit,AuditEvent,Principal};
use Hostider\Finance\{Currency,Decimal,Rounding};

final readonly class CatalogService
{
    public Repository $repository;
    public function __construct(private Database $db) { $this->repository=new Repository($db); }
    private function text(array $data,string $key,int $limit=120): string
    {
        if(!is_string($data[$key]??null)){throw new HttpError(422,'INVALID_CATALOG_FIELD');}
        return Validation::text($data[$key],$limit);
    }
    private function choice(array $data,string $key,array $choices): string
    {
        $value=$this->text($data,$key); if(!in_array($value,$choices,true)){throw new HttpError(422,'INVALID_CATALOG_CHOICE');} return $value;
    }
    private function integer(array $data,string $key,int $min,int $max): int
    {
        $value=$data[$key]??null; if(!is_int($value)||$value<$min||$value>$max){throw new HttpError(422,'INVALID_CATALOG_INTEGER');} return $value;
    }
    private function flag(array $data,string $key): int
    {
        if(!is_bool($data[$key]??null)){throw new HttpError(422,'INVALID_CATALOG_FLAG');} return $data[$key]?1:0;
    }
    private function decimal(array $data,string $key,int $scale=10): string
    {
        $value=new Decimal($this->text($data,$key,64));
        if($value->compare(new Decimal('0'))<0 || $value->compare(new Decimal($scale===10?'99999999999999.9999999999':'9999.999999999999'))>0
            || $value->compare(new Decimal($value->quantize($scale,Rounding::TowardZero)))!==0){throw new HttpError(422,'INVALID_CATALOG_DECIMAL');}
        return (string)$value;
    }
    private function optionalId(array $data,string $key,?string $kind=null): ?string
    {
        if(($data[$key]??null)===null){return null;}
        $id=$this->text($data,$key,36);
        if(!preg_match('/^[a-f0-9-]{36}$/D',$id)){throw new HttpError(422,'INVALID_CATALOG_ID');}
        if($kind!==null){$this->repository->find($kind,$id,true);} return $id;
    }
    private function item(string $id,string $kind,string $name): void { $this->repository->insert('item',['id'=>$id,'kind'=>$kind,'name'=>$name]); }
    /** All fields are administrative proposals; HTTP authorization and explicit confirmation are separate. */
    public function execute(string $action,array $data,Principal $actor,string $trace): array
    {
        $this->db->requireTransaction();
        if($actor->impersonating){throw new HttpError(403,'CATALOG_IMPERSONATION_FORBIDDEN');}
        $id=Id::uuid();
        switch($action) {
            case 'tax-create':
                Validation::fields($data,['name','rate','inclusive'],['name','rate','inclusive']); $rate=$this->decimal($data,'rate',12);
                if((new Decimal($rate))->compare(new Decimal('1'))>0){throw new HttpError(422,'INVALID_TAX_RATE');}
                $this->repository->insert('tax',['id'=>$id,'name'=>$this->text($data,'name'),'rate'=>$rate,'inclusive'=>$this->flag($data,'inclusive')]); break;
            case 'category-create':
                Validation::fields($data,['name','tax_id'],['name']);
                $this->repository->insert('category',['id'=>$id,'name'=>$this->text($data,'name'),'tax_id'=>$this->optionalId($data,'tax_id','tax')]); break;
            case 'category-update':
                Validation::fields($data,['id','name','tax_id','version'],['id','name','version']);$id=$this->text($data,'id',36);$this->repository->find('category',$id,true);$tax=$this->optionalId($data,'tax_id','tax');$name=$this->text($data,'name');$version=$this->integer($data,'version',1,PHP_INT_MAX);
                if($this->db->execute('UPDATE catalog_categories SET name=?,tax_id=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?',[$name,$tax,$id,$version])->rowCount()!==1){throw new HttpError(409,'CATALOG_VERSION_CONFLICT');}break;
            case 'cycle-create':
                Validation::fields($data,['name','kind','interval_count'],['name','kind','interval_count']);
                $kind=$this->choice($data,'kind',['ONCE','DAYS','MONTHS']);
                $this->repository->insert('cycle',['id'=>$id,'name'=>$this->text($data,'name'),'kind'=>$kind,'interval_count'=>$this->integer($data,'interval_count',$kind==='ONCE'?0:1,$kind==='ONCE'?0:3660)]); break;
            case 'product-create':
                Validation::fields($data,['name','category_id','product_type','pricing_strategy','source_currency','tax_id','activation_policy','refund_policy'],['name','category_id','product_type','pricing_strategy','source_currency','activation_policy','refund_policy']);
                $category=$this->text($data,'category_id',36); $this->repository->find('category',$category,true); $currency=new Currency($this->text($data,'source_currency',3));
                $values=['id'=>$id,'category_id'=>$category,'product_type'=>$this->choice($data,'product_type',['HOSTING','DOMAIN','CLOUD']),'pricing_strategy'=>$this->choice($data,'pricing_strategy',['SOURCE_FX','FIXED_PER_CURRENCY','FIXED_THEN_FX']),'source_currency'=>$currency->code,'tax_id'=>$this->optionalId($data,'tax_id','tax'),'activation_policy'=>$this->choice($data,'activation_policy',['AUTO','MANUAL']),'refund_policy'=>$this->choice($data,'refund_policy',['none','prorated','staff_approval'])];
                $this->item($id,'PRODUCT',$this->text($data,'name')); $this->repository->insert('product',$values); break;
            case 'variant-create':
                Validation::fields($data,['name','product_id'],['name','product_id']); $product=$this->text($data,'product_id',36); $this->repository->find('product',$product,true);
                $this->item($id,'VARIANT',$this->text($data,'name')); $this->repository->insert('variant',['id'=>$id,'product_id'=>$product]); break;
            case 'item-rename':
                Validation::fields($data,['id','name','version'],['id','name','version']);$id=$this->text($data,'id',36);$this->repository->find('item',$id,true);if($this->db->execute('UPDATE catalog_items SET name=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?',[$this->text($data,'name'),$id,$this->integer($data,'version',1,PHP_INT_MAX)])->rowCount()!==1){throw new HttpError(409,'CATALOG_VERSION_CONFLICT');}break;
            case 'product-update':
                Validation::fields($data,['id','name','category_id','pricing_strategy','source_currency','tax_id','activation_policy','refund_policy','version'],['id','name','category_id','pricing_strategy','source_currency','activation_policy','refund_policy','version']);$id=$this->text($data,'id',36);$this->repository->find('product',$id,true);$this->repository->find('item',$id,true);$category=$this->text($data,'category_id',36);$this->repository->find('category',$category,true);$tax=$this->optionalId($data,'tax_id','tax');$currency=new Currency($this->text($data,'source_currency',3));$strategy=$this->choice($data,'pricing_strategy',['SOURCE_FX','FIXED_PER_CURRENCY','FIXED_THEN_FX']);$activation=$this->choice($data,'activation_policy',['AUTO','MANUAL']);$refund=$this->choice($data,'refund_policy',['none','prorated','staff_approval']);
                if($this->db->execute('UPDATE catalog_items SET name=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?',[$this->text($data,'name'),$id,$this->integer($data,'version',1,PHP_INT_MAX)])->rowCount()!==1){throw new HttpError(409,'CATALOG_VERSION_CONFLICT');}$this->db->execute('UPDATE catalog_products SET category_id=?,tax_id=?,pricing_strategy=?,source_currency=?,activation_policy=?,refund_policy=? WHERE id=?',[$category,$tax,$strategy,$currency->code,$activation,$refund,$id]);break;
            case 'group-create':
                Validation::fields($data,['name','product_id','required'],['name','product_id','required']); $product=$this->text($data,'product_id',36); $this->repository->find('product',$product,true);
                $this->repository->insert('group',['id'=>$id,'product_id'=>$product,'name'=>$this->text($data,'name'),'required'=>$this->flag($data,'required')]); break;
            case 'option-create':
                Validation::fields($data,['name','group_id'],['name','group_id']); $group=$this->text($data,'group_id',36); $this->repository->find('group',$group,true);
                $this->item($id,'OPTION',$this->text($data,'name')); $this->repository->insert('option',['id'=>$id,'group_id'=>$group]); break;
            case 'addon-create':
                Validation::fields($data,['name','standalone'],['name','standalone']); $name=$this->text($data,'name'); $standalone=$this->flag($data,'standalone');
                $this->item($id,'ADDON',$name); $this->repository->insert('addon',['id'=>$id,'standalone'=>$standalone]); break;
            case 'addon-link':
                Validation::fields($data,['product_id','addon_id'],['product_id','addon_id']); $product=$this->text($data,'product_id',36); $addon=$this->text($data,'addon_id',36);
                $this->repository->find('product',$product,true); $this->repository->find('addon',$addon,true);
                $this->db->execute('INSERT INTO catalog_product_addons(product_id,addon_id) VALUES (?,?)',[$product,$addon]); $id=$product; break;
            case 'addon-policy':
                Validation::fields($data,['addon_id','category_id','tax_id','pricing_strategy','source_currency','activation_policy','refund_policy','sale_status','version'],['addon_id','category_id','pricing_strategy','source_currency','activation_policy','refund_policy','sale_status','version']);
                $id=$this->text($data,'addon_id',36);$addon=$this->repository->find('addon',$id,true);$this->repository->find('item',$id,true);if(!(bool)$addon['standalone']){throw new HttpError(409,'ADDON_NOT_STANDALONE');}$category=$this->text($data,'category_id',36);$this->repository->find('category',$category,true);$tax=$this->optionalId($data,'tax_id','tax');$currency=new Currency($this->text($data,'source_currency',3));$strategy=$this->choice($data,'pricing_strategy',['SOURCE_FX','FIXED_PER_CURRENCY','FIXED_THEN_FX']);$activation=$this->choice($data,'activation_policy',['AUTO','MANUAL']);$refund=$this->choice($data,'refund_policy',['none','prorated','staff_approval']);$status=$this->choice($data,'sale_status',['DRAFT','ACTIVE','CLOSED']);$version=$this->integer($data,'version',1,PHP_INT_MAX);
                if($status==='ACTIVE'&&!$this->db->execute('SELECT 1 FROM catalog_prices WHERE item_id=? LIMIT 1',[$id])->fetchColumn()){throw new HttpError(409,'PUBLISHED_PRICE_REQUIRED');}
                if($this->db->execute('UPDATE catalog_items SET version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?',[$id,$version])->rowCount()!==1){throw new HttpError(409,'CATALOG_VERSION_CONFLICT');}
                $this->db->execute('INSERT INTO catalog_addon_policies(addon_id,category_id,tax_id,pricing_strategy,source_currency,activation_policy,refund_policy,sale_status) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id),tax_id=VALUES(tax_id),pricing_strategy=VALUES(pricing_strategy),source_currency=VALUES(source_currency),activation_policy=VALUES(activation_policy),refund_policy=VALUES(refund_policy),sale_status=VALUES(sale_status)',[$id,$category,$tax,$strategy,$currency->code,$activation,$refund,$status]);break;
            case 'product-status':
                Validation::fields($data,['id','version','sale_status'],['id','version','sale_status']); $id=$this->text($data,'id',36); $this->repository->find('product',$id,true); $this->repository->find('item',$id,true);
                $status=$this->choice($data,'sale_status',['DRAFT','ACTIVE','CLOSED']); $version=$this->integer($data,'version',1,PHP_INT_MAX);
                if($status==='ACTIVE' && !$this->db->execute('SELECT 1 FROM catalog_prices p LEFT JOIN catalog_variants v ON v.id=p.item_id WHERE p.item_id=? OR v.product_id=? LIMIT 1',[$id,$id])->fetchColumn()){throw new HttpError(409,'PUBLISHED_PRICE_REQUIRED');}
                if($this->db->execute('UPDATE catalog_items SET version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?',[$id,$version])->rowCount()!==1){throw new HttpError(409,'CATALOG_VERSION_CONFLICT');}
                $this->db->execute('UPDATE catalog_products SET sale_status=? WHERE id=?',[$status,$id]); break;
            case 'price-publish':
                Validation::fields($data,['item_id','cycle_id','currency','amount'],['item_id','cycle_id','currency','amount']);
                $item=$this->text($data,'item_id',36); $cycle=$this->text($data,'cycle_id',36); $this->repository->find('item',$item,true); $this->repository->find('cycle',$cycle,true);
                $currency=new Currency($this->text($data,'currency',3)); $amount=$this->decimal($data,'amount');
                $version=(int)($this->repository->price($item,$cycle,$currency->code)['version']??0)+1;
                $this->db->execute('INSERT INTO catalog_prices(id,item_id,cycle_id,currency,amount,version,published_by) VALUES (?,?,?,?,?,?,?)',[$id,$item,$cycle,$currency->code,$amount,$version,$actor->actorId]); break;
            case 'cost-propose':
                Validation::fields($data,['item_id','cost_currency','cost_amount','proposed_sale_amount','proposed_sale_currency','source_reference'],['item_id','cost_currency','cost_amount','source_reference']);
                $item=$this->text($data,'item_id',36); $this->repository->find('item',$item,true); $currency=new Currency($this->text($data,'cost_currency',3));
                $this->db->execute('INSERT INTO catalog_cost_proposals(id,item_id,cost_currency,cost_amount,proposed_sale_amount,source_reference,proposed_sale_currency) VALUES (?,?,?,?,?,?,?)',[$id,$item,$currency->code,$this->decimal($data,'cost_amount'),isset($data['proposed_sale_amount'])?$this->decimal($data,'proposed_sale_amount'):null,$this->text($data,'source_reference',128),isset($data['proposed_sale_amount'])?(new Currency($this->text($data,'proposed_sale_currency',3)))->code:null]); break;
            case 'provider-map':
                Validation::fields($data,['item_id','provider','plan_reference'],['item_id','provider','plan_reference']); $item=$this->text($data,'item_id',36); $this->repository->find('item',$item,true);
                $this->db->execute('INSERT INTO catalog_provider_mappings(id,item_id,provider,plan_reference) VALUES (?,?,?,?)',[$id,$item,$this->choice($data,'provider',['cpanel','voyar','resellerclub','domainnameapi']),$this->text($data,'plan_reference',128)]); break;
            case 'promotion-create':
                Validation::fields($data,['code','rate','period_scope','combinable','max_uses','per_customer_limit','starts_at','ends_at','product_id','category_id','customer_id','item_id'],['code','rate','period_scope','combinable','max_uses','per_customer_limit','starts_at','ends_at']);
                $code=$this->text($data,'code',64); if(!preg_match('/^[A-Z0-9_-]{1,64}$/D',$code)){throw new HttpError(422,'INVALID_PROMOTION_CODE');}
                $rate=$this->decimal($data,'rate',12); if((new Decimal($rate))->compare(new Decimal('0'))<=0 || (new Decimal($rate))->compare(new Decimal('1'))>0){throw new HttpError(422,'INVALID_PROMOTION_RATE');}
                $start=$this->date($data,'starts_at'); $end=$this->date($data,'ends_at'); if($end<=$start){throw new HttpError(422,'INVALID_PROMOTION_PERIOD');}
                $this->repository->insert('promotion',['id'=>$id,'code'=>$code,'rate'=>$rate,'period_scope'=>$this->choice($data,'period_scope',['FIRST','RECURRING','ALL']),'combinable'=>$this->flag($data,'combinable'),'max_uses'=>$this->integer($data,'max_uses',1,1000000000),'per_customer_limit'=>$this->integer($data,'per_customer_limit',1,1000000000),'starts_at'=>$start,'ends_at'=>$end,'product_id'=>$this->optionalId($data,'product_id','product'),'category_id'=>$this->optionalId($data,'category_id','category'),'customer_id'=>$this->optionalId($data,'customer_id'),'item_id'=>$this->optionalId($data,'item_id','item')]); break;
            default: throw new HttpError(422,'UNKNOWN_CATALOG_COMMAND');
        }
        (new Audit($this->db))->record($actor,AuditEvent::CatalogChanged,$id,$trace);
        (new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'catalog.changed',$id,['entity_id'=>$id,'action'=>$action],$trace);
        return ['id'=>$id];
    }
    private function date(array $data,string $key): string
    {
        $value=$this->text($data,$key,26); $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new \DateTimeZone('UTC'));
        if(!$date || $date->format('Y-m-d H:i:s')!==$value){throw new HttpError(422,'INVALID_CATALOG_DATE');} return $value;
    }
}
