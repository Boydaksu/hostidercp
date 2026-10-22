<?php
declare(strict_types=1);
namespace Hostider\Identity;
use Hostider\Core\{Config,CanonicalJson,Idempotency,Validation};
use Hostider\Core\Http\{HttpError,Request,Response,Router};
use Hostider\Core\Security\{AuditEvent,Crypto,Csrf,Session,RateLimiter};

final class Controller
{
    private IdentityService $identity;
    public function __construct(private readonly \Closure $factory,private readonly Config $config) {}
    private function ensure(): void { if (!isset($this->identity)) { $this->identity=($this->factory)(); } }
    public function routes(Router $router): void
    {
        foreach(['admin','client'] as $context) {
            $base='/api/v1/'.$context.'/identity/';
            $router->add('GET',$base.'bootstrap',fn(Request $r,string $trace): Response=>$this->bootstrap($context,$trace));
            $router->add('GET',$base.'status',fn(Request $r,string $trace): Response=>$this->status($context,$trace));
            foreach(['me','users','user','customers','roles','staff','account','members','member-roles','invitations','sessions','devices','keys','events'] as $view) {
                $router->add('GET',$base.$view,function(Request $r,string $trace) use($context,$view): Response {
                    $this->ensure();
                    $native=Session::start($context,new PersistentSessionGuard($this->identity->repository,$context));
                    $customer=$r->query['customer'] ?? $r->query['id'] ?? null;
                    if ($customer!==null && !is_string($customer)) { throw new HttpError(422,'INVALID_ID'); }
                    return Response::data($this->identity->view($native->reference,$context,$view,$customer),$trace);
                });
            }
            foreach(['confirmation-prepare','login','register','token-request','token-complete','logout','logout-all','session-revoke','renew','step-up','enroll','enroll-confirm','mfa','password','mfa-disable','recovery','device-create','device-revoke','switch','account-update','customer-create','role-save','role-assign','role-remove','user-save','staff-save','invite','member-remove','member-scope','impersonate','impersonation-end','key-create','key-change'] as $action) {
                $router->add('POST',$base.$action,fn(Request $r,string $trace): Response=>$this->mutation($r,$context,$action,$trace));
            }
        }
        $router->add('GET','/api/v1/integration/identity',function(Request $r,string $trace): Response {
            $this->ensure();
            if (!(new RateLimiter($this->identity->db,base64_decode($this->config->key,true)))->allow('identity-bearer:'.$r->remoteAddress,60,300)) { throw new HttpError(429,'RATE_LIMITED'); }
            $token=$r->header('authorization'); if (!str_starts_with($token,'Bearer ')) { throw new HttpError(401,'INVALID_API_KEY'); }
            $customer=$r->query['customer'] ?? null; if ($customer!==null && !is_string($customer)) { throw new HttpError(422,'INVALID_ID'); }
            $permission=$customer===null?'identity.users.view':'account.view';
            $data=$this->identity->withKey(substr($token,7),$permission,$customer,fn($p): array=>['actor_id'=>$p->actorId,'customer_id'=>$p->customerId]);
            return Response::data($data,$trace);
        });
    }
    private function bootstrap(string $context,string $trace): Response
    {
        $state=Session::mutate($context,fn(array $data): array=>$data+['csrf'=>Csrf::token()]);
        return Response::data(['csrf'=>$state->data['csrf']],$trace);
    }
    private function status(string $context,string $trace): Response
    {
        $this->ensure();
        $native=Session::start($context,new PersistentSessionGuard($this->identity->repository,$context));
        $data=$this->identity->locked($native->reference,$context,function(array $u,array $s): array {
            $factor=$this->identity->db->execute('SELECT enabled FROM identity_mfa WHERE user_id=?',[$u['id']])->fetchColumn();
            return ['stage'=>$s['mfa_verified']?'active':($factor?'mfa':'enroll')];
        },true); return Response::data($data,$trace);
    }
    private function fields(array $data,array $allowed,array $required=[]): void { Validation::fields($data,$allowed,$required); }
    private function text(array $data,string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || strlen($data[$key])>4096 || str_contains($data[$key],"\0")) { throw new HttpError(422,'INVALID_FIELD'); }
        if (in_array($key,['id','user','customer','role'],true) && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$data[$key])) { throw new HttpError(422,'INVALID_ID'); }
        return $data[$key];
    }
    private function list(array $data,string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key]) || !array_is_list($data[$key])) { throw new HttpError(422,'INVALID_LIST'); }
        foreach($data[$key] as $value) { if (!is_string($value)) { throw new HttpError(422,'INVALID_LIST'); } } return $data[$key];
    }
    private function integer(array $data,string $key): int
    {
        if (!isset($data[$key]) || !is_int($data[$key])) { throw new HttpError(422,'INVALID_INTEGER'); } return $data[$key];
    }
    private function flag(array $data,string $key): bool
    {
        if (!isset($data[$key]) || !is_bool($data[$key])) { throw new HttpError(422,'INVALID_BOOLEAN'); } return $data[$key];
    }
    private function mutation(Request $r,string $context,string $action,string $trace): Response
    {
        $this->ensure();
        $native=Session::start($context); Csrf::verify($r,$native->data['csrf'] ?? ''); $data=$r->json();
        if (!in_array($action,['login','register','token-request','token-complete'],true)) { (new PersistentSessionGuard($this->identity->repository,$context))->assertActive($native); }
        $limiter=new RateLimiter($this->identity->db,base64_decode($this->config->key,true));
        $account=is_string($data['email'] ?? null)?strtolower(trim($data['email'])):$native->reference;
        if (!$limiter->allow('identity-http-net:'.$r->remoteAddress,60,300) || !$limiter->allow('identity-http:'.$action.':'.$account,10,300)) { throw new HttpError(429,'RATE_LIMITED'); }
        $key=$r->header('idempotency-key'); if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key)) { throw new HttpError(422,'INVALID_IDEMPOTENCY_KEY'); }
        $secret=base64_decode($this->config->key,true); $crypto=new Crypto($secret); $scope=$native->reference.':'.$context.':'.$action;
        $executed=false; $sensitive=in_array($action,['enroll','enroll-confirm','recovery','device-create','key-create'],true);
        try {
            $wrapped=(new Idempotency($this->identity->db))->run($native->reference,$scope,$key,['hmac'=>$this->identity->digest(CanonicalJson::encode($data))],function() use($r,$context,$action,$data,$native,$scope,$key,$crypto,&$executed): array {
                $executed=true;
                if(in_array($action,CriticalActions::ACTIONS,true)){
                    $command=$data;$approval=$command['confirmation'] ?? null;unset($command['confirmation']);
                    $result=(new CriticalActions($this->identity))->execute($native->reference,$context,$action,$command,$approval,fn():array=>$this->dispatch($command,$context,$action,$native->reference,$r));
                }else{$result=$this->dispatch($data,$context,$action,$native->reference,$r);}
                return ['cipher'=>$crypto->encrypt(json_encode($result,JSON_THROW_ON_ERROR),'http:'.$scope.':'.$key)];
            });
        } catch(HttpError $e) {
            $this->identity->recordDenial($native->reference,$context,in_array($action,['mfa','step-up'],true)?AuditEvent::MfaFailed:AuditEvent::AccessDenied); throw $e;
        }
        if (!$executed && $sensitive) { throw new HttpError(409,'ONE_TIME_RESPONSE_ALREADY_SHOWN'); }
        $result=json_decode($crypto->decrypt($wrapped['cipher'],'http:'.$scope.':'.$key),true,32,JSON_THROW_ON_ERROR);
        if (isset($result['denied'])) { throw new HttpError(401,'INVALID_CREDENTIALS'); }
        if ($action==='device-create') { setcookie('__Host-hostider_device',$result['device_token'],['expires'=>time()+2592000,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']); }
        if (in_array($action,['login','mfa','enroll-confirm','renew','switch','impersonate','impersonation-end'],true)) { Session::rotate($context); }
        if (in_array($action,['logout','logout-all','password','mfa-disable'],true)) { Session::end($context); }
        else { $result['csrf']=Session::start($context)->data['csrf'] ?? ''; }
        return Response::data($result,$trace);
    }
    private function dispatch(array $d,string $context,string $action,string $ref,Request $request): array
    {
        $s=$this->identity;
        switch($action) {
            case 'confirmation-prepare':
                $this->fields($d,['action','payload'],['action','payload']);$operation=$this->text($d,'action');
                if(!$d['payload'] instanceof \stdClass){throw new HttpError(422,'PAYLOAD_MUST_BE_OBJECT');}
                $payload=get_object_vars($d['payload']);
                return (new CriticalActions($s))->prepare($ref,$context,$operation,$payload,fn():array=>$this->dispatch($payload,$context,$operation,$ref,$request));
            case 'login': $this->fields($d,['email','password','device'],['email','password']); $device=$d['device'] ?? $_COOKIE['__Host-hostider_device'] ?? null; if($device!==null&&!is_string($device)){throw new HttpError(422,'INVALID_DEVICE');} return $s->login($this->text($d,'email'),$this->text($d,'password'),$context,$ref,$request->remoteAddress.':'.$request->header('user-agent'),$device);
            case 'register': $this->fields($d,['email','name','password'],['email','name','password']); if($context!=='client'){throw new HttpError(403,'CLIENT_ONLY');} $s->register($this->text($d,'email'),$this->text($d,'name'),$this->text($d,'password'),$request->remoteAddress); break;
            case 'token-request': $this->fields($d,['email','purpose'],['email','purpose']); $s->requestToken($this->text($d,'email'),$this->text($d,'purpose'),$request->remoteAddress); break;
            case 'token-complete': $this->fields($d,['token','purpose','password'],['token','purpose']); $s->completeToken($this->text($d,'token'),$this->text($d,'purpose'),isset($d['password'])?$this->text($d,'password'):null); break;
            case 'logout': case 'logout-all': $this->fields($d,[]); $s->revokeSession($ref,$context,all:$action==='logout-all'); break;
            case 'session-revoke': $this->fields($d,['id'],['id']); $s->revokeSession($ref,$context,$this->text($d,'id')); break;
            case 'renew': $this->fields($d,[]); $s->locked($ref,$context,fn()=>null); break;
            case 'step-up': $this->fields($d,['password','code'],['password']); $s->stepUp($ref,$context,$this->text($d,'password'),isset($d['code'])?$this->text($d,'code'):''); break;
            case 'enroll': $this->fields($d,['password'],['password']); return $s->enroll($ref,$context,$this->text($d,'password'));
            case 'enroll-confirm': $this->fields($d,['code'],['code']); return $s->confirmEnrollment($ref,$context,$this->text($d,'code'));
            case 'mfa': $this->fields($d,['code'],['code']); $s->verifyMfa($ref,$context,$this->text($d,'code')); break;
            case 'password': $this->fields($d,['current','next'],['current','next']); $s->changePassword($ref,$context,$this->text($d,'current'),$this->text($d,'next')); break;
            case 'mfa-disable': $this->fields($d,[]); $s->disableMfa($ref,$context); break;
            case 'recovery': $this->fields($d,[]); return $s->regenerateRecovery($ref,$context);
            case 'device-create': $this->fields($d,[]); return $s->trustDevice($ref,$context);
            case 'device-revoke': $this->fields($d,['id'],['id']); $s->revokeDevice($ref,$context,$this->text($d,'id')); break;
            case 'switch': $this->fields($d,['customer'],['customer']); $s->switchCustomer($ref,$context,$this->text($d,'customer')); break;
            case 'account-update': $this->fields($d,['customer','name','mfa_required','version'],['customer','name','mfa_required','version']); $s->updateAccount($ref,$context,$this->text($d,'customer'),$this->text($d,'name'),$this->flag($d,'mfa_required'),$this->integer($d,'version')); break;
            case 'customer-create': $this->fields($d,['name','currency'],['name','currency']); return $s->createCustomer($ref,$context,$this->text($d,'name'),$this->text($d,'currency'));
            case 'role-save': $this->fields($d,['id','name','permissions'],['name','permissions']); return $s->manageRole($ref,$context,isset($d['id'])?$this->text($d,'id'):null,$this->text($d,'name'),$this->list($d,'permissions'));
            case 'role-assign':
                $this->fields($d,['user','role','customer','grant'],['user','role']); $grant=$d['grant'] ?? null;
                if($grant!==null && !$grant instanceof \stdClass){throw new HttpError(422,'GRANT_MUST_BE_OBJECT');}
                $s->assignRole($ref,$context,$this->text($d,'user'),$this->text($d,'role'),isset($d['customer'])?$this->text($d,'customer'):null,$grant?get_object_vars($grant):null); break;
            case 'staff-save': $this->fields($d,['user','enabled','administrative'],['user','enabled','administrative']); $s->staff($ref,$context,$this->text($d,'user'),$this->flag($d,'enabled'),$this->flag($d,'administrative')); break;
            case 'user-save': $this->fields($d,['id','name','email','status'],['name']); return $s->managedUser($ref,$context,isset($d['id'])?$this->text($d,'id'):null,$this->text($d,'name'),isset($d['email'])?$this->text($d,'email'):null,isset($d['status'])?$this->text($d,'status'):null);
            case 'role-remove': $this->fields($d,['id'],['id']); $s->removeRole($ref,$context,$this->text($d,'id')); break;
            case 'invite': $this->fields($d,['customer','email','role'],['customer','email','role']); $s->invite($ref,$context,$this->text($d,'customer'),$this->text($d,'email'),$this->text($d,'role')); break;
            case 'member-remove': $this->fields($d,['customer','user'],['customer','user']); $s->removeMembership($ref,$context,$this->text($d,'customer'),$this->text($d,'user')); break;
            case 'member-scope': $this->fields($d,['customer','user','permission','service','department'],['customer','user','permission']); $s->memberScope($ref,$context,$this->text($d,'customer'),$this->text($d,'user'),$this->text($d,'permission'),isset($d['service'])?$this->text($d,'service'):null,isset($d['department'])?$this->text($d,'department'):null); break;
            case 'impersonate': $this->fields($d,['customer','user','reason'],['customer','user','reason']); return $s->impersonate($ref,$context,$this->text($d,'customer'),$this->text($d,'user'),$this->text($d,'reason'));
            case 'impersonation-end': $this->fields($d,['password','code'],['password','code']); $s->endImpersonation($ref,$context,$this->text($d,'password'),$this->text($d,'code')); break;
            case 'key-create': $this->fields($d,['scopes','seconds'],['scopes','seconds']); return $s->createKey($ref,$context,$this->list($d,'scopes'),$this->integer($d,'seconds'));
            case 'key-change': $this->fields($d,['id','scopes'],['id']); $s->changeKey($ref,$context,$this->text($d,'id'),isset($d['scopes'])?$this->list($d,'scopes'):null); break;
            default: throw new HttpError(404,'NOT_FOUND');
        }
        return ['ok'=>true];
    }
}
