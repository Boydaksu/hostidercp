<?php
declare(strict_types=1);
namespace Hostider\Support\Advanced;
use Hostider\Commerce\Services;
use Hostider\Core\Config;
use Hostider\Core\Http\{Router,Request,Response};
use Hostider\Core\Security\{Session,Csrf,RateLimiter};
use Hostider\Identity\PersistentSessionGuard;
final class Controller
{
    private Services $c;
    public function __construct(private readonly \Closure $factory,private readonly Config $config){}
    public function routes(Router $r): void
    {
        foreach(['admin','client'] as $ctx){foreach(['incident','csat'] as $v){$r->add('GET','/api/v1/'.$ctx.'/support-advanced/'.$v,fn($q,$t)=>$this->handle($q,$t,$ctx,$v));}}
        $r->add('POST','/api/v1/client/support-advanced/csat',fn($q,$t)=>$this->handle($q,$t,'client','csat'));foreach(['prepare','execute','simulate'] as $v){$r->add('POST','/api/v1/admin/support-advanced/'.$v,fn($q,$t)=>$this->handle($q,$t,'admin',$v));}foreach(['report','rules','executions'] as $v){$r->add('GET','/api/v1/admin/support-advanced/'.$v,fn($q,$t)=>$this->handle($q,$t,'admin',$v));}
    }
    private function handle(Request $q,string $trace,string $ctx,string $action): Response
    {
        if(!isset($this->c)){$this->c=($this->factory)();}$m=new Common($this->c);$m->gate();$session=Session::start($ctx,new PersistentSessionGuard($this->c->identity->repository,$ctx));$ref=$session->reference;if($q->method==='GET'){$result=match($action){'incident'=>(new Incidents($m))->view($ref,$ctx,$q->query),'csat'=>(new Csat($m))->view($ref,$ctx,$q->query),'rules','executions'=>(new Views($m))->view($ref,$action,$q->query),default=>(new Reports($m))->view($ref,$q->query)};return Response::data($result,$trace);}
        Csrf::verify($q,$session->data['csrf']??'');$p=$q->json();if($action==='simulate'){return Response::data((new \Hostider\Support\Automation\Engine($m))->simulate($ref,$p),$trace);}if(!(new RateLimiter($this->c->db,base64_decode($this->config->key,true)))->allow('support-advanced:'.$ref,120,300)){throw new \Hostider\Core\Http\HttpError(429,'RATE_LIMITED');}$result=$ctx==='client'?(new Csat($m))->command($ref,$p,$q->header('idempotency-key'),$trace):(new Commands($m))->command($ref,$action,$p,$q->header('idempotency-key'),$trace);return Response::data($result,$trace);
    }
}
