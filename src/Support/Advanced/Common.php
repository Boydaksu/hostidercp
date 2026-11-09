<?php
declare(strict_types=1);
namespace Hostider\Support\Advanced;
use Hostider\Commerce\Services;
use Hostider\Core\{CanonicalJson,Id,Validation};
use Hostider\Core\Security\{Audit,AuditEvent,Crypto,Principal,AccessGrant};
use Hostider\Core\Http\HttpError;
use Hostider\Support\{Access,Service};
final readonly class Common
{
    public Access $access;public Crypto $crypto;
    public function __construct(public Services $c){$this->access=new Access($c);$this->crypto=new Crypto(base64_decode($c->config->key,true));}
    public function gate(): void{if($this->c->config->environment!=='test'||$this->c->config->mail){throw new HttpError(503,'SUPPORT_ADVANCED_TEST_ONLY');}}
    public function hash(array $v): string{return hash_hmac('sha256',CanonicalJson::encode($v),base64_decode($this->c->config->key,true));}
    public static function object(mixed $v): array{if($v instanceof \stdClass){$v=json_decode(CanonicalJson::encode($v),true,32,JSON_THROW_ON_ERROR);}if(!is_array($v)||($v!==[]&&array_is_list($v))){throw new HttpError(422,'OBJECT_REQUIRED');}return $v;}
    public static function text(mixed $v,int $max=4000,bool $empty=false): string{if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max||(!$empty&&trim($v)==='')||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v)){throw new HttpError(422,'SUPPORT_TEXT_INVALID');}return $v;}
    public static function version(mixed $v,bool $zero=false): int{if($zero&&$v===0){return 0;}return Service::version($v);}
    public function scoped(array $u,array $s,string $dep,string $customer,string $permission): Principal{return $this->access->require($u,$s,$customer,$dep,$permission);}
    public function department(array $u,array $s,string $dep,string $permission): Principal
    {
        $a=$this->access->actor($u,$s);$this->access->department($dep);if(!$a->staff||!$this->c->db->execute('SELECT 1 FROM support_department_members WHERE department_id=? AND user_id=?',[$dep,$u['id']])->fetchColumn()){throw new HttpError(404,'SUPPORT_NOT_FOUND');}
        // Department-wide metadata requires an explicit wildcard customer grant.
        foreach($a->grants as $g){if($g instanceof AccessGrant&&$g->permission===$permission&&in_array('*',$g->customers,true)&&($g->services===[]||in_array('*',$g->services,true))&&(in_array('*',$g->departments,true)||in_array($dep,$g->departments,true))){return $this->c->identity->authorize($u,$s,$permission,department:$dep);}}
        throw new HttpError(404,'SUPPORT_NOT_FOUND');
    }
    public function event(string $dep,string $subject,string $kind,Principal $actor,string $trace,array $details=[],?array $ticket=null,?string $sponsor=null,?string $execution=null,?string $cause=null): string
    {
        $this->c->db->requireTransaction();$id=Id::uuid();$this->c->db->execute('INSERT INTO support_advanced_events(id,ticket_id,customer_id,department_id,subject_id,kind,actor_id,sponsor_id,execution_id,details,trace_id,causation_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$ticket['id']??null,$ticket['customer_id']??null,$dep,$subject,$kind,$actor->actorId,$sponsor,$execution,CanonicalJson::encode($details),$trace,$cause]);(new Audit($this->c->db))->record($actor,AuditEvent::SupportChanged,$subject,$trace,$cause);return $id;
    }
    public function sqlScope(Principal $a,string $permission): array
    {
        [$view,$vp]=$this->access->listing($a);$grants=[];foreach($a->grants as $g){if($g instanceof AccessGrant&&$g->permission===$permission){$grants[]=new AccessGrant('support.ticket.view',$g->customers,$g->services,$g->departments);}}
        $mapped=new Principal($a->actorId,in_array($permission,$a->permissions,true)?['support.ticket.view']:[],$a->customerId,$a->departments,$a->staff,$a->mfa,$a->expiresAt,$a->verifiedAt,false,$grants);
        [$other,$op]=$this->access->listing($mapped);return ['('.$view.') AND ('.$other.')',[...$vp,...$op]];
    }
    public static function offset(mixed $v): int{if(!is_string($v)||!ctype_digit($v)||(int)$v>1000000){throw new HttpError(422,'INVALID_OFFSET');}return (int)$v;}
}
