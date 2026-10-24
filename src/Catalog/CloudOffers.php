<?php
declare(strict_types=1);
namespace Hostider\Catalog;
use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
/** Immutable exact configuration sold by one immutable price. No retrospective entitlements. */
final readonly class CloudOffers
{
    public function __construct(private Database $db){}
    public function publish(Config $config,string $catalog,string $price,array $spec): string
    {
        $this->thirtyDays($price);$mapping=(new \Hostider\Cloud\Catalog($this->db,$config))->find($catalog);(new \Hostider\Cloud\Catalog($this->db,$config))->validate($mapping['policy'],$spec);
        return $this->db->transaction(function()use($mapping,$catalog,$price,$spec): string{
            $p=$this->db->execute('SELECT * FROM catalog_prices WHERE id=?',[$price])->fetch();
            if(!$p||$p['item_id']!==$mapping['product_id']){throw new HttpError(409,'CLOUD_OFFER_PRICE_PRODUCT_MISMATCH');}
            $id=Id::uuid();$this->db->execute('INSERT INTO catalog_cloud_fixed_offers(id,product_id,price_id,catalog_id,spec_json) VALUES (?,?,?,?,?)',[$id,$mapping['product_id'],$price,$catalog,CanonicalJson::encode($spec)]);return $id;
        });
    }
    public function snapshot(string $product,array $sources,array $selection): array
    {
        if(count($sources)!==1||$sources[0]['item_id']!==$product||isset($selection['variant_id'])||!empty($selection['options'])||!empty($selection['addons'])){throw new HttpError(409,'CLOUD_FIXED_OFFER_ONLY');}
        $row=$this->db->execute('SELECT * FROM catalog_cloud_fixed_offers WHERE product_id=? AND price_id=?',[$product,$sources[0]['price_id']])->fetch();
        if(!$row){throw new HttpError(409,'CLOUD_PURCHASE_ENTITLEMENT_REQUIRED');}
        $this->thirtyDays($row['price_id']);
        return ['offer_id'=>$row['id'],'product_id'=>$product,'price_id'=>$row['price_id'],'catalog_id'=>$row['catalog_id'],'spec'=>json_decode($row['spec_json'],true,32,JSON_THROW_ON_ERROR)];
    }
    private function thirtyDays(string $price): void
    {
        $r=$this->db->execute('SELECT c.kind,c.interval_count FROM catalog_prices p JOIN catalog_cycles c ON c.id=p.cycle_id WHERE p.id=?',[$price])->fetch();
        if(!$r||$r['kind']!=='DAYS'||(int)$r['interval_count']!==30){throw new HttpError(409,'CLOUD_30_DAY_PREPAID_REQUIRED');}
    }
    public function clonePrice(string $oldPrice,string $newPrice): void
    {
        $this->db->requireTransaction();$this->thirtyDays($newPrice);$old=$this->db->execute('SELECT * FROM catalog_cloud_fixed_offers WHERE price_id=?',[$oldPrice])->fetch();
        $p=$this->db->execute('SELECT * FROM catalog_prices WHERE id=?',[$newPrice])->fetch();if(!$old||!$p||$p['item_id']!==$old['product_id']){throw new HttpError(409,'CLOUD_PRICE_OFFER_BINDING_REQUIRED');}
        $this->db->execute('INSERT INTO catalog_cloud_fixed_offers(id,product_id,price_id,catalog_id,spec_json) VALUES (?,?,?,?,?)',[Id::uuid(),$old['product_id'],$newPrice,$old['catalog_id'],$old['spec_json']]);
    }
}
