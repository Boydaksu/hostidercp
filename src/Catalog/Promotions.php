<?php
declare(strict_types=1);
namespace Hostider\Catalog;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
final readonly class Promotions
{
    public function __construct(private Database $db) {}
    public function forQuote(array $codes,string $customer,array $product,bool $renewal,\DateTimeImmutable $now): array
    {
        if(count($codes)>10 || count(array_unique($codes))!==count($codes)){throw new HttpError(422,'INVALID_PROMOTION_SELECTION');}
        sort($codes,SORT_STRING); $eligible=[];
        foreach($codes as $code) {
            if(!is_string($code)){throw new HttpError(422,'INVALID_PROMOTION_CODE');}
            $row=$this->db->execute('SELECT * FROM catalog_promotions WHERE code=?',[$code])->fetch();
            if(!$row){throw new HttpError(409,'PROMOTION_NOT_FOUND');}
            if($row['product_id']!==null && $row['product_id']!==$product['id'] || $row['category_id']!==null && $row['category_id']!==$product['category_id']){continue;}
            $this->validate($row,$customer,$renewal,$now,false); $eligible[]=$row;
        }
        return $eligible;
    }
    private function validate(array $row,string $customer,bool $renewal,\DateTimeImmutable $now,bool $lock): void
    {
        $date=$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        if($row['starts_at']>$date || $row['ends_at']<=$date || $row['customer_id']!==null && $row['customer_id']!==$customer
            || $row['period_scope']==='FIRST' && $renewal || $row['period_scope']==='RECURRING' && !$renewal){throw new HttpError(409,'PROMOTION_NOT_ELIGIBLE');}
        $suffix=$lock?' FOR UPDATE':'';
        $used=(int)$this->db->execute('SELECT COUNT(*) FROM catalog_redemptions WHERE promotion_id=?'.$suffix,[$row['id']])->fetchColumn();
        $own=(int)$this->db->execute('SELECT COUNT(*) FROM catalog_redemptions WHERE promotion_id=? AND customer_id=?'.$suffix,[$row['id'],$customer])->fetchColumn();
        if($used>=(int)$row['max_uses'] || $own>=(int)$row['per_customer_limit']){throw new HttpError(409,'PROMOTION_LIMIT_REACHED');}
    }
    public function redeem(array $snapshots,string $order,string $customer,bool $renewal,\DateTimeImmutable $now): void
    {
        $this->db->requireTransaction(); usort($snapshots,fn(array $a,array $b): int=>strcmp($a['id'],$b['id']));
        foreach($snapshots as $snapshot) {
            $row=$this->db->execute('SELECT * FROM catalog_promotions WHERE id=? FOR UPDATE',[$snapshot['id']])->fetch();
            if(!$row || (string)$row['version']!==$snapshot['version']){throw new HttpError(409,'PROMOTION_CHANGED');}
            if($this->db->execute('SELECT 1 FROM catalog_redemptions WHERE promotion_id=? AND order_id=? FOR UPDATE',[$row['id'],$order])->fetchColumn()){continue;}
            $this->validate($row,$customer,$renewal,$now,true);
            $this->db->execute('INSERT INTO catalog_redemptions(promotion_id,order_id,customer_id) VALUES (?,?,?)',[$row['id'],$order,$customer]);
        }
    }
}
