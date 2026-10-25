<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,Idempotency,CanonicalJson,Validation};
use Hostider\Core\Http\HttpError;
use Hostider\Support\Access;
final readonly class Commands
{
    private Store $store;
    public function __construct(private Services $c){$this->store=new Store($c);}
    private function hash(array $p): string{return hash_hmac('sha256',CanonicalJson::encode($p),base64_decode($this->c->config->key,true));}
    private function gate(): void{if($this->c->config->environment!=='test'){throw new HttpError(503,'F5_TEST_ONLY_GATE');}}
    public function command(string $reference,string $route,array $body,string $key,string $trace): array
    {
        $this->gate();Validation::fields($body,['action','payload','confirmation'],['action','payload']);if(!is_string($body['action'])||!in_array($body['action'],['policy-publish','roster-set','assign','auto','enroll'],true)){throw new HttpError(422,'INVALID_WORK_ACTION');}$action=$body['action'];$p=Policy::array($body['payload']);$p=$this->fields($action,$p);
        $work=function(bool $replay)use($reference,$route,$action,$p,$body,$trace): array{
            (new Eligibility($this->c))->fence();return $this->c->identity->withStepUp($reference,'admin',function(array $u,array $s)use($route,$action,$p,$body,$trace,$replay): array{
                $this->authorize($u,$s,$action,$p);if($replay){return [];}$state=$this->state($action,$p);$now=Time::now($this->c->db);$this->validate($action,$p,$state,$now);
                if($route==='prepare'){$id=Id::uuid();$this->c->db->execute('INSERT INTO support_work_confirmations(id,actor_id,session_id,identity_version,action,payload_hmac,state_hmac,expires_at) VALUES (?,?,?,?,?,?,?,TIMESTAMPADD(SECOND,300,UTC_TIMESTAMP(6)))',[$id,$u['id'],$s['id'],$u['identity_version'],$action,$this->hash($p),$this->hash($state)]);$this->c->identity->confirmationEvent($u['id'],$id,false);return ['id'=>$id,'action'=>$action,'current_version'=>$p['version'],'expires_in'=>300,'proposed'=>$p,'effect'=>'TEST_ONLY_SUPPORT_WORK_NO_MAIL_OR_PROVIDER'];}
                $approval=Policy::array($body['confirmation']??[]);Validation::fields($approval,['id','accepted'],['id','accepted']);if($approval['accepted']!==true){throw new HttpError(403,'CONFIRMATION_REQUIRED');}Access::id($approval['id']);$r=$this->c->db->execute('SELECT *,UNIX_TIMESTAMP(expires_at) expiry FROM support_work_confirmations WHERE id=? FOR UPDATE',[$approval['id']])->fetch();
                if(!$r||$r['actor_id']!==$u['id']||$r['session_id']!==$s['id']||(int)$r['identity_version']!==(int)$u['identity_version']||$r['action']!==$action||$r['used_at']!==null||(int)$r['expiry']<=time()||!hash_equals($r['payload_hmac'],$this->hash($p))){throw new HttpError(409,'WORK_CONFIRMATION_INVALID');}if(!hash_equals($r['state_hmac'],$this->hash($state))){throw new HttpError(409,'WORK_CONFIRMATION_STALE');}
                $this->c->db->execute('UPDATE support_work_confirmations SET used_at=UTC_TIMESTAMP(6) WHERE id=?',[$r['id']]);$result=$this->apply($u,$s,$action,$p,$state,$now,$trace);$this->c->identity->confirmationEvent($u['id'],$r['id'],true);return $result;
            });
        };
        return (new Idempotency($this->c->db))->run($reference,'support-work:'.$route,$key,['hmac'=>$this->hash($body)],fn(): array=>$work(false),fn(): array=>$work(true));
    }
    private function fields(string $action,array $p): array
    {
        $keys=match($action){'policy-publish'=>['department_id','version','policy'],'roster-set'=>['department_id','user_id','version','roster'],'assign'=>['id','version','assignee_id'],default=>['id','version']};Validation::fields($p,$keys,$keys);Policy::integer($p['version'],in_array($action,['policy-publish','roster-set'],true)?0:1,2147483647);
        foreach(['id','department_id','user_id','assignee_id'] as $k){if(array_key_exists($k,$p)&&($k!=='assignee_id'||$p[$k]!==null)){Access::id($p[$k]);}}
        if($action==='policy-publish'){$p['policy']=Policy::validate(Policy::array($p['policy']));}if($action==='roster-set'){$p['roster']=Policy::roster(Policy::array($p['roster']));}return $p;
    }
    private function authorize(array $u,array $s,string $action,array $p): void
    {
        $access=new Access($this->c);$a=$access->actor($u,$s);if(!$a->staff){throw new HttpError(403,'STAFF_REQUIRED');}
        if(in_array($action,['policy-publish','roster-set'],true)){$access->management($u,$s);$access->department($p['department_id']);}else{$access->ticket($u,$s,$p['id'],'support.ticket.assign');}
    }
    private function head(string $dep): array{return $this->c->db->execute('SELECT department_id,policy_id,version FROM support_work_heads WHERE department_id=? FOR UPDATE',[$dep])->fetch()?:['department_id'=>$dep,'policy_id'=>null,'version'=>0];}
    private function state(string $action,array $p): array
    {
        $db=$this->c->db;if(in_array($action,['policy-publish','roster-set'],true)){$dep=$p['department_id'];$state=['department'=>$db->execute('SELECT id,version FROM support_departments WHERE id=? FOR UPDATE',[$dep])->fetch(),'head'=>$this->head($dep)];if($action==='roster-set'){$state['roster']=$db->execute('SELECT roster_id,version FROM support_work_roster_heads WHERE department_id=? AND user_id=? FOR UPDATE',[$dep,$p['user_id']])->fetch()?:['roster_id'=>null,'version'=>0];$state['user']=$db->execute('SELECT id,identity_version,status FROM identity_users WHERE id=?',[$p['user_id']])->fetch();}return $state;}
        $t=$db->execute('SELECT * FROM support_tickets WHERE id=? FOR UPDATE',[$p['id']])->fetch();$w=$db->execute('SELECT * FROM support_work_tickets WHERE ticket_id=? FOR UPDATE',[$t['id']])->fetch();$state=['ticket'=>array_intersect_key($t,array_flip(['id','customer_id','department_id','version','status','effective_priority'])),'work'=>$w?array_intersect_key($w,array_flip(['policy_id','cycle_id','assignee_id','manual_override','assignment_version'])):null,'head'=>$this->head($t['department_id'])];
        if($action==='assign'&&$p['assignee_id']!==null){$state['target']=$db->execute('SELECT id,identity_version,status FROM identity_users WHERE id=?',[$p['assignee_id']])->fetch();$state['target_roster']=$db->execute('SELECT roster_id,version FROM support_work_roster_heads WHERE department_id=? AND user_id=?',[$t['department_id'],$p['assignee_id']])->fetch();}return $state;
    }
    private function validate(string $action,array $p,array $state,int $at): void
    {
        $version=match($action){'policy-publish'=>$state['head']['version'],'roster-set'=>$state['roster']['version'],default=>$state['ticket']['version']};if((int)$version!==$p['version']){throw new HttpError(409,'SUPPORT_VERSION_CONFLICT');}
        if($action==='roster-set'){if(!$this->c->db->execute('SELECT 1 FROM identity_staff WHERE user_id=?',[$p['user_id']])->fetchColumn()||Time::parse($p['roster']['expires_at'])<=$at){throw new HttpError(422,'VALID_STAFF_ROSTER_REQUIRED');}}
        if(in_array($action,['assign','auto','enroll'],true)){
            if(in_array($state['ticket']['status'],['CLOSED','RESOLVED'],true)){throw new HttpError(409,'ACTIVE_TICKET_REQUIRED');}
            if($action==='enroll'){if(($state['work']['policy_id']??null)!==null||$state['head']['policy_id']===null){throw new HttpError(409,'WORK_ENROLLMENT_CONFLICT');}}
            elseif(($state['work']['policy_id']??null)===null){throw new HttpError(409,'WORK_ENROLLMENT_REQUIRED');}
            if($action==='assign'&&$p['assignee_id']!==null&&!(new Eligibility($this->c))->eligible($state['ticket'],$p['assignee_id'],$at)){throw new HttpError(409,'ASSIGNEE_INELIGIBLE');}
        }
    }
    private function apply(array $u,array $s,string $action,array $p,array $state,int $at,string $trace): array
    {
        $db=$this->c->db;$actor=$this->c->identity->principal($u,$s);
        if($action==='policy-publish'){$id=Id::uuid();$this->store->head($p['department_id']);$db->execute('INSERT INTO support_work_policies(id,department_id,revision,spec,actor_id,session_id) VALUES (?,?,?,?,?,?)',[$id,$p['department_id'],$p['version']+1,CanonicalJson::encode($p['policy']),$u['id'],$s['id']]);$db->execute('UPDATE support_work_heads SET policy_id=?,version=version+1 WHERE department_id=?',[$id,$p['department_id']]);$this->store->event(null,$p['department_id'],'POLICY_PUBLISHED',$actor,$trace,['policy_id'=>$id,'revision'=>$p['version']+1]);return ['id'=>$id,'version'=>$p['version']+1];}
        if($action==='roster-set'){$id=Id::uuid();$db->execute('INSERT INTO support_work_rosters(id,department_id,user_id,revision,spec,actor_id,session_id) VALUES (?,?,?,?,?,?,?)',[$id,$p['department_id'],$p['user_id'],$p['version']+1,CanonicalJson::encode($p['roster']),$u['id'],$s['id']]);$db->execute('INSERT INTO support_work_roster_heads(department_id,user_id,roster_id,version) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE roster_id=VALUES(roster_id),version=VALUES(version)',[$p['department_id'],$p['user_id'],$id,$p['version']+1]);$this->store->event(null,$p['department_id'],'ROSTER_PUBLISHED',$actor,$trace,['roster_id'=>$id,'revision'=>$p['version']+1],target:$p['user_id']);return ['id'=>$id,'version'=>$p['version']+1];}
        $t=$db->execute('SELECT * FROM support_tickets WHERE id=? FOR UPDATE',[$p['id']])->fetch();$w=$this->store->work($t);
        if($action==='enroll'){$t=$this->store->bump($t);$source=Id::uuid();$cycle=(new Clocks($this->store))->start($t,$state['head']['policy_id'],$source,$at);$this->store->event($t,$t['department_id'],'MANUAL_ENROLLED',$actor,$trace,['cycle_id'=>$cycle],source:$source,eventId:$source);return ['id'=>$t['id'],'version'=>$t['version'],'cycle_id'=>$cycle];}
        $target=$action==='auto'?null:$p['assignee_id'];$manual=$action!=='auto';$t=(new Assignments($this->store))->set($t,$w,$target,$manual,$manual?($target===null?'MANUAL_QUEUE':'MANUAL'):'AWAITING_CRON',$actor,$trace,$at);return ['id'=>$t['id'],'version'=>(int)$t['version'],'assignee_id'=>$target,'manual_override'=>$manual];
    }
    public function view(string $reference,string $view,array $p): array
    {
        $this->gate();Validation::fields($p,match($view){'detail'=>['id'],'settings'=>['department_id'],default=>['offset']},$view==='detail'?['id']:($view==='settings'?['department_id']:[]));
        return $this->c->identity->locked($reference,'admin',function(array $u,array $s)use($view,$p): array{
            $access=new Access($this->c);$a=$access->actor($u,$s);if(!$a->staff){throw new HttpError(403,'STAFF_REQUIRED');}
            if($view==='settings'){$access->management($u,$s);$access->department(Access::id($p['department_id']));$head=$this->head($p['department_id']);return ['head'=>$head,'policy'=>array_intersect_key($this->store->policy($head['policy_id'])??[],array_flip(['id','revision','data','created_at'])),'rosters'=>$this->c->db->execute('SELECT user_id,roster_id,version FROM support_work_roster_heads WHERE department_id=? ORDER BY user_id LIMIT 64',[$p['department_id']])->fetchAll()];}
            if($view==='detail'){$t=$access->ticket($u,$s,Access::id($p['id']));return $this->summary($t);}
            $offset=$p['offset']??'0';if(!is_string($offset)||!ctype_digit($offset)||(int)$offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}[$where,$params]=$access->listing($a);$rows=$this->c->db->execute("SELECT t.id FROM support_tickets t LEFT JOIN support_work_tickets w ON w.ticket_id=t.id WHERE ".$where." AND (w.assignee_id IS NULL) AND t.status NOT IN ('RESOLVED','CLOSED') ORDER BY t.id LIMIT 50 OFFSET ".(int)$offset,$params)->fetchAll();$out=[];foreach($rows as $r){$t=$access->ticket($u,$s,$r['id']);$out[]=['id'=>$t['id'],'department_id'=>$t['department_id'],'version'=>(int)$t['version'],'assignment'=>'UNASSIGNED'];}return $out;
        });
    }
    private function summary(array $t): array
    {
        $db=$this->c->db;$w=$db->execute('SELECT * FROM support_work_tickets WHERE ticket_id=?',[$t['id']])->fetch();$out=['id'=>$t['id'],'version'=>(int)$t['version'],'department_id'=>$t['department_id'],'assignee_id'=>$w['assignee_id']??null,'manual_override'=>(bool)($w['manual_override']??false),'assignment_reason'=>$w['reason']??'UNCONFIGURED','policy_id'=>$w['policy_id']??null,'cycle_id'=>$w['cycle_id']??null,'clocks'=>[]];if(($w['cycle_id']??null)===null){return $out;}$policy=$this->store->policy($w['policy_id']);$at=Time::now($db);$cal=new Calendar($policy['data']['calendar']);
        $rows=$db->execute("SELECT * FROM support_sla_clocks WHERE cycle_id=? AND (metric<>'NEXT' OR status IN ('RUNNING','PAUSED')) ORDER BY metric,id LIMIT 102",[$w['cycle_id']])->fetchAll();foreach($rows as $clock){$spent=(int)$clock['consumed_us'];if($clock['status']==='RUNNING'){$spent+=$cal->elapsed(Time::parse($clock['last_at']),$at);}$target=(int)$clock['target_us'];$out['clocks'][]=['id'=>$clock['id'],'metric'=>$clock['metric'],'status'=>$clock['status'],'deadline_at'=>$clock['deadline_at'],'remaining_business_minutes'=>Time::minutes($target-$spent),'risk'=>$spent>=$target?'BREACH':($spent>=intdiv($target*$policy['data']['risk_percent']+99,100)?'RISK':'NORMAL'),'evaluated_at'=>$clock['last_at'],'alerts'=>array_column($db->execute('SELECT level FROM support_sla_alerts WHERE clock_id=? ORDER BY level',[$clock['id']])->fetchAll(),'level')];}$out['policy_revision']=(int)$policy['revision'];$out['cycles']=$db->execute('SELECT id,policy_id,department_id,opened_at,closed_at,close_reason FROM support_sla_cycles WHERE ticket_id=? AND department_id=? ORDER BY opened_at DESC,id DESC LIMIT 20',[$t['id'],$t['department_id']])->fetchAll();return $out;
    }
}
