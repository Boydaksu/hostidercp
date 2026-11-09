<?php
declare(strict_types=1);
namespace Hostider\Support\AutoClose;
use Hostider\Commerce\Services;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Authorization,AccessGrant};
use Hostider\Support\{Access,Service};
use Hostider\Support\Advanced\Common;
use Hostider\Support\Work\{Eligibility as WorkFence,Time};

final readonly class Eligibility
{
    public function __construct(private Services $c){}
    public static function candidate(array $p):array
    {
        \Hostider\Core\Validation::fields($p,['ticket_id','ticket_version','department_id','policy_id','policy_version','resolution_event'],['ticket_id','ticket_version','department_id','policy_id','policy_version','resolution_event']);
        foreach(['ticket_id','department_id','policy_id','resolution_event'] as $k){Access::id($p[$k]);}
        Service::version($p['ticket_version']);Service::version($p['policy_version']);return $p;
    }
    /** Native capability selection, like Work.Eligibility; no login/session/impersonation is created. */
    private function authority(array $p,array $t):bool
    {
        $u=$this->c->identity->repository->user($p['actor_id']);
        if($u['status']!=='ACTIVE'||!$u['administrative']||(int)$u['identity_version']!==(int)$p['identity_version']
            ||!$this->c->db->execute('SELECT 1 FROM identity_mfa WHERE user_id=? AND enabled=TRUE',[$u['id']])->fetchColumn()
            ||!$this->c->db->execute('SELECT 1 FROM support_department_members WHERE user_id=? AND department_id=?',[$u['id'],$t['department_id']])->fetchColumn()){return false;}
        $a=$this->c->identity->repository->principal($u,['context'=>'admin','customer_id'=>null,'mfa_verified'=>true,'expiry'=>time()+60,'verified'=>0]);
        (new Authorization())->require($a,'identity.staff.manage',administrative:true);
        foreach(['support.ticket.view','support.ticket.manage'] as $permission){
            $covered=false;foreach($a->grants as $g){if($g instanceof AccessGrant&&in_array('*',$g->customers,true)&&$g->covers($permission,$t['customer_id'],null,$t['department_id'])){$covered=true;break;}}
            if(!$covered){return false;}(new Authorization())->require($a,$permission,$t['customer_id'],$t['department_id']);
        }return true;
    }
    public function locked(array $candidate):?array
    {
        (new Common($this->c))->gate();$db=$this->c->db;$db->requireTransaction();$p=self::candidate($candidate);
        (new WorkFence($this->c))->fence();$d=(new Access($this->c))->department($p['department_id']);
        $h=(new Policy($this->c))->head($d['id']);if(!$d['enabled']||$h['policy_id']!==$p['policy_id']||(int)$h['version']!==$p['policy_version']){return null;}
        $policy=$db->execute('SELECT * FROM support_auto_close_policies WHERE id=? AND department_id=? AND revision=? FOR UPDATE',[$p['policy_id'],$d['id'],$p['policy_version']])->fetch();
        if(!$policy||!$policy['enabled']){return null;}
        $t=$db->execute('SELECT * FROM support_tickets WHERE id=? FOR UPDATE',[$p['ticket_id']])->fetch();
        if($t&&$db->execute("SELECT 1 FROM support_merge_members WHERE ticket_id=? AND side='SOURCE'",[$t['id']])->fetchColumn()){return null;}
        if(!$t||$t['department_id']!==$d['id']||$t['status']!=='RESOLVED'||(int)$t['version']!==$p['ticket_version']){return null;}
        $e=$db->execute("SELECT id,created_at,ticket_version,to_department FROM support_events WHERE ticket_id=? AND customer_id=? AND to_state='RESOLVED' AND (from_state IS NULL OR from_state<>'RESOLVED') ORDER BY ticket_version DESC,id DESC LIMIT 1 FOR UPDATE",[$t['id'],$t['customer_id']])->fetch();
        if(!$e||$e['id']!==$p['resolution_event']||$e['to_department']!==$d['id']||(int)$e['ticket_version']>(int)$t['version']
            ||Time::now($db)<Time::parse($e['created_at'])+(int)$policy['wait_seconds']*1_000_000
            ||$db->execute('SELECT 1 FROM support_auto_close_events WHERE ticket_id=? AND resolution_event=?',[$t['id'],$e['id']])->fetchColumn()
            ||$db->execute("SELECT 1 FROM support_events WHERE ticket_id=? AND kind='CUSTOMER_REPLY' AND ticket_version>? LIMIT 1",[$t['id'],$e['ticket_version']])->fetchColumn()
            ||$db->execute("SELECT 1 FROM support_sla_clocks c JOIN support_sla_cycles y ON y.id=c.cycle_id WHERE y.ticket_id=? AND c.status IN ('RUNNING','PAUSED') LIMIT 1",[$t['id']])->fetchColumn()){return null;}
        try{if(!$this->authority($policy,$t)){return null;}}catch(HttpError $error){if(!in_array($error->status,[401,403,404],true)){throw $error;}return null;}
        return ['ticket'=>$t,'department'=>$d,'policy'=>$policy,'resolution'=>$e];
    }
}
