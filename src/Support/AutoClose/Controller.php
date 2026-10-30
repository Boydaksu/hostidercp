<?php
declare(strict_types=1);
namespace Hostider\Support\AutoClose;
use Hostider\Commerce\Services;
use Hostider\Core\Config;
use Hostider\Core\Http\{Router,Request,Response,HttpError};
use Hostider\Core\Security\{Session,Csrf,RateLimiter};
use Hostider\Identity\PersistentSessionGuard;
final class Controller
{
    private Services $c;
    public function __construct(private readonly \Closure $factory,private readonly Config $config){}
    public function routes(Router $r):void
    {
        foreach(['settings','preview'] as $v){$r->add('GET','/api/v1/admin/support-auto-close/'.$v,fn($q,$t)=>$this->handle($q,$t,$v));}
        foreach(['prepare','execute'] as $v){$r->add('POST','/api/v1/admin/support-auto-close/'.$v,fn($q,$t)=>$this->handle($q,$t,$v));}
    }
    private function handle(Request $q,string $trace,string $route):Response
    {
        if(!isset($this->c)){$this->c=($this->factory)();}$policy=new Policy($this->c);$policy->m->gate();
        $s=Session::start('admin',new PersistentSessionGuard($this->c->identity->repository,'admin'));
        if(!(new RateLimiter($this->c->db,base64_decode($this->config->key,true)))->allow('support-auto-close:'.$s->reference,120,300)){throw new HttpError(429,'RATE_LIMITED');}
        if($q->method==='GET'){return Response::data($policy->view($s->reference,$route,$q->query),$trace);}
        Csrf::verify($q,$s->data['csrf']??'');
        return Response::data($policy->command($s->reference,$route,$q->json(),$q->header('idempotency-key'),$trace),$trace);
    }
}
