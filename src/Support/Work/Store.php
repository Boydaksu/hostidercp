<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Security\{Audit,AuditEvent,Principal};
use Hostider\Core\Queue\{Outbox,Queue};
final readonly class Store
{
    public function __construct(public Services $c){}
    public function head(string $dep): array
    {
        $this->c->db->requireTransaction();$this->c->db->execute('INSERT IGNORE INTO support_work_heads(department_id) VALUES (?)',[$dep]);return $this->c->db->execute('SELECT * FROM support_work_heads WHERE department_id=? FOR UPDATE',[$dep])->fetch();
    }
    public function policy(?string $id): ?array
    {
        if($id===null){return null;}$r=$this->c->db->execute('SELECT * FROM support_work_policies WHERE id=?',[$id])->fetch();if(!$r){throw new \LogicException('Missing work policy');}$r['data']=json_decode($r['spec'],true,32,JSON_THROW_ON_ERROR);return $r;
    }
    public function work(array $t): array
    {
        $this->c->db->requireTransaction();$this->c->db->execute('INSERT IGNORE INTO support_work_tickets(ticket_id,customer_id,department_id) VALUES (?,?,?)',[$t['id'],$t['customer_id'],$t['department_id']]);return $this->c->db->execute('SELECT * FROM support_work_tickets WHERE ticket_id=? FOR UPDATE',[$t['id']])->fetch();
    }
    public function event(?array $t,string $dep,string $kind,Principal $actor,string $trace,array $details=[],?string $target=null,?string $clock=null,?string $source=null,?string $eventId=null): string
    {
        $this->c->db->requireTransaction();$id=$eventId??Id::uuid();$this->c->db->execute('INSERT INTO support_work_events(id,ticket_id,customer_id,department_id,kind,actor_id,target_user,clock_id,source_event,ticket_version,details,trace_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$t['id']??null,$t['customer_id']??null,$dep,$kind,$actor->actorId,$target,$clock,$source,$t['version']??null,CanonicalJson::encode($details),$trace]);
        (new Audit($this->c->db))->record($actor,AuditEvent::SupportChanged,$t['id']??$dep,$trace);
        if($t!==null){(new Outbox($this->c->db,new Queue($this->c->db)))->append($id,'support.work.held',$t['id'],['event_uuid'=>$id,'ticket_id'=>$t['id']],$trace);}return $id;
    }
    public function bump(array $t): array
    {
        if($this->c->db->execute('UPDATE support_tickets SET version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?',[$t['id'],$t['version']])->rowCount()!==1){throw new \Hostider\Core\Http\HttpError(409,'SUPPORT_VERSION_CONFLICT');}$t['version']=(int)$t['version']+1;return $t;
    }
}
