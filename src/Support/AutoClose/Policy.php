<?php
declare(strict_types=1);
namespace Hostider\Support\AutoClose;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,Idempotency,Validation};
use Hostider\Core\Http\HttpError;
use Hostider\Support\{Access,Service};
use Hostider\Support\Advanced\Common;
use Hostider\Support\Work\{Eligibility as WorkFence,Store};

final readonly class Policy
{
    public Common $m;
    public function __construct(public Services $c){$this->m=new Common($c);}
    public function head(string $dep):array
    {
        $this->c->db->requireTransaction();
        return $this->c->db->execute('SELECT department_id,policy_id,version FROM support_auto_close_heads WHERE department_id=? FOR UPDATE',[$dep])->fetch()
            ?:['department_id'=>$dep,'policy_id'=>null,'version'=>0];
    }
    public function authorize(array $u,array $s,string $dep):void
    {
        $this->m->access->management($u,$s);
        foreach(['support.ticket.view','support.ticket.manage'] as $p){$this->m->department($u,$s,$dep,$p);}
    }
    private function payload(array $p):array
    {
        Validation::fields($p,['department_id','version','enabled','wait_seconds'],['department_id','version','enabled','wait_seconds']);
        Access::id($p['department_id']);Common::version($p['version'],true);
        if(!is_bool($p['enabled'])||($p['enabled']?(!is_int($p['wait_seconds'])||$p['wait_seconds']<1||$p['wait_seconds']>31536000):$p['wait_seconds']!==null)){
            throw new HttpError(422,'AUTO_CLOSE_POLICY_INVALID');
        }return $p;
    }
    public function command(string $ref,string $route,array $body,string $key,string $trace):array
    {
        $this->m->gate();Validation::fields($body,['action','payload','confirmation'],['action','payload']);
        if($body['action']!=='policy-set'||!in_array($route,['prepare','execute'],true)){throw new HttpError(422,'AUTO_CLOSE_ACTION_INVALID');}
        $p=$this->payload(Common::object($body['payload']));
        $work=function(bool $replay)use($ref,$route,$body,$p,$trace):array{
            (new WorkFence($this->c))->fence();
            return $this->c->identity->withStepUp($ref,'admin',function($u,$s)use($route,$body,$p,$trace,$replay):array{
                $this->authorize($u,$s,$p['department_id']);if($replay){return [];}
                $d=$this->m->access->department($p['department_id']);$h=$this->head($d['id']);
                if((int)$h['version']!==$p['version']){throw new HttpError(409,'AUTO_CLOSE_POLICY_STALE');}
                if($p['enabled']&&!$d['enabled']){throw new HttpError(409,'AUTO_CLOSE_DEPARTMENT_DISABLED');}
                $state=['department_version'=>(int)$d['version'],'head'=>$h];$db=$this->c->db;
                if($route==='prepare'){
                    $id=Id::uuid();$db->execute("INSERT INTO support_work_confirmations(id,actor_id,session_id,identity_version,action,payload_hmac,state_hmac,expires_at) VALUES (?,?,?,?,'AUTO_CLOSE_POLICY',?,?,TIMESTAMPADD(SECOND,300,UTC_TIMESTAMP(6)))",[$id,$u['id'],$s['id'],$u['identity_version'],$this->m->hash($p),$this->m->hash($state)]);
                    $this->c->identity->confirmationEvent($u['id'],$id,false);
                    return ['id'=>$id,'version'=>$p['version'],'proposed'=>$p,'expires_in'=>300,'effect'=>'DEVELOPMENT_ONLY_OPT_IN_AUTO_CLOSE'];
                }
                $a=Common::object($body['confirmation']??[]);Validation::fields($a,['id','accepted'],['id','accepted']);Access::id($a['id']);
                if($a['accepted']!==true){throw new HttpError(403,'CONFIRMATION_REQUIRED');}
                $r=$db->execute('SELECT *,UNIX_TIMESTAMP(expires_at) expiry FROM support_work_confirmations WHERE id=? FOR UPDATE',[$a['id']])->fetch();
                if(!$r||$r['used_at']!==null||$r['actor_id']!==$u['id']||$r['session_id']!==$s['id']||(int)$r['identity_version']!==(int)$u['identity_version']
                    ||$r['action']!=='AUTO_CLOSE_POLICY'||(int)$r['expiry']<=time()||!hash_equals($r['payload_hmac'],$this->m->hash($p))||!hash_equals($r['state_hmac'],$this->m->hash($state))){
                    throw new HttpError(409,'AUTO_CLOSE_CONFIRMATION_INVALID');
                }
                $id=Id::uuid();$v=$p['version']+1;
                $db->execute('INSERT INTO support_auto_close_policies(id,department_id,revision,enabled,wait_seconds,actor_id,session_id,identity_version) VALUES (?,?,?,?,?,?,?,?)',[$id,$d['id'],$v,(int)$p['enabled'],$p['wait_seconds'],$u['id'],$s['id'],$u['identity_version']]);
                $db->execute('INSERT INTO support_auto_close_heads(department_id,policy_id,version) VALUES (?,?,?) ON DUPLICATE KEY UPDATE policy_id=VALUES(policy_id),version=VALUES(version)',[$d['id'],$id,$v]);
                $db->execute('UPDATE support_work_confirmations SET used_at=UTC_TIMESTAMP(6) WHERE id=?',[$r['id']]);
                (new Store($this->c))->event(null,$d['id'],'AUTO_CLOSE_POLICY',$this->m->access->actor($u,$s),$trace,['policy_id'=>$id,'revision'=>$v,'enabled'=>$p['enabled'],'wait_seconds'=>$p['wait_seconds']]);
                $this->c->identity->confirmationEvent($u['id'],$r['id'],true);return ['id'=>$id,'version'=>$v,'enabled'=>$p['enabled'],'wait_seconds'=>$p['wait_seconds']];
            });
        };
        return (new Idempotency($this->c->db))->run($ref,'support-auto-close:'.$route,$key,['hmac'=>$this->m->hash($body)],fn()=>$work(false),fn()=>$work(true));
    }
    public function view(string $ref,string $view,array $p):array
    {
        $this->m->gate();Validation::fields($p,['department_id'],['department_id']);$dep=Access::id($p['department_id']);
        return $this->c->db->transaction(function()use($ref,$view,$dep):array{
            (new WorkFence($this->c))->fence();
            return $this->c->identity->withStepUp($ref,'admin',function($u,$s)use($view,$dep):array{
                $this->authorize($u,$s,$dep);
                if($view==='preview'){return (new Engine($this->c))->preview($dep);}
                $h=$this->head($dep);$r=$h['policy_id']===null?null:$this->c->db->execute('SELECT enabled,wait_seconds,created_at FROM support_auto_close_policies WHERE id=?',[$h['policy_id']])->fetch();
                return ['department_id'=>$dep,'version'=>(int)$h['version'],'enabled'=>(bool)($r['enabled']??false),'wait_seconds'=>($r['wait_seconds']??null)===null?null:(int)$r['wait_seconds'],
                    'created_at'=>$r['created_at']??null,'last_success'=>$this->c->db->execute("SELECT id,policy_version,closed_at,actor_kind FROM support_auto_close_events WHERE department_id=? ORDER BY closed_at DESC,id DESC LIMIT 1",[$dep])->fetch()?:null,
                    'recent'=>$this->c->db->execute("SELECT policy_version,closed_at,actor_kind FROM support_auto_close_events WHERE department_id=? ORDER BY closed_at DESC,id DESC LIMIT 20",[$dep])->fetchAll(),'jobs'=>$this->c->db->execute("SELECT status,COUNT(*) total FROM jobs WHERE job_type='support.auto-close' AND payload_purged_at IS NULL AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.department_id'))=? GROUP BY status ORDER BY status",[$dep])->fetchAll(),'live_auto_close_allowed'=>false];
            });
        });
    }
}
