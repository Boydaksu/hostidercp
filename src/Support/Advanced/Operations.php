<?php
declare(strict_types=1);
namespace Hostider\Support\Advanced;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Support\Automation\Ast;
use Hostider\Support\{Service,State};
use Hostider\Support\Work\{Store,Eligibility,Time};
final readonly class Operations
{
    public function __construct(private Common $m){}
    public function preflight(array $u,array $s,array $t,array $a): void
    {
        Ast::action($a);if(in_array($t['status'],['CLOSED','RESOLVED'],true)){throw new HttpError(409,'ACTIVE_TICKET_REQUIRED');}
        $permission=match($a['kind']){'assign'=>'support.ticket.assign','note'=>'support.ticket.internal_note','incident'=>'support.incident.manage',default=>'support.ticket.manage'};$this->m->scoped($u,$s,$t['department_id'],$t['customer_id'],$permission);
        if($a['kind']==='state'){State::transition($t['status'],$a['value'],$this->m->access->department($t['department_id']));}
        if($a['kind']==='assign'){$w=$this->m->c->db->execute('SELECT policy_id FROM support_work_tickets WHERE ticket_id=?',[$t['id']])->fetchColumn();if(!$w||($a['value']!==null&&!(new Eligibility($this->m->c))->eligible($t,$a['value'],Time::now($this->m->c->db)))){throw new HttpError(409,'ASSIGNEE_INELIGIBLE');}}
        if($a['kind']==='incident'){(new Incidents($this->m))->linkable($u,$s,$t,$a['value']);}
        if($a['kind']==='tag'&&(int)$this->m->c->db->execute('SELECT COUNT(*) FROM support_ticket_tags WHERE ticket_id=?',[$t['id']])->fetchColumn()>=32&&!$this->m->c->db->execute('SELECT 1 FROM support_ticket_tags WHERE ticket_id=? AND tag=?',[$t['id'],$a['value']])->fetchColumn()){throw new HttpError(409,'TICKET_TAG_LIMIT');}
    }
    public function apply(string $ref,array $u,array $s,array $t,array $a,string $trace,?string $execution=null,?string $cause=null): array
    {
        $this->preflight($u,$s,$t,$a);$db=$this->m->c->db;$system=$execution!==null;$actor=$system?new Principal('support-rule',[],customerId:$t['customer_id']):$this->m->access->actor($u,$s);$effect=null;
        if($a['kind']==='state'||$a['kind']==='note'){$p=['id'=>$t['id'],'version'=>(int)$t['version'],...($a['kind']==='note'?['body'=>$a['value']]:['status'=>$a['value']])];$r=(new Service($this->m->c))->delegated($ref,$a['kind'],$p,$trace);$effect=$a['kind']==='note'?$r['message_id']:$db->execute("SELECT id FROM support_events WHERE ticket_id=? AND ticket_version=? AND kind='STATE'",[$t['id'],$r['version']])->fetchColumn();}
        elseif($a['kind']==='assign'){$r=(new \Hostider\Support\Work\Commands($this->m->c))->delegated($ref,['id'=>$t['id'],'version'=>(int)$t['version'],'assignee_id'=>$a['value']],$trace);$effect=$db->execute('SELECT id FROM support_work_events WHERE ticket_id=? AND ticket_version=? AND kind=?',[$t['id'],$r['version'],$a['value']===null?'UNASSIGNED':'ASSIGNED'])->fetchColumn();if($effect===false){return ['changed'=>false,'effect_id'=>null];}}
        elseif($a['kind']==='incident'){$effect=(new Incidents($this->m))->link($u,$s,$t,$a['value'],$trace,$execution,$cause);}
        elseif($a['kind']==='notify'){if($execution===null){throw new HttpError(422,'RULE_EXECUTION_REQUIRED');}$effect=\Hostider\Core\Id::uuid();$db->execute("INSERT INTO support_staff_held VALUES (?,?,?,?,?,'HELD')",[$effect,$t['id'],$t['customer_id'],$t['department_id'],$execution]);(new \Hostider\Core\Queue\Outbox($db,new \Hostider\Core\Queue\Queue($db)))->append($effect,'support.advanced.held',$t['id'],['event_uuid'=>$effect,'ticket_id'=>$t['id'],'execution_id'=>$execution],$trace,$cause);}
        elseif($a['kind']==='priority'){
            if($t['effective_priority']===$a['value']){return ['changed'=>false,'effect_id'=>null];}$old=$t['effective_priority'];$t=(new Store($this->m->c))->bump($t);$db->execute('UPDATE support_tickets SET effective_priority=? WHERE id=?',[$a['value'],$t['id']]);$t['effective_priority']=$a['value'];$w=$db->execute('SELECT policy_id,assignee_id,weight FROM support_work_tickets WHERE ticket_id=? FOR UPDATE',[$t['id']])->fetch();$policy=($w&&$w['policy_id']!==null)?(new Store($this->m->c))->policy($w['policy_id']):null;if($w&&$w['assignee_id']!==null){$db->execute('UPDATE support_work_tickets SET weight=?,version=version+1 WHERE ticket_id=?',[$policy['data']['assignment']['weights'][$a['value']],$t['id']]);}$effect=$this->m->event($t['department_id'],$t['id'],'PRIORITY',$actor,$trace,['old'=>$old,'new'=>$a['value'],'reason'=>$system?'RULE_REVISION':'CONFIRMED_STAFF','version'=>$t['version'],'sla_effect'=>'SNAPSHOT_UNCHANGED','policy_id'=>$w['policy_id']??null,'policy_revision'=>isset($policy)?$policy['revision']:null,'previous_assignment_weight'=>$w['weight']??null,'new_assignment_weight'=>$w&&$w['assignee_id']!==null?$policy['data']['assignment']['weights'][$a['value']]:0],$t,$u['id'],$execution,$cause);return ['changed'=>true,'effect_id'=>$effect];
        }else{
            if($db->execute('SELECT 1 FROM support_ticket_tags WHERE ticket_id=? AND tag=?',[$t['id'],$a['value']])->fetchColumn()){return ['changed'=>false,'effect_id'=>null];}$t=(new Store($this->m->c))->bump($t);$effect=$this->m->event($t['department_id'],$t['id'],'TAG',$actor,$trace,['tag'=>$a['value'],'version'=>$t['version']],$t,$u['id'],$execution,$cause);$db->execute('INSERT INTO support_ticket_tags VALUES (?,?,?,?)',[$t['id'],$t['customer_id'],$a['value'],$effect]);return ['changed'=>true,'effect_id'=>$effect];
        }
        $this->m->event($t['department_id'],$t['id'],'ACTION_'.$a['kind'],$actor,$trace,['canonical_effect'=>$effect],$t,$u['id'],$execution,$cause);return ['changed'=>true,'effect_id'=>$effect];
    }
}
