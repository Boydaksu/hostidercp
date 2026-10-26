<?php
declare(strict_types=1);
namespace Hostider\Support\UI;
use Hostider\Commerce\Services;
use Hostider\Core\Config;
use Hostider\Core\Http\{Router,Request,Response,HttpError};
use Hostider\Core\Security\{Session,Csrf,RateLimiter};
use Hostider\Identity\PersistentSessionGuard;
use Hostider\Support\Advanced\Common;
final class Controller
{
    private Services $c;
    public function __construct(private readonly \Closure $factory,private readonly Config $config){}
    public function routes(Router $r): void
    {
        foreach(['admin','client'] as $ctx){$r->add('GET','/'.$ctx.'/support',fn($q,$t)=>$this->handle($q,$t,$ctx,'page'));foreach(['status','list','detail','poll','draft','filters','export'] as $v){$r->add('GET','/api/v1/'.$ctx.'/support-ui/'.$v,fn($q,$t)=>$this->handle($q,$t,$ctx,$v));}foreach(['draft-save','draft-delete','filter-save','filter-delete','prepare','execute'] as $v){$r->add('POST','/api/v1/'.$ctx.'/support-ui/'.$v,fn($q,$t)=>$this->handle($q,$t,$ctx,$v));}}
        foreach(['eligible','settings','rule','mail-outbound','presence','clocks'] as $v){$r->add('GET','/api/v1/admin/support-ui/'.$v,fn($q,$t)=>$this->handle($q,$t,'admin',$v));}$r->add('POST','/api/v1/admin/support-ui/presence',fn($q,$t)=>$this->handle($q,$t,'admin','presence'));
    }
    private function handle(Request $q,string $trace,string $ctx,string $v): Response
    {
        if(!isset($this->c)){$this->c=($this->factory)();}$x=new Context(new Common($this->c));$x->m->gate();$s=Session::start($ctx,new PersistentSessionGuard($this->c->identity->repository,$ctx));$ref=$s->reference;
        if(!(new RateLimiter($this->c->db,base64_decode($this->config->key,true)))->allow('support-ui:'.$ref,240,300)){throw new HttpError(429,'RATE_LIMITED');}
        if($q->method==='GET'){
            if($v==='page'){$x->run($ref,$ctx,static fn()=>true);return Panel::response($ctx);}
            $views=new Views($x);$store=new StateStore($x);$result=match($v){'status'=>$x->status($ref,$ctx),'list'=>$views->listing($ref,$ctx,$q->query),'detail','poll'=>$views->detail($ref,$ctx,$q->query,$v==='poll'),'draft','filters'=>$store->view($ref,$ctx,$v,$q->query),'presence'=>$store->presence($ref,$q->query),'export'=>$x->run($ref,$ctx,static fn()=>throw new HttpError(422,'SUPPORT_EXPORT_UNSUPPORTED')),default=>$views->operations($ref,$v,$q->query)};return Response::data($result,$trace);
        }
        Csrf::verify($q,$s->data['csrf']??'');$p=$q->json();$result=in_array($v,['prepare','execute'],true)?(new Commands($x))->command($ref,$ctx,$v,$p,$q->header('idempotency-key'),$trace):(new StateStore($x))->command($ref,$ctx,$v,$p,$q->header('idempotency-key'),$trace);return Response::data($result,$trace);
    }
}
