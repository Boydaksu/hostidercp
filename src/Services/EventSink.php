<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config};use Hostider\Core\Database\Database;use Hostider\Core\Queue\{Outbox,Queue,Worker};use Hostider\Core\Security\Principal;
final readonly class EventSink
{
    public function __construct(private Database $db,private Config $config){}
    public function register(Worker $worker): void
    {
        foreach(['services.changed','services.execute','services.renew','services.action','services.inspect'] as $type){$worker->register($type,fn(array $payload): string=>$this->consume($type,$payload));}
    }
    public function consume(string $type,array $payload): string
    {
        if($type==='services.inspect'){
            if($this->config->environment!=='test'||array_keys($payload)!==['inspection_job_id']||!is_string($payload['inspection_job_id'])){return 'FAILED';}$events=$this->db->execute("SELECT payload FROM outbox_events WHERE event_type='services.inspect' AND aggregate_id=?",[$payload['inspection_job_id']])->fetchAll();$matched=false;foreach($events as $event){if(CanonicalJson::fromJson($event['payload'])===CanonicalJson::encode($payload)){$matched=true;}}if(!$matched){return 'FAILED';}(new Inspector($this->db,$this->config))->once($payload['inspection_job_id']);return $this->db->execute('SELECT status FROM svc_inspection_jobs WHERE id=?',[$payload['inspection_job_id']])->fetchColumn()==='SUCCEEDED'?'SUCCEEDED':'FAILED';
        }
        if($this->config->environment!=='test'){return 'FAILED';}$field=$type==='services.changed'?'service_id':'operation_id';if(array_keys($payload)!==[$field]||!is_string($payload[$field])||!preg_match('/^[a-f0-9-]{36}$/D',$payload[$field])){return 'FAILED';}
        $events=$this->db->execute('SELECT event_uuid,payload FROM outbox_events WHERE event_type=? AND aggregate_id=? ORDER BY id',[$type,$payload[$field]])->fetchAll();$matched=[];foreach($events as $event){if(CanonicalJson::fromJson($event['payload'])===CanonicalJson::encode($payload)){$matched[]=$event['event_uuid'];}}if($matched===[]){return 'FAILED';}
        if($type!=='services.changed'){
            $expected=match($type){'services.execute'=>['CREATE'],'services.renew'=>['RENEW'],'services.action'=>['SUSPEND','UNSUSPEND','TERMINATE','CHANGE_PACKAGE'],default=>[]};$operation=$this->db->execute('SELECT kind FROM svc_operations WHERE id=?',[$payload[$field]])->fetchColumn();if(!in_array($operation,$expected,true)){return 'FAILED';}
            (new Runner($this->db,$this->config))->once(new Principal('service-outbox-worker',[]),$payload[$field]);$status=$this->db->execute('SELECT status FROM svc_operations WHERE id=?',[$payload[$field]])->fetchColumn();if($status!=='SUCCEEDED'){return $status==='UNKNOWN'?'UNKNOWN':'FAILED';}
        }
        $outbox=new Outbox($this->db,new Queue($this->db));foreach($matched as $event){$outbox->consume('services.delivery.v1',$event,static fn(): null=>null);}return 'SUCCEEDED';
    }
}
