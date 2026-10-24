<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config,Idempotency,Validation};use Hostider\Core\Http\{Request,Response,Router,HttpError};use Hostider\Core\Security\{Crypto,Csrf,Session,RateLimiter};use Hostider\Identity\PersistentSessionGuard;
final class Controller
{
    private \Hostider\Commerce\Services $c;
    public function __construct(private readonly \Closure $factory,private readonly Config $config){}
    private function ensure(): void{if(!isset($this->c)){$this->c=($this->factory)();}}
    public function routes(Router $router): void
    {
        foreach(['admin','client'] as $context){$base='/api/v1/'.$context.'/services/';foreach(['status','list','detail','pools'] as $view){$router->add('GET',$base.$view,fn(Request $r,string $t): Response=>$this->view($r,$context,$view,$t));}foreach(['prepare','execute'] as $action){$router->add('POST',$base.$action,fn(Request $r,string $t): Response=>$this->mutation($r,$context,$action==='prepare',$t));}$router->add('GET','/'.$context.'/services',fn(): Response=>Panel::response($context));}
    }
    private function customer(array $u,array $s,mixed $requested): ?string
    {
        if($requested!==null&&(!is_string($requested)||!preg_match('/^[a-f0-9-]{36}$/D',$requested))){throw new HttpError(422,'INVALID_CUSTOMER_ID');}$actor=$this->c->identity->principal($u,$s);$customer=$actor->staff&&!$actor->impersonating?$requested:$actor->customerId;if((!$actor->staff||$actor->impersonating)&&$requested!==null&&$requested!==$customer){throw new HttpError(403,'CUSTOMER_OVERRIDE_FORBIDDEN');}return $customer;
    }
    private function view(Request $r,string $context,string $view,string $trace): Response
    {
        $this->ensure();$native=Session::start($context,new PersistentSessionGuard($this->c->identity->repository,$context));return $this->c->identity->locked($native->reference,$context,function(array $u,array $s)use($r,$view,$trace): Response{
            $actor=$this->c->identity->principal($u,$s);$customer=$this->customer($u,$s,$r->query['customer']??null);if($view==='status'){return Response::data(['name'=>$u['display_name'],'staff'=>$actor->staff,'impersonating'=>$actor->impersonating,'permissions'=>$actor->impersonating?$actor->effectivePermissions:$actor->permissions,'customer_id'=>$customer,'environment'=>$this->config->environment,'live_provisioning'=>false],$trace);}$views=new Views($this->c);if($view==='pools'){return Response::data($views->pools($actor),$trace);}if($customer===null){throw new HttpError(422,'CUSTOMER_REQUIRED');}$offset=$r->query['offset']??'0';if(!is_string($offset)||!ctype_digit($offset)||(int)$offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}$id=$r->query['id']??null;if($id!==null&&(!is_string($id)||!preg_match('/^[a-f0-9-]{36}$/D',$id))){throw new HttpError(422,'INVALID_SERVICE_IDENTIFIER');}return Response::data($view==='list'?$views->list($u,$s,$actor,$customer,(int)$offset):$views->detail($u,$s,$actor,$customer,$id??throw new HttpError(422,'ID_REQUIRED')),$trace);
        });
    }
    private function mutation(Request $r,string $context,bool $prepare,string $trace): Response
    {
        if($this->config->environment!=='test'){throw new HttpError(503,'F3_TEST_ONLY_GATE');}$this->ensure();$native=Session::start($context,new PersistentSessionGuard($this->c->identity->repository,$context));Csrf::verify($r,$native->data['csrf']??'');$data=$r->json();Validation::fields($data,['action','payload','customer','confirmation'],['action','payload']);$action=Validation::text($data['action'],48);if(!$data['payload'] instanceof \stdClass){throw new HttpError(422,'PAYLOAD_OBJECT_REQUIRED');}$payload=json_decode(json_encode($data['payload'],JSON_THROW_ON_ERROR),true,32,JSON_THROW_ON_ERROR);$requested=$data['customer']??null;if(!(new RateLimiter($this->c->db,base64_decode($this->config->key,true)))->allow('services:'.$native->reference,120,300)){throw new HttpError(429,'RATE_LIMITED');}
        $this->c->db->execute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$key=$r->header('idempotency-key');$scope='services:'.$context.':'.($prepare?'prepare':'execute');$binding=$native->reference.':'.$scope.':'.$key;$crypto=new Crypto(base64_decode($this->config->key,true));$commands=new Commands($this->c);
        try{$result=(new Idempotency($this->c->db))->run($native->reference,$scope,$key,['hmac'=>$this->c->identity->digest(CanonicalJson::encode($data))],function()use($native,$context,$action,$payload,$requested,$data,$prepare,$trace,$crypto,$binding,$commands): array{
            $out=$this->c->identity->withStepUp($native->reference,$context,function(array $u,array $s)use($action,$payload,$requested,$data,$prepare,$trace,$commands): array{$customer=$this->customer($u,$s,$requested);$confirmation=$data['confirmation']??new \stdClass();if(!$confirmation instanceof \stdClass){throw new HttpError(422,'CONFIRMATION_OBJECT_REQUIRED');}$confirmations=new Confirmations($commands);return $prepare?$confirmations->prepare($u,$s,$action,$payload,$customer):$confirmations->execute($u,$s,$action,$payload,$customer,get_object_vars($confirmation),$trace);});return ['encrypted'=>$crypto->encrypt(json_encode($out,JSON_THROW_ON_ERROR),'services-result:'.$binding)];
        },function()use($native,$context,$action,$payload,$requested,$commands): void{$this->c->identity->withStepUp($native->reference,$context,fn(array $u,array $s): \Hostider\Core\Security\Principal=>$commands->authorize($u,$s,$action,$payload,$this->customer($u,$s,$requested)));});return Response::data(json_decode($crypto->decrypt($result['encrypted'],'services-result:'.$binding),true,64,JSON_THROW_ON_ERROR),$trace);
        }catch(\InvalidArgumentException|\OverflowException|\DivisionByZeroError){throw new HttpError(422,'INVALID_SERVICE_VALUE');}catch(HttpError $e){if(in_array($e->status,[401,403],true)){$this->c->identity->recordDenial($native->reference,$context,\Hostider\Core\Security\AuditEvent::AccessDenied);}throw $e;}
    }
}
