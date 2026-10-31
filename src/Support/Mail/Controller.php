<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Core\{Config,Validation};
use Hostider\Core\Http\{Request,Response,Router,HttpError};
use Hostider\Core\Security\{Session,Csrf,RateLimiter};
use Hostider\Identity\PersistentSessionGuard;
use Hostider\Commerce\Services;
use Hostider\Support\Access;
final class Controller
{
    private Services $c;
    public function __construct(private readonly \Closure $factory,private readonly Config $config){}
    public function routes(Router $r): void
    {
        foreach(['client','admin'] as $ctx){foreach(['upload','download-token'] as $action){$r->add('POST','/api/v1/'.$ctx.'/support-mail/'.$action,fn($q,$t)=>$this->handle($q,$t,$ctx,$action));}$r->add('GET','/api/v1/'.$ctx.'/support-mail/files',fn($q,$t)=>$this->handle($q,$t,$ctx,'files'));$r->add('GET','/api/v1/'.$ctx.'/support-mail/download',fn($q,$t)=>$this->handle($q,$t,$ctx,'download'));}
        foreach(['proof','reply-token'] as $action){$r->add('POST','/api/v1/client/support-mail/'.$action,fn($q,$t)=>$this->handle($q,$t,'client',$action));}
        foreach(['prepare','execute','ingest'] as $action){$r->add('POST','/api/v1/admin/support-mail/'.$action,fn($q,$t)=>$this->handle($q,$t,'admin',$action));}foreach(['list','detail','raw','outbound','settings'] as $action){$r->add('GET','/api/v1/admin/support-mail/'.$action,fn($q,$t)=>$this->handle($q,$t,'admin',$action));}
    }
    private function handle(Request $q,string $trace,string $ctx,string $action): Response
    {
        if(!isset($this->c)){$this->c=($this->factory)();}$m=new Common($this->c);$m->gate();$session=Session::start($ctx,new PersistentSessionGuard($this->c->identity->repository,$ctx));$ref=$session->reference;$files=Factory::files($m);$commands=new Commands($m);
        if($q->method==='GET'){if($action==='files'){Validation::fields($q->query,['ticket_id','message_id'],['ticket_id','message_id']);return Response::data($files->listing($ref,$ctx,Access::id($q->query['ticket_id']),Access::id($q->query['message_id'])),$trace);}if($action==='download'){Validation::fields($q->query,['id'],['id']);if(strlen($q->header('x-download-token'))>128||$q->header('x-download-token')===''){throw new HttpError(422,'MAIL_TOKEN_INVALID');}$data=$files->download($ref,$ctx,Access::id($q->query['id']),$q->header('x-download-token'),$trace);return new Response(200,[],binary:$data['bytes'],downloadName:$data['filename']);}return Response::data($commands->view($ref,$action,$q->query,$trace),$trace);}
        Csrf::verify($q,$session->data['csrf']??'');if(!(new RateLimiter($this->c->db,base64_decode($this->config->key,true)))->allow('support-mail:'.$ref,120,300)){throw new HttpError(429,'RATE_LIMITED');}$p=$q->json();$token=new Tokens($m);
        if(in_array($action,['prepare','execute'],true)){return Response::data($commands->command($ref,$action,$p,$q->header('idempotency-key'),$trace),$trace);}
        if($action==='upload'){return Response::data($files->upload($ref,$ctx,$p,$q->header('idempotency-key'),$trace),$trace);}if($action==='download-token'){Validation::fields($p,['id'],['id']);return Response::data($files->token($ref,$ctx,Access::id($p['id'])),$trace);}
        if($action==='proof'){Validation::fields($p,['department_id','sha256'],['department_id','sha256']);if(!is_string($p['sha256'])){throw new HttpError(422,'MAIL_DIGEST_INVALID');}return Response::data(['proof'=>$token->proof($ref,Access::id($p['department_id']),$p['sha256'])],$trace);}
        if($action==='reply-token'){Validation::fields($p,['ticket_id'],['ticket_id']);return Response::data(['token'=>$token->reply($ref,Access::id($p['ticket_id']))],$trace);}
        Validation::fields($p,['department_id','raw_base64','proof'],['department_id','raw_base64','proof']);Access::id($p['department_id']);if(!is_string($p['raw_base64'])||!is_string($p['proof'])||strlen($p['proof'])>128||($raw=base64_decode($p['raw_base64'],true))===false){throw new HttpError(422,'MAIL_RAW_ENCODING');}
        $result=(new Inbound($m,$files))->ingest($p['department_id'],$raw,$p['proof'],fn()=>$this->c->identity->withStepUp($ref,'admin',fn($u,$s)=>$m->reviewer($u,$s,$p['department_id'],null)));return Response::data(array_intersect_key($result,array_flip(['id','status','reason','version'])),$trace);
    }
}
