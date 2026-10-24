<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;

/** Initial registration boundary only; renewal requires its own new paid period. */
final readonly class RegistrationEligibility
{
    public function __construct(private Database $db,private Config $config){}
    public function lock(string $id,string $customer,string $type): array
    {
        $this->db->requireTransaction();$repo=new Repository($this->db,$this->config);
        $identity=$repo->find($id,$customer,false);
        $order=$this->db->execute('SELECT * FROM orders WHERE id=? FOR UPDATE',[$identity['order_id']])->fetch();
        $item=$this->db->execute('SELECT * FROM order_items WHERE id=? FOR UPDATE',[$identity['order_item_id']])->fetch();
        $service=$repo->find($id,$customer);
        $bill=$this->db->execute('SELECT r.*,s.status,s.refund_status FROM billing_requests r JOIN billing_request_states s ON s.request_id=r.id WHERE r.id=? FOR UPDATE',[$order['payment_request_id']??null])->fetch();
        $product=$this->db->execute('SELECT product_type FROM catalog_products WHERE id=?',[$service['product_id']])->fetchColumn();
        if(!$order||!$item||!$bill||$this->config->environment!=='test'||$service['origin_environment']!=='test'||$order['origin_environment']!=='test'||$service['imported']||$service['status']!=='QUEUED'||$product!==$type||$order['customer_id']!==$customer||$bill['customer_id']!==$customer||$bill['order_id']!==$order['id']||$item['order_id']!==$order['id']||$item['product_id']!==$service['product_id']||$order['status']!=='READY_FOR_PROVISIONING'||$order['refund_status']!=='NONE'||$bill['status']!=='PAID'||$bill['refund_status']!=='NONE'){throw new HttpError(409,'REGISTRATION_SOURCE_INELIGIBLE');}
        $line=json_decode($item['snapshot'],true,64,JSON_THROW_ON_ERROR);
        $config=$repo->configuration($id);
        if(($line['activation_policy']??null)!=='AUTO'||CanonicalJson::encode($line)!==CanonicalJson::encode($config['original_line'])||(int)$service['unit_no']<1||(int)$service['unit_no']>($line['quantity']??0)){throw new HttpError(409,'REGISTRATION_SOURCE_INELIGIBLE');}
        return $service;
    }
}
