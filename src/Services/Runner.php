<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\Config;use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\Principal;
final readonly class Runner
{
    public function __construct(private Database $db,private Config $config){}
    public function once(Principal $actor,?string $id=null): bool
    {
        if($this->config->environment!=='test'||$actor->impersonating){throw new HttpError(403,'F3_TEST_ONLY');}
        $rows=$this->db->execute("SELECT id,kind FROM svc_operations WHERE status='PENDING'".($id!==null?' AND id=?':'').' ORDER BY created_at,id LIMIT 100',$id!==null?[$id]:[])->fetchAll();
        foreach($rows as $row){try{
            $worked=match($row['kind']){'CREATE'=>(new Worker($this->db,$this->config))->once($actor,$row['id']),'RENEW'=>(new RenewalWorker($this->db,$this->config))->once($row['id'],$actor),'SUSPEND','UNSUSPEND','TERMINATE'=>(new ActionWorker($this->db,$this->config))->once($row['id'],$actor),default=>throw new HttpError(409,'UNSUPPORTED_SERVICE_OPERATION')};if($worked){return true;}
        }catch(HttpError $e){if($id!==null||!in_array($e->errorCode,['NO_VERIFIED_MOCK_CAPACITY','SERVICE_SOURCE_CHANGED','SERVICE_ACTION_STATE_CHANGED','SERVICE_RENEWAL_CONFLICT','FULL_RENEWAL_PAYMENT_REQUIRED'],true)){throw $e;}}}return false;
    }
}
