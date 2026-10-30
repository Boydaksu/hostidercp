<?php
declare(strict_types=1);
namespace Hostider\Support\Merge;
use Hostider\Commerce\Services;
use Hostider\Core\Http\HttpError;
use Hostider\Support\Access;
use Hostider\Support\Advanced\Common;
final readonly class Projection
{
    public function __construct(private Services $c){}
    public function edge(string $id):?array
    {
        return $this->c->db->execute('SELECT e.* FROM support_ticket_merges e JOIN support_merge_members m ON m.merge_id=e.id WHERE m.ticket_id=?',[$id])->fetch()?:null;
    }
    public function alias(array $u,array $s,array $t):?array
    {
        $e=$this->edge($t['id']);if($e===null){return null;}$a=new Access($this->c);$tickets=[];
        foreach([$e['source_id'],$e['target_id']] as $id){$row=$this->c->db->execute('SELECT * FROM support_tickets WHERE id=?',[$id])->fetch();if(!$row||$row['customer_id']!==$e['customer_id']||$row['department_id']!==$e['department_id']){throw new HttpError(404,'SUPPORT_NOT_FOUND');}$a->require($u,$s,$row['customer_id'],$row['department_id']);$tickets[$id]=$row;}
        return ['source_id'=>$e['source_id'],'source_number'=>$e['source_number'],'target_id'=>$e['target_id'],'target_number'=>$tickets[$e['target_id']]['number'],'source_version'=>(int)$tickets[$e['source_id']]['version'],'target_version'=>(int)$tickets[$e['target_id']]['version'],'read_only'=>$t['id']===$e['source_id'],'created_at'=>$e['created_at']];
    }
    public function writable(array $u,array $s,array $t):void
    {
        $e=$this->edge($t['id']);if($e!==null&&$e['source_id']===$t['id']){$a=$this->alias($u,$s,$t);throw new HttpError(409,'SUPPORT_TICKET_MERGED_TARGET_'.$a['target_id']);}
    }
    /** Combined messages are a bounded read projection. Attachments retain the origin ticket. */
    public function augment(array $u,array $s,array $t,array $out,int $offset=0):array
    {
        $alias=$this->alias($u,$s,$t);if($alias===null){return $out;}$m=new Common($this->c);$a=$m->access->actor($u,$s);$notes=false;
        if($a->staff){try{$m->access->require($u,$s,$t['customer_id'],$t['department_id'],'support.ticket.internal_note');$notes=true;}catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}}}
        $rows=$this->c->db->execute('SELECT m.id,m.ticket_id origin_ticket_id,t.number origin_ticket_number,m.type,m.customer_visible,m.author_id,m.created_at,m.body_encrypted FROM support_messages m JOIN support_tickets t ON t.id=m.ticket_id AND t.customer_id=m.customer_id WHERE m.ticket_id IN (?,?) AND m.customer_id=?'.($notes?'':" AND m.customer_visible=TRUE AND m.type<>'internal_note'").' ORDER BY m.created_at DESC,m.id DESC LIMIT 51 OFFSET '.$offset,[$alias['source_id'],$alias['target_id'],$t['customer_id']])->fetchAll();
        $out['older_messages']=count($rows)>50;$out['messages']=[];
        foreach(array_reverse(array_slice($rows,0,50)) as $row){$row['body']=$m->crypto->decrypt($row['body_encrypted'],'support-message:'.$row['id']);unset($row['body_encrypted']);$origin=$this->c->db->execute('SELECT origin FROM support_mail_sources WHERE message_id=? AND customer_id=?',[$row['id'],$t['customer_id']])->fetchColumn();if($origin){$row['mail_origin']=$origin;}if($notes&&$row['type']==='internal_note'){$system=$this->c->db->execute("SELECT execution_id,sponsor_id FROM support_advanced_events WHERE ticket_id=? AND customer_id=? AND kind='ACTION_note' AND execution_id IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.canonical_effect'))=? LIMIT 1",[$row['origin_ticket_id'],$t['customer_id'],$row['id']])->fetch();if($system){$row['system_origin']=['actor_kind'=>'SYSTEM_RULE','execution_id'=>$system['execution_id'],'sponsor_id'=>$system['sponsor_id']];}}$row['customer_visible']=(bool)$row['customer_visible'];$row['can_attach']=false;if($row['origin_ticket_id']===$alias['target_id']&&$row['author_id']===$u['id']&&$row['customer_visible']&&$row['type']!=='internal_note'){try{$m->access->require($u,$s,$t['customer_id'],$t['department_id'],'support.ticket.reply');$row['can_attach']=true;}catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}}}if(!$a->staff){unset($row['author_id']);}$out['messages'][]=$row;}
        $kinds=$notes?['CREATED','CUSTOMER_REPLY','STAFF_REPLY','INTERNAL_NOTE','STATE','MOVE']:['CREATED','CUSTOMER_REPLY','STAFF_REPLY','STATE','MOVE'];
        $out['timeline']=$this->c->db->execute('SELECT ticket_id origin_ticket_id,kind,ticket_version,from_state,to_state,created_at FROM support_events WHERE ticket_id IN (?,?) AND customer_id=? AND kind IN ('.implode(',',array_fill(0,count($kinds),'?')).') ORDER BY created_at DESC,id DESC LIMIT 20',[$alias['source_id'],$alias['target_id'],$t['customer_id'],...$kinds])->fetchAll();
        $out['merge_alias']=$alias;
        if($alias['read_only']){foreach($out['capabilities']??[] as $key=>$value){$out['capabilities'][$key]=false;}$out['transitions']=[];if(isset($out['csat'])){$out['csat']['eligible']=false;}}
        return $out;
    }
}
