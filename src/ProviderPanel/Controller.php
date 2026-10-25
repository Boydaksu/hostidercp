<?php
declare(strict_types=1);
namespace Hostider\ProviderPanel;
use Hostider\Commerce\Services;
use Hostider\Core\{Config,Idempotency,Validation};
use Hostider\Core\Http\{Request,Response,Router,HttpError};
use Hostider\Core\Security\{Session,Csrf,RateLimiter,Crypto};
use Hostider\Identity\PersistentSessionGuard;
final class Controller
{
    private Services $c;
    public function __construct(private readonly \Closure $factory,private readonly Config $config){}
    private function ensure(): void{if($this->config->environment!=='test'){throw new HttpError(503,'F4_TEST_ONLY_GATE');}if(!isset($this->c)){$this->c=($this->factory)();}}
    public function routes(Router $router): void
    {
        foreach(['client','admin'] as $ctx){foreach(['domains','cloud','cloud-costs'] as $screen){if($ctx==='client'&&$screen==='cloud-costs'){continue;}$router->add('GET','/'.$ctx.'/'.$screen,fn(Request $r,string $t): Response=>$this->page($ctx,$screen));}
            foreach(['status','list','detail','costs'] as $view){$router->add('GET','/api/v1/'.$ctx.'/provider-panel/'.$view,fn(Request $r,string $t): Response=>$this->view($r,$ctx,$view,$t));}
            foreach(['prepare','execute','containment-prepare','containment-execute'] as $action){$router->add('POST','/api/v1/'.$ctx.'/provider-panel/'.$action,fn(Request $r,string $t): Response=>$this->mutation($r,$ctx,$action,$t));}}
    }
    private function page(string $ctx,string $screen): Response
    {
        $this->ensure();$n=Session::start($ctx,new PersistentSessionGuard($this->c->identity->repository,$ctx));return $this->c->identity->locked($n->reference,$ctx,function(array $u,array $s)use($ctx,$screen): Response{
            $a=$this->c->identity->principal($u,$s);if($a->impersonating||!in_array('services.view',$a->permissions,true)){throw new HttpError(403,'ACCESS_DENIED');}if($ctx==='admin'&&!$a->staff){throw new HttpError(403,'STAFF_REQUIRED');}return Panel::response($ctx,$screen);
        });
    }
    private function view(Request $r,string $ctx,string $view,string $trace): Response
    {
        $this->ensure();$n=Session::start($ctx,new PersistentSessionGuard($this->c->identity->repository,$ctx));return $this->c->identity->locked($n->reference,$ctx,function(array $u,array $s)use($r,$view,$trace): Response{
            $access=new Access($this->c);$a=$this->c->identity->principal($u,$s);if($a->impersonating){throw new HttpError(403,'PANEL_IMPERSONATION_FORBIDDEN');}$customer=$access->customer($u,$s,$r->query['customer']??null);
            if($view==='status'){if(!in_array('services.view',$a->permissions,true)){throw new HttpError(403,'ACCESS_DENIED');}return Response::data(['staff'=>$a->staff,'customer_id'=>$customer,'environment'=>'test','live_provider'=>false,'permissions'=>$a->permissions],$trace);}
            if($customer===null){throw new HttpError(422,'CUSTOMER_REQUIRED');}$views=new Views($this->c);if($view==='list'){$offset=$r->query['offset']??'0';if(!is_string($offset)||!ctype_digit($offset)||(int)$offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}$data=$views->list($u,$s,$customer,$r->query['kind']??'DOMAIN',(int)$offset);}else{$id=Access::id($r->query['id']??null);$data=$view==='costs'?$views->costs($u,$s,$customer,$id):$views->detail($u,$s,$customer,$id);}return Response::data($data,$trace);
        });
    }
    private function mutation(Request $r,string $ctx,string $route,string $trace): Response
    {
        $this->ensure();$n=Session::start($ctx,new PersistentSessionGuard($this->c->identity->repository,$ctx));Csrf::verify($r,$n->data['csrf']??'');$d=$r->json();
        if(!(new RateLimiter($this->c->db,base64_decode($this->config->key,true)))->allow('panel:'.$n->reference,120,300)){throw new HttpError(429,'RATE_LIMITED');}
        if(str_starts_with($route,'containment-')){return $this->containment($d,$n->reference,$ctx,$route,$trace);}
        Validation::fields($d,['action','payload','customer','confirmation'],['action','payload']);$action=Validation::text($d['action'],40);if(!$d['payload'] instanceof \stdClass){throw new HttpError(422,'PAYLOAD_OBJECT_REQUIRED');}$p=json_decode(json_encode($d['payload'],JSON_THROW_ON_ERROR),true,32,JSON_THROW_ON_ERROR);$cf=$d['confirmation']??new \stdClass();if(!$cf instanceof \stdClass){throw new HttpError(422,'CONFIRMATION_OBJECT_REQUIRED');}
        $cmd=new Commands($this->c);$key=$r->header('idempotency-key');$scope='provider-panel:'.$ctx.':'.$route;$binding=$n->reference.':'.$scope.':'.$key;$crypto=new Crypto(base64_decode($this->config->key,true));$this->c->db->execute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $authorize=fn(\Closure $work): mixed=>$this->c->identity->withStepUp($n->reference,$ctx,function(array $u,array $s)use($cmd,$d,$work): mixed{$customer=$cmd->access->customer($u,$s,$d['customer']??null);if($customer===null){throw new HttpError(422,'CUSTOMER_REQUIRED');}return $work($u,$s,$customer);});
        try{$result=(new Idempotency($this->c->db))->run($n->reference,$scope,$key,['hmac'=>$cmd->hash($d)],function()use($authorize,$cmd,$route,$action,$p,$cf,$trace,$crypto,$binding): array{
            $out=$authorize(function(array $u,array $s,string $customer)use($cmd,$route,$action,$p,$cf,$trace): array{$confirm=new Confirmations($cmd);return $route==='prepare'?$confirm->prepare($u,$s,$action,$p,$customer):$confirm->execute($u,$s,$action,$p,$customer,get_object_vars($cf),$trace);});return ['encrypted'=>$crypto->encrypt(json_encode($out,JSON_THROW_ON_ERROR),'panel-result:'.$binding)];
        },fn()=>$authorize(fn(array $u,array $s,string $customer)=>$cmd->authorize($u,$s,$action,$p,$customer,true)));return Response::data(json_decode($crypto->decrypt($result['encrypted'],'panel-result:'.$binding),true,32,JSON_THROW_ON_ERROR),$trace);
        }catch(\InvalidArgumentException|\OverflowException|\DivisionByZeroError){throw new HttpError(422,'INVALID_PANEL_VALUE');}
    }
    private function containment(array $d,string $reference,string $ctx,string $route,string $trace): Response
    {
        if($ctx!=='admin'){throw new HttpError(403,'STAFF_REQUIRED');}$prepare=$route==='containment-prepare';$fields=$prepare?['service_id','customer','kind','reason']:['service_id','customer','confirmation','accepted'];Validation::fields($d,$fields,$fields);$service=Access::id($d['service_id']);$customer=Access::id($d['customer']);
        $instance=$this->c->identity->withStepUp($reference,'admin',function(array $u,array $s)use($service,$customer): string{$access=new Access($this->c);$access->staff($this->c->identity->principal($u,$s));$access->service($u,$s,$customer,$service,'services.manage',true);return (new Commands($this->c))->instance($service,$customer)['id'];});
        $native=new \Hostider\Cloud\Containment($this->c->db,$this->config);
        if($prepare){$id=$native->prepare($instance,Validation::text($d['kind'],24),Validation::text($d['reason'],1000),$reference);$wait=$this->c->db->execute('SELECT GREATEST(0,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(6),not_before)) FROM cloud_safety_confirmations WHERE id=?',[$id])->fetchColumn();return Response::data(['id'=>$id,'wait_seconds'=>(int)$wait,'financial_exception'=>true,'provider_cost_ends'=>false,'irreversible'=>$d['kind']==='TERMINATE'],$trace);}
        if(!is_bool($d['accepted'])){throw new HttpError(422,'BOOLEAN_REQUIRED');}return Response::data(['operation_id'=>$native->execute($instance,Access::id($d['confirmation']),$reference,$d['accepted']),'status'=>'PENDING','financial_exception'=>true],$trace);
    }
}
