<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\Config;use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\Principal;
final readonly class Runner
{
    public function __construct(private Database $db,private Config $config){}
    public function once(Principal $actor,?string $id=null,?int $deadline=null): bool
    {
        if($this->config->environment!=='test'||$actor->impersonating||$this->db->pdo->inTransaction()){throw new HttpError(403,'F3_TEST_ONLY');}
        $deadline??=hrtime(true)+20_000_000_000;
        $rows=$this->db->execute("SELECT id,kind FROM svc_operations WHERE status='PENDING'".($id!==null?' AND id=?':'').' ORDER BY runner_checked_at,created_at,id LIMIT 100',$id!==null?[$id]:[])->fetchAll();
        foreach($rows as $row){if(hrtime(true)>=$deadline){return false;}
            // Scheduling metadata is committed before dispatch; it never grants a provider claim.
            $this->db->execute("UPDATE svc_operations SET runner_checked_at=UTC_TIMESTAMP(6),runner_hold_code=NULL WHERE id=? AND status='PENDING'",[$row['id']]);
            try{
            $worked=match($row['kind']){'CREATE'=>(new Worker($this->db,$this->config))->once($actor,$row['id']),'RENEW'=>(new RenewalWorker($this->db,$this->config))->once($row['id'],$actor),'SUSPEND','UNSUSPEND','TERMINATE','CHANGE_PACKAGE'=>(new ActionWorker($this->db,$this->config))->once($row['id'],$actor),default=>throw new HttpError(409,'UNSUPPORTED_SERVICE_OPERATION')};if($worked){return true;}
        }catch(HttpError $e){if($id!==null||!in_array($e->errorCode,['NO_VERIFIED_MOCK_CAPACITY','SERVICE_SOURCE_CHANGED','SERVICE_CLAIM_CONFLICT','SERVICE_ACTION_STATE_CHANGED','SERVICE_RENEWAL_CONFLICT','FULL_RENEWAL_PAYMENT_REQUIRED'],true)){throw $e;}
            $this->db->execute("UPDATE svc_operations SET runner_hold_code=? WHERE id=? AND status='PENDING'",[$e->errorCode,$row['id']]);}}
        if(hrtime(true)>=$deadline){return false;}
        $inspection=$this->db->execute("SELECT id FROM svc_inspection_jobs WHERE status='PENDING'".($id!==null?' AND id=?':'').' ORDER BY created_at,id LIMIT 1',$id!==null?[$id]:[])->fetchColumn();return $inspection?(new Inspector($this->db,$this->config))->once($inspection):false;
    }
    /** Admission budget, not a hard interruption of an in-flight provider effect. */
    public function run(Principal $actor,int $maximum=100,int $seconds=20): array
    {
        if($maximum<1||$maximum>100||$seconds<1||$seconds>20){throw new \InvalidArgumentException('Invalid service worker budget');}
        $deadline=hrtime(true)+$seconds*1_000_000_000;$worked=0;
        $limit=ini_get('memory_limit');$bytes=(int)$limit;
        $bytes*=match(strtolower(substr($limit,-1))){'g'=>1024*1024*1024,'m'=>1024*1024,'k'=>1024,default=>1};
        $memoryBudget=$bytes>0?min(64*1024*1024,max(0,$bytes-8*1024*1024)):64*1024*1024;
        while($worked<$maximum&&hrtime(true)<$deadline&&memory_get_usage(true)<$memoryBudget){
            if(!$this->once($actor,deadline:$deadline)){break;}
            ++$worked;
        }
        return ['worked'=>$worked,'limit'=>$maximum,'budget_seconds'=>$seconds];
    }
}
