<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Commerce\Services;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Authorization;
final readonly class Eligibility
{
    public function __construct(private Services $c){}
    /** Internal commit fence only; never authenticates a target or creates its session. */
    public function fence(?string $actor=null): void
    {
        $this->c->db->requireTransaction();
        // A locking first read avoids creating a stale REPEATABLE READ snapshot before the fence.
        try{$ids=array_column($this->c->db->execute('SELECT user_id FROM identity_staff ORDER BY user_id LIMIT 65 FOR UPDATE NOWAIT')->fetchAll(),'user_id');if(count($ids)>64){throw new HttpError(409,'SUPPORT_STAFF_BOUND');}if($actor!==null){$ids[]=$actor;}$ids=array_values(array_unique($ids));sort($ids,SORT_STRING);foreach($ids as $id){$this->c->db->execute('SELECT id FROM identity_users WHERE id=? FOR UPDATE NOWAIT',[$id])->fetch();}}catch(\PDOException $e){if(in_array($e->errorInfo[1]??0,[1205,1213,3572],true)){throw new HttpError(409,'SUPPORT_WORK_BUSY');}throw $e;}
    }
    public function eligible(array $t,string $user,int $at): bool
    {
        $db=$this->c->db;if(!$db->execute("SELECT 1 FROM identity_staff f JOIN identity_users u ON u.id=f.user_id JOIN identity_mfa m ON m.user_id=u.id WHERE u.id=? AND u.status='ACTIVE' AND m.enabled=TRUE",[$user])->fetchColumn()||!$db->execute('SELECT 1 FROM support_department_members m JOIN support_departments d ON d.id=m.department_id WHERE m.department_id=? AND m.user_id=? AND d.enabled=TRUE',[$t['department_id'],$user])->fetchColumn()){return false;}
        $row=$db->execute('SELECT r.spec FROM support_work_roster_heads h JOIN support_work_rosters r ON r.id=h.roster_id WHERE h.department_id=? AND h.user_id=?',[$t['department_id'],$user])->fetch();if(!$row){return false;}$r=json_decode($row['spec'],true,32,JSON_THROW_ON_ERROR);
        if(!$r['available']||Time::parse($r['expires_at'])<=$at||!in_array($t['effective_priority'],$r['priorities'],true)||!(new Calendar($r['calendar']))->working($at)){return false;}foreach($r['leaves'] as [$start,$end]){if($at>=Time::parse($start)&&$at<Time::parse($end)){return false;}}
        // Native grant resolver is used for capability selection, not target impersonation.
        $u=$this->c->identity->repository->user($user);$p=$this->c->identity->repository->principal($u,['context'=>'admin','customer_id'=>null,'mfa_verified'=>true,'expiry'=>time()+60,'verified'=>0]);
        try{foreach(['support.ticket.view','support.ticket.reply'] as $permission){(new Authorization())->require($p,$permission,$t['customer_id'],$t['department_id']);}}catch(HttpError){return false;}return true;
    }
    public function candidates(array $t,int $at): array
    {
        $ids=array_column($this->c->db->execute('SELECT user_id FROM support_department_members WHERE department_id=? ORDER BY user_id LIMIT 65',[$t['department_id']])->fetchAll(),'user_id');if(count($ids)>64){throw new HttpError(409,'SUPPORT_STAFF_BOUND');}return array_values(array_filter($ids,fn(string $id): bool=>$this->eligible($t,$id,$at)));
    }
}
