<?php
declare(strict_types=1);
namespace Hostider\Support\UI;
use Hostider\Support\Advanced\Common;
use Hostider\Core\Security\AccessGrant;
use Hostider\Core\Http\HttpError;
final readonly class Context
{
    public function __construct(public Common $m){}
    public function run(string $ref,string $ctx,\Closure $work,bool $fresh=false): mixed
    {
        $this->m->gate();if(!in_array($ctx,['admin','client'],true)){throw new HttpError(403,'SUPPORT_CONTEXT_INVALID');}$callback=function($u,$s)use($ctx,$work){$a=$this->m->access->actor($u,$s);if($ctx==='admin'){if(!$a->staff||!$a->mfa||!in_array('support.ticket.view',$a->permissions,true)||$this->departments($u,$s)===[]){throw new HttpError(403,'SUPPORT_UI_SCOPE_REQUIRED');}}else{if($a->staff||$a->customerId===null){throw new HttpError(403,'CUSTOMER_REQUIRED');}$this->m->c->identity->authorize($u,$s,'support.view',$a->customerId);}return $work($u,$s);};return $fresh?$this->m->c->identity->withStepUp($ref,$ctx,$callback):$this->m->c->identity->locked($ref,$ctx,$callback);
    }
    public function departments(array $u,array $s): array
    {
        $a=$this->m->access->actor($u,$s);if(!$a->staff){return $this->m->c->db->execute('SELECT id,name FROM support_departments WHERE enabled=TRUE ORDER BY id LIMIT 64')->fetchAll();}$rows=$this->m->c->db->execute('SELECT d.id,d.name,d.version FROM support_departments d JOIN support_department_members m ON m.department_id=d.id WHERE m.user_id=? ORDER BY d.id LIMIT 64',[$u['id']])->fetchAll();$out=[];foreach($rows as $r){foreach($a->grants as $g){if($g instanceof AccessGrant&&$g->customers!==[]&&$g->permission==='support.ticket.view'&&in_array($r['id'],$a->departments,true)&&$g->covers('support.ticket.view',null,null,$r['id'])){$out[]=$r;break;}}}return $out;
    }
    public function permission(array $u,array $s,array $t,string $p): bool{try{$this->m->access->require($u,$s,$t['customer_id'],$t['department_id'],$p);return true;}catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}return false;}}
    public function epoch(array $u,array $s): string{$a=$this->m->access->actor($u,$s);return $this->m->hash(['session'=>$s['id'],'version'=>$u['identity_version'],'customer'=>$a->customerId,'permissions'=>$a->permissions,'grants'=>array_map(static fn($g)=>[$g->permission,$g->customers,$g->services,$g->departments],$a->grants),'departments'=>$this->departments($u,$s)]);}
    public function status(string $ref,string $ctx): array{return $this->run($ref,$ctx,function($u,$s)use($ctx){$a=$this->m->access->actor($u,$s);return ['name'=>$u['display_name'],'user_id'=>$u['id'],'customer_id'=>$a->customerId,'context'=>$ctx,'permissions'=>$a->permissions,'departments'=>$this->departments($u,$s),'scope_epoch'=>$this->epoch($u,$s),'fresh'=>(int)$s['verified']>time()-300&&(int)$s['verified']<=time(),'search'=>'UNSUPPORTED_ENCRYPTED_SUBJECT','export'=>'UNSUPPORTED','environment'=>'TEST_MOCK','mail_delivery'=>false,'presence'=>'APPROXIMATE_120_SECONDS','draft_ttl_seconds'=>1800];});}
}
