<?php
declare(strict_types=1);
namespace Hostider\Support;
use Hostider\Commerce\Services;
use Hostider\Core\Config;
use Hostider\Core\Http\{Request,Response,Router,HttpError};
use Hostider\Core\Security\{Session,Csrf,RateLimiter};
use Hostider\Identity\PersistentSessionGuard;
final class Controller
{
    private Services $c;
    public function __construct(private readonly \Closure $factory,private readonly Config $config){}
    public function routes(Router $router): void
    {
        foreach(['client','admin'] as $ctx){
            foreach(['list','detail','departments'] as $v){$router->add('GET','/api/v1/'.$ctx.'/support/'.$v,fn(Request $r,string $t): Response=>$this->handle($r,$ctx,$v,$t,false));}
            foreach($ctx==='client'?['create','reply']:['reply','note','state','move','department','department-new','member'] as $v){$router->add('POST','/api/v1/'.$ctx.'/support/'.$v,fn(Request $r,string $t): Response=>$this->handle($r,$ctx,$v,$t,true));}
        }
    }
    private function handle(Request $r,string $ctx,string $action,string $trace,bool $mutation): Response
    {
        if($this->config->environment!=='test'){throw new HttpError(503,'F5_TEST_ONLY_GATE');}if(!isset($this->c)){$this->c=($this->factory)();}
        $n=Session::start($ctx,new PersistentSessionGuard($this->c->identity->repository,$ctx));$service=new Service($this->c);
        if(!$mutation){return Response::data($service->view($n->reference,$ctx,$action,$r->query),$trace);}
        Csrf::verify($r,$n->data['csrf']??'');$p=$r->json();
        if(!(new RateLimiter($this->c->db,base64_decode($this->config->key,true)))->allow('support:'.$n->reference,120,300)){throw new HttpError(429,'RATE_LIMITED');}
        return Response::data($service->command($n->reference,$ctx,$action,$p,$r->header('idempotency-key'),$trace),$trace);
    }
}
