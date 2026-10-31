<?php
declare(strict_types=1);
namespace Hostider\Support\Split;
use Hostider\Commerce\Services;
use Hostider\Core\Http\HttpError;
use Hostider\Support\Access;
use Hostider\Support\Advanced\Common;
final readonly class Projection
{
 public function __construct(private Services $c){}
 public function edge(string $id,bool $lock=false):?array{if($lock){$this->c->db->requireTransaction();}return $this->c->db->execute('SELECT e.* FROM support_ticket_splits e JOIN support_split_members m ON m.split_id=e.id WHERE m.ticket_id=?'.($lock?' FOR UPDATE':''),[$id])->fetch()?:null;}
 public function scope(array $u,array $s,array $e):array{
  $a=new Access($this->c);$rows=[];$ids=[$e['source_id'],$e['child_id']];sort($ids,SORT_STRING);foreach($ids as $id){$t=$a->ticket($u,$s,$id);if($t['customer_id']!==$e['customer_id']||$t['department_id']!==$e['department_id']||$t['requester_id']!==$e['requester_id']){throw new HttpError(404,'SUPPORT_NOT_FOUND');}$rows[$id]=$t;}return $rows;
 }
 public function fileScope(array $u,array $s,array $file):void{
  $e=$this->c->db->execute('SELECT e.* FROM support_ticket_splits e JOIN support_split_messages m ON m.split_id=e.id WHERE m.message_id=? FOR UPDATE',[$file['message_id']])->fetch();if(!$e){return;}if($file['ticket_id']!==$e['source_id']||$file['customer_id']!==$e['customer_id']){throw new HttpError(404,'MAIL_NOT_FOUND');}$this->scope($u,$s,$e);
 }
 public function messageWritable(string $message):void{if($this->c->db->execute('SELECT 1 FROM support_split_messages WHERE message_id=? FOR UPDATE',[$message])->fetchColumn()){throw new HttpError(409,'SPLIT_HISTORICAL_MESSAGE_READ_ONLY');}}
 private function message(array $u,array $s,array $t,array $row,?array $e=null):array{
  $m=new Common($this->c);$a=$m->access->actor($u,$s);$row['body']=$m->crypto->decrypt($row['body_encrypted'],'support-message:'.$row['id']);unset($row['body_encrypted']);$row['customer_visible']=(bool)$row['customer_visible'];$transferred=$e!==null&&$row['origin_ticket_id']===$e['source_id'];$row['can_attach']=false;
  if(!$transferred&&$row['origin_ticket_id']===$t['id']&&$row['author_id']===$u['id']&&$row['customer_visible']&&$row['type']!=='internal_note'){try{$m->access->require($u,$s,$t['customer_id'],$t['department_id'],'support.ticket.reply');$row['can_attach']=true;}catch(HttpError $x){if(!in_array($x->status,[403,404],true)){throw $x;}}}
  if($transferred){$row['split_id']=$e['id'];$row['transferred']=true;$row['split_child_id']=$e['child_id'];}
  $origin=$this->c->db->execute('SELECT origin FROM support_mail_sources WHERE message_id=? AND customer_id=?',[$row['id'],$t['customer_id']])->fetchColumn();if($origin){$row['mail_origin']=$origin;}
  if($a->staff&&$row['type']==='internal_note'){$sys=$this->c->db->execute("SELECT execution_id,sponsor_id FROM support_advanced_events WHERE ticket_id=? AND customer_id=? AND kind='ACTION_note' AND execution_id IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.canonical_effect'))=? LIMIT 1",[$row['origin_ticket_id'],$t['customer_id'],$row['id']])->fetch();if($sys){$row['system_origin']=['actor_kind'=>'SYSTEM_RULE',...$sys];}}
  if(!$a->staff){unset($row['author_id']);}return $row;
 }
 public function augment(array $u,array $s,array $t,array $out,int $offset=0):array{
  $db=$this->c->db;$e=$this->edge($t['id'],true);
  if($e===null){$out['public_message_count']=(int)$db->execute("SELECT COUNT(*) FROM support_messages WHERE ticket_id=? AND customer_visible=TRUE AND type<>'internal_note'",[$t['id']])->fetchColumn();return $out;}
  $tickets=$this->scope($u,$s,$e);$source=$t['id']===$e['source_id'];$m=new Common($this->c);$a=$m->access->actor($u,$s);$notes=false;if($a->staff){try{$m->access->require($u,$s,$t['customer_id'],$t['department_id'],'support.ticket.internal_note');$notes=true;}catch(HttpError $x){if(!in_array($x->status,[403,404],true)){throw $x;}}}
  $columns='m.id,m.ticket_id origin_ticket_id,t.number origin_ticket_number,m.type,m.customer_visible,m.author_id,m.created_at,m.body_encrypted';
  $where=$source?'m.ticket_id=? AND NOT EXISTS(SELECT 1 FROM support_split_messages x WHERE x.message_id=m.id)':'(m.ticket_id=? OR EXISTS(SELECT 1 FROM support_split_messages x WHERE x.split_id=? AND x.message_id=m.id AND m.customer_visible=TRUE AND m.type<>\'internal_note\'))';$params=$source?[$t['id']]:[$t['id'],$e['id']];$where.=' AND m.customer_id=?';$params[]=$t['customer_id'];if(!$notes){$where.=" AND m.customer_visible=TRUE AND m.type<>'internal_note'";}
  $rows=$db->execute('SELECT '.$columns.' FROM support_messages m JOIN support_tickets t ON t.id=m.ticket_id AND t.customer_id=m.customer_id WHERE '.$where.' ORDER BY m.created_at DESC,m.id DESC LIMIT 51 OFFSET '.$offset,$params)->fetchAll();$out['older_messages']=count($rows)>50;$out['messages']=[];foreach(array_reverse(array_slice($rows,0,50)) as $row){$mapped=(!$source&&$row['origin_ticket_id']===$e['source_id'])?$e:null;$out['messages'][]=$this->message($u,$s,$t,$row,$mapped);}
  $out['public_message_count']=(int)$db->execute('SELECT COUNT(*) FROM support_messages m WHERE '.$where." AND m.customer_visible=TRUE AND m.type<>'internal_note'",$params)->fetchColumn();
  $selected=$db->execute('SELECT '.$columns.' FROM support_split_messages x JOIN support_messages m ON m.id=x.message_id AND m.ticket_id=x.source_id AND m.customer_id=x.customer_id JOIN support_tickets t ON t.id=m.ticket_id WHERE x.split_id=? ORDER BY m.created_at,m.id LIMIT 25',[$e['id']])->fetchAll();$out['split_history']=[];if($source){foreach($selected as $row){$out['split_history'][]=$this->message($u,$s,$t,$row,$e);}}
  $out['split_relation']=['id'=>$e['id'],'source_id'=>$e['source_id'],'source_number'=>$tickets[$e['source_id']]['number'],'source_version'=>(int)$tickets[$e['source_id']]['version'],'child_id'=>$e['child_id'],'child_number'=>$tickets[$e['child_id']]['number'],'child_version'=>(int)$tickets[$e['child_id']]['version'],'side'=>$source?'SOURCE':'CHILD','created_at'=>$e['created_at']];
  if(isset($out['capabilities'])){$out['capabilities']['split']=false;$out['capabilities']['merge']=false;}
  $kinds=$notes?['CREATED','SPLIT_CREATED','CUSTOMER_REPLY','STAFF_REPLY','INTERNAL_NOTE','STATE','MOVE']:['CREATED','SPLIT_CREATED','CUSTOMER_REPLY','STAFF_REPLY','STATE','MOVE'];
  $timeline=$db->execute('SELECT v.ticket_id origin_ticket_id,v.subject_id message_id,v.kind,v.ticket_version,v.from_state,v.to_state,v.created_at,x.split_id,x.child_id split_child_id FROM support_events v LEFT JOIN support_split_messages x ON x.message_id=v.subject_id AND x.split_id=? WHERE (v.ticket_id=?'.($source?'':' OR x.split_id IS NOT NULL').') AND v.customer_id=? AND v.kind IN ('.implode(',',array_fill(0,count($kinds),'?')).') ORDER BY v.created_at DESC,v.id DESC LIMIT 20',[$e['id'],$t['id'],$t['customer_id'],...$kinds])->fetchAll();$out['timeline']=$timeline;return $out;
 }
}
