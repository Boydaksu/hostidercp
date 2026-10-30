<?php
declare(strict_types=1);
namespace Hostider\Support\Merge;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,Idempotency,Validation};
use Hostider\Core\Http\HttpError;
use Hostider\Support\{Access,Service};
use Hostider\Support\Advanced\Common;
use Hostider\Support\Work\{Eligibility,Store};
final readonly class Commands
{
    public Common $m;
    public function __construct(public Services $c){$this->m=new Common($c);}
    private function payload(array $p):array
    {
        Validation::fields($p,['source_id','target_id','source_version','target_version'],['source_id','target_id','source_version','target_version']);
        Access::id($p['source_id']);Access::id($p['target_id']);Service::version($p['source_version']);Service::version($p['target_version']);
        if($p['source_id']===$p['target_id']){throw new HttpError(422,'MERGE_DISTINCT_TICKETS_REQUIRED');}return $p;
    }
    /** Lock departments, then both ticket IDs in lexical order. Identity fence precedes both. */
    private function pair(array $u,array $s,array $p):array
    {
        $db=$this->c->db;if(!$db->execute('SELECT 1 FROM identity_mfa WHERE user_id=? AND enabled=TRUE FOR UPDATE',[$u['id']])->fetchColumn()){throw new HttpError(403,'MFA_ENROLLMENT_REQUIRED');}$ids=[$p['source_id'],$p['target_id']];sort($ids,SORT_STRING);$deps=[];
        foreach($ids as $id){$dep=$db->execute('SELECT department_id FROM support_tickets WHERE id=?',[$id])->fetchColumn();if(!$dep){throw new HttpError(404,'SUPPORT_NOT_FOUND');}$deps[]=$dep;}
        sort($deps,SORT_STRING);foreach(array_unique($deps) as $dep){$this->m->access->department($dep);}
        $rows=[];foreach($ids as $id){$t=$db->execute('SELECT * FROM support_tickets WHERE id=? FOR UPDATE',[$id])->fetch();if(!$t||!in_array($t['department_id'],$deps,true)){throw new HttpError(409,'MERGE_SCOPE_CHANGED');}
            foreach(['support.ticket.view','support.ticket.manage','support.ticket.merge'] as $perm){$this->m->access->require($u,$s,$t['customer_id'],$t['department_id'],$perm);}$rows[$id]=$t;}
        $a=$rows[$p['source_id']];$b=$rows[$p['target_id']];
        if($a['customer_id']!==$b['customer_id']||$a['department_id']!==$b['department_id']){throw new HttpError(409,'MERGE_SCOPE_MISMATCH');}
        return [$a,$b];
    }
    private function ready(array $a,array $b,array $p):array
    {
        $db=$this->c->db;
        if((int)$a['version']!==$p['source_version']||(int)$b['version']!==$p['target_version']){throw new HttpError(409,'MERGE_VERSION_CONFLICT');}
        if($db->execute('SELECT 1 FROM support_merge_members WHERE ticket_id IN (?,?) LIMIT 1 FOR UPDATE',[$a['id'],$b['id']])->fetchColumn()){throw new HttpError(409,'MERGE_CHAIN_BLOCKED');}
        if($db->execute("SELECT 1 FROM schema_migrations WHERE status<>'APPLIED' LIMIT 1 FOR UPDATE")->fetchColumn()
            ||$db->execute("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='f6b_runs'")->fetchColumn()){
            throw new HttpError(409,'MERGE_MIGRATION_BLOCKED');
        }
        if($db->execute("SELECT 1 FROM jobs WHERE status='RUNNING' AND payload_purged_at IS NULL AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.ticket_id')) IN (?,?) LIMIT 1 FOR UPDATE",[$a['id'],$b['id']])->fetchColumn()
            ||$db->execute("SELECT 1 FROM support_mail_outbound WHERE ticket_id IN (?,?) AND status IN ('PENDING','RETRYABLE_FAILURE') AND attempt_count>0 LIMIT 1 FOR UPDATE",[$a['id'],$b['id']])->fetchColumn()){
            throw new HttpError(409,'MERGE_OPERATION_UNCERTAIN');
        }
        $d=$this->m->access->department($a['department_id']);if(!$d['enabled']){throw new HttpError(409,'MERGE_DEPARTMENT_DISABLED');}
        $policies=[];foreach(['support_work_heads','support_auto_close_heads'] as $table){$policies[$table]=$db->execute('SELECT * FROM '.$table.' WHERE department_id=? FOR UPDATE',[$d['id']])->fetch()?:null;}
        return ['source'=>[$a['id'],(int)$a['version'],$a['customer_id'],$a['department_id'],$a['status'],$a['number']], 'target'=>[$b['id'],(int)$b['version'],$b['customer_id'],$b['department_id'],$b['status']], 'department_version'=>(int)$d['version'],'policies'=>$policies];
    }
    public function command(string $ref,string $route,array $body,string $key,string $trace):array
    {
        $this->m->gate();if(!in_array($route,['prepare','execute'],true)){throw new HttpError(422,'MERGE_ACTION_INVALID');}
        Validation::fields($body,['action','payload','confirmation'],['action','payload']);if($body['action']!=='merge'){throw new HttpError(422,'MERGE_ACTION_INVALID');}$p=$this->payload(Common::object($body['payload']));
        $work=function(bool $replay)use($ref,$route,$body,$p,$trace):array{
            (new Eligibility($this->c))->fence();
            return $this->c->identity->withStepUp($ref,'admin',function($u,$s)use($route,$body,$p,$trace,$replay):array{
                [$a,$b]=$this->pair($u,$s,$p);if($replay){return [];}$state=$this->ready($a,$b,$p);$state['authorization_epoch']=(new \Hostider\Support\UI\Context($this->m))->epoch($u,$s);$db=$this->c->db;
                if($route==='prepare'){
                    $id=Id::uuid();$db->execute("INSERT INTO support_work_confirmations(id,actor_id,session_id,identity_version,action,payload_hmac,state_hmac,expires_at) VALUES (?,?,?,?,'TICKET_MERGE',?,?,TIMESTAMPADD(SECOND,300,UTC_TIMESTAMP(6)))",[$id,$u['id'],$s['id'],$u['identity_version'],$this->m->hash($p),$this->m->hash($state)]);$this->c->identity->confirmationEvent($u['id'],$id,false);
                    return ['id'=>$id,'source'=>['id'=>$a['id'],'number'=>$a['number'],'version'=>(int)$a['version']],'target'=>['id'=>$b['id'],'number'=>$b['number'],'version'=>(int)$b['version']],'direction'=>'SOURCE_TO_TARGET','messages_moved'=>false,'sla_history_rewritten'=>false,'expires_in'=>300,'live_merge_allowed'=>false];
                }
                $c=Common::object($body['confirmation']??[]);Validation::fields($c,['id','accepted'],['id','accepted']);Access::id($c['id']);if($c['accepted']!==true){throw new HttpError(403,'CONFIRMATION_REQUIRED');}
                $v=$db->execute('SELECT *,UNIX_TIMESTAMP(expires_at) expiry FROM support_work_confirmations WHERE id=? FOR UPDATE',[$c['id']])->fetch();
                if(!$v||$v['used_at']!==null||$v['actor_id']!==$u['id']||$v['session_id']!==$s['id']||(int)$v['identity_version']!==(int)$u['identity_version']||$v['action']!=='TICKET_MERGE'||(int)$v['expiry']<=time()||!hash_equals($v['payload_hmac'],$this->m->hash($p))||!hash_equals($v['state_hmac'],$this->m->hash($state))){throw new HttpError(409,'MERGE_CONFIRMATION_INVALID');}
                $store=new Store($this->c);$a=$store->bump($a);$b=$store->bump($b);$actor=$this->m->access->actor($u,$s);$details=['source_id'=>$a['id'],'target_id'=>$b['id'],'source_number'=>$a['number'],'direction'=>'SOURCE_TO_TARGET'];
                $id=$store->event($a,$a['department_id'],'MERGED_SOURCE',$actor,$trace,$details);$store->event($b,$b['department_id'],'MERGED_TARGET',$actor,$trace,$details,source:$id);
                $db->execute('INSERT INTO support_ticket_merges(id,source_id,target_id,customer_id,department_id,source_number,source_version,target_version,actor_id,session_id,identity_version) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$id,$a['id'],$b['id'],$a['customer_id'],$a['department_id'],$a['number'],$p['source_version'],$p['target_version'],$u['id'],$s['id'],$u['identity_version']]);
                foreach([[$a['id'],'SOURCE'],[$b['id'],'TARGET']] as [$ticket,$side]){$db->execute('INSERT INTO support_merge_members(ticket_id,merge_id,side) VALUES (?,?,?)',[$ticket,$id,$side]);}
                $db->execute('UPDATE support_work_confirmations SET used_at=UTC_TIMESTAMP(6) WHERE id=?',[$v['id']]);$this->c->identity->confirmationEvent($u['id'],$v['id'],true);
                return ['id'=>$b['id'],'source_id'=>$a['id'],'target_id'=>$b['id'],'merge_id'=>$id,'version'=>(int)$b['version'],'source_version'=>(int)$a['version'],'live_merge_allowed'=>false];
            });
        };
        return (new Idempotency($this->c->db))->run($ref,'support-merge:'.$route,$key,['hmac'=>$this->m->hash($body)],fn()=>$work(false),fn()=>$work(true));
    }
}
