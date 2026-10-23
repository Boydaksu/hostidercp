<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\Principal;
/** Bounded shared-hosting cron: held notices and policy-authorized suspend requests; no provider I/O. */
final readonly class Scheduler
{
    public function __construct(private Database $db,private Config $config){}
    public function lockName(): string{return 'svc-scheduler:'.substr(hash('sha256',$this->config->dsn),0,40);}
    public function run(): array
    {
        if($this->config->environment!=='test'||$this->db->pdo->inTransaction()){throw new HttpError(403,'F3_TEST_ONLY');}
        if((int)$this->db->execute('SELECT GET_LOCK(?,0)',[$this->lockName()])->fetchColumn()!==1){return ['overlap'=>true,'notices'=>0,'suspensions'=>0,'held'=>0];}
        try{
            $result=['overlap'=>false,'notices'=>0,'suspensions'=>0,'held'=>0];$actor=new Principal('service-scheduler',[]);$trace=Id::uuid();$deadline=hrtime(true)+20_000_000_000;$repository=new Repository($this->db,$this->config);$actions=new Actions($this->db,$this->config);
            $rows=$this->db->execute("SELECT s.id,s.current_period_id FROM svc_services s JOIN svc_periods p ON p.id=s.current_period_id WHERE s.origin_environment='test' AND s.imported=FALSE AND s.status='ACTIVE' AND p.status='VERIFIED' AND p.ends_at<=UTC_TIMESTAMP(6) ORDER BY s.scheduler_checked_at,s.id LIMIT 100")->fetchAll();
            foreach($rows as $row){if(hrtime(true)>$deadline){break;}$result['notices']+=$this->db->transaction(function()use($row,$repository,$actor,$trace): int{$service=$repository->find($row['id']);if($service['current_period_id']!==$row['current_period_id']||$service['status']!=='ACTIVE'){return 0;}$this->db->execute('UPDATE svc_services SET scheduler_checked_at=UTC_TIMESTAMP(6) WHERE id=?',[$service['id']]);$statement=$this->db->execute("INSERT INTO svc_notices(id,service_id,customer_id,kind,reference_id) VALUES (?,?,?,'PAYMENT_OR_RENEWAL_OVERDUE',?) ON DUPLICATE KEY UPDATE id=id",[Id::uuid(),$service['id'],$service['customer_id'],$row['current_period_id']]);if($statement->rowCount()===0){return 0;}$repository->event($service,'OVERDUE_NOTICE_HELD',$actor,$trace);return 1;});
                try{$actions->suspendDue($row['id'],$actor,$trace);++$result['suspensions'];}catch(HttpError $e){if($e->status!==409){throw $e;}++$result['held'];}
            }return $result;
        }finally{$this->db->execute('SELECT RELEASE_LOCK(?)',[$this->lockName()]);}
    }
}
