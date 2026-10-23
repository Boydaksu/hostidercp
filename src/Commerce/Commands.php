<?php
declare(strict_types=1);
namespace Hostider\Commerce;
use Hostider\Core\{Id,Validation};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Finance\{Currency,Decimal,FxSnapshot,Money,Rounding};
final readonly class Commands
{
    public const CATALOG=['tax-create','category-create','category-update','cycle-create','product-create','product-update','item-rename','variant-create','group-create','option-create','addon-create','addon-link','addon-policy','product-status','price-publish','cost-propose','provider-map','promotion-create'];
    public function __construct(private Services $s) {}
    public function permission(string $action): string
    {
        if(in_array($action,self::CATALOG,true)){return 'catalog.manage';}
        return match($action){'quote-create'=>'orders.create','order-submit'=>'orders.create','wallet-pay'=>'orders.pay','order-cancel'=>'orders.cancel','attempt-create'=>'wallet.topup','bank-notify'=>'payment.notify','reconciliation-resolve','bank-approve','customer-currency','order-refund','refund-request'=>'finance.approve','expense-create','cash-category','recurring-create','recurring-due','recurring-pay'=>'finance.manage','fx-create'=>'pricing.manage','document-start','document-chunk','document-finish'=>'billing.documents.manage',default=>throw new HttpError(422,'UNKNOWN_COMMERCE_COMMAND')};
    }
    public function authorize(array $user,array $session,string $action,?string $customer): Principal
    {
        $permission=$this->permission($action);$global=in_array($permission,['catalog.manage','pricing.manage','finance.manage'],true);
        if(!$global&&$customer===null){throw new HttpError(422,'CUSTOMER_REQUIRED');}
        $actor=$this->s->identity->authorize($user,$session,$permission,$global?null:$customer,privileged:true);
        if($actor->impersonating){throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN');}
        if(in_array($permission,['catalog.manage','pricing.manage','finance.manage','finance.approve','billing.documents.manage'],true)&&!$actor->staff){throw new HttpError(403,'STAFF_REQUIRED');}if($action==='customer-currency'){$this->s->identity->lockCustomerUsers($customer);}return $actor;
    }
    private function text(array $p,string $key,int $max=128): string {return Validation::text($p[$key]??null,$max);}
    private function integer(array $p,string $key,int $min=0,int $max=PHP_INT_MAX): int
    {
        $value=$p[$key]??null;if(!is_int($value)||$value<$min||$value>$max){throw new HttpError(422,'INVALID_INTEGER');}return $value;
    }
    private function customer(?string $id): array {return $this->s->identity->repository->customer($id??throw new HttpError(422,'CUSTOMER_REQUIRED'),true);}
    private function money(array $p,?string $customer): Money {return new Money($this->text($p,'minor',20),new Currency($this->customer($customer)['currency']));}
    private function requestMoney(array $p,string $customer): Money {$request=$this->s->billing->repository->request($this->text($p,'request_id',36),$customer,true);return new Money($this->text($p,'minor',20),new Currency($request['currency']));}
    private function paymentMoney(array $p,string $customer): Money {$payment=$this->s->finance->repository->payment($this->text($p,'payment_id',36));if($payment['customer_id']!==$customer){throw new HttpError(404,'PAYMENT_NOT_FOUND');}return new Money($this->text($p,'minor',20),new Currency($payment['currency']));}
    private function now(): \DateTimeImmutable {return new \DateTimeImmutable('now',new \DateTimeZone('UTC'));}
    private function date(string $value): \DateTimeImmutable
    {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|\+00:00)?$/D',$value)){throw new HttpError(422,'ABSOLUTE_UTC_DATE_REQUIRED');}$date=new \DateTimeImmutable($value,new \DateTimeZone('UTC'));$errors=\DateTimeImmutable::getLastErrors();if($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0)||$date->format('Y-m-d')!==substr($value,0,10)){throw new HttpError(422,'INVALID_FINANCIAL_DATE');}return $date;
    }
    private function cart(array $p): array {if(!is_array($p['cart']??null)){throw new HttpError(422,'CART_REQUIRED');}return $p['cart'];}
    private function codes(array $p): array { $codes=$p['promotions']??[];if(!is_array($codes)||!array_is_list($codes)){throw new HttpError(422,'INVALID_PROMOTIONS');}return $codes; }
    public function plan(string $action,array $p,?string $customer,Principal $actor): array
    {
        if(in_array($action,self::CATALOG,true)){return $this->catalogPlan($action,$p,$actor);}
        $fields=match($action){'reconciliation-resolve'=>['case_id','resolution','evidence'],'quote-create'=>['cart','promotions'],'order-submit'=>['quote_id'],'wallet-pay'=>['order_id','minor'],'order-cancel'=>['order_id'],'attempt-create'=>['minor','order_id'],'bank-notify'=>['attempt_id','bank_reference'],'bank-approve'=>['notice_id','version'],'customer-currency'=>['currency'],'order-refund'=>['request_id','minor'],'refund-request'=>['payment_id','minor'],'cash-category'=>['name','kind'],'expense-create'=>['category_id','minor','currency','reference','description'],'recurring-create'=>['category_id','minor','currency','kind','interval_count','first_due'],'recurring-due'=>['rule_id'],'recurring-pay'=>['due_id'],'fx-create'=>['source','target','rate','spread','observed_at','reference','trusted'],'document-start'=>['request_id'],'document-chunk'=>['upload_id','offset','base64'],'document-finish'=>['upload_id','number','date','type','issuer_tax_id','customer_tax_id','jurisdiction'],default=>throw new HttpError(422,'UNKNOWN_COMMERCE_COMMAND')};
        $required=$fields;if($action==='quote-create'){$required=['cart'];}if($action==='attempt-create'){$required=['minor'];}if($action==='document-finish'){$required=['upload_id','number','date','type'];}Validation::fields($p,$fields,$required);
        foreach($p as $key=>$value){if(in_array($key,['version','interval_count','offset'],true)){$this->integer($p,$key);}elseif(!in_array($key,['cart','promotions','trusted'],true)&&!is_string($value)){throw new HttpError(422,'STRING_VALUE_REQUIRED');}}
        $current=[];$proposed=$p;
        if($action==='reconciliation-resolve'){$proposed=(new \Hostider\Finance\Reconciliation($this->s->db,$this->s->config,$this->s->finance))->plan($this->text($p,'case_id',36),$customer,$this->text($p,'resolution',32),$this->text($p,'evidence',4000),$actor);}
        elseif($action==='quote-create'){$snapshot=$this->s->quotes->plan($customer,new Currency($this->customer($customer)['currency']),$this->cart($p),$this->codes($p),$this->now());$proposed=$snapshot->jsonSerialize();}
        elseif($action==='order-submit'){$proposed=$this->s->quotes->get($this->text($p,'quote_id',36),$customer,$this->now(),true);}
        elseif(in_array($action,['wallet-pay','order-cancel'],true)){
            $order=$this->s->orders->repository->find($this->text($p,'order_id',36),$customer,true);$bill=$this->s->billing->repository->request($order['payment_request_id'],$customer,true);$current=['order_status'=>$order['status'],'request_status'=>$bill['status'],'total_minor'=>(string)$bill['total_minor'],'paid_minor'=>$this->s->finance->repository->allocated($bill['id'],true),'currency'=>$bill['currency']];
            if($action==='wallet-pay'){$money=$this->money($p,$customer);$wallet=$this->s->finance->repository->wallet($customer);$current['balance_minor']=$this->s->finance->repository->balance($wallet['id'],true);if((new Decimal($money->minor))->compare(new Decimal('0'))<=0||(new Decimal($current['balance_minor']))->compare(new Decimal($money->minor))<0||(new Decimal($current['paid_minor']))->add(new Decimal($money->minor))->compare(new Decimal($current['total_minor']))>0){throw new HttpError(409,'INVALID_WALLET_PAYMENT_PLAN');}$proposed['currency']=$money->currency->code;}
        }
        elseif($action==='attempt-create'){$money=$this->money($p,$customer);if((new Decimal($money->minor))->compare(new Decimal('0'))<=0){throw new HttpError(422,'POSITIVE_AMOUNT_REQUIRED');}if(isset($p['order_id'])){$current=$this->s->orders->repository->find($this->text($p,'order_id',36),$customer,true);}$proposed['currency']=$money->currency->code;}
        elseif($action==='bank-notify'){$current=$this->s->payments->repository->attempt($this->text($p,'attempt_id',36),$customer);$this->text($p,'bank_reference');if($current['status']!=='PENDING'){throw new HttpError(409,'ATTEMPT_NOT_PENDING');}}
        elseif($action==='bank-approve'){$row=$this->s->payments->repository->notice($this->text($p,'notice_id',36),$customer);$current=['status'=>$row['status'],'minor'=>(string)$row['expected_minor'],'currency'=>$row['currency'],'version'=>(int)$row['version'],'bank_reference'=>$this->s->payments->bankReference($row['id'],$customer)];if($row['status']!=='PENDING'||(int)$row['version']!==$p['version']){throw new HttpError(409,'BANK_NOTICE_VERSION_CONFLICT');}}
        elseif($action==='customer-currency'){$proposed=(new CurrencyChanges($this->s))->plan($customer,$this->text($p,'currency',3),$actor);}
        elseif($action==='order-refund'){$proposed=$this->s->credits->plan($this->text($p,'request_id',36),$customer,$this->requestMoney($p,$customer));}
        elseif($action==='refund-request'){$payment=$this->s->finance->repository->payment($this->text($p,'payment_id',36));if($payment['customer_id']!==$customer){throw new HttpError(404,'PAYMENT_NOT_FOUND');}$money=$this->paymentMoney($p,$customer);$available=$this->s->finance->repository->available($payment);if((new Decimal($money->minor))->compare(new Decimal('0'))<=0||(new Decimal($available))->compare(new Decimal($money->minor))<0||$payment['currency']!==$payment['original_currency']){throw new HttpError(409,'REFUND_POLICY_OR_LIMIT');}$current=['available_minor'=>$available,'currency'=>$payment['currency']];$proposed['effect']='ORIGINAL_METHOD_REFUND_REQUEST_ONLY';}
        elseif($action==='fx-create'){$fx=$this->fx($p);if($this->s->config->environment!=='test'){throw new HttpError(403,'SYNTHETIC_RATE_FORBIDDEN');}if(!is_bool($p['trusted'])){throw new HttpError(422,'BOOLEAN_REQUIRED');}$proposed=$fx->jsonSerialize()+['trusted'=>$p['trusted'],'environment'=>'test'];}
        elseif($action==='document-chunk'){$row=$this->s->db->execute('SELECT * FROM billing_uploads WHERE id=? AND actor_id=? AND customer_id=? FOR UPDATE',[$p['upload_id'],$actor->actorId,$customer])->fetch();if(!$row){throw new HttpError(404,'UPLOAD_NOT_FOUND');}$bytes=base64_decode($p['base64'],true);if($bytes===false||strlen($bytes)<1||strlen($bytes)>32768||(int)$row['received_bytes']!==$p['offset']||(bool)$row['finalized']){throw new HttpError(422,'INVALID_DOCUMENT_CHUNK');}$proposed=['upload_id'=>$p['upload_id'],'offset'=>$p['offset'],'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)];}
        elseif($action==='document-finish'){$current=$this->s->documents()->preview($p['upload_id'],$customer,$actor);}
        elseif($action==='document-start'){$r=$this->s->billing->repository->request($this->text($p,'request_id',36),$customer,true);$current=['request_id'=>$r['id'],'currency'=>$r['currency'],'total_minor'=>(string)$r['total_minor']];}
        elseif(in_array($action,['expense-create','recurring-create'],true)){$money=new Money($this->text($p,'minor',20),new Currency($this->text($p,'currency',3)));if((new Decimal($money->minor))->compare(new Decimal('0'))<=0){throw new HttpError(422,'POSITIVE_AMOUNT_REQUIRED');}$row=$this->s->db->execute('SELECT * FROM finance_cash_categories WHERE id=? FOR UPDATE',[$this->text($p,'category_id',36)])->fetch();if(!$row){throw new HttpError(404,'CASH_CATEGORY_NOT_FOUND');}$current=['category'=>$row['name'],'kind'=>$row['kind']];if($action==='recurring-create'){$date=$this->date($p['first_due']);\Hostider\Catalog\BillingCycle::next($date,$p['kind'],$this->integer($p,'interval_count',1,3660));$proposed['first_due']=$date->format('Y-m-d H:i:s.u');}}
        if($customer!==null){$current['account']=$this->customer($customer);}
        if($action==='wallet-pay'){$proposed['resulting_balance_minor']=(string)(new Decimal($current['balance_minor']))->subtract(new Decimal($p['minor']));$proposed['remaining_after_minor']=(string)(new Decimal($current['total_minor']))->subtract(new Decimal($current['paid_minor']))->subtract(new Decimal($p['minor']));}
        return ['current'=>$current,'proposed'=>$proposed,'effect'=>'Yerel kayıtlar güncellenir. Canlı ödeme veya sağlayıcı kurulumu başlatılmaz.'];
    }
    private function fx(array $p): FxSnapshot
    {
        return new FxSnapshot(new Currency($this->text($p,'source',3)),new Currency($this->text($p,'target',3)),new Decimal($this->text($p,'rate',64)),new Decimal($this->text($p,'spread',64)),$this->text($p,'reference'),$this->date($this->text($p,'observed_at',40)),Rounding::HalfUp);
    }
    private function catalogPlan(string $action,array $p,Principal $actor): array
    {
        $schemas=[
            'tax-create'=>['name','rate','inclusive'],'category-create'=>['name','tax_id'],'cycle-create'=>['name','kind','interval_count'],
            'product-create'=>['name','category_id','product_type','pricing_strategy','source_currency','tax_id','activation_policy','refund_policy'],
            'variant-create'=>['name','product_id'],'group-create'=>['name','product_id','required'],'option-create'=>['name','group_id'],'addon-create'=>['name','standalone'],'addon-link'=>['product_id','addon_id'],'product-status'=>['id','version','sale_status'],'price-publish'=>['item_id','cycle_id','currency','amount'],'cost-propose'=>['item_id','cost_currency','cost_amount','proposed_sale_amount','proposed_sale_currency','source_reference'],'provider-map'=>['item_id','provider','plan_reference'],'promotion-create'=>['code','rate','period_scope','combinable','max_uses','per_customer_limit','starts_at','ends_at','product_id','category_id','customer_id','item_id']
        ];$schemas['addon-policy']=['addon_id','category_id','tax_id','pricing_strategy','source_currency','activation_policy','refund_policy','sale_status','version'];$schemas['category-update']=['id','name','tax_id','version'];$schemas['item-rename']=['id','name','version'];$schemas['product-update']=['id','name','category_id','pricing_strategy','source_currency','tax_id','activation_policy','refund_policy','version'];$fields=$schemas[$action];$optional=['tax_id','proposed_sale_amount','proposed_sale_currency'];if($action==='promotion-create'){$optional=['product_id','category_id','customer_id','item_id'];}Validation::fields($p,$fields,array_values(array_diff($fields,$optional)));
        foreach($p as $key=>$value){if($value===null){continue;}if(in_array($key,['version','interval_count','max_uses','per_customer_limit'],true)){$this->integer($p,$key);}elseif(in_array($key,['inclusive','required','standalone','combinable'],true)){if(!is_bool($value)){throw new HttpError(422,'BOOLEAN_REQUIRED');}}else{$this->text($p,$key);}}
        foreach(['rate','amount','cost_amount','proposed_sale_amount'] as $key){if(isset($p[$key])){if((new Decimal($p[$key]))->compare(new Decimal('0'))<0){throw new HttpError(422,'NONNEGATIVE_DECIMAL_REQUIRED');}}}
        if(isset($p['currency'])){new Currency($p['currency']);}foreach(['source_currency','cost_currency','proposed_sale_currency'] as $key){if(isset($p[$key])){new Currency($p[$key]);}}
        if(isset($p['rate'])&&in_array($action,['tax-create','promotion-create'],true)){if((new Decimal($p['rate']))->compare(new Decimal('1'))>0||$action==='promotion-create'&&(new Decimal($p['rate']))->compare(new Decimal('0'))<=0){throw new HttpError(422,'INVALID_PERCENTAGE');}}
        if(isset($p['proposed_sale_amount'])&&!isset($p['proposed_sale_currency'])){throw new HttpError(422,'PROPOSAL_CURRENCY_REQUIRED');}
        if(in_array('tax_id',$fields,true)){$p['tax_id']??=null;}
        $current=[];foreach(['category_id'=>'category','tax_id'=>'tax','product_id'=>'product','group_id'=>'group','item_id'=>'item','cycle_id'=>'cycle','addon_id'=>'addon'] as $key=>$kind){if(isset($p[$key])){$row=$this->s->catalog->repository->find($kind,$p[$key],true);$current[$key]=['id'=>$row['id'],'name'=>$row['name']??$row['id']];}}
        if(isset($p['customer_id'])){(new \Hostider\Core\Security\Authorization())->require($actor,'catalog.manage',$p['customer_id']);$current['promotion_account']=$this->s->identity->repository->customer($p['customer_id'],true);}
        if(isset($p['id'])){$current['record']=$this->s->catalog->repository->find($action==='category-update'?'category':'item',$p['id'],true);if(in_array($action,['product-status','product-update'],true)){$current['product_policy']=$this->s->catalog->repository->product($p['id'],true);}}
        return ['current'=>$current,'proposed'=>$p,'effect'=>$action==='cost-propose'?'Yalnız maliyet önerisi kaydedilir; satış fiyatı değişmez.':'Katalog kaydı yayımlanır; gerçek hizmet kurulumu başlatılmaz.'];
    }
    public function execute(string $action,array $p,?string $customer,Principal $actor,string $trace): array
    {
        if(in_array($action,self::CATALOG,true)){return $this->s->catalog->execute($action,$p,$actor,$trace);}$s=$this->s;
        return match($action){
            'reconciliation-resolve'=>(new \Hostider\Finance\Reconciliation($s->db,$s->config,$s->finance))->resolve($p['case_id'],$customer,$p['resolution'],$p['evidence'],$actor,$trace),
            'quote-create'=>$s->quotes->issue($s->quotes->plan($customer,new Currency($this->customer($customer)['currency']),$this->cart($p),$this->codes($p),$this->now())),
            'order-submit'=>$s->orders->submit($p['quote_id'],$customer,$this->customer($customer),$actor,$trace,$this->now()),
            'wallet-pay'=>$s->orders->fund($p['order_id'],$customer,$this->money($p,$customer),'WALLET',null,Id::uuid(),$actor,$trace),
            'order-cancel'=>$s->orders->cancel($p['order_id'],$customer,$actor,$trace),
            'attempt-create'=>$this->attempt($p,$customer,$actor,$trace),
            'bank-notify'=>$s->payments->notifyBank($p['attempt_id'],$customer,$p['bank_reference'],$actor,$trace),
            'bank-approve'=>$s->payments->approveBank($p['notice_id'],$customer,$p['version'],$actor,$trace),
            'refund-request'=>['id'=>$s->finance->requestOriginalRefund($p['payment_id'],$this->paymentMoney($p,$customer),Id::uuid(),$actor,$trace),'status'=>'REQUESTED_NOT_SENT'],
            'customer-currency'=>(new CurrencyChanges($s))->execute($customer,$p['currency'],$actor,$trace),
            'order-refund'=>$s->credits->issue($p['request_id'],$customer,$this->requestMoney($p,$customer),Id::uuid(),$actor,$trace),
            'fx-create'=>['id'=>$s->fx->publishSynthetic($this->fx($p),$p['trusted'])],
            'cash-category'=>['id'=>$s->cash->category($p['name'],$p['kind'],$actor)],
            'expense-create'=>['id'=>$s->cash->expense($p['category_id'],new Money($p['minor'],new Currency($p['currency'])),$p['reference'],$p['description'],$actor,$trace)],
            'recurring-create'=>['id'=>$s->cash->recurring($p['category_id'],new Money($p['minor'],new Currency($p['currency'])),$p['kind'],$p['interval_count'],$this->date($p['first_due']),$actor)],
            'recurring-due'=>['id'=>$s->cash->materialize($p['rule_id'],$this->now(),$actor)],'recurring-pay'=>['id'=>$s->cash->payDue($p['due_id'],$actor,$trace)],
            'document-start'=>['id'=>$s->documents()->start($p['request_id'],$customer,$actor)],'document-chunk'=>['received_bytes'=>$s->documents()->chunk($p['upload_id'],$p['offset'],base64_decode($p['base64'],true),$actor)],
            'document-finish'=>['id'=>$s->documents()->finish($p['upload_id'],$p['number'],$p['date'],$p['type'],$actor,$trace,array_intersect_key($p,array_flip(['issuer_tax_id','customer_tax_id','jurisdiction'])))],default=>throw new HttpError(422,'UNKNOWN_COMMERCE_COMMAND')};
    }
    private function attempt(array $p,string $customer,Principal $actor,string $trace): array
    {
        $currency=new Currency($this->customer($customer)['currency']);$this->s->finance->openWallet($customer,$currency,$actor);
        return $this->s->payments->createAttempt($customer,new Money($p['minor'],$currency),$p['order_id']??null,$actor,$trace);
    }
}
