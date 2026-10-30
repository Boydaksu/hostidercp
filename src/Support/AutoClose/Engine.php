<?php
declare(strict_types=1);
namespace Hostider\Support\AutoClose;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Queue\{Queue,Worker};
use Hostider\Core\Http\HttpError;
use Hostider\Support\{Service,Access};
use Hostider\Support\Advanced\Common;
use Hostider\Support\Work\Policy as Bounds;

final readonly class Engine
{
    public function __construct(private Services $c){}
    public static function dedupe(array $p):string
    {
        return 'support-auto-close:'.$p['ticket_id'].':'.$p['ticket_version'].':'.$p['resolution_event'].':'.$p['policy_id'].':'.$p['policy_version'];
    }
    public function candidates(int $limit=25,?string $dep=null,bool $includeQueued=false):array
    {
        Bounds::integer($limit,1,25);if($dep!==null){Access::id($dep);}
        $sql="SELECT t.id ticket_id,t.version ticket_version,t.department_id,p.id policy_id,h.version policy_version,e.id resolution_event FROM support_tickets t JOIN support_departments d ON d.id=t.department_id JOIN support_auto_close_heads h ON h.department_id=d.id JOIN support_auto_close_policies p ON p.id=h.policy_id AND p.department_id=d.id AND p.revision=h.version JOIN support_events e ON e.ticket_id=t.id AND e.customer_id=t.customer_id AND e.to_department=d.id AND e.to_state='RESOLVED' AND (e.from_state IS NULL OR e.from_state<>'RESOLVED') WHERE t.status='RESOLVED' AND d.enabled=TRUE AND p.enabled=TRUE AND TIMESTAMPADD(SECOND,p.wait_seconds,e.created_at)<=UTC_TIMESTAMP(6) AND NOT EXISTS(SELECT 1 FROM support_events later WHERE later.ticket_id=t.id AND later.to_state='RESOLVED' AND (later.from_state IS NULL OR later.from_state<>'RESOLVED') AND later.ticket_version>e.ticket_version)";
        $params=[];if($dep!==null){$sql.=' AND d.id=?';$params[]=$dep;}
        if(!$includeQueued){$sql.=" AND NOT EXISTS(SELECT 1 FROM jobs j WHERE j.dedupe_key=CONCAT('support-auto-close:',t.id,':',t.version,':',e.id,':',p.id,':',h.version))";}
        $rows=$this->c->db->execute($sql.' ORDER BY e.created_at,t.id LIMIT '.$limit,$params)->fetchAll();
        foreach($rows as &$r){$r['ticket_version']=(int)$r['ticket_version'];$r['policy_version']=(int)$r['policy_version'];}unset($r);return $rows;
    }
    public function preview(string $dep):array
    {
        $this->c->db->requireTransaction();$rows=$this->candidates(25,$dep,true);$eligible=0;
        foreach($rows as $p){if((new Eligibility($this->c))->locked($p)!==null){++$eligible;}}
        return ['checked'=>count($rows),'would_close'=>$eligible,'limit'=>25,'effects_allowed'=>false,'live_auto_close_allowed'=>false];
    }
    public function run(int $limit=25,int $seconds=20,bool $dryRun=false):array
    {
        (new Common($this->c))->gate();Bounds::integer($limit,1,25);Bounds::integer($seconds,1,20);
        $rows=$this->candidates($limit,null,$dryRun);$deadline=hrtime(true)+$seconds*1_000_000_000;$queued=0;$eligible=0;
        foreach($rows as $p){
            if(hrtime(true)>=$deadline||memory_get_usage(true)>64*1024*1024){break;}
            if($dryRun){$eligible+=(int)$this->c->db->transaction(fn()=>(new Eligibility($this->c))->locked($p)!==null);continue;}
            $this->c->db->transaction(function()use($p,&$queued):void{
                $dedupe=self::dedupe($p);if($this->c->db->execute('SELECT 1 FROM jobs WHERE dedupe_key=?',[$dedupe])->fetchColumn()){return;}
                (new Queue($this->c->db))->enqueue('support.auto-close',$p,$dedupe,Id::uuid(),true);++$queued;
            });
        }
        return ['selected'=>count($rows),'queued'=>$queued,'would_close'=>$dryRun?$eligible:null,'dry_run'=>$dryRun,'limit'=>$limit,'max_attempts'=>3,'test_only'=>true,'live_auto_close_allowed'=>false];
    }
    public function register(Worker $w):void{$w->register('support.auto-close',fn(array $p):string=>$this->consume($p));}
    public function consume(array $p):string
    {
        (new Common($this->c))->gate();$p=Eligibility::candidate($p);$this->c->db->execute('SET SESSION innodb_lock_wait_timeout=2');
        return $this->c->db->transaction(function()use($p):string{
            $j=$this->c->db->execute("SELECT payload,payload_fingerprint FROM jobs WHERE job_type='support.auto-close' AND dedupe_key=? AND status='RUNNING' AND leased_until>UTC_TIMESTAMP(6) AND payload_purged_at IS NULL FOR UPDATE",[self::dedupe($p)])->fetch();
            $json=CanonicalJson::encode($p);
            if(!$j||CanonicalJson::fromJson($j['payload'])!==$json||!hash_equals(hash('sha256',$json),$j['payload_fingerprint']??'')){throw new HttpError(409,'AUTO_CLOSE_JOB_BINDING');}
            (new Service($this->c))->autoClose($p,Id::uuid());return 'SUCCEEDED';
        });
    }
}
