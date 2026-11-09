<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Commerce\Services;
use Hostider\Core\Config;
use Hostider\Core\Http\{Router,Request,Response,HttpError};
use Hostider\Core\Security\{Session,Csrf,RateLimiter};
use Hostider\Identity\PersistentSessionGuard;
final class Controller
{
    private Services $c;
    public function __construct(private readonly \Closure $factory,private readonly Config $config){}
    public function routes(Router $r): void
    {
        foreach(['detail','unassigned','settings'] as $v){$r->add('GET','/api/v1/admin/support-work/'.$v,fn(Request $q,string $t): Response=>$this->handle($q,$v,$t,false));}
        foreach(['prepare','execute'] as $v){$r->add('POST','/api/v1/admin/support-work/'.$v,fn(Request $q,string $t): Response=>$this->handle($q,$v,$t,true));}
    }
    private function handle(Request $r,string $action,string $trace,bool $mutation): Response
    {
        if($this->config->environment!=='test'){throw new HttpError(503,'F5_TEST_ONLY_GATE');}if(!isset($this->c)){$this->c=($this->factory)();}$s=Session::start('admin',new PersistentSessionGuard($this->c->identity->repository,'admin'));$commands=new Commands($this->c);if(!$mutation){return Response::data($commands->view($s->reference,$action,$r->query),$trace);}
        Csrf::verify($r,$s->data['csrf']??'');if(!(new RateLimiter($this->c->db,base64_decode($this->config->key,true)))->allow('support-work:'.$s->reference,120,300)){throw new HttpError(429,'RATE_LIMITED');}return Response::data($commands->command($s->reference,$action,$r->json(),$r->header('idempotency-key'),$trace),$trace);
    }
}
