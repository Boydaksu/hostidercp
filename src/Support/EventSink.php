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
        if($this->config->environment!=='test'||$fields!==['message_id','ticket_id']){return 'FAILED';}
        if(!is_string($p['ticket_id'])||(!is_string($p['message_id'])&&$p['message_id']!==null)){return 'FAILED';}
        try{Access::id($p['ticket_id']);if($p['message_id']!==null){Access::id($p['message_id']);}}catch(\Hostider\Core\Http\HttpError){return 'FAILED';}
        $rows=$this->db->execute("SELECT o.event_uuid,o.payload FROM outbox_events o JOIN support_events e ON e.id=o.event_uuid AND e.ticket_id=o.aggregate_id WHERE o.event_type='support.changed' AND o.aggregate_id=? AND e.kind IN ('CREATED','CUSTOMER_REPLY','STAFF_REPLY','STATE','MOVE')",[$p['ticket_id']])->fetchAll();$matched=[];
        foreach($rows as $r){if(CanonicalJson::fromJson($r['payload'])===CanonicalJson::encode($p)){$matched[]=$r['event_uuid'];}}
        if($matched===[]){return 'FAILED';}
        if($p['message_id']!==null&&!$this->db->execute("SELECT 1 FROM support_messages WHERE id=? AND ticket_id=? AND customer_visible=TRUE AND type IN ('customer_reply','staff_reply')",[$p['message_id'],$p['ticket_id']])->fetchColumn()){return 'FAILED';}
        $outbox=new Outbox($this->db,new Queue($this->db));foreach($matched as $id){$outbox->consume('support.held.v1',$id,static fn(): null=>null);}return 'SUCCEEDED';
    }
}
