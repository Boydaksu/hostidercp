<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Http\HttpError;
use Hostider\Support\Access;
final readonly class Tokens
{
    public function __construct(private Common $m){}
    public function issue(string $kind,array $u,array $s,string $dep,?string $ticket=null,?string $file=null,?string $raw=null,int $ttl=300): string
    {
        $this->m->gate();$this->m->c->db->requireTransaction();if(!in_array($kind,['PROOF','REPLY','DOWNLOAD'],true)||$ttl<1||$ttl>3600){throw new HttpError(422,'MAIL_TOKEN_SCOPE');}$r=['id'=>Id::uuid(),'kind'=>$kind,'actor_id'=>$u['id'],'customer_id'=>$s['customer_id'],'department_id'=>$dep,'ticket_id'=>$ticket,'file_id'=>$file,'raw_sha'=>$raw,'session_id'=>$s['id'],'identity_version'=>(int)$u['identity_version'],'expiry'=>time()+$ttl];
        $this->m->c->db->execute('INSERT INTO support_mail_tokens(id,kind,actor_id,customer_id,department_id,ticket_id,file_id,raw_sha,session_id,identity_version,expiry) VALUES (?,?,?,?,?,?,?,?,?,?,?)',array_values($r));return $r['id'].'.'.$this->m->hmac(CanonicalJson::encode($r));
    }
    public function check(string $value,string $kind): array
    {
        $this->m->gate();$this->m->c->db->requireTransaction();if(!preg_match('/^([a-f0-9-]{36})\.([a-f0-9]{64})$/D',$value,$p)){throw new HttpError(403,'MAIL_TOKEN_INVALID');}Access::id($p[1]);$r=$this->m->c->db->execute('SELECT id,kind,actor_id,customer_id,department_id,ticket_id,file_id,raw_sha,session_id,identity_version,expiry FROM support_mail_tokens WHERE id=?',[$p[1]])->fetch();if(!$r){throw new HttpError(403,'MAIL_TOKEN_INVALID');}$r['identity_version']=(int)$r['identity_version'];$r['expiry']=(int)$r['expiry'];if($r['kind']!==$kind||$r['expiry']<=time()||!hash_equals($this->m->hmac(CanonicalJson::encode($r)),$p[2])){throw new HttpError(403,'MAIL_TOKEN_INVALID');}$live=$this->m->c->db->execute('SELECT u.status,u.identity_version,s.revoked_at,s.mfa_verified,UNIX_TIMESTAMP(s.expires_at) expiry,s.context,s.customer_id FROM identity_users u JOIN identity_sessions s ON s.user_id=u.id WHERE u.id=? AND s.id=? FOR UPDATE',[$r['actor_id'],$r['session_id']])->fetch();if(!$live||$live['status']!=='ACTIVE'||(int)$live['identity_version']!==$r['identity_version']||$live['revoked_at']!==null||!$live['mfa_verified']||(int)$live['expiry']<=time()||($kind!=='DOWNLOAD'&&($live['context']!=='client'||$live['customer_id']!==$r['customer_id']))){throw new HttpError(403,'MAIL_TOKEN_REVOKED');}return $r;
    }
    public function proof(string $reference,string $dep,string $sha): string
    {
        $this->m->gate();Access::id($dep);if(!preg_match('/^[a-f0-9]{64}$/D',$sha)){throw new HttpError(422,'MAIL_DIGEST_INVALID');}
        return $this->m->c->db->transaction(fn()=>$this->m->c->identity->locked($reference,'client',function($u,$s)use($dep,$sha){$a=(new Access($this->m->c))->actor($u,$s);if($u['administrative']!==null||!$u['verified_at']||$a->customerId===null){throw new HttpError(403,'MAIL_VERIFIED_CUSTOMER_REQUIRED');}$d=(new Access($this->m->c))->department($dep);if(!$d['enabled']){throw new HttpError(404,'MAIL_NOT_FOUND');}(new Access($this->m->c))->require($u,$s,$a->customerId,$dep);$this->m->retention($dep);return $this->issue('PROOF',$u,$s,$dep,raw:$sha);}));
    }
    public function reply(string $reference,string $ticket): string
    {
        $this->m->gate();return $this->m->c->db->transaction(fn()=>$this->m->c->identity->locked($reference,'client',function($u,$s)use($ticket){$t=(new Access($this->m->c))->ticket($u,$s,Access::id($ticket),'support.ticket.reply');if($u['administrative']!==null||!$u['verified_at']){throw new HttpError(403,'MAIL_VERIFIED_CUSTOMER_REQUIRED');}return $this->issue('REPLY',$u,$s,$t['department_id'],$t['id'],ttl:3600);}));
    }
}
