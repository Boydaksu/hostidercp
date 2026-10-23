<?php
declare(strict_types=1);
namespace Hostider\Commerce;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Authorization,Principal};
use Hostider\Finance\{Currency,Decimal,Money};
use Hostider\Pricing\ExchangeRatePolicy;
/** Coordinates Identity and Finance application services in one local transaction. */
final readonly class CurrencyChanges
{
    public function __construct(private Services $s) {}
    public function plan(string $customer,string $target,Principal $actor): array
    {
        $this->s->db->requireTransaction();(new Authorization())->require($actor,'finance.approve',$customer,privileged:true,administrative:true);
        $users=$this->s->identity->lockCustomerUsers($customer);$account=$this->s->identity->repository->customer($customer,true);
        if(!in_array($target,['TRY','USD','EUR'],true)||$account['currency']===$target){throw new HttpError(422,'NEW_SUPPORTED_CURRENCY_REQUIRED');}
        if($this->s->db->execute("SELECT 1 FROM billing_requests r JOIN billing_request_states st ON st.request_id=r.id WHERE r.customer_id=? AND st.status IN ('ISSUED','PARTIALLY_PAID') LIMIT 1 FOR UPDATE",[$customer])->fetchColumn()||$this->s->db->execute("SELECT 1 FROM payment_attempts WHERE customer_id=? AND status IN ('PENDING','REVIEW') LIMIT 1 FOR UPDATE",[$customer])->fetchColumn()){throw new HttpError(409,'OPEN_FINANCIAL_ITEMS_REQUIRE_RECONCILIATION');}
        foreach($this->s->db->execute('SELECT id FROM finance_payments WHERE customer_id=? ORDER BY id FOR UPDATE',[$customer])->fetchAll() as $row){$payment=$this->s->finance->repository->payment($row['id']);if((new Decimal($this->s->finance->repository->available($payment)))->compare(new Decimal('0'))!==0){throw new HttpError(409,'UNALLOCATED_FUNDS_REQUIRE_RECONCILIATION');}}
        if($this->s->db->execute('SELECT 1 FROM finance_refunds f JOIN finance_payments p ON p.id=f.payment_id WHERE p.customer_id=? LIMIT 1 FOR UPDATE',[$customer])->fetchColumn()){throw new HttpError(409,'REFUND_RECONCILIATION_REQUIRED');}
        try{$wallet=$this->s->finance->repository->wallet($customer);if($wallet['currency']!==$account['currency']){throw new HttpError(409,'WALLET_ACCOUNT_CURRENCY_MISMATCH');}$balance=$this->s->finance->repository->balance($wallet['id'],true);}catch(HttpError $error){if($error->status!==404){throw $error;}$balance='0';}
        $source=new Currency($account['currency']);$rate=$this->s->fx->resolve($source,new Currency($target),new ExchangeRatePolicy(3600),new \DateTimeImmutable('now',new \DateTimeZone('UTC')));$original=new Money($balance,$source);$converted=$rate['snapshot']->convert($original);
        if($balance!=='0'&&$converted->minor==='0'){throw new HttpError(409,'POSITIVE_BALANCE_CONVERSION_ROUNDS_TO_ZERO');}
        return ['account'=>$account,'original'=>$original->jsonSerialize(),'converted'=>$converted->jsonSerialize(),'fx'=>$rate['snapshot']->jsonSerialize(),'affected_users'=>$users,'effect'=>'Hesap ve tek cüzdan birlikte dönüştürülür. Eski belgeler değişmez. Hesaba bağlı oturumlar sonlandırılır; bildirim gönderimi bekletilir.'];
    }
    public function execute(string $customer,string $target,Principal $actor,string $trace): array
    {
        $plan=$this->plan($customer,$target,$actor);$account=$plan['account'];$source=new Currency($account['currency']);$rate=$this->s->fx->resolve($source,new Currency($target),new ExchangeRatePolicy(3600),new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
        $this->s->finance->openWallet($customer,$source,$actor);$result=$this->s->finance->convertWallet($customer,$rate['snapshot'],$actor,$trace);$this->s->identity->changeBillingCurrency($customer,(int)$account['version'],$target,$actor);return $result+['customer_id'=>$customer,'new_currency'=>$target];
    }
}
