<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\{Config,Id,Validation};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\CloudCosts\Store;
/** Synthetic normalized metering fixture only; this is NOT a Voyar response mapper. */
final readonly class CloudMetering
{
    private Store $store;
    public function __construct(private Database $db,Config $config){$this->store=new Store($db,$config);}
    public function record(string $account,string $kind,string $reference,array $payload): string
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('Provider fixture outside application transaction required');}
        if(!preg_match('/^[A-Za-z0-9:_-]{1,128}$/D',$reference)||!in_array($kind,['USAGE','INVOICE'],true)){throw new HttpError(422,'CLOUD_METER_REFERENCE_INVALID');}
        $fields=$kind==='USAGE'?['instance_id','resource_ref','task_ref','tariff_id','sequence','starts_at','ends_at','observed_at','addons','fees','provenance']:['instance_id','resource_ref','task_ref','usage_refs','currency','total_minor','starts_at','ends_at','observed_at','final','provenance'];
        Validation::fields($payload,$fields,$fields);if($payload['provenance']!=='MOCK'){throw new HttpError(403,'CLOUD_LIVE_METERING_DISABLED');}
        return $this->db->transaction(function()use($account,$kind,$reference,$payload): string{
            if(!$this->db->execute('SELECT id FROM cloud_accounts WHERE id=? FOR UPDATE',[$account])->fetchColumn()){throw new HttpError(404,'CLOUD_METER_ACCOUNT_NOT_FOUND');}
            $old=$this->db->execute('SELECT * FROM mock_cloud_meter_events WHERE account_id=? AND kind=? AND provider_ref=?',[$account,$kind,$reference])->fetch();
            if($old){if(!hash_equals($old['fingerprint'],$this->store->hash($payload))){throw new HttpError(409,'CLOUD_METER_REFERENCE_CONFLICT');}return $old['id'];}
            $id=Id::uuid();$this->db->execute('INSERT INTO mock_cloud_meter_events(id,account_id,kind,provider_ref,payload_encrypted,fingerprint) VALUES (?,?,?,?,?,?)',[$id,$account,$kind,$reference,$this->store->encrypt($payload,'cloud-meter:'.$id),$this->store->hash($payload)]);return $id;
        });
    }
}
