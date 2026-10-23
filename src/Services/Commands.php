<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Id,Validation};use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{Authorization,Principal};
/** HTTP application commands. Planning never invokes a business mutation or provider. */
final readonly class Commands
{
    public Repository $repository;
    public function __construct(public \Hostider\Commerce\Services $c){$this->repository=new Repository($c->db,$c->config);}
    private function fields(string $action): array
    {
        return match($action){
            'approve','renew','cancel','cancel-undo','suspend','unsuspend','inspect'=>['service_id'],
            'terminate'=>['service_id','safeguard'], 'wallet-opt-in'=>['service_id','enabled'],
            'renewal-dispatch','renewal-wallet-pay'=>['service_id','renewal_id'],
            'resolve'=>['service_id','inspection_id'], 'package-request'=>['service_id','selection'], 'package-dispatch'=>['service_id','change_id'],
            'transfer-start'=>['service_id','target_customer_id'], 'transfer-accept'=>['transfer_id','token'],
            'pool-create'=>['product_id','capacity','weight'], 'mapping-create'=>['product_id','pool_id','package','reseller_scope','selection'],
            'policy-set'=>['scope_kind','scope_id','version','policy'], default=>throw new HttpError(422,'UNSUPPORTED_SERVICE_COMMAND')};
    }
    public function validate(string $action,array $payload): void
    {
        $fields=$this->fields($action);Validation::fields($payload,$fields,$fields);
        foreach($payload as $key=>$value){if(str_ends_with($key,'_id')&&$key!=='scope_id'){if(!is_string($value)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$value)){throw new HttpError(422,'INVALID_SERVICE_IDENTIFIER');}}}
        foreach(['safeguard','enabled'] as $key){if(array_key_exists($key,$payload)&&!is_bool($payload[$key])){throw new HttpError(422,'SERVICE_BOOLEAN_REQUIRED');}}
        foreach(['capacity','weight','version'] as $key){if(array_key_exists($key,$payload)&&(!is_int($payload[$key])||$payload[$key]<($key==='version'?0:1)||$payload[$key]>1000000)){throw new HttpError(422,'SERVICE_INTEGER_REQUIRED');}}
        foreach(['selection','policy'] as $key){if(array_key_exists($key,$payload)&&!is_array($payload[$key])){throw new HttpError(422,'SERVICE_OBJECT_REQUIRED');}}
        foreach(['scope_kind','scope_id','package','reseller_scope','token'] as $key){if(array_key_exists($key,$payload)){Validation::text($payload[$key],128);}}
    }
    public function offer(array $payload,string $customer): array
    {
        $row=$this->c->db->execute('SELECT * FROM svc_transfers WHERE id=? AND to_customer_id=?',[$payload['transfer_id'],$customer])->fetch();$key=base64_decode($this->c->config->key,true);if(!$row||!is_string($payload['token'])||!hash_equals($row['token_hmac'],hash_hmac('sha256',$payload['token'],$key))){throw new HttpError(404,'TRANSFER_NOT_FOUND');}return $row;
    }
    public function serviceId(string $action,array $payload,?string $customer): ?string{return $action==='transfer-accept'?$this->offer($payload,$customer??throw new HttpError(422,'CUSTOMER_REQUIRED'))['service_id']:($payload['service_id']??null);}
    public function authorize(array $user,array $session,string $action,array $payload,?string $customer): Principal
    {
        $this->validate($action,$payload);$actor=$this->c->identity->principal($user,$session);if($actor->impersonating){throw new HttpError(403,'SERVICE_IMPERSONATION_FORBIDDEN');}
        $permission=match($action){'approve'=>'services.approve','renew','renewal-dispatch'=>'services.renew','wallet-opt-in','renewal-wallet-pay'=>'services.wallet','cancel','cancel-undo'=>'services.cancel','suspend'=>'services.suspend','unsuspend'=>'services.unsuspend','terminate'=>'services.terminate','inspect','resolve'=>'services.reconcile','package-request','package-dispatch'=>'services.package','transfer-start'=>'services.transfer','transfer-accept'=>'services.transfer.accept','policy-set'=>'services.policy','pool-create','mapping-create'=>'services.pools',default=>throw new HttpError(422,'UNSUPPORTED_SERVICE_COMMAND')};
        $global=in_array($action,['pool-create','mapping-create','policy-set'],true);if($global){(new Authorization())->require($actor,$permission,privileged:true,administrative:true);return $actor;}if($customer===null){throw new HttpError(422,'CUSTOMER_REQUIRED');}
        if(in_array($action,['approve','suspend','unsuspend','terminate','inspect','resolve'],true)&&!$actor->staff){throw new HttpError(403,'STAFF_REQUIRED');}
        if($action==='transfer-accept'){$actor=$this->c->identity->authorize($user,$session,$permission,$customer,privileged:true);$offer=$this->offer($payload,$customer);if($offer['status']==='COMPLETED'){$this->c->identity->authorize($user,$session,'services.view',$customer,$offer['service_id']);}return $actor;}
        return $this->c->identity->authorize($user,$session,$permission,$customer,$payload['service_id'],privileged:true);
    }
    public function lockState(string $action,array $payload,?string $customer): array
    {
        $db=$this->c->db;$db->requireTransaction();$versions=new \Hostider\Core\Database\VersionFence($db);$state=['services_version'=>$versions->read('services',$action==='policy-set'),'catalog_version'=>$versions->read('catalog'),'fx_version'=>$versions->read('fx')];$id=$this->serviceId($action,$payload,$customer);
        if($id!==null){
            $identity=$this->repository->find($id,lock:false);$this->c->identity->repository->owns($id,$identity['customer_id']);$accounts=array_values(array_unique(array_filter([$customer,$identity['customer_id']])));sort($accounts);foreach($accounts as $account){$state['accounts'][]=$this->c->identity->repository->customer($account,true);}
            $state['source']=$db->execute('SELECT * FROM orders WHERE id=? FOR UPDATE',[$identity['order_id']])->fetch();$db->execute('SELECT id FROM order_items WHERE id=? FOR UPDATE',[$identity['order_item_id']]);$service=$this->repository->find($id);$state['service']=$service;$state['period']=$db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE',[$service['current_period_id']])->fetch();$state['price']=$db->execute('SELECT * FROM svc_prices WHERE id=?',[$service['current_price_id']])->fetch();
            $state['operation']=$db->execute('SELECT * FROM svc_operations WHERE id=? FOR UPDATE',[$service['current_operation_id']])->fetch();$state['preference']=$db->execute('SELECT * FROM svc_preferences WHERE service_id=? FOR UPDATE',[$id])->fetch();$state['renewals']=$db->execute("SELECT r.*,o.payment_request_id,o.status AS order_status,o.refund_status,b.status AS bill_status,b.version AS bill_version,b.refund_status AS bill_refund FROM svc_renewals r JOIN orders o ON o.id=r.order_id JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE r.service_id=? AND r.status IN ('AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN') ORDER BY r.id FOR UPDATE",[$id])->fetchAll();$state['packages']=$db->execute("SELECT p.*,o.payment_request_id,o.status AS order_status,b.status AS bill_status,b.version AS bill_version,b.refund_status FROM svc_package_changes p JOIN orders o ON o.id=p.order_id JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE p.service_id=? AND p.status IN ('AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN') ORDER BY p.id FOR UPDATE",[$id])->fetchAll();$state['cancellations']=$db->execute("SELECT * FROM svc_cancellations WHERE service_id=? AND status='PENDING' ORDER BY id FOR UPDATE",[$id])->fetchAll();$state['transfers']=$db->execute("SELECT * FROM svc_transfers WHERE service_id=? AND status='PENDING' ORDER BY id FOR UPDATE",[$id])->fetchAll();$state['bill']=$db->execute('SELECT * FROM billing_request_states WHERE request_id=? FOR UPDATE',[$state['period']['billing_request_id']??null])->fetch();
            if($action==='renewal-wallet-pay'&&$customer!==null){$wallet=$this->c->finance->repository->wallet($customer);$state['wallet']=$wallet;$state['balance']=$this->c->finance->repository->balance($wallet['id'],true);}
        }else{$state['pools']=$db->execute('SELECT id,pool_id,capacity,weight,healthy,enabled,observed_at,capabilities FROM hosting_servers ORDER BY id FOR UPDATE')->fetchAll();$state['mappings']=$db->execute('SELECT * FROM hosting_mappings ORDER BY id FOR UPDATE')->fetchAll();$state['policies']=$db->execute('SELECT * FROM svc_policies ORDER BY scope_kind,scope_id FOR UPDATE')->fetchAll();}
        return $state;
    }
    public function plan(string $action,array $payload,?string $customer,Principal $actor,\DateTimeImmutable $now): array
    {
        $id=$this->serviceId($action,$payload,$customer);$service=$id!==null?$this->repository->find($id,lock:false):null;$result=['action'=>$action,'environment'=>'test','provider_execution'=>'DURABLE_MOCK_ONLY','effect'=>'İşlem yalnız test/mock ortamında kaydedilecek.'];
        $this->planChecks($action,$payload,$service,$customer);
        if($service!==null){$result=array_replace($result,['service_name'=>$service['name'],'current_status'=>$service['status'],'service_version'=>(string)$service['version']]);}
        if($action==='renew'){$quote=(new Renewals($this->c->db,$this->c->config))->plan($id,$customer,$actor,$now)->jsonSerialize();$result=array_replace($result,['currency'=>$quote['currency'],'total_minor'=>$quote['total_minor'],'effect'=>'Yeni yenileme faturası oluşturulacak; ödeme tek başına hizmet süresini uzatmaz.']);}
        elseif($action==='package-request'){$plan=(new PackageChanges($this->c->db,$this->c->config))->plan($id,$customer,$payload['selection'],$actor,$now);$quote=$plan['quote']->jsonSerialize();$result=array_replace($result,['currency'=>$quote['currency'],'total_minor'=>$quote['total_minor'],'target_name'=>$plan['target']['name'],'remaining_seconds'=>$plan['remaining_seconds'],'rounding'=>$plan['rounding'],'effect'=>'Kalan süre için paket farkı faturalandırılacak. Tam ödeme ve sağlayıcı kanıtı sonrası aynı kaynakta paket değişecek.']);}
        elseif($action==='terminate'){$result['effect']='Bekleme ve safeguard doğrulanırsa mock hesap kalıcı olarak sonlandırılacak. Bu işlem geri alınamaz.';}
        elseif($action==='inspect'){$result['effect']='Ayrı worker mevcut mock kaynağı inceleyecek. Create veya başka mutation tekrar edilmeyecek.';}
        elseif($action==='resolve'){$inspection=$this->c->db->execute('SELECT id,service_id,actor_id,found,expires_at>UTC_TIMESTAMP(6) AS live FROM svc_inspections WHERE id=?',[$payload['inspection_id']])->fetch();if(!$inspection||$inspection['service_id']!==$id||$inspection['actor_id']!==$actor->actorId){throw new HttpError(404,'INSPECTION_NOT_FOUND');}if(!$inspection['found']||!$inspection['live']){throw new HttpError(409,'INSPECTION_REQUIRES_MANUAL_REVIEW');}$result['effect']='Mevcut bağımsız inspect kanıtı yeniden doğrulanarak UNKNOWN sonucu bağlanacak; tahsilat veya mutation tekrarı yok.';}
        elseif(in_array($action,['renewal-dispatch','renewal-wallet-pay'],true)){$row=$this->c->db->execute('SELECT order_id FROM svc_renewals WHERE id=? AND service_id=? AND customer_id=?',[$payload['renewal_id'],$id,$customer])->fetch();if(!$row){throw new HttpError(404,'RENEWAL_NOT_FOUND');}$order=$this->c->orders->repository->find($row['order_id'],$customer);$bill=$this->c->billing->detail($order['payment_request_id'],$customer);$result=array_replace($result,['currency'=>$bill['currency'],'total_minor'=>$bill['total_minor'],'paid_minor'=>$bill['paid_minor'],'effect'=>$action==='renewal-wallet-pay'?'Açık opt-in varsa kalan yenileme tutarı cüzdandan ödenecek.':'Tam ödenen yenileme sağlayıcı doğrulaması için kuyruğa alınacak.']);}
        elseif($action==='package-dispatch'){if(!$this->c->db->execute('SELECT 1 FROM svc_package_changes WHERE id=? AND service_id=? AND customer_id=?',[$payload['change_id'],$id,$customer])->fetchColumn()){throw new HttpError(404,'PACKAGE_CHANGE_NOT_FOUND');}$result['effect']='Tam ödenen paket değişikliği mevcut mock kaynak için kuyruğa alınacak.';}
        elseif(in_array($action,['transfer-start','transfer-accept'],true)){$result['effect']='İki gerçek müşteri sahibinin süreli onayı doğrulanırsa yalnız hizmet sahipliği değişecek; eski finans belgeleri taşınmaz.';}
        elseif($action==='pool-create'){$result=array_replace($result,['capacity'=>$payload['capacity'],'weight'=>$payload['weight'],'effect'=>'Yalnız sentetik kimlik bilgisi içeren mock sunucu havuzu oluşturulacak.']);}
        if($action==='wallet-opt-in'){$result['effect']=$payload['enabled']?'Bu hizmetin yenilemesini cüzdandan ödemeye izin vereceksiniz. Bu onayda bakiye harcanmaz.':'Bu hizmetin cüzdandan otomatik yenileme izni kaldırılacak.';}
        elseif($action==='approve'){$result['effect']='Tam ödenen hizmet onaylanıp mock kurulum kuyruğuna alınacak. Sağlayıcı kanıtı olmadan aktif olmaz.';}
        elseif($action==='cancel'){$policy=(new Policies($this->c->db,$this->c->config))->resolve($service);$result['effect']='İptal dönem sonuna planlanacak. Sonlandırma için ayrıca en az '.$policy['termination_wait_seconds'].' saniye bekleme ve açık safeguard gerekir.';}
        elseif($action==='cancel-undo'){$result['effect']='Bekleyen iptal kaldırılacak; sonlandırılmış kaynak geri oluşturulmaz.';}
        elseif($action==='suspend'){$result['effect']='Mevcut mock hesap askıya alınacak. Finans kayıtları ve geçmiş faturalar silinmez.';}
        elseif($action==='unsuspend'){$result['effect']='Güncel tam ödenmiş ve sağlayıcıda doğrulanmış dönem varsa mock hesap yeniden açılacak.';}
        elseif($action==='policy-set'){$result['effect']='Seçilen kapsamın hizmet politikası değişecek. Boş değerler üst kapsamdan devralınır; açık canlı GATE değişmez.';}
        return $result;
    }
    private function planChecks(string $action,array $payload,?array $service,?string $customer): void
    {
        $db=$this->c->db;if($service!==null&&($service['imported']||$service['origin_environment']!=='test')){throw new HttpError(409,'SERVICE_ACTION_ORIGIN_FORBIDDEN');}
        $expected=match($action){'approve'=>['PENDING_APPROVAL'],'cancel'=>['ACTIVE','SUSPENDED'],'cancel-undo','terminate'=>['CANCEL_SCHEDULED'],'suspend'=>['ACTIVE'],'unsuspend'=>['SUSPENDED'],'inspect','resolve'=>['UNKNOWN'],default=>null};if($expected!==null&&($service===null||!in_array($service['status'],$expected,true))){throw new HttpError(409,'SERVICE_ACTION_STATE_CONFLICT');}
        if($action==='cancel'){$policy=(new Policies($db,$this->c->config))->resolve($service);if(Policies::required($policy,'allow_cancel')!==true){throw new HttpError(409,'SERVICE_CANCELLATION_DISABLED');}Policies::required($policy,'termination_wait_seconds');}
        if($action==='terminate'){$due=$db->execute("SELECT 1 FROM svc_cancellations WHERE service_id=? AND status='PENDING' AND execute_after<=UTC_TIMESTAMP(6)",[$service['id']])->fetchColumn();if(!$payload['safeguard']||!$due){throw new HttpError(409,'TERMINATION_WAIT_AND_EXPLICIT_SAFEGUARD_REQUIRED');}}
        if($action==='policy-set'){$policies=new Policies($db,$this->c->config);$policies->validate($payload['scope_kind'],$payload['scope_id'],$payload['policy']);$version=$db->execute('SELECT version FROM svc_policies WHERE scope_kind=? AND scope_id=?',[$payload['scope_kind'],$payload['scope_id']])->fetchColumn();if((int)($version?:0)!==$payload['version']){throw new HttpError(409,'POLICY_VERSION_CONFLICT');}}
        if($action==='pool-create'){if($payload['weight']>65535||$this->c->catalog->repository->product($payload['product_id'])['product_type']!=='HOSTING'){throw new HttpError(422,'INVALID_MOCK_POOL');}}
        if($action==='mapping-create'){Validation::fields($payload['selection'],['product_id','cycle_id','quantity','variant_id','options','addons'],[]);if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$payload['package'])||!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$payload['reseller_scope'])||!$db->execute('SELECT 1 FROM hosting_pools WHERE id=? AND enabled=TRUE',[$payload['pool_id']])->fetchColumn()||$this->c->catalog->repository->product($payload['product_id'])['product_type']!=='HOSTING'){throw new HttpError(422,'INVALID_MOCK_MAPPING');}}
        if($action==='transfer-accept'){$offer=$this->offer($payload,$customer);if($offer['status']!=='PENDING'||new \DateTimeImmutable($offer['expires_at'],new \DateTimeZone('UTC'))<=new \DateTimeImmutable('now',new \DateTimeZone('UTC'))){throw new HttpError(409,'TRANSFER_EXPIRED_USED_OR_SERVICE_CHANGED');}}
    }
    private function done(\Closure $work): array{$work();return ['accepted'=>true];}
    public function execute(string $action,array $p,?string $customer,Principal $actor,array $user,array $session,string $trace,\DateTimeImmutable $now): array
    {
        $db=$this->c->db;$config=$this->c->config;$id=$p['service_id']??null;$renewals=new Renewals($db,$config);$actions=new Actions($db,$config);$packages=new PackageChanges($db,$config);$result=match($action){
            'approve'=>$this->done(fn()=>(new Engine($db,$config))->approve($id,$customer,$actor,$trace)),
            'renew'=>$renewals->request($id,$customer,$actor,$trace,$now),
            'wallet-opt-in'=>$this->done(fn()=>$renewals->walletOptIn($id,$customer,$p['enabled'],$actor,$trace)),
            'renewal-wallet-pay'=>$renewals->autoFund($p['renewal_id'],$actor,$trace),
            'renewal-dispatch'=>$this->done(fn()=>$renewals->preparePaid($p['renewal_id'],$actor,$trace)),
            'cancel'=>$actions->scheduleCancel($id,$customer,$actor,$trace), 'cancel-undo'=>$this->done(fn()=>$actions->undoCancel($id,$customer,$actor,$trace)),
            'suspend','unsuspend','terminate'=>['operation_id'=>$actions->request($id,$customer,strtoupper($action),$actor,$trace,$p['safeguard']??false)],
            'inspect'=>(new Inspector($db,$config))->request($id,$customer,$session['native_reference'],$user['id'],$trace),
            'resolve'=>(new Reconciliation($db,$config))->resolve($id,$customer,$p['inspection_id'],$actor,$trace),
            'package-request'=>$packages->request($id,$customer,$p['selection'],$actor,$trace,$now), 'package-dispatch'=>$this->done(fn()=>$packages->preparePaid($p['change_id'],$actor,$trace)),
            'transfer-start'=>(new Transfers($db,$config))->start($id,$customer,$p['target_customer_id'],$session['native_reference'],$trace),
            'transfer-accept'=>(new Transfers($db,$config))->accept($p['transfer_id'],$customer,$p['token'],$session['native_reference'],$trace),
            'pool-create'=>(new \Hostider\Hosting\Pools($db,$config))->configure($p['product_id'],$p['capacity'],$p['weight']),
            'mapping-create'=>['id'=>(new \Hostider\Hosting\Pools($db,$config))->mapSelection($p['product_id'],$p['pool_id'],$p['package'],$p['reseller_scope'],$p['selection'])],
            'policy-set'=>$this->done(fn()=>(new Policies($db,$config))->set($p['scope_kind'],$p['scope_id'],$p['policy'],$p['version'],$actor)),default=>throw new HttpError(422,'UNSUPPORTED_SERVICE_COMMAND')};
        // Client responses never include encrypted request/config, original financial references or provider identities.
        $allowed=['accepted','id','order_id','period_id','status','cancellation_id','service_id','ownership_changed','transfer_id','recipient_token','expires_in'];if($actor->staff){$allowed=array_merge($allowed,['inspection_job_id','resolution_id','pool','server','mapping','operation_id']);}return array_intersect_key($result,array_flip($allowed));
    }
}
