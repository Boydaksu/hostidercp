<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Core\{CanonicalJson,Config};
use Hostider\Core\Database\Database;
use Hostider\Core\Queue\{Outbox,Queue,Worker};
final readonly class HeldSink
{
    public function __construct(private Database $db,private Config $config){}
    public function register(Worker $w): void{$w->register('support.work.held',fn(array $p): string=>$this->consume($p));}
    public function consume(array $p): string
    {
        $keys=array_keys($p);sort($keys,SORT_STRING);if($this->config->environment!=='test'||$keys!==['event_uuid','ticket_id']){return 'FAILED';}try{\Hostider\Support\Access::id($p['event_uuid']);\Hostider\Support\Access::id($p['ticket_id']);}catch(\Hostider\Core\Http\HttpError){return 'FAILED';}if(!$this->valid($p)){return 'FAILED';}
        try{(new Outbox($this->db,new Queue($this->db)))->consume('support.work.held.v1',$p['event_uuid'],function()use($p): void{if(!$this->valid($p,true)){throw new \UnexpectedValueException('Invalid held work binding');}});}catch(\UnexpectedValueException){return 'FAILED';}return 'SUCCEEDED';
    }
    private function valid(array $p,bool $lock=false): bool
    {
        $r=$this->db->execute("SELECT o.payload,j.payload AS job_payload,j.payload_fingerprint FROM outbox_events o JOIN support_work_events e ON e.id=o.event_uuid AND e.ticket_id=o.aggregate_id JOIN jobs j ON j.dedupe_key=CONCAT('outbox:',o.event_uuid) AND j.job_type=o.event_type WHERE o.event_uuid=? AND o.aggregate_id=? AND o.event_type='support.work.held' AND o.published_at IS NOT NULL AND j.payload_purged_at IS NULL".($lock?' FOR UPDATE':''),[$p['event_uuid'],$p['ticket_id']])->fetch();if(!$r){return false;}$canonical=CanonicalJson::encode($p);return CanonicalJson::fromJson($r['payload'])===$canonical&&CanonicalJson::fromJson($r['job_payload'])===$canonical&&hash_equals(hash('sha256',$canonical),$r['payload_fingerprint']??'');
    }
}
