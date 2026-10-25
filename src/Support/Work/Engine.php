<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,Config};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
final readonly class Engine
{
    public Store $store;
    public function __construct(private Services $c){$this->store=new Store($c);}
    public function observe(array $t,string $kind,string $source): void
    {
        if($this->c->config->environment!=='test'){throw new HttpError(503,'F5_TEST_ONLY_GATE');}$this->c->db->requireTransaction();if($kind==='INTERNAL_NOTE'){return;}
        $e=$this->c->db->execute('SELECT * FROM support_events WHERE id=? AND ticket_id=? AND customer_id=?',[$source,$t['id'],$t['customer_id']])->fetch();if(!$e||$e['kind']!==$kind){throw new \LogicException('Invalid support source event');}$at=Time::parse($e['created_at']);$w=$this->store->work($t);$clocks=new Clocks($this->store);$actor=new Principal($e['actor_id'],[],customerId:$t['customer_id']);
        $closedCycle=$w['cycle_id']===null?false:$this->c->db->execute('SELECT closed_at FROM support_sla_cycles WHERE id=?',[$w['cycle_id']])->fetchColumn();
        if($kind==='MOVE'){
            $old=$t;$old['department_id']=$w['department_id'];$clocks->advance($old,$w['cycle_id'],$at,$actor,$e['trace_id'],$source,'MOVED');
            if($w['assignee_id']!==null){$this->store->event($t,$t['department_id'],'UNASSIGNED',$actor,$e['trace_id'],['reason'=>'DEPARTMENT_CHANGED','previous_department'=>$w['department_id']],target:$w['assignee_id'],source:$source);}
            $this->c->db->execute("UPDATE support_work_tickets SET department_id=?,cycle_id=NULL,policy_id=NULL,assignee_id=NULL,manual_override=FALSE,weight=0,reason='AWAITING_CRON',assignment_version=assignment_version+1,version=version+1 WHERE ticket_id=?",[$t['department_id'],$t['id']]);$w=$this->store->work($t);
        }
        $start=$kind==='CREATED'||$kind==='MOVE'||($closedCycle!==null&&$closedCycle!==false&&!in_array($t['status'],['CLOSED','RESOLVED'],true));
        if($start){if(in_array($t['status'],['CLOSED','RESOLVED'],true)){return;}$head=$this->store->head($t['department_id']);if($head['policy_id']!==null){$clocks->start($t,$head['policy_id'],$source,$at);$this->c->db->execute("UPDATE support_work_tickets SET reason=IF(assignee_id IS NULL,'AWAITING_CRON',reason) WHERE ticket_id=?",[$t['id']]);}return;}
        $reply=false;if($kind==='STAFF_REPLY'){$reply=(bool)$this->c->db->execute("SELECT 1 FROM support_messages WHERE id=? AND ticket_id=? AND author_id=? AND type='staff_reply' AND customer_visible=TRUE",[$e['subject_id'],$t['id'],$e['actor_id']])->fetchColumn();}
        $end=in_array($t['status'],['CLOSED','RESOLVED'],true)?$t['status']:null;$clocks->advance($t,$w['cycle_id'],$at,$actor,$e['trace_id'],$source,$end,$reply);
        if($kind==='CUSTOMER_REPLY'&&$w['cycle_id']!==null&&$end===null){$clocks->add($w['cycle_id'],'NEXT',$source,$t,$at);}
    }
    /** Testable clock seam; HTTP cannot choose time. CLI uses the database UTC clock. */
    public function tick(string $ticket,?int $at=null): bool
    {
        if($this->c->config->environment!=='test'){throw new HttpError(503,'F5_TEST_ONLY_GATE');}
        try{return $this->c->db->transaction(function()use($ticket,$at): bool{
            (new Eligibility($this->c))->fence();$dep=$this->c->db->execute('SELECT department_id FROM support_tickets WHERE id=?',[$ticket])->fetchColumn();if(!$dep){return false;}(new \Hostider\Support\Access($this->c))->department($dep);$t=$this->c->db->execute('SELECT * FROM support_tickets WHERE id=? FOR UPDATE',[$ticket])->fetch();if(!$t||$t['department_id']!==$dep){return false;}$w=$this->store->work($t);$at??=Time::now($this->c->db);$actor=new Principal('support-cron',[],customerId:$t['customer_id']);$trace=Id::uuid();
            if(!in_array($t['status'],['CLOSED','RESOLVED'],true)){(new Clocks($this->store))->advance($t,$w['cycle_id'],$at,$actor,$trace);(new Assignments($this->store))->evaluate($t,$w,$at,$actor,$trace);}
            $this->c->db->execute('UPDATE support_work_tickets SET checked_at=? WHERE ticket_id=?',[Time::format($at),$ticket]);return true;
        });}catch(HttpError $e){if($e->errorCode==='SUPPORT_WORK_BUSY'){return false;}throw $e;}
    }
    public function run(int $max=25,int $seconds=20): array
    {
        Policy::integer($max,1,25);Policy::integer($seconds,1,20);if($this->c->config->environment!=='test'){throw new HttpError(503,'F5_TEST_ONLY_GATE');}$deadline=hrtime(true)+$seconds*1_000_000_000;$count=0;
        $ids=$this->c->db->execute("SELECT w.ticket_id FROM support_work_tickets w JOIN support_tickets t ON t.id=w.ticket_id WHERE t.status NOT IN ('RESOLVED','CLOSED') ORDER BY w.checked_at,w.ticket_id LIMIT ".$max)->fetchAll();foreach($ids as $r){if(hrtime(true)>=$deadline||memory_get_usage(true)>64*1024*1024){break;}if($this->tick($r['ticket_id'])){++$count;}}return ['checked'=>$count,'limit'=>$max,'test_only'=>true];
    }
}
