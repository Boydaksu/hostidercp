<?php
declare(strict_types=1);
namespace Hostider\Cloud;
use Hostider\Core\{Config,CanonicalJson};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;
final readonly class Entitlements
{
    private Store $store;
    public function __construct(private Database $db,private Config $config){$this->store=new Store($db,$config);}
    public function bind(array $instance,array $purchase,array $spec): void
    {
        $canonical=(new \Hostider\Services\CloudServices($this->db,$this->config))->purchase($instance['service_id'],$instance['customer_id']);
        if($this->store->hash($canonical)!==$this->store->hash($purchase)){throw new HttpError(409,'CLOUD_PURCHASE_SOURCE_MISMATCH');}
        if($purchase['offer']['catalog_id']!==$instance['catalog_id']||CanonicalJson::encode($purchase['offer']['spec'])!==CanonicalJson::encode($spec)){throw new HttpError(409,'CLOUD_SPEC_NOT_PURCHASED');}
        $this->db->execute('INSERT INTO cloud_purchased_entitlements(instance_id,service_id,order_id,order_item_id,unit_no,offer_id,source_encrypted,fingerprint) VALUES (?,?,?,?,?,?,?,?)',[$instance['id'],$purchase['service_id'],$purchase['order_id'],$purchase['order_item_id'],$purchase['unit_no'],$purchase['offer']['offer_id'],$this->store->encrypt($purchase,'cloud-purchase:'.$instance['id']),$this->store->hash($purchase)]);
    }
    public function valid(array $instance): bool
    {
        try{
            $purchase=(new \Hostider\Services\CloudServices($this->db,$this->config))->purchase($instance['service_id'],$instance['customer_id'],['QUEUED','PROVISIONING','ACTIVE','SUSPENDED','UNKNOWN']);
            $row=$this->db->execute('SELECT * FROM cloud_purchased_entitlements WHERE instance_id=?',[$instance['id']])->fetch();
            if(!$row||$row['service_id']!==$instance['service_id']||!hash_equals($row['fingerprint'],$this->store->hash($purchase))){return false;}
            $this->store->decrypt($row['source_encrypted'],$row['fingerprint'],'cloud-purchase:'.$instance['id']);
            return $purchase['offer']['catalog_id']===$instance['catalog_id']&&$this->store->hash($purchase['offer']['spec'])===$instance['fingerprint'];
        }catch(HttpError){return false;}
    }
    public function action(array $instance,string $kind,array $data): void
    {
        $spec=$this->store->decrypt($instance['spec_encrypted'],$instance['fingerprint'],'cloud-spec:'.$instance['id']);
        if(($kind==='UPGRADE'&&($data['pid']??null)!==($spec['pid']??null))||($kind==='REINSTALL'&&($data['osid']??null)!==$spec['osid'])){throw new HttpError(409,'CLOUD_CHANGE_NOT_PURCHASED');}
    }
}
