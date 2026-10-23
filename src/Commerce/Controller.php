<?php
declare(strict_types=1);
namespace Hostider\Commerce;
use Hostider\Core\{CanonicalJson,Config,Idempotency,Validation};
use Hostider\Core\Http\{HttpError,Request,Response,Router};
use Hostider\Core\Security\{Crypto,Csrf,RateLimiter,Session};
use Hostider\Identity\PersistentSessionGuard;
final class Controller
{
    private Services $s;
    public function __construct(private readonly \Closure $factory,private readonly Config $config) {}
    private function ensure(): void {if(!isset($this->s)){$this->s=($this->factory)();}}
    public function routes(Router $router): void
    {
        foreach(['admin','client'] as $context){
            $base='/api/v1/'.$context.'/commerce/';
            foreach(['status','catalog','configuration','catalog-admin','orders','order','bills','bill','wallet','payments','notices','cash','ledger','reconciliations','reconciliation','documents','document'] as $view){$router->add('GET',$base.$view,fn(Request $r,string $trace): Response=>$this->view($r,$context,$view,$trace));}
            $router->add('POST',$base.'prepare',fn(Request $r,string $trace): Response=>$this->mutation($r,$context,true,$trace));
            $router->add('POST',$base.'execute',fn(Request $r,string $trace): Response=>$this->mutation($r,$context,false,$trace));
            $router->add('GET','/'.$context.'/commerce',fn(): Response=>Panel::response($context));
        }
        $router->add('POST','/api/v1/webhooks/paytr',function(): never {
            $this->ensure();$this->s->db->transaction(fn()=>(new \Hostider\Core\Security\Audit($this->s->db))->record(new \Hostider\Core\Security\Principal('anonymous',[]),\Hostider\Core\Security\AuditEvent::AccessDenied,\Hostider\Core\Id::uuid(),\Hostider\Core\Id::uuid()));
            (new \Hostider\Payments\PaytrBoundary())->acceptCallback([]);
        });
    }
    private function customer(array $user,array $session,?string $requested): ?string
    {
        $principal=$this->s->identity->principal($user,$session);$id=$principal->staff&&!$principal->impersonating?$requested:$principal->customerId;
        if(!$principal->staff&&$requested!==null&&$requested!==$id){throw new HttpError(403,'CUSTOMER_OVERRIDE_FORBIDDEN');}
        if($id!==null&&!preg_match('/^[a-f0-9-]{36}$/D',$id)){throw new HttpError(422,'INVALID_CUSTOMER_ID');}return $id;
    }
    private function query(Request $r,string $key,bool $required=false): ?string
    {
        $value=$r->query[$key]??null;if($value===null&&!$required){return null;}if(!is_string($value)||strlen($value)>128){throw new HttpError(422,'INVALID_QUERY');}return $value;
    }
    private static function moneyStrings(array $data): array
    {
        foreach($data as $key=>$value){if(is_array($value)){$data[$key]=self::moneyStrings($value);}elseif(is_int($value)&&(str_ends_with((string)$key,'_minor')||$key==='minor')){$data[$key]=(string)$value;}}return $data;
    }
    private function view(Request $r,string $context,string $view,string $trace): Response
    {
        $this->ensure();$native=Session::start($context,new PersistentSessionGuard($this->s->identity->repository,$context));
        return $this->s->identity->locked($native->reference,$context,function(array $u,array $session)use($r,$view,$trace): Response {
            $customer=$this->customer($u,$session,$this->query($r,'customer'));$p=$this->s->identity->principal($u,$session);
            if($p->impersonating){throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN');}
            if($view==='status'){return Response::data(['name'=>$u['display_name'],'permissions'=>$p->permissions,'customer_id'=>$customer,'staff'=>$p->staff,'administrative'=>$p->administrative,'environment'=>$this->config->environment,'step_up_fresh'=>(int)$session['verified']>time()-300,'live_payments'=>false,'live_provisioning'=>false],$trace);}
            $permission=match($view){'catalog','configuration'=>'catalog.view','catalog-admin'=>'catalog.manage','orders','order'=>'orders.view','bills','bill','documents','document'=>'billing.view','wallet'=>'wallet.view','payments','notices'=>'billing.view','cash','ledger','reconciliations','reconciliation'=>'finance.view',default=>throw new HttpError(404,'VIEW_NOT_FOUND')};
            $global=in_array($view,['catalog','configuration','catalog-admin','cash','ledger','reconciliations'],true);if(!$global&&$customer===null){throw new HttpError(422,'CUSTOMER_REQUIRED');}
            $this->s->identity->authorize($u,$session,$permission,$global?null:$customer);
            if(in_array($view,['catalog-admin','cash','ledger','reconciliations','reconciliation'],true)&&!$p->staff){throw new HttpError(403,'STAFF_REQUIRED');}
            $offset=$this->query($r,'offset')??'0';if(!ctype_digit($offset)||(int)$offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}$offset=(int)$offset;
            $id=$this->query($r,'id');$data=match($view){
                'catalog'=>$this->s->catalog->repository->listProducts(true,$offset),'configuration'=>$this->s->catalog->repository->configuration($id??throw new HttpError(422,'ID_REQUIRED')),
                'catalog-admin'=>$this->catalogAdmin($offset),'orders'=>$this->s->orders->repository->list($customer,$offset),'order'=>$this->s->orders->repository->find($id??throw new HttpError(422,'ID_REQUIRED'),$customer),
                'bills'=>$this->s->billing->repository->list($customer,$offset),'bill'=>$this->s->billing->detail($id??throw new HttpError(422,'ID_REQUIRED'),$customer),'wallet'=>$this->wallet($customer,$offset),
                'payments'=>$this->s->db->execute('SELECT id,method,external_reference,CAST(amount_minor AS CHAR) amount_minor,currency,created_at FROM finance_payments WHERE customer_id=? ORDER BY created_at DESC,id LIMIT 50 OFFSET '.$offset,[$customer])->fetchAll(),
                'notices'=>$this->s->db->execute('SELECT n.id,n.attempt_id,n.status,n.version,a.purpose,a.order_id,CAST(a.expected_minor AS CHAR) expected_minor,a.currency FROM payment_bank_notices n JOIN payment_attempts a ON a.id=n.attempt_id WHERE a.customer_id=? ORDER BY n.created_at DESC,n.id LIMIT 50 OFFSET '.$offset,[$customer])->fetchAll(),
                'cash'=>$this->s->cash->report()+['categories'=>$this->s->db->execute('SELECT * FROM finance_cash_categories ORDER BY id LIMIT 50 OFFSET '.$offset)->fetchAll(),'recurring'=>$this->s->db->execute('SELECT * FROM finance_recurring_rules ORDER BY id LIMIT 50 OFFSET '.$offset)->fetchAll(),'due'=>$this->s->db->execute('SELECT * FROM finance_due_expenses ORDER BY id LIMIT 50 OFFSET '.$offset)->fetchAll()],
                'reconciliation'=>(new \Hostider\Finance\Reconciliation($this->s->db,$this->config,$this->s->finance))->detail($id??throw new HttpError(422,'ID_REQUIRED'),$customer,$p),
                'ledger'=>$this->ledger($offset),'reconciliations'=>$this->s->db->execute('SELECT r.id,r.subject_id,r.code,r.actor_id,r.trace_id,r.created_at,p.customer_id,CASE WHEN x.id IS NULL THEN \'OPEN\' ELSE \'RESOLVED\' END status,x.action resolution_action,x.evidence_sha256 FROM finance_reconciliations r LEFT JOIN finance_payments p ON p.id=r.subject_id LEFT JOIN finance_reconciliation_resolutions x ON x.case_id=r.id ORDER BY r.created_at DESC,r.id LIMIT 50 OFFSET '.$offset)->fetchAll(),
                'documents'=>$this->s->documents()->list($id??throw new HttpError(422,'ID_REQUIRED'),$customer),'document'=>$this->s->documents()->download($id??throw new HttpError(422,'ID_REQUIRED'),$customer)};
            if($view==='document'){return new Response(200,[],binary:$data['bytes'],downloadName:$data['filename']);}return Response::data(self::moneyStrings($data),$trace);
        });
    }
    private function wallet(string $customer,int $offset): array
    {
        $account=$this->s->identity->repository->customer($customer);$row=$this->s->db->execute('SELECT * FROM finance_wallets WHERE customer_id=?',[$customer])->fetch();
        if(!$row){return ['currency'=>$account['currency'],'balance_minor'=>'0','entries'=>[],'attempts'=>[]];}
        return ['currency'=>$row['currency'],'balance_minor'=>$this->s->finance->repository->balance($row['id']),'entries'=>$this->s->db->execute('SELECT e.id,CAST(e.amount_minor AS CHAR) amount_minor,e.reference,e.created_at,j.currency FROM finance_wallet_entries e JOIN finance_journals j ON j.id=e.journal_id WHERE e.wallet_id=? ORDER BY e.created_at DESC,e.id LIMIT 50 OFFSET '.$offset,[$row['id']])->fetchAll(),'attempts'=>$this->s->db->execute('SELECT id,purpose,CAST(expected_minor AS CHAR) expected_minor,currency,status,order_id FROM payment_attempts WHERE customer_id=? ORDER BY created_at DESC,id LIMIT 50 OFFSET '.$offset,[$customer])->fetchAll()];
    }
    private function catalogAdmin(int $offset): array
    {
        $out=[];foreach(['categories','tax_rules','cycles','items','products','variants','option_groups','option_values','addons','product_addons','prices','cost_proposals','provider_mappings','promotions'] as $table){$out[$table]=$this->s->db->execute('SELECT * FROM catalog_'.$table.' ORDER BY 1 LIMIT 50 OFFSET '.$offset)->fetchAll();}
        $out['fx_rates']=$this->s->db->execute('SELECT * FROM pricing_fx_rates ORDER BY observed_at DESC LIMIT 50 OFFSET '.$offset)->fetchAll();return $out;
    }
    private function ledger(int $offset): array
    {
        $journals=$this->s->db->execute('SELECT id,reference,currency,actor_id,trace_id,reversal_of,created_at FROM finance_journals ORDER BY created_at DESC,id LIMIT 50 OFFSET '.$offset)->fetchAll();
        foreach($journals as &$journal){$journal['lines']=$this->s->db->execute('SELECT account,side,CAST(amount_minor AS CHAR) amount_minor FROM finance_lines WHERE journal_id=? ORDER BY id',[$journal['id']])->fetchAll();}unset($journal);return $journals;
    }
    private function mutation(Request $r,string $context,bool $prepare,string $trace): Response
    {
        if($this->config->environment==='production'){throw new HttpError(503,'F2_PRODUCTION_RELEASE_GATE_OPEN');}
        $this->ensure();$native=Session::start($context,new PersistentSessionGuard($this->s->identity->repository,$context));Csrf::verify($r,$native->data['csrf']??'');$data=$r->json();Validation::fields($data,['action','payload','customer','confirmation'],['action','payload']);
        $action=Validation::text($data['action'],64);if(!$data['payload'] instanceof \stdClass){throw new HttpError(422,'PAYLOAD_OBJECT_REQUIRED');}
        $payload=json_decode(json_encode($data['payload'],JSON_THROW_ON_ERROR),true,32,JSON_THROW_ON_ERROR);$requested=$data['customer']??null;if($requested!==null&&!is_string($requested)){throw new HttpError(422,'INVALID_CUSTOMER_ID');}
        if(!(new RateLimiter($this->s->db,base64_decode($this->config->key,true)))->allow('commerce:'.$native->reference,120,300)){throw new HttpError(429,'RATE_LIMITED');}
        // Each statement sees the current locked state; Identity uses the same user/session commit fence.
        $this->s->db->execute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$key=$r->header('idempotency-key');$scope='commerce:'.$context.':'.($prepare?'prepare':'execute');$crypto=new Crypto(base64_decode($this->config->key,true));$binding=$native->reference.':'.$scope.':'.$key;
        try{
            $result=(new Idempotency($this->s->db))->run($native->reference,$scope,$key,['hmac'=>$this->s->identity->digest(CanonicalJson::encode($data))],function()use($native,$context,$action,$payload,$requested,$data,$prepare,$trace,$crypto,$binding): array {
                $out=$this->s->identity->withStepUp($native->reference,$context,function(array $u,array $session)use($action,$payload,$requested,$data,$prepare,$trace): array {
                    $customer=$this->customer($u,$session,$requested);$commands=new Commands($this->s);$confirmations=new Confirmations($this->s,$commands);
                    $confirmation=$data['confirmation']??new \stdClass();if(!$confirmation instanceof \stdClass){throw new HttpError(422,'CONFIRMATION_OBJECT_REQUIRED');}
                    return $prepare?$confirmations->prepare($u,$session,$action,$payload,$customer):$confirmations->execute($u,$session,$action,$payload,$customer,get_object_vars($confirmation),$trace);
                });return ['encrypted'=>$crypto->encrypt(json_encode($out,JSON_THROW_ON_ERROR),'commerce-result:'.$binding)];
            },function()use($native,$context,$action,$requested): void {
                $this->s->identity->withStepUp($native->reference,$context,function(array $u,array $session)use($action,$requested): void {
                    (new Commands($this->s))->authorize($u,$session,$action,$this->customer($u,$session,$requested));
                });
            });
            return Response::data(json_decode($crypto->decrypt($result['encrypted'],'commerce-result:'.$binding),true,64,JSON_THROW_ON_ERROR),$trace);
        }catch(\InvalidArgumentException|\OverflowException|\DivisionByZeroError){throw new HttpError(422,'INVALID_FINANCIAL_VALUE');}
        catch(HttpError $error){if(in_array($error->status,[401,403],true)){$this->s->identity->recordDenial($native->reference,$context,\Hostider\Core\Security\AuditEvent::AccessDenied);}throw $error;}
    }
}
