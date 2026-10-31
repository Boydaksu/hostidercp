<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Support\Access;
final readonly class Inbound
{
    public function __construct(private Common $m,private Attachments $files){}
    public function poll(InboundAdapter $adapter,int $max=25): array{$this->m->gate();if($max<1||$max>25){throw new HttpError(422,'MAIL_BATCH_LIMIT');}$out=[];for($n=0;$n<$max;++$n){$e=$adapter->receive();if($e===null){break;}$out[]=$this->ingest($e['department_id'],$e['raw'],$e['proof']??'');$adapter->acknowledge();}return $out;}
    public function ingest(string $dep,string $raw,string $proof='',?\Closure $authorize=null): array
    {
        $this->m->gate();Access::id($dep);if(strlen($raw)>131072||$raw===''){throw new HttpError(413,'MAIL_RAW_LIMIT');}$parsed=null;$reason='NO_PROOF';try{$parsed=Mime::parse($raw);}catch(HttpError){$reason='MALFORMED_MIME';}$sha=hash('sha256',$raw);$key=$this->m->hmac($parsed['message_id']??'missing:'.$sha);$db=$this->m->c->db;
        return $db->transaction(function()use($dep,$raw,$proof,$parsed,$reason,$sha,$key,$db,$authorize){if($proof!==''){try{$p=(new Tokens($this->m))->check($proof,'PROOF');$db->execute('SELECT id FROM identity_users WHERE id=? FOR UPDATE NOWAIT',[$p['actor_id']])->fetch();}catch(HttpError){}}if($authorize!==null){$authorize();}(new Access($this->m->c))->department($dep);$claim=$db->execute('SELECT raw_sha FROM support_mail_claims WHERE department_id=? AND message_key=? FOR UPDATE',[$dep,$key])->fetch();if(!$claim){$db->execute('INSERT INTO support_mail_claims VALUES (?,?,?)',[$dep,$key,$sha]);$claim=['raw_sha'=>$sha];}$r=$db->execute('SELECT * FROM support_mail_inbound WHERE department_id=? AND message_key=? AND raw_sha=? FOR UPDATE',[$dep,$key,$sha])->fetch();if($r){return $this->result($r);}
            if((int)$db->execute('SELECT COUNT(*) FROM support_mail_inbound WHERE department_id=? AND message_key=?',[$dep,$key])->fetchColumn()>=4||(int)$db->execute('SELECT COUNT(*) FROM support_mail_inbound WHERE department_id=? AND purged_at IS NULL',[$dep])->fetchColumn()>=1000){throw new HttpError(413,'MAIL_VARIANT_LIMIT');}$id=Id::uuid();$trace=Id::uuid();$days=(int)$this->m->retention($dep)['raw_days'];$status='REVIEW';if($claim['raw_sha']!==$sha){$reason='MESSAGE_ID_CONFLICT';}elseif($parsed!==null&&$parsed['loop']){$status='BLOCKED';$reason='MAIL_LOOP';}elseif($parsed!==null&&$parsed['message_id']===null){$reason='MISSING_MESSAGE_ID';}
            $db->execute('INSERT INTO support_mail_inbound(id,department_id,message_key,raw_sha,raw_encrypted,status,reason,expires_at) VALUES (?,?,?,?,?,?,?,TIMESTAMPADD(DAY,?,UTC_TIMESTAMP(6)))',[$id,$dep,$key,$sha,$this->m->crypto->encrypt($raw,'mail-raw:'.$id),$status,$reason,$days]);$this->m->event($id,'INBOUND_RECORDED',new Principal('mail-ingest',[]),$trace,['status'=>$status,'reason'=>$reason]);
            if($status==='REVIEW'&&$parsed!==null&&$parsed['message_id']!==null&&$claim['raw_sha']===$sha&&$proof!==''){
                $db->execute('SAVEPOINT mail_canonical');try{$this->attach($id,$parsed,$proof,$trace);}
                catch(HttpError $e){$db->execute('ROLLBACK TO SAVEPOINT mail_canonical');if(!in_array($e->status,[401,403,404,409,422],true)){throw $e;}$db->execute("UPDATE support_mail_inbound SET reason='VERIFICATION_REQUIRED' WHERE id=?",[$id]);}
                finally{if($db->pdo->inTransaction()){$db->execute('RELEASE SAVEPOINT mail_canonical');}}
            }return $this->result($db->execute('SELECT * FROM support_mail_inbound WHERE id=?',[$id])->fetch());});
    }
    public function attach(string $id,array $mail,string $proof,string $trace): array
    {
        $db=$this->m->c->db;$db->requireTransaction();$r=$db->execute('SELECT * FROM support_mail_inbound WHERE id=? FOR UPDATE',[$id])->fetch();if(!$r||$r['status']!=='REVIEW'||in_array($r['reason'],['MESSAGE_ID_CONFLICT','MISSING_MESSAGE_ID','MALFORMED_MIME'],true)||$r['purged_at']!==null||$mail['message_id']===null||$mail['loop']){throw new HttpError(409,'MAIL_REVIEW_NOT_ATTACHABLE');}
        $token=(new Tokens($this->m))->check($proof,'PROOF');if($token['department_id']!==$r['department_id']||!hash_equals($token['raw_sha']??'',$r['raw_sha'])){throw new HttpError(403,'MAIL_SENDER_PROOF');}$session=$db->execute('SELECT native_reference FROM identity_sessions WHERE id=?',[$token['session_id']])->fetchColumn();if(!is_string($session)){throw new HttpError(403,'MAIL_SENDER_PROOF');}
        return $this->m->c->identity->locked($session,'client',function($u,$s)use($id,$mail,$token,$r,$trace,$db,$session){
            if($u['administrative']!==null||$u['id']!==$token['actor_id']||$s['id']!==$token['session_id']||(int)$u['identity_version']!==$token['identity_version']||$s['customer_id']!==$token['customer_id']||!$u['verified_at']||Mime::address($u['email'])!==$mail['from']){throw new HttpError(403,'MAIL_SENDER_PROOF');}
            $ticket=null;$hasHint=$mail['references']!==[]||$mail['reply_token']!==null||preg_match('/HD-[A-F0-9]{24}/i',$mail['subject']);
            if($hasHint){if(!is_string($mail['reply_token'])||$mail['references']===[]){throw new HttpError(403,'MAIL_REPLY_PROOF');}$reply=(new Tokens($this->m))->check($mail['reply_token'],'REPLY');foreach(['actor_id','customer_id','department_id','identity_version'] as $k){if($reply[$k]!==$token[$k]){throw new HttpError(403,'MAIL_REPLY_PROOF');}}$ticket=(new Access($this->m->c))->ticket($u,$s,$reply['ticket_id'],'support.ticket.reply');if($ticket['department_id']!==$r['department_id']){throw new HttpError(403,'MAIL_REPLY_PROOF');}
                foreach($mail['references'] as $ref){$link=$db->execute('SELECT ticket_id,customer_id,recipient_id FROM support_mail_outbound WHERE message_ref=?',[$ref])->fetch();if(!$link||$link['ticket_id']!==$ticket['id']||$link['customer_id']!==$token['customer_id']||$link['recipient_id']!==$u['id']){throw new HttpError(403,'MAIL_REFERENCE_AMBIGUOUS');}}
            }
            $p=$ticket===null?['department_id'=>$r['department_id'],'subject'=>$mail['subject'],'body'=>$mail['body'],'priority'=>'NORMAL']:['id'=>$ticket['id'],'version'=>(int)$ticket['version'],'body'=>$mail['body']];
            $result=(new \Hostider\Support\Service($this->m->c))->inbound($session,$ticket===null?'create':'reply',$p,$trace);$t=$db->execute('SELECT * FROM support_tickets WHERE id=?',[$result['id']])->fetch();foreach($mail['files'] as $file){$this->files->save($t,$result['message_id'],$u['id'],$file['name'],$file['bytes'],$trace);}$event=$db->execute('SELECT id FROM support_events WHERE ticket_id=? AND subject_id=?',[$t['id'],$result['message_id']])->fetchColumn();
            $db->execute("UPDATE support_mail_inbound SET status='ATTACHED',reason='VERIFIED',customer_id=?,ticket_id=?,message_id=?,version=version+1 WHERE id=?",[$token['customer_id'],$t['id'],$result['message_id'],$id]);$db->execute("INSERT INTO support_mail_sources(inbound_id,ticket_id,customer_id,message_id,event_id,actor_id,proof_id,origin) VALUES (?,?,?,?,?,?,?,'TEST_ADAPTER')",[$id,$t['id'],$token['customer_id'],$result['message_id'],$event,$u['id'],$token['id']]);$this->m->event($id,'INBOUND_ATTACHED',(new Access($this->m->c))->actor($u,$s),$trace,['ticket_id'=>$t['id'],'message_id'=>$result['message_id']]);return $result;
        });
    }
    private function result(array $r): array{return array_intersect_key($r,array_flip(['id','status','reason','version','ticket_id','message_id']));}
}
