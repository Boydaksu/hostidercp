<?php
declare(strict_types=1);
namespace Hostider\Support\Advanced;
use Hostider\Core\{Id,Idempotency,Validation};
use Hostider\Core\Http\HttpError;
use Hostider\Support\{Access,Service};
use Hostider\Support\Work\{Time,Policy};
final readonly class Csat
{
    public function __construct(private Common $m){}
    public function command(string $ref,array $p,string $key,string $trace): array
    {
        $this->m->gate();Validation::fields($p,['ticket_id','ticket_version','version','score','comment'],['ticket_id','ticket_version','version','score','comment']);Access::id($p['ticket_id']);Service::version($p['ticket_version']);Common::version($p['version'],true);Policy::integer($p['score'],1,5);Common::text($p['comment'],2000,true);
        $work=function(bool $replay)use($ref,$p,$trace){return $this->m->c->identity->locked($ref,'client',function($u,$s)use($p,$trace,$replay){$a=$this->m->access->actor($u,$s);if($a->staff||$u['administrative']!==null){throw new HttpError(403,'CUSTOMER_REQUIRED');}$t=$this->m->access->ticket($u,$s,$p['ticket_id']);if($replay){return [];}$db=$this->m->c->db;if($t['status']!=='RESOLVED'){throw new HttpError(409,'CSAT_RESOLVED_REQUIRED');}if((int)$t['version']!==$p['ticket_version']){throw new HttpError(409,'SUPPORT_VERSION_CONFLICT');}$policy=$db->execute('SELECT * FROM support_csat_policies WHERE department_id=? FOR UPDATE',[$t['department_id']])->fetch();if(!$policy){throw new HttpError(409,'CSAT_POLICY_UNCONFIGURED');}$resolution=$db->execute("SELECT id,created_at FROM support_events WHERE ticket_id=? AND customer_id=? AND to_state='RESOLVED' AND (from_state IS NULL OR from_state<>'RESOLVED') ORDER BY ticket_version DESC,id DESC LIMIT 1",[$t['id'],$t['customer_id']])->fetch();if(!$resolution||Time::now($db)>=Time::parse($resolution['created_at'])+(int)$policy['window_seconds']*1_000_000){throw new HttpError(409,'CSAT_WINDOW_EXPIRED');}$r=$db->execute('SELECT * FROM support_ratings WHERE ticket_id=? FOR UPDATE',[$t['id']])->fetch();if(($r&&(int)$r['version']!==$p['version'])||(!$r&&$p['version']!==0)||($r&&$r['resolution_event']!==$resolution['id'])){throw new HttpError(409,'CSAT_VERSION_CONFLICT');}if(!$r){$db->execute('INSERT INTO support_ratings(ticket_id,customer_id,resolution_event) VALUES (?,?,?)',[$t['id'],$t['customer_id'],$resolution['id']]);}$id=Id::uuid();$v=$p['version']+1;$db->execute('INSERT INTO support_rating_revisions(id,ticket_id,customer_id,revision,score,comment_encrypted,policy_version,window_seconds,actor_id) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$t['id'],$t['customer_id'],$v,$p['score'],$this->m->crypto->encrypt($p['comment'],'csat:'.$id),$policy['version'],$policy['window_seconds'],$u['id']]);$db->execute('UPDATE support_ratings SET revision_id=?,version=? WHERE ticket_id=?',[$id,$v,$t['id']]);$this->m->event($t['department_id'],$t['id'],'CSAT_SUBMITTED',$a,$trace,['revision_id'=>$id,'version'=>$v],$t);return ['id'=>$id,'version'=>$v];});};return (new Idempotency($this->m->c->db))->run($ref,'support-csat',$key,['hmac'=>$this->m->hash($p)],fn()=>$work(false),fn()=>$work(true));
    }
    public function view(string $ref,string $ctx,array $p): array
    {
        $this->m->gate();Validation::fields($p,['ticket_id'],['ticket_id']);return $this->m->c->identity->locked($ref,$ctx,function($u,$s)use($p,$ctx){$t=$this->m->access->ticket($u,$s,Access::id($p['ticket_id']));if($ctx==='admin'){$this->m->scoped($u,$s,$t['department_id'],$t['customer_id'],'support.report.view');}$r=$this->m->c->db->execute('SELECT r.id,r.score,r.comment_encrypted,r.created_at,h.version FROM support_ratings h JOIN support_rating_revisions r ON r.id=h.revision_id WHERE h.ticket_id=? AND h.customer_id=?',[$t['id'],$t['customer_id']])->fetch();if(!$r){return ['rating'=>null];}return ['id'=>$r['id'],'score'=>(int)$r['score'],'comment'=>$this->m->crypto->decrypt($r['comment_encrypted'],'csat:'.$r['id']),'created_at'=>$r['created_at'],'version'=>(int)$r['version'],'content_format'=>'PLAIN_TEXT'];});
    }
}
