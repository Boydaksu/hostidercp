<?php
declare(strict_types=1);
namespace Hostider\Pricing;
use Hostider\Catalog\{Repository,Promotions};
use Hostider\Core\{Id,Validation};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Finance\{Currency,Decimal,Money,Rounding};

final readonly class QuoteService
{
    public function __construct(private Database $db,private Repository $catalog,private PriceResolver $prices) {}
    /** Pure price planning: no persistence, audit, savepoint, or external I/O. */
    public function plan(string $customer,Currency $currency,array $cart,array $codes,\DateTimeImmutable $now,bool $renewal=false): PriceSnapshot
    {
        if(!array_is_list($cart)||count($cart)<1||count($cart)>50){throw new HttpError(422,'INVALID_CART');}
        $lines=[]; $promotions=[]; $net=new Money('0',$currency); $tax=new Money('0',$currency); $total=new Money('0',$currency);
        foreach($cart as $selection) {
            if(!is_array($selection)){throw new HttpError(422,'INVALID_SELECTION');}
            Validation::fields($selection,['product_id','variant_id','cycle_id','options','addons','quantity'],['product_id','cycle_id','quantity']);
            foreach(['product_id','cycle_id'] as $key){if(!is_string($selection[$key])){throw new HttpError(422,'INVALID_SELECTION_ID');}}
            if(!is_int($selection['quantity'])||$selection['quantity']<1||$selection['quantity']>1000){throw new HttpError(422,'INVALID_QUANTITY');}
            $product=$this->catalog->product($selection['product_id']); $cycle=$this->catalog->find('cycle',$selection['cycle_id']);
            if($product['sale_status']!=='ACTIVE'){throw new HttpError(409,'PRODUCT_NOT_FOR_SALE');}
            $items=$this->selection($product,$selection); $unit=new Decimal('0'); $sources=[];
            foreach($items as $item) {
                $itemCycle=$this->catalog->find('cycle',$item['cycle_id']);
                if($renewal&&$itemCycle['kind']==='ONCE'){if($item['base']){throw new HttpError(409,'ONE_TIME_PRODUCT_NOT_RENEWABLE');}continue;}
                $price=$this->prices->resolve($product,$item['id'],$itemCycle['id'],$currency,$now); $unit=$unit->add($price['amount']);
                $sources[]=['item_id'=>$item['id'],'cycle'=>$itemCycle,'charge_kind'=>$itemCycle['kind']==='ONCE'?'ONCE':'RECURRING','amount'=>(string)$price['amount'],'price_id'=>$price['price_id'],'price_version'=>$price['price_version'],'source_currency'=>$price['source_currency'],'source_amount'=>$price['source_amount'],'fx'=>$price['fx']];
            }
            $amount=$unit->multiply(new Decimal((string)$selection['quantity']));
            $eligible=(new Promotions($this->db))->forQuote($codes,$customer,$product,$renewal,$now);
            $discounted=(new PromotionEngine())->apply($amount,$eligible);
            foreach($discounted['promotions'] as $promotion){$promotions[$promotion['id']]=$promotion;}
            $calculated=(new TaxCalculator())->calculate($discounted['amount'],$currency,$product['tax']);
            $net=$net->add(new Money($calculated['net_minor'],$currency)); $tax=$tax->add(new Money($calculated['tax_minor'],$currency)); $total=$total->add(new Money($calculated['total_minor'],$currency));
            $lines[]=['product_id'=>$product['id'],'name'=>$product['name'],'product_version'=>(string)$product['version'],'quantity'=>$selection['quantity'],'cycle'=>$cycle,'selection'=>$selection,'sources'=>$sources,'original_minor'=>Money::fromDecimal($amount,$currency,Rounding::HalfUp)->minor,'discount_minor'=>Money::fromDecimal($discounted['discount'],$currency,Rounding::HalfUp)->minor,'promotions'=>$discounted['promotions'],'activation_policy'=>$product['activation_policy'],'refund_policy'=>$product['refund_policy']]+$calculated;
            if($product['product_type']==='CLOUD'){$lines[array_key_last($lines)]['cloud_entitlement']=(new \Hostider\Catalog\CloudOffers($this->db))->snapshot($product['id'],$sources,$selection);}
        }
        if(count($promotions)!==count($codes)){throw new HttpError(409,'PROMOTION_NOT_APPLICABLE');}
        if(count($promotions)>1 && in_array(false,array_column($promotions,'combinable'),true)){throw new HttpError(409,'PROMOTION_COMBINATION_FORBIDDEN');}
        return new PriceSnapshot(['customer_id'=>$customer,'currency'=>$currency->code,'net_minor'=>$net->minor,'tax_minor'=>$tax->minor,'total_minor'=>$total->minor,'lines'=>$lines,'promotions'=>array_values($promotions),'renewal'=>$renewal,'rounding'=>'PER_LINE_HALF_UP','created_at'=>$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),'expires_at'=>$now->modify('+15 minutes')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')]);
    }
    private function selection(array $product,array $selection): array
    {
        $base=$product['id'];
        if(isset($selection['variant_id'])) {
            if(!is_string($selection['variant_id'])){throw new HttpError(422,'INVALID_VARIANT');}
            $variant=$this->catalog->find('variant',$selection['variant_id']);
            if($variant['product_id']!==$product['id']){throw new HttpError(422,'VARIANT_PRODUCT_MISMATCH');} $base=$variant['id'];
        }
        $items=[['id'=>$base,'cycle_id'=>$selection['cycle_id'],'base'=>true]]; $groups=[];$seenAddons=[];
        foreach(['options','addons'] as $key) {
            $values=$selection[$key]??[];
            if(!is_array($values)||!array_is_list($values)||count($values)>50||count(array_unique($values,SORT_REGULAR))!==count($values)){throw new HttpError(422,'INVALID_CONFIGURATION');}
            foreach($values as $value) {
                $itemCycle=$selection['cycle_id'];
                if($key==='addons'&&is_array($value)){Validation::fields($value,['id','cycle_id'],['id','cycle_id']);$itemCycle=$value['cycle_id'];$value=$value['id'];}
                if(!is_string($value)||!is_string($itemCycle)){throw new HttpError(422,'INVALID_CONFIGURATION');}
                if($key==='options') {
                    $option=$this->catalog->find('option',$value); $group=$this->catalog->find('group',$option['group_id']);
                    if($group['product_id']!==$product['id']||isset($groups[$group['id']])){throw new HttpError(422,'OPTION_PRODUCT_OR_GROUP_MISMATCH');} $groups[$group['id']]=true;
                } else {
                    if(isset($seenAddons[$value])){throw new HttpError(422,'DUPLICATE_ADDON');}$seenAddons[$value]=true;
                    $this->catalog->find('addon',$value);
                    if(!$this->db->execute('SELECT 1 FROM catalog_product_addons WHERE product_id=? AND addon_id=?',[$product['id'],$value])->fetchColumn()){throw new HttpError(422,'ADDON_PRODUCT_MISMATCH');}
                }
                $items[]=['id'=>$value,'cycle_id'=>$itemCycle,'base'=>false];
            }
        }
        foreach($this->db->execute('SELECT id FROM catalog_option_groups WHERE product_id=? AND required=TRUE',[$product['id']])->fetchAll() as $group) { if(!isset($groups[$group['id']])){throw new HttpError(422,'REQUIRED_OPTION_MISSING');} }
        return $items;
    }
    public function issue(PriceSnapshot $snapshot): array
    {
        $this->db->requireTransaction(); $id=Id::uuid(); $data=$snapshot->jsonSerialize();
        $this->db->execute('INSERT INTO pricing_quotes(id,customer_id,currency,total_minor,snapshot,fingerprint,created_at,expires_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$data['customer_id'],$data['currency'],$data['total_minor'],json_encode($data,JSON_THROW_ON_ERROR),$snapshot->fingerprint(),$data['created_at'],$data['expires_at']]); return ['id'=>$id]+$data;
    }
    public function get(string $id,string $customer,\DateTimeImmutable $now,bool $lock=false): array
    {
        if($lock){$this->db->requireTransaction();}
        $row=$this->db->execute('SELECT * FROM pricing_quotes WHERE id=? AND customer_id=?'.($lock?' FOR UPDATE':''),[$id,$customer])->fetch();
        if(!$row){throw new HttpError(404,'QUOTE_NOT_FOUND');}
        if(new \DateTimeImmutable($row['expires_at'],new \DateTimeZone('UTC'))<=$now){throw new HttpError(409,'QUOTE_EXPIRED_REPRICE_REQUIRED');}
        $data=json_decode($row['snapshot'],true,64,JSON_THROW_ON_ERROR);
        if(!hash_equals($row['fingerprint'],(new PriceSnapshot($data))->fingerprint())){throw new HttpError(409,'QUOTE_INTEGRITY_FAILURE');}
        return ['id'=>$row['id']]+$data;
    }
}
