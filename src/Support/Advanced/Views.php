<?php
declare(strict_types=1);
namespace Hostider\Support\Advanced;
use Hostider\Core\Validation;
use Hostider\Support\Access;
final readonly class Views
{
    public function __construct(private Common $m){}
    public function view(string $ref,string $view,array $p): array
    {
        $this->m->gate();Validation::fields($p,$view==='executions'?['ticket_id','offset']:['department_id','customer_id'],$view==='executions'?['ticket_id']:['department_id','customer_id']);return $this->m->c->identity->withStepUp($ref,'admin',function($u,$s)use($view,$p){$db=$this->m->c->db;if($view==='executions'){$t=$this->m->access->ticket($u,$s,Access::id($p['ticket_id']),'support.automation.manage');$offset=Common::offset($p['offset']??'0');return ['limit'=>50,'offset'=>$offset,'executions'=>$db->execute('SELECT x.id,x.event_uuid,x.rule_revision,x.rule_id,x.source_ticket_version,x.causation_id,x.correlation_id,x.actor_kind,x.result,x.reason,x.created_at FROM support_rule_executions x JOIN support_automation_events e ON e.id=x.event_uuid WHERE e.ticket_id=? AND e.customer_id=? AND e.department_id=? ORDER BY x.created_at,x.id LIMIT 50 OFFSET '.$offset,[$t['id'],$t['customer_id'],$t['department_id']])->fetchAll()];}
            Access::id($p['department_id']);Access::id($p['customer_id']);$this->m->access->department($p['department_id']);$this->m->scoped($u,$s,$p['department_id'],$p['customer_id'],'support.automation.manage');$rows=$db->execute('SELECT h.id,h.version,r.id revision_id,r.enabled,r.ordering,r.spec,r.created_at FROM support_rule_heads h JOIN support_rule_revisions r ON r.id=h.revision_id WHERE h.department_id=? AND h.customer_id=? ORDER BY r.ordering,h.id LIMIT 16',[$p['department_id'],$p['customer_id']])->fetchAll();$out=[];foreach($rows as $r){$spec=json_decode($r['spec'],true,32,JSON_THROW_ON_ERROR);unset($r['spec']);$r['trigger']=$spec['trigger'];$r['actions']=array_column($spec['actions'],'kind');$out[]=$r;}return ['rules'=>$out,'limit'=>16,'predicate_values'=>'REDACTED','template_content'=>'REDACTED'];});
    }
}
