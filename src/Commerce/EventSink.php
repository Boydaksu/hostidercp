<?php
declare(strict_types=1);
namespace Hostider\Commerce;
use Hostider\Core\CanonicalJson;
use Hostider\Core\Database\Database;
use Hostider\Core\Queue\{Outbox,Queue,Worker};
/** Durable local fact delivery only. No payment, mail, or provisioning dispatch. */
final readonly class EventSink
{
    private const FIELDS=['finance.posted'=>'journal_id','catalog.changed'=>'entity_id','orders.submitted'=>'order_id','orders.ready'=>'order_id','orders.approval_required'=>'order_id','orders.cancelled'=>'order_id','orders.refund_recorded'=>'order_id','finance.cash_changed'=>'entity_id','billing.document_attached'=>'document_id','finance.wallet_converted'=>'conversion_id','identity.mail.held'=>'notification_id'];
    public function __construct(private Database $db) {}
    public function register(Worker $worker): void
    {
        foreach(array_keys(self::FIELDS) as $type){$worker->register($type,fn(array $payload): string=>$this->record($type,$payload));}
    }
    public function record(string $type,array $payload): string
    {
        $field=self::FIELDS[$type]??null;$reference=$field===null?null:($payload[$field]??null);
        if(!is_string($reference)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$reference)){return 'FAILED';}
        $candidates=$type==='identity.mail.held'?$this->db->execute("SELECT * FROM outbox_events WHERE event_type=? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.notification_id'))=? ORDER BY id",[$type,$reference])->fetchAll():$this->db->execute('SELECT * FROM outbox_events WHERE event_type=? AND aggregate_id=? ORDER BY id',[$type,$reference])->fetchAll();
        $matched=false;$canonical=CanonicalJson::encode($payload);$outbox=new Outbox($this->db,new Queue($this->db));
        foreach($candidates as $event){if(CanonicalJson::fromJson($event['payload'])!==$canonical){continue;}$matched=true;
            $status=match($type){'orders.ready','orders.approval_required'=>'HELD_FOR_F3','identity.mail.held'=>'MAIL_HELD',default=>'RECORDED'};
            $outbox->consume('commerce.local-facts.v1',$event['event_uuid'],fn()=>$this->db->execute('INSERT INTO commerce_event_receipts(event_uuid,event_type,aggregate_id,status,trace_id) VALUES (?,?,?,?,?)',[$event['event_uuid'],$type,$event['aggregate_id'],$status,$event['correlation_id']]));
        }
        return $matched?'SUCCEEDED':'FAILED';
    }
}
