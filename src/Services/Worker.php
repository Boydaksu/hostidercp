<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Security\{Crypto,Principal};
use Hostider\Providers\Contract\Operation;use Hostider\Providers\Mock\DurableCpanel;
final readonly class Worker
{
    public function __construct(private Database $db,private Config $config){}
    public function once(Principal $actor,?string $id=null,string $scenario='SUCCESS'): bool
    {
        $engine=new Engine($this->db,$this->config);$trace=Id::uuid();
        if($id===null){
            $candidates=$this->db->execute("SELECT o.id FROM svc_operations o JOIN svc_services s ON s.id=o.service_id JOIN orders source ON source.id=s.order_id WHERE o.status='PENDING' AND s.status='QUEUED' AND source.status='READY_FOR_PROVISIONING' AND source.refund_status='NONE' AND EXISTS(SELECT 1 FROM hosting_mappings m WHERE m.product_id=s.product_id AND m.enabled=TRUE) ORDER BY o.created_at,o.id LIMIT 100")->fetchAll();
            foreach($candidates as $candidate){try{if($this->once($actor,$candidate['id'],$scenario)){return true;}}catch(\Hostider\Core\Http\HttpError $e){if($e->status!==409){throw $e;}}}return false;
        }
        $claim=$engine->claim($actor,$trace,$id,true);if(!$claim){return false;}
        try{
            $provider=$this->db->execute('SELECT * FROM svc_provider_operations WHERE lifecycle_operation_id=?',[$claim['id']])->fetch();$key=base64_decode($this->config->key,true);$json=(new Crypto($key))->decrypt($provider['request_encrypted'],'svc-operation:'.$provider['id']);
            if(!hash_equals($provider['request_fingerprint'],hash_hmac('sha256',$json,$key))){throw new \RuntimeException('Operation request integrity');}
            $request=json_decode($json,true,64,JSON_THROW_ON_ERROR);$reservation=$this->db->execute('SELECT * FROM hosting_reservations WHERE operation_id=?',[$claim['id']])->fetch();$adapter=new DurableCpanel($this->db,$this->config,$provider['server_id'],$scenario);
            $result=$adapter->createAccount(new Operation($provider['id'],'createAccount',null,['service_id'=>$request['service_id'],'package_name'=>$reservation['package_name'],'cycle'=>$request['config']['original_line']['cycle']]));$proof=null;
            if($result->status==='SUCCESS'&&$result->externalResourceId!==null){$inspect=$adapter->inspectAccount(new Operation(Id::uuid(),'inspectAccount',$result->externalResourceId));if($inspect->status==='SUCCESS'){$proof=$adapter->evidence($result->externalResourceId);}}
            $engine->finishMock($claim['id'],$claim['lease_token'],$result,$proof,$actor,$trace);
        }catch(\Throwable $e){try{$engine->markUnknown($claim['id'],$claim['lease_token'],$actor,$trace);}catch(\Throwable){}throw $e;}
        return true;
    }
}
