<?php
declare(strict_types=1);
namespace Hostider\Support\Advanced;
use Hostider\Core\{Id,Validation};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Support\Access;
final readonly class Incidents
{
    public const STATES=['INVESTIGATING','IDENTIFIED','MONITORING','RESOLVED'];
    public function __construct(private Common $m){}
    public function row(array $u,array $s,string $id): array
    {
        $r=$this->m->c->db->execute('SELECT * FROM support_incidents WHERE id=?',[$id])->fetch();if(!$r){throw new HttpError(404,'SUPPORT_NOT_FOUND');}$this->m->department($u,$s,$r['department_id'],'support.incident.manage');return $this->m->c->db->execute('SELECT * FROM support_incidents WHERE id=? FOR UPDATE',[$id])->fetch();
    }
    public function linkable(array $u,array $s,array $t,string $id): array
    {
        $this->m->scoped($u,$s,$t['department_id'],$t['customer_id'],'support.ticket.manage');$this->m->scoped($u,$s,$t['department_id'],$t['customer_id'],'support.incident.manage');(new \Hostider\Support\Merge\Projection($this->m->c))->writable($u,$s,$t);$r=$this->m->c->db->execute('SELECT * FROM support_incidents WHERE id=? FOR UPDATE',[$id])->fetch();if(!$r||$r['department_id']!==$t['department_id']){throw new HttpError(404,'SUPPORT_NOT_FOUND');}if((int)$this->m->c->db->execute('SELECT COUNT(*) FROM support_incident_tickets WHERE incident_id=?',[$id])->fetchColumn()>=1000){throw new HttpError(409,'INCIDENT_LINK_LIMIT');}return $r;
    }
    public function link(array $u,array $s,array $t,string $id,string $trace,?string $execution=null,?string $cause=null): string
    {
        $this->linkable($u,$s,$t,$id);$db=$this->m->c->db;if(!$db->execute('SELECT 1 FROM support_incident_tickets WHERE incident_id=? AND ticket_id=?',[$id,$t['id']])->fetchColumn()){$db->execute('INSERT INTO support_incident_tickets(incident_id,ticket_id,customer_id,actor_id) VALUES (?,?,?,?)',[$id,$t['id'],$t['customer_id'],$u['id']]);(new \Hostider\Support\Work\Store($this->m->c))->bump($t);$this->m->event($t['department_id'],$id,'INCIDENT_LINKED',$execution?new Principal('support-rule',[],customerId:$t['customer_id']):$this->m->access->actor($u,$s),$trace,[],$t,$u['id'],$execution,$cause);}return $id;
    }
    public function create(array $u,array $s,array $p,string $trace): array
    {
        $this->m->department($u,$s,$p['department_id'],'support.incident.manage');$id=Id::uuid();$crypt=fn(string $key,string $v)=>$this->m->crypto->encrypt($v,'incident-'.$key.':'.$id);$this->m->c->db->execute("INSERT INTO support_incidents(id,department_id,title_encrypted,description_encrypted,root_encrypted,affected_encrypted,status,created_by) VALUES (?,?,?,?,?,?,'INVESTIGATING',?)",[$id,$p['department_id'],$crypt('title',$p['title']),$crypt('description',$p['description']),$crypt('root',$p['private_root']),$crypt('affected',$p['affected']),$u['id']]);$initial=Id::uuid();$this->m->c->db->execute('INSERT INTO support_incident_updates(id,incident_id,version,status,public_encrypted,private_encrypted,actor_id) VALUES (?,?,1,?,?,?,?)',[$initial,$id,'INVESTIGATING',$this->m->crypto->encrypt($p['description'],'incident-update:'.$initial),$this->m->crypto->encrypt($p['private_root'],'incident-update-root:'.$initial),$u['id']]);$this->m->event($p['department_id'],$id,'INCIDENT_CREATED',$this->m->access->actor($u,$s),$trace);return ['id'=>$id,'version'=>1];
    }
    public function update(array $u,array $s,array $p,string $trace): array
    {
        $r=$this->row($u,$s,$p['id']);if((int)$r['version']!==$p['version']){throw new HttpError(409,'INCIDENT_VERSION_CONFLICT');}$allowed=match($r['status']){'INVESTIGATING'=>['INVESTIGATING','IDENTIFIED','RESOLVED'],'IDENTIFIED'=>['IDENTIFIED','MONITORING','RESOLVED'],'MONITORING'=>['MONITORING','IDENTIFIED','RESOLVED'],default=>[]};if(!in_array($p['status'],$allowed,true)){throw new HttpError(409,'INCIDENT_TRANSITION_INVALID');}$db=$this->m->c->db;$id=Id::uuid();$v=$p['version']+1;$db->execute('UPDATE support_incidents SET status=?,root_encrypted=?,resolved_at=IF(?=\'RESOLVED\',UTC_TIMESTAMP(6),NULL),version=version+1 WHERE id=?',[$p['status'],$this->m->crypto->encrypt($p['private_root'],'incident-root:'.$r['id']),$p['status'],$r['id']]);$db->execute('INSERT INTO support_incident_updates(id,incident_id,version,status,public_encrypted,private_encrypted,actor_id) VALUES (?,?,?,?,?,?,?)',[$id,$r['id'],$v,$p['status'],$this->m->crypto->encrypt($p['public_update'],'incident-update:'.$id),$this->m->crypto->encrypt($p['private_root'],'incident-update-root:'.$id),$u['id']]);$this->m->event($r['department_id'],$r['id'],'INCIDENT_UPDATED',$this->m->access->actor($u,$s),$trace,['update_id'=>$id,'version'=>$v]);return ['id'=>$r['id'],'version'=>$v,'update_id'=>$id];
    }
    public function view(string $ref,string $context,array $p): array
    {
        $this->m->gate();Validation::fields($p,$context==='client'?['id','ticket_id']:['id'],$context==='client'?['id','ticket_id']:['id']);Access::id($p['id']);return $this->m->c->identity->locked($ref,$context,function($u,$s)use($context,$p){$private=$context==='admin';if($private){$r=$this->row($u,$s,$p['id']);}else{$t=$this->m->access->ticket($u,$s,Access::id($p['ticket_id']));$r=$this->m->c->db->execute('SELECT i.id,i.title_encrypted,i.description_encrypted,i.status,i.started_at,i.resolved_at,i.version FROM support_incidents i JOIN support_incident_tickets l ON l.incident_id=i.id WHERE i.id=? AND l.ticket_id=? AND l.customer_id=? AND i.department_id=?',[$p['id'],$t['id'],$t['customer_id'],$t['department_id']])->fetch();if(!$r){throw new HttpError(404,'SUPPORT_NOT_FOUND');}}
            $id=$r['id'];$out=['id'=>$id,'title'=>$this->m->crypto->decrypt($r['title_encrypted'],'incident-title:'.$id),'description'=>$this->m->crypto->decrypt($r['description_encrypted'],'incident-description:'.$id),'status'=>$r['status'],'started_at'=>$r['started_at'],'resolved_at'=>$r['resolved_at'],'version'=>(int)$r['version'],'updates'=>[],'content_format'=>'PLAIN_TEXT'];foreach($this->m->c->db->execute('SELECT id,status,public_encrypted,created_at FROM support_incident_updates WHERE incident_id=? ORDER BY version DESC LIMIT 50',[$id])->fetchAll() as $v){$out['updates'][]=['id'=>$v['id'],'status'=>$v['status'],'body'=>$this->m->crypto->decrypt($v['public_encrypted'],'incident-update:'.$v['id']),'created_at'=>$v['created_at']];}if($private){$out['private_root']=$this->m->crypto->decrypt($r['root_encrypted'],'incident-root:'.$id);$out['affected']=$this->m->crypto->decrypt($r['affected_encrypted'],'incident-affected:'.$id);}return $out;});
    }
}
