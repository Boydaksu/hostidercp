<?php
declare(strict_types=1);
namespace Hostider\Support;
use Hostider\Core\{CanonicalJson,Config};
use Hostider\Core\Database\Database;
use Hostider\Core\Queue\{Outbox,Queue,Worker};
/** Local held receipt only. No mail, recipient resolution, provider or notification dispatch. */
final readonly class EventSink
{
    public function __construct(private Database $db,private Config $config){}
    public function register(Worker $worker): void{$worker->register('support.changed',fn(array $p): string=>$this->consume($p));}
    public function consume(array $p): string
    {
        $fields=array_keys($p);sort($fields,SORT_STRING);
        if($this->config->environment!=='test'||$fields!==['event_uuid','message_id','ticket_id']){return 'FAILED';}
        try{Access::id($p['event_uuid']);Access::id($p['ticket_id']);if($p['message_id']!==null){Access::id($p['message_id']);}}catch(\Hostider\Core\Http\HttpError){return 'FAILED';}
        if(!$this->bound($p)){return 'FAILED';}
        try{
            (new Outbox($this->db,new Queue($this->db)))->consume('support.held.v1',$p['event_uuid'],function()use($p): void{
                // Recheck current durable bindings inside the native receipt transaction.
                if(!$this->bound($p,true)){throw new \UnexpectedValueException('Invalid support event binding');}
            });
        }catch(\UnexpectedValueException){return 'FAILED';}
        return 'SUCCEEDED';
    }
    private function bound(array $p,bool $lock=false): bool
    {
        if($lock){$this->db->requireTransaction();}
        $row=$this->db->execute("SELECT o.payload,e.kind,e.subject_id,e.customer_id,j.payload AS job_payload,j.payload_fingerprint FROM outbox_events o JOIN support_events e ON e.id=o.event_uuid AND e.ticket_id=o.aggregate_id JOIN support_tickets t ON t.id=e.ticket_id AND t.customer_id=e.customer_id JOIN jobs j ON j.dedupe_key=CONCAT('outbox:',o.event_uuid) AND j.job_type=o.event_type WHERE o.event_uuid=? AND o.event_type='support.changed' AND o.aggregate_id=? AND o.published_at IS NOT NULL AND e.provenance='TEST_PANEL' AND j.payload_purged_at IS NULL".($lock?' FOR UPDATE':''),[$p['event_uuid'],$p['ticket_id']])->fetch();
        if(!$row){return false;}$canonical=CanonicalJson::encode($p);
        if(CanonicalJson::fromJson($row['payload'])!==$canonical||CanonicalJson::fromJson($row['job_payload'])!==$canonical||!hash_equals(hash('sha256',$canonical),$row['payload_fingerprint']??'')){return false;}
        if($p['message_id']===null){return in_array($row['kind'],['STATE','MOVE'],true)&&$row['subject_id']===$p['ticket_id'];}
        $type=match($row['kind']){'CREATED','CUSTOMER_REPLY'=>'customer_reply','STAFF_REPLY'=>'staff_reply',default=>null};
        if($type===null||$row['subject_id']!==$p['message_id']){return false;}
        return (bool)$this->db->execute('SELECT 1 FROM support_messages WHERE id=? AND ticket_id=? AND customer_id=? AND type=? AND customer_visible=TRUE'.($lock?' FOR UPDATE':''),[$p['message_id'],$p['ticket_id'],$row['customer_id'],$type])->fetchColumn();
    }
}
