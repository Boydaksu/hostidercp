<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Commerce\Services;
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Crypto,Audit,AuditEvent,Principal,Authorization};
use Hostider\Support\Access;
final readonly class Common
{
    public Crypto $crypto;
    public function __construct(public Services $c){$this->crypto=new Crypto(base64_decode($c->config->key,true));}
    public function gate(): void{if($this->c->config->environment!=='test'||$this->c->config->mail){throw new HttpError(503,'MAIL_LIVE_GATE_CLOSED');}}
    public function hmac(string $s): string{return hash_hmac('sha256',$s,base64_decode($this->c->config->key,true));}
    public function event(string $id,string $kind,Principal $actor,string $trace,array $details=[]): void{$this->c->db->requireTransaction();$this->c->db->execute('INSERT INTO support_mail_events(id,subject_id,kind,actor_id,details,trace_id) VALUES (?,?,?,?,?,?)',[Id::uuid(),$id,$kind,$actor->actorId,CanonicalJson::encode($details),$trace]);(new Audit($this->c->db))->record($actor,AuditEvent::SupportChanged,$id,$trace);}
    public function reviewer(array $u,array $s,string $dep,?string $customer): Principal
    {
        $a=(new Access($this->c))->actor($u,$s);(new Access($this->c))->department($dep);if(!$a->staff||!$this->c->db->execute('SELECT 1 FROM support_department_members WHERE department_id=? AND user_id=? FOR UPDATE',[$dep,$u['id']])->fetchColumn()){throw new HttpError(404,'MAIL_NOT_FOUND');}
        // Unknown customer requires an explicit wildcard grant, never an implicit global-role bypass.
        (new Authorization())->require($a,'support.mail_review',$customer??'*',$dep,privileged:true);return $a;
    }
    public function retention(string $dep): array{$r=$this->c->db->execute('SELECT * FROM support_mail_retention WHERE department_id=?',[$dep])->fetch();if(!$r||!$r['enabled']){throw new HttpError(409,'MAIL_NOT_CONFIGURED');}return $r;}
}
