<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\Config;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
/** Trusted Cloud application gateway; other modules never write Services tables. */
final readonly class CloudServices
{
    public function __construct(private Database $db,private Config $config){if($config->environment!=='test'){throw new HttpError(403,'F4_TEST_ONLY');}}
    public function source(string $service,string $customer,array $states=['QUEUED']): array{return (new RegistrationEligibility($this->db,$this->config))->lock($service,$customer,'CLOUD',$states);}
    public function purchase(string $id,string $customer,array $states=['QUEUED']): array
    {
        $s=$this->source($id,$customer,$states);$item=$this->db->execute('SELECT * FROM order_items WHERE id=?',[$s['order_item_id']])->fetch();$line=json_decode($item['snapshot'],true,64,JSON_THROW_ON_ERROR);
        $period=$this->db->execute('SELECT * FROM svc_periods WHERE service_id=? AND generation=1 FOR UPDATE',[$id])->fetch();
        $price=$period?$this->db->execute('SELECT * FROM svc_prices WHERE id=?',[$period['price_id']])->fetch():false;
        $order=$this->db->execute('SELECT * FROM orders WHERE id=?',[$s['order_id']])->fetch();
        $bill=$this->db->execute('SELECT * FROM billing_requests WHERE id=?',[$order['payment_request_id']])->fetch();
        $offer=$line['cloud_entitlement']??null;
        if(!$offer||!$period||!$price||$period['billing_request_id']!==$bill['id']||$price['service_id']!==$id||$price['customer_id']!==$customer||$s['current_price_id']!==$price['id']||$price['currency']!==$bill['currency']||\Hostider\Core\CanonicalJson::fromJson($price['snapshot'])!==\Hostider\Core\CanonicalJson::encode($line)){throw new HttpError(409,'CLOUD_PURCHASE_BINDING_INVALID');}
        $canonical=(new \Hostider\Catalog\CloudOffers($this->db))->snapshot($s['product_id'],$line['sources'],$line['selection']);
        if(\Hostider\Core\CanonicalJson::encode($canonical)!==\Hostider\Core\CanonicalJson::encode($offer)){throw new HttpError(409,'CLOUD_PURCHASE_OFFER_MISMATCH');}
        $billSnapshot=json_decode($bill['snapshot'],true,64,JSON_THROW_ON_ERROR);
        $quantity=(int)$line['quantity'];$unit=(int)$s['unit_no'];
        foreach(['net_minor','tax_minor'] as $field){[$part,$remainder]=\Hostider\Finance\Unsigned::divide((string)$line[$field],(string)$quantity);if($unit<=(int)$remainder){$part=\Hostider\Finance\Unsigned::add($part,'1');}if($part!==(string)$price[$field]){throw new HttpError(409,'CLOUD_UNIT_PRICE_MISMATCH');}}
        if((string)$price['total_minor']!==\Hostider\Finance\Unsigned::add((string)$price['net_minor'],(string)$price['tax_minor'])||(string)$billSnapshot['total_minor']!==(string)$bill['total_minor']){throw new HttpError(409,'CLOUD_PRICE_TOTAL_MISMATCH');}
        if(!in_array(\Hostider\Core\CanonicalJson::encode($line),array_map(\Hostider\Core\CanonicalJson::encode(...),$billSnapshot['lines']),true)){throw new HttpError(409,'CLOUD_PURCHASE_BILL_MISMATCH');}
        return ['service_id'=>$id,'customer_id'=>$customer,'order_id'=>$s['order_id'],'order_item_id'=>$s['order_item_id'],'unit_no'=>(int)$s['unit_no'],'offer'=>$offer,'line'=>$line,'price_id'=>$price['id'],'price_currency'=>$price['currency'],'net_minor'=>(string)$price['net_minor'],'tax_minor'=>(string)$price['tax_minor'],'total_minor'=>(string)$price['total_minor'],'billing_request_id'=>$bill['id'],'bill_snapshot'=>$billSnapshot];
    }
    public function status(string $id): string{return $this->db->execute('SELECT status FROM svc_services WHERE id=?',[$id])->fetchColumn();}
    public function financialContext(string $id): array
    {
        return $this->db->execute('SELECT v.status service_status,o.id order_id,o.status order_status,o.refund_status order_refund,b.request_id,b.status payment_status,b.refund_status payment_refund FROM svc_services v LEFT JOIN orders o ON o.id=v.order_id LEFT JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE v.id=?',[$id])->fetch();
    }
    public function state(string $service,string $status,?string $operation=null): void
    {
        $this->db->requireTransaction();$this->db->execute('UPDATE svc_services SET status=?,current_operation_id=COALESCE(?,current_operation_id),version=version+1 WHERE id=?',[$status,$operation,$service]);
    }
    public function imported(string $service,string $customer): array
    {
        $row=(new Repository($this->db,$this->config))->find($service,$customer);$type=$this->db->execute('SELECT product_type FROM catalog_products WHERE id=?',[$row['product_id']])->fetchColumn();if(!$row['imported']||$row['origin_environment']!=='test'||$type!=='CLOUD'){throw new HttpError(409,'CLOUD_IMPORTED_SERVICE_REQUIRED');}return $row;
    }
}
