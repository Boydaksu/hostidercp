<?php
declare(strict_types=1);
namespace Hostider\Support\Split;
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
 private function payload(array $p):array{
  Validation::fields($p,['source_id','version','message_ids','subject'],['source_id','version','message_ids','subject']);Access::id($p['source_id']);Service::version($p['version']);Common::text($p['subject'],191);
  if(!is_array($p['message_ids'])||!array_is_list($p['message_ids'])||count($p['message_ids'])<1||count($p['message_ids'])>25){throw new HttpError(422,'SPLIT_MESSAGE_LIMIT');}foreach($p['message_ids'] as $id){Access::id($id);}if(count(array_unique($p['message_ids']))!==count($p['message_ids'])){throw new HttpError(422,'SPLIT_DUPLICATE_MESSAGE');}sort($p['message_ids'],SORT_STRING);return $p;
 }
 private function source(array $u,array $s,array $p):array{
  $t=$this->m->access->ticket($u,$s,$p['source_id'],'support.ticket.split');$this->m->access->require($u,$s,$t['customer_id'],$t['department_id'],'support.ticket.manage');
  if(!(bool)$this->m->access->actor($u,$s)->staff||!$this->c->db->execute('SELECT 1 FROM identity_mfa WHERE user_id=? AND enabled=TRUE FOR UPDATE',[$u['id']])->fetchColumn()){throw new HttpError(403,'SPLIT_STAFF_MFA_REQUIRED');}return $t;
 }
 private function state(array $t,array $p):array{
  $db=$this->c->db;if((int)$t['version']!==$p['version']){throw new HttpError(409,'SPLIT_VERSION_CONFLICT');}
  if((new \Hostider\Support\Merge\Projection($this->c))->edge($t['id'],true)!==null||(new Projection($this->c))->edge($t['id'],true)!==null){throw new HttpError(409,'SPLIT_COMBINATION_BLOCKED');}
  $d=$this->m->access->department($t['department_id']);if(!$d['enabled']){throw new HttpError(409,'SPLIT_DEPARTMENT_DISABLED');}
  $requester=$this->c->identity->repository->user($t['requester_id'],true);if($requester['status']!=='ACTIVE'||!$requester['verified_at']||!$db->execute('SELECT 1 FROM identity_memberships WHERE user_id=? AND customer_id=? FOR UPDATE',[$t['requester_id'],$t['customer_id']])->fetchColumn()){throw new HttpError(409,'SPLIT_REQUESTER_UNAVAILABLE');}
  $publicCount=(int)$db->execute("SELECT COUNT(*) FROM support_messages WHERE ticket_id=? AND customer_id=? AND customer_visible=TRUE AND type IN ('customer_reply','staff_reply') FOR UPDATE",[$t['id'],$t['customer_id']])->fetchColumn();$messages=$db->execute('SELECT id,type,customer_visible,author_id,created_at FROM support_messages WHERE ticket_id=? AND customer_id=? AND id IN ('.implode(',',array_fill(0,count($p['message_ids']),'?')).') ORDER BY id FOR UPDATE',[$t['id'],$t['customer_id'],...$p['message_ids']])->fetchAll();$public=[];foreach($messages as $m){if($m['customer_visible']&&in_array($m['type'],['customer_reply','staff_reply'],true)){$public[$m['id']]=$m;}}
  foreach($p['message_ids'] as $id){if(!isset($public[$id])){throw new HttpError(404,'SPLIT_MESSAGE_NOT_AVAILABLE');}}
  if($publicCount<=count($p['message_ids'])){throw new HttpError(409,'SPLIT_SOURCE_PUBLIC_REQUIRED');}
  if($db->execute("SELECT 1 FROM jobs WHERE status='RUNNING' AND payload_purged_at IS NULL AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.ticket_id'))=? LIMIT 1 FOR UPDATE",[$t['id']])->fetchColumn()||$db->execute("SELECT 1 FROM support_mail_outbound WHERE ticket_id=? AND status IN ('PENDING','RETRYABLE_FAILURE') AND attempt_count>0 LIMIT 1 FOR UPDATE",[$t['id']])->fetchColumn()){throw new HttpError(409,'SPLIT_OPERATION_UNCERTAIN');}
  if($db->execute("SELECT 1 FROM schema_migrations WHERE status<>'APPLIED' LIMIT 1 FOR UPDATE")->fetchColumn()||$db->execute("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='f6b_runs'")->fetchColumn()){throw new HttpError(409,'SPLIT_MIGRATION_BLOCKED');}
  $heads=[];foreach(['support_work_heads','support_auto_close_heads'] as $table){$heads[$table]=$db->execute('SELECT * FROM '.$table.' WHERE department_id=? FOR UPDATE',[$d['id']])->fetch()?:null;}
  return ['source'=>[$t['id'],(int)$t['version'],$t['customer_id'],$t['department_id'],$t['requester_id'],$t['status']],'department_version'=>(int)$d['version'],'messages'=>array_values(array_intersect_key($public,array_flip($p['message_ids']))),'public_count'=>$publicCount,'heads'=>$heads];
 }
 public function command(string $ref,string $route,array $body,string $key,string $trace):array{
  $this->m->gate();Validation::fields($body,['action','payload','confirmation'],['action','payload']);if($body['action']!=='split'||!in_array($route,['prepare','execute'],true)){throw new HttpError(422,'SPLIT_ACTION_INVALID');}$p=$this->payload(Common::object($body['payload']));
  $work=function(bool $replay)use($ref,$route,$body,$p,$trace):array{
   $hint=$this->c->db->execute('SELECT requester_id FROM support_tickets WHERE id=?',[$p['source_id']])->fetchColumn();(new Eligibility($this->c))->fence(is_string($hint)?$hint:null);
   return $this->c->identity->withStepUp($ref,'admin',function($u,$s)use($route,$body,$p,$trace,$replay,$hint):array{
    $t=$this->source($u,$s,$p);if($t['requester_id']!==$hint){throw new HttpError(409,'SPLIT_SCOPE_CHANGED');}
    if($replay){$edge=(new Projection($this->c))->edge($t['id'],true);if($edge!==null){(new Projection($this->c))->scope($u,$s,$edge);}return [];}
    $state=$this->state($t,$p);$state['authorization_epoch']=(new \Hostider\Support\UI\Context($this->m))->epoch($u,$s);$db=$this->c->db;
    if($route==='prepare'){$id=Id::uuid();$db->execute("INSERT INTO support_work_confirmations(id,actor_id,session_id,identity_version,action,payload_hmac,state_hmac,expires_at) VALUES (?,?,?,?,'TICKET_SPLIT',?,?,TIMESTAMPADD(SECOND,300,UTC_TIMESTAMP(6)))",[$id,$u['id'],$s['id'],$u['identity_version'],$this->m->hash($p),$this->m->hash($state)]);$this->c->identity->confirmationEvent($u['id'],$id,false);$preview=[];foreach($state['messages'] as $m){$m['body']=$this->m->crypto->decrypt($db->execute('SELECT body_encrypted FROM support_messages WHERE id=?',[$m['id']])->fetchColumn(),'support-message:'.$m['id']);$preview[]=$m;}return ['id'=>$id,'expires_in'=>300,'preview'=>$preview,'source_number'=>$t['number'],'effect'=>'NEW_INDEPENDENT_TEST_TICKET_NO_MAIL','live_split_allowed'=>false];}
    $a=Common::object($body['confirmation']??[]);Validation::fields($a,['id','accepted'],['id','accepted']);Access::id($a['id']);if($a['accepted']!==true){throw new HttpError(403,'CONFIRMATION_REQUIRED');}$v=$db->execute('SELECT *,UNIX_TIMESTAMP(expires_at) expiry FROM support_work_confirmations WHERE id=? FOR UPDATE',[$a['id']])->fetch();
    if(!$v||$v['used_at']!==null||$v['actor_id']!==$u['id']||$v['session_id']!==$s['id']||(int)$v['identity_version']!==(int)$u['identity_version']||$v['action']!=='TICKET_SPLIT'||(int)$v['expiry']<=time()||!hash_equals($v['payload_hmac'],$this->m->hash($p))||!hash_equals($v['state_hmac'],$this->m->hash($state))){throw new HttpError(409,'SPLIT_CONFIRMATION_INVALID');}
    $child=(new Service($this->c))->createSplit($u,$s,$t,$p['subject'],$trace);$store=new Store($this->c);$t=$store->bump($t);$id=Id::uuid();$details=['split_id'=>$id,'source_id'=>$t['id'],'child_id'=>$child['id'],'message_ids'=>$p['message_ids']];$actor=$this->m->access->actor($u,$s);$store->event($t,$t['department_id'],'SPLIT_SOURCE',$actor,$trace,$details,eventId:$id);$store->event($child,$child['department_id'],'SPLIT_CHILD',$actor,$trace,$details,source:$id);
    $db->execute('INSERT INTO support_ticket_splits(id,source_id,child_id,customer_id,department_id,requester_id,source_version,actor_id,session_id,identity_version) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$t['id'],$child['id'],$t['customer_id'],$t['department_id'],$t['requester_id'],$p['version'],$u['id'],$s['id'],$u['identity_version']]);foreach([[$t['id'],'SOURCE'],[$child['id'],'CHILD']] as [$ticket,$side]){$db->execute('INSERT INTO support_split_members(ticket_id,split_id,side) VALUES (?,?,?)',[$ticket,$id,$side]);}foreach($p['message_ids'] as $message){$db->execute('INSERT INTO support_split_messages(split_id,message_id,source_id,child_id,customer_id,department_id) VALUES (?,?,?,?,?,?)',[$id,$message,$t['id'],$child['id'],$t['customer_id'],$t['department_id']]);}
    $db->execute('UPDATE support_work_confirmations SET used_at=UTC_TIMESTAMP(6) WHERE id=?',[$v['id']]);$this->c->identity->confirmationEvent($u['id'],$v['id'],true);return ['id'=>$child['id'],'number'=>$child['number'],'version'=>1,'source_id'=>$t['id'],'source_version'=>(int)$t['version'],'split_id'=>$id,'live_split_allowed'=>false];
   });
  };
  try{return (new Idempotency($this->c->db))->run($ref,'support-split:'.$route,$key,['hmac'=>$this->m->hash($body)],fn()=>$work(false),fn()=>$work(true));}catch(\PDOException $e){if(in_array($e->errorInfo[1]??0,[1205,1213,3572],true)){throw new HttpError(409,'SPLIT_BUSY');}throw $e;}
 }
}
