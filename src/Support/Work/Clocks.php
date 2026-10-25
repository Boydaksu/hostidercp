<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Security\Principal;
final readonly class Clocks
{
    public function __construct(private Store $store){}
    private function history(array $clock,string $source,string $kind,int $at): void
    {
        $this->store->c->db->execute('INSERT INTO support_sla_history(id,clock_id,source_event,kind,snapshot,observed_at) VALUES (?,?,?,?,?,?)',[Id::uuid(),$clock['id'],$source,$kind,CanonicalJson::encode(array_intersect_key($clock,array_flip(['status','target_us','consumed_us','last_at','deadline_at','completed_at','version']))),Time::format($at)]);
    }
    public function start(array $t,string $policyId,string $source,int $at): string
    {
        $db=$this->store->c->db;$cycle=Id::uuid();$db->execute('INSERT INTO support_sla_cycles(id,ticket_id,customer_id,department_id,policy_id,source_event,opened_at) VALUES (?,?,?,?,?,?,?)',[$cycle,$t['id'],$t['customer_id'],$t['department_id'],$policyId,$source,Time::format($at)]);$db->execute('UPDATE support_work_tickets SET cycle_id=?,policy_id=?,version=version+1 WHERE ticket_id=?',[$cycle,$policyId,$t['id']]);$this->add($cycle,'FIRST',$source,$t,$at);$this->add($cycle,'RESOLUTION',$source,$t,$at);return $cycle;
    }
    public function add(string $cycle,string $metric,string $source,array $t,int $at): void
    {
        if($metric==='NEXT'&&(int)$this->store->c->db->execute("SELECT COUNT(*) FROM support_sla_clocks WHERE cycle_id=? AND metric='NEXT' AND status IN ('RUNNING','PAUSED')",[$cycle])->fetchColumn()>=100){throw new \Hostider\Core\Http\HttpError(409,'SLA_CLOCK_CAPACITY');}
        $db=$this->store->c->db;$p=$this->store->policy($db->execute('SELECT policy_id FROM support_sla_cycles WHERE id=?',[$cycle])->fetchColumn());$target=$p['data']['targets'][$t['effective_priority']][$metric]*Time::MINUTE;$paused=in_array($t['status'],$p['data']['pause'][$metric],true);$id=Id::uuid();$deadline=$paused?null:Time::format((new Calendar($p['data']['calendar']))->deadline($at,$target));
        $db->execute('INSERT INTO support_sla_clocks(id,cycle_id,metric,source_event,target_us,last_at,status,deadline_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$cycle,$metric,$source,$target,Time::format($at),$paused?'PAUSED':'RUNNING',$deadline]);$this->history($db->execute('SELECT * FROM support_sla_clocks WHERE id=?',[$id])->fetch(),$source,'STARTED',$at);
    }
    public function advance(array $t,?string $cycle,int $at,Principal $actor,string $trace,?string $source=null,?string $end=null,bool $reply=false): void
    {
        if($cycle===null){return;}$db=$this->store->c->db;$p=$this->store->policy($db->execute('SELECT policy_id FROM support_sla_cycles WHERE id=?',[$cycle])->fetchColumn());$cal=new Calendar($p['data']['calendar']);
        foreach($db->execute("SELECT * FROM support_sla_clocks WHERE cycle_id=? AND status IN ('RUNNING','PAUSED') ORDER BY id FOR UPDATE",[$cycle])->fetchAll() as $clock){
            $last=Time::parse($clock['last_at']);if($at<$last){throw new \Hostider\Core\Http\HttpError(409,'SLA_EVENT_TIME_CONFLICT');}$consumed=(int)$clock['consumed_us'];if($clock['status']==='RUNNING'){$consumed+=$cal->elapsed($last,$at);}
            foreach(['RISK','BREACH'] as $level){$threshold=$level==='BREACH'?(int)$clock['target_us']:intdiv((int)$clock['target_us']*$p['data']['risk_percent']+99,100);if($consumed>=$threshold&&!$db->execute('SELECT 1 FROM support_sla_alerts WHERE clock_id=? AND level=?',[$clock['id'],$level])->fetchColumn()){$alert=Id::uuid();$db->execute('INSERT INTO support_sla_alerts(id,clock_id,level,consumed_us,target_us,observed_at) VALUES (?,?,?,?,?,?)',[$alert,$clock['id'],$level,$consumed,$clock['target_us'],Time::format($at)]);$this->store->event($t,$t['department_id'],'SLA_'.$level,$actor,$trace,['alert_id'=>$alert],clock:$clock['id'],source:$source);}}
            $status=in_array($t['status'],$p['data']['pause'][$clock['metric']],true)?'PAUSED':'RUNNING';$completed=null;
            if($reply&&$clock['metric']!=='RESOLUTION'){$status='COMPLETED';$completed=Time::format($at);}
            if($end!==null){$status=$clock['metric']==='RESOLUTION'&&$end==='RESOLVED'?'COMPLETED':'CANCELLED';$completed=Time::format($at);}
            $deadline=$clock['deadline_at'];if($status==='PAUSED'){$deadline=null;}elseif($status==='RUNNING'&&($clock['status']==='PAUSED'||$deadline===null)){$deadline=Time::format($cal->deadline($at,max(0,(int)$clock['target_us']-$consumed)));}
            if($consumed!==(int)$clock['consumed_us']||$at!==$last||$status!==$clock['status']||$deadline!==$clock['deadline_at']||$completed!==$clock['completed_at']){$db->execute('UPDATE support_sla_clocks SET consumed_us=?,last_at=?,status=?,deadline_at=?,completed_at=?,version=version+1 WHERE id=?',[$consumed,Time::format($at),$status,$deadline,$completed,$clock['id']]);}$current=$db->execute('SELECT * FROM support_sla_clocks WHERE id=?',[$clock['id']])->fetch();if($source!==null){$this->history($current,$source,$status===$clock['status']?'OBSERVED':$status,$at);}
        }
        if($end!==null){$db->execute('UPDATE support_sla_cycles SET closed_at=?,close_reason=? WHERE id=? AND closed_at IS NULL',[Time::format($at),$end,$cycle]);}
    }
}
