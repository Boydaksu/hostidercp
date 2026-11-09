<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Core\Security\Principal;
final readonly class Assignments
{
    public function __construct(private Store $store){}
    public function set(array $t,array $w,?string $target,bool $manual,string $reason,Principal $actor,string $trace,int $at): array
    {
        $db=$this->store->c->db;$policy=$this->store->policy($w['policy_id']);$weight=$target===null?0:$policy['data']['assignment']['weights'][$t['effective_priority']];
        if($w['assignee_id']===$target&&(bool)$w['manual_override']===$manual&&$w['reason']===$reason){return $t;}
        $t=$this->store->bump($t);$db->execute('UPDATE support_work_tickets SET assignee_id=?,manual_override=?,reason=?,weight=?,assignment_version=assignment_version+1,version=version+1,checked_at=? WHERE ticket_id=?',[$target,(int)$manual,$reason,$weight,Time::format($at),$t['id']]);$this->store->event($t,$t['department_id'],$target===null?'UNASSIGNED':'ASSIGNED',$actor,$trace,['reason'=>$reason,'manual'=>$manual,'previous_user'=>$w['assignee_id'],'policy_id'=>$w['policy_id'],'weight'=>$weight],target:$target);return $t;
    }
    public function evaluate(array $t,array $w,int $at,Principal $actor,string $trace): array
    {
        $eligibility=new Eligibility($this->store->c);$p=$this->store->policy($w['policy_id']);if($p===null){return $t;}
        if($w['assignee_id']!==null&&$eligibility->eligible($t,$w['assignee_id'],$at)){return $t;}
        if((bool)$w['manual_override']||$p['data']['assignment']['mode']==='MANUAL'){return $this->set($t,$w,null,(bool)$w['manual_override'],$w['assignee_id']===null?'MANUAL_QUEUE':'ASSIGNEE_INELIGIBLE',$actor,$trace,$at);}
        $ids=$eligibility->candidates($t,$at);$target=null;$mode=$p['data']['assignment']['mode'];
        if($ids!==[]){if($mode==='ROUND_ROBIN'){$head=$this->store->head($t['department_id']);foreach($ids as $id){if($head['rr_cursor']===null||strcmp($id,$head['rr_cursor'])>0){$target=$id;break;}}$target??=$ids[0];$this->store->c->db->execute('UPDATE support_work_heads SET rr_cursor=? WHERE department_id=?',[$target,$t['department_id']]);}
            else{$loads=[];foreach($ids as $id){$loads[$id]=(int)$this->store->c->db->execute("SELECT COALESCE(SUM(w.weight),0) FROM support_work_tickets w JOIN support_tickets t ON t.id=w.ticket_id WHERE w.department_id=? AND w.assignee_id=? AND NOT EXISTS(SELECT 1 FROM support_merge_members mm WHERE mm.ticket_id=t.id AND mm.side='SOURCE') AND t.status NOT IN ('RESOLVED','CLOSED')",[$t['department_id'],$id])->fetchColumn();}uasort($loads,static fn(int $a,int $b): int=>$a<=>$b);$target=array_key_first($loads);}}
        return $this->set($t,$w,$target,false,$target===null?'NO_ELIGIBLE_STAFF':$mode,$actor,$trace,$at);
    }
}
