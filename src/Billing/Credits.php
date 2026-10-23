<?php
declare(strict_types=1);
namespace Hostider\Billing;
use Hostider\Core\Id;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Authorization,Principal};
use Hostider\Finance\{Currency,Decimal,FinanceService,FxSnapshot,Ledger,Money,Rounding,Unsigned};
use Hostider\Pricing\{ExchangeRatePolicy,FxRateRepository};
use Hostider\Orders\OrderService;
final readonly class Credits
{
    public function __construct(private Database $db,private BillingService $billing,private FinanceService $finance,private OrderService $orders,private ?FxRateRepository $fx=null) {}
    public function plan(string $request,string $customer,Money $amount): array
    {
        $this->db->requireTransaction();$candidate=$this->billing->repository->request($request,$customer);$this->orders->repository->find($candidate['order_id'],$customer,true);$row=$this->billing->repository->request($request,$customer,true);
        if($row['status']!=='PAID'||$row['currency']!==$amount->currency->code||(new Decimal($amount->minor))->compare(new Decimal('0'))<=0){throw new HttpError(409,'PAID_REFUND_REQUEST_REQUIRED');}
        $snapshot=json_decode($row['snapshot'],true,64,JSON_THROW_ON_ERROR);foreach($snapshot['lines'] as $line){if($line['refund_policy']!=='staff_approval'){throw new HttpError(409,$line['refund_policy']==='none'?'PRODUCT_REFUND_FORBIDDEN':'REFUND_REQUIRES_SERVICE_POLICY_REVIEW');}}
        $sums=$this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) gross,CAST(COALESCE(SUM(tax_minor),0) AS CHAR) tax FROM billing_credit_notes WHERE request_id=? FOR UPDATE',[$request])->fetch();$remaining=(new Decimal((string)$row['total_minor']))->subtract(new Decimal($sums['gross']));$remainingTax=(new Decimal((string)$row['tax_minor']))->subtract(new Decimal($sums['tax']));
        if($remaining->compare(new Decimal($amount->minor))<0){throw new HttpError(409,'REFUND_EXCEEDS_PAID_BALANCE');}
        $tax=(new Decimal($amount->minor))->multiply($remainingTax)->divide($remaining,0,Rounding::HalfUp);$net=(new Decimal($amount->minor))->subtract($tax);$allocations=$this->finance->refundableAllocations($request,$customer);$available='0';foreach($allocations as $a){$available=Unsigned::add($available,$a['available_minor']);}
        if(Unsigned::compare($available,$amount->minor)<0){throw new HttpError(409,'REFUND_SOURCE_LIMIT');}$distributed='0';$parts=[];
        foreach($allocations as $a){[$part,$remainder]=Unsigned::divide(Unsigned::multiply($amount->minor,$a['available_minor']),$available);$parts[]=$a+['refund_minor'=>$part,'remainder'=>$remainder];$distributed=Unsigned::add($distributed,$part);}
        usort($parts,fn(array $a,array $b): int=>Unsigned::compare($b['remainder'],$a['remainder'])?:strcmp($a['allocation_id'],$b['allocation_id']));$left=Unsigned::subtract($amount->minor,$distributed);
        foreach($parts as &$part){if($left!=='0'&&Unsigned::compare($part['refund_minor'],$part['available_minor'])<0){$part['refund_minor']=Unsigned::add($part['refund_minor'],'1');$left=Unsigned::subtract($left,'1');}unset($part['remainder']);}unset($part);
        if($left!=='0'){throw new \LogicException('Refund distribution invariant');}
        $wallet=$this->finance->repository->wallet($customer);foreach($parts as &$part){if($part['wallet_entry_id']!==null&&$part['refund_minor']!=='0'&&$part['currency']!==$wallet['currency']){$rate=$this->fx?->resolve(new Currency($part['currency']),new Currency($wallet['currency']),new ExchangeRatePolicy(3600),new \DateTimeImmutable('now',new \DateTimeZone('UTC')))??throw new HttpError(409,'WALLET_REFUND_FX_POLICY_REQUIRED');$part['wallet_fx']=$rate['snapshot']->jsonSerialize();$part['wallet_credit']=$rate['snapshot']->convert(new Money($part['refund_minor'],new Currency($part['currency'])))->jsonSerialize();if($part['wallet_credit']['minor']==='0'){throw new HttpError(409,'POSITIVE_BALANCE_CONVERSION_ROUNDS_TO_ZERO');}}}unset($part);
        return ['request_id'=>$request,'order_id'=>$row['order_id'],'currency'=>$row['currency'],'amount_minor'=>$amount->minor,'net_minor'=>(string)$net,'tax_minor'=>(string)$tax,'full'=>$remaining->compare(new Decimal($amount->minor))===0,'sources'=>$parts,'rounding'=>'LARGEST_REMAINDER_SOURCE_DISTRIBUTION','external_refund_status'=>'REQUESTED_NOT_SENT'];
    }
    public function issue(string $request,string $customer,Money $amount,string $reference,Principal $actor,string $trace): array
    {
        $this->db->requireTransaction();if($actor->impersonating||!$actor->staff){throw new HttpError(403,'STAFF_REFUND_APPROVAL_REQUIRED');}(new Authorization())->require($actor,'finance.approve',$customer,privileged:true);
        $candidate=$this->billing->repository->request($request,$customer);$this->orders->repository->find($candidate['order_id'],$customer,true);$this->billing->repository->request($request,$customer,true);
        $old=$this->db->execute('SELECT * FROM billing_credit_notes WHERE reference=? FOR UPDATE',[$reference])->fetch();if($old){if($old['request_id']!==$request||(string)$old['amount_minor']!==$amount->minor){throw new HttpError(409,'CREDIT_NOTE_REFERENCE_CONFLICT');}return ['id'=>$old['id'],'status'=>'RECORDED'];}
        $plan=$this->plan($request,$customer,$amount);$id=Id::uuid();$lines=[['account'=>'receivable','side'=>'CREDIT','minor'=>$amount->minor]];foreach(['net_minor'=>'revenue','tax_minor'=>'tax_payable'] as $key=>$account){if($plan[$key]!=='0'){$lines[]=['account'=>$account,'side'=>'DEBIT','minor'=>$plan[$key]];}}
        $journal=(new Ledger($this->db))->post('credit-note:'.$reference,$amount->currency,$lines,$actor,$trace);
        foreach($plan['sources'] as $source){if($source['refund_minor']==='0'){continue;}$money=new Money($source['refund_minor'],$amount->currency);$sourceReference=$id.':'.$source['allocation_id'];if($source['wallet_entry_id']!==null){$data=$source['wallet_fx']??null;$fx=$data===null?null:new FxSnapshot(new Currency($data['source']),new Currency($data['target']),new Decimal($data['rate']),new Decimal($data['spread']),$data['reference'],new \DateTimeImmutable($data['observed_at'],new \DateTimeZone('UTC')),Rounding::from($data['rounding']));$this->finance->creditWalletSpend($customer,$source['wallet_entry_id'],$money,$sourceReference,$actor,$trace,$fx);}else{$this->finance->reversePaymentAllocation($source['allocation_id'],$customer,$money,$sourceReference,$actor,$trace);$this->finance->requestOriginalRefund($source['payment_id'],$money,$sourceReference,$actor,$trace);}}
        $this->db->execute('INSERT INTO billing_credit_notes(id,request_id,reference,amount_minor,net_minor,tax_minor,currency,source_plan,journal_id,actor_id) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$request,$reference,$amount->minor,$plan['net_minor'],$plan['tax_minor'],$amount->currency->code,json_encode($plan,JSON_THROW_ON_ERROR),$journal,$actor->actorId]);
        $this->db->execute('UPDATE billing_request_states SET refund_status=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE request_id=?',[$plan['full']?'FULL':'PARTIAL',$request]);$this->orders->markRefunded($plan['order_id'],$customer,$plan['full'],$actor,$trace);return ['id'=>$id,'status'=>'RECORDED','plan'=>$plan];
    }
}
