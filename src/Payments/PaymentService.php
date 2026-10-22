<?php
declare(strict_types=1);
namespace Hostider\Payments;
use Hostider\Billing\BillingService;
use Hostider\Core\{CanonicalJson,Config,Id,Validation};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Audit,AuditEvent,Authorization,Principal};
use Hostider\Finance\{Currency,Decimal,FinanceService,Money};
use Hostider\Orders\OrderService;

final readonly class PaymentService
{
    public Repository $repository;
    public function __construct(private Database $db,private Config $config,private FinanceService $finance,private BillingService $billing,private OrderService $orders) { $this->repository=new Repository($db); }
    private function mutation(Principal $actor): void
    {
        $this->db->requireTransaction(); if($actor->impersonating){throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN');}
    }
    private function lockAttempt(string $id,string $customer): array
    {
        $candidate=$this->repository->attempt($id,$customer,false);
        if($candidate['order_id']!==null){$order=$this->orders->repository->find($candidate['order_id'],$customer,true);$this->billing->repository->request($order['payment_request_id'],$customer,true);}
        $this->finance->repository->wallet($customer);return $this->repository->attempt($id,$customer);
    }
    public function createAttempt(string $customer,Money $amount,?string $order,Principal $actor,string $trace): array
    {
        $this->mutation($actor);
        if((new Decimal($amount->minor))->compare(new Decimal('0'))<=0){throw new HttpError(422,'POSITIVE_AMOUNT_REQUIRED');}
        if($order!==null) {
            $o=$this->orders->repository->find($order,$customer,true);$request=$this->billing->repository->request($o['payment_request_id'],$customer,true);
            $remaining=(new Decimal((string)$request['total_minor']))->subtract(new Decimal($this->finance->repository->allocated($request['id'],true)));
            if(!in_array($o['status'],['AWAITING_PAYMENT','PARTIALLY_PAID'],true)||$remaining->compare(new Decimal($amount->minor))<0){throw new HttpError(409,'INVALID_PAYMENT_ATTEMPT_AMOUNT');}
        }
        $wallet=$this->finance->repository->wallet($customer);
        if($wallet['currency']!==$amount->currency->code){throw new HttpError(422,'ACCOUNT_PAYMENT_CURRENCY_REQUIRED');}
        $id=Id::uuid();$this->db->execute('INSERT INTO payment_attempts(id,customer_id,order_id,purpose,expected_minor,currency) VALUES (?,?,?,?,?,?)',[$id,$customer,$order,$order===null?'TOPUP':'ORDER',$amount->minor,$amount->currency->code]);
        (new Audit($this->db))->record($actor,AuditEvent::PaymentChanged,$id,$trace);return ['id'=>$id,'status'=>'PENDING'];
    }
    public function notifyBank(string $attempt,string $customer,string $bankReference,Principal $actor,string $trace): array
    {
        $this->mutation($actor);$row=$this->lockAttempt($attempt,$customer);
        if($row['status']!=='PENDING'){throw new HttpError(409,'ATTEMPT_NOT_PENDING');}
        Validation::text($bankReference,128);$hash=hash_hmac('sha256',$bankReference,base64_decode($this->config->key,true));
        $old=$this->db->execute('SELECT * FROM payment_bank_notices WHERE attempt_id=? FOR UPDATE',[$attempt])->fetch();
        if($old){if(!hash_equals($old['reference_hash'],$hash)){throw new HttpError(409,'BANK_NOTICE_REFERENCE_CONFLICT');}return ['id'=>$old['id'],'status'=>$old['status']];}
        $id=Id::uuid();$this->db->execute('INSERT INTO payment_bank_notices(id,attempt_id,reference_hash) VALUES (?,?,?)',[$id,$attempt,$hash]);
        (new Audit($this->db))->record($actor,AuditEvent::PaymentChanged,$id,$trace);return ['id'=>$id,'status'=>'PENDING'];
    }
    public function approveBank(string $notice,string $customer,int $version,Principal $actor,string $trace): array
    {
        $this->mutation($actor);(new Authorization())->require($actor,'finance.approve',$customer,privileged:true);
        if(!$actor->staff){throw new HttpError(403,'STAFF_BANK_APPROVAL_REQUIRED');}
        $candidate=$this->repository->notice($notice,$customer,false);$attempt=$this->lockAttempt($candidate['attempt_id'],$customer);$row=$this->repository->notice($notice,$customer);
        if($row['status']==='APPROVED'){return ['id'=>$notice,'status'=>'APPROVED','payment_id'=>$row['payment_id']];}
        if($row['status']!=='PENDING'||(int)$row['version']!==$version){throw new HttpError(409,'BANK_NOTICE_VERSION_CONFLICT');}
        if($attempt['status']!=='PENDING'){throw new HttpError(409,'ATTEMPT_NOT_PENDING');}
        $money=new Money((string)$row['expected_minor'],new Currency($row['currency']));$payment=$this->finance->recordBankCollection($customer,$money,$notice,$actor,$trace);
        $result=$this->settle($attempt,$payment,$money,$actor,$trace);
        $this->db->execute("UPDATE payment_bank_notices SET status='APPROVED',approved_by=?,payment_id=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?",[$actor->actorId,$payment,$notice]);
        (new Audit($this->db))->record($actor,AuditEvent::PaymentChanged,$notice,$trace);return ['id'=>$notice,'status'=>'APPROVED','payment_id'=>$payment]+$result;
    }
    private function settle(array $attempt,string $payment,Money $amount,Principal $actor,string $trace): array
    {
        if($attempt['purpose']==='TOPUP') {$this->finance->creditWallet($attempt['customer_id'],$payment,$amount,'attempt:'.$attempt['id'],$actor,$trace);$result=['allocation'=>'WALLET'];}
        else {
            $order=$this->orders->repository->find($attempt['order_id'],$attempt['customer_id'],true);$request=$this->billing->repository->request($order['payment_request_id'],$attempt['customer_id'],true);
            $remaining=(new Decimal((string)$request['total_minor']))->subtract(new Decimal($this->finance->repository->allocated($request['id'],true)));
            if(in_array($order['status'],['AWAITING_PAYMENT','PARTIALLY_PAID'],true)&&$remaining->compare(new Decimal($amount->minor))>=0){$result=$this->orders->fund($order['id'],$attempt['customer_id'],$amount,'COLLECTION',$payment,'attempt:'.$attempt['id'],$actor,$trace);}
            else {$result=['allocation'=>'UNALLOCATED_RECONCILIATION_REQUIRED'];$this->finance->reconcile($payment,'LATE_OR_EXCESS_PAYMENT',$actor,$trace);}
        }
        $this->db->execute("UPDATE payment_attempts SET status='SUCCEEDED',payment_id=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?",[$payment,$attempt['id']]);return $result;
    }
    /** Synthetic test protocol, deliberately unrelated to PayTR merchant fields or signatures. */
    public function acceptTestFixture(array $fixture,Principal $actor,string $trace): array
    {
        $this->mutation($actor);if($this->config->environment!=='test'){throw new HttpError(403,'TEST_FIXTURE_FORBIDDEN');}
        Validation::fields($fixture,['attempt_id','customer_id','external_reference','minor','currency','status','signature'],['attempt_id','customer_id','external_reference','minor','currency','status','signature']);
        foreach($fixture as $value){if(!is_string($value)){throw new HttpError(422,'INVALID_TEST_FIXTURE');}}
        $signature=$fixture['signature'];unset($fixture['signature']);$expected=hash_hmac('sha256','hostider-test-fixture-v1:'.CanonicalJson::encode($fixture),base64_decode($this->config->key,true));
        if(!hash_equals($expected,$signature)){throw new HttpError(403,'INVALID_TEST_FIXTURE_SIGNATURE');}
        $attempt=$this->lockAttempt($fixture['attempt_id'],$fixture['customer_id']);$fingerprint=hash('sha256',CanonicalJson::encode($fixture));
        $old=$this->db->execute('SELECT * FROM payment_fixture_callbacks WHERE external_reference=? FOR UPDATE',[$fixture['external_reference']])->fetch();
        if($old){if(!hash_equals($old['fingerprint'],$fingerprint)){throw new HttpError(409,'FIXTURE_REFERENCE_CONFLICT');}return json_decode($old['result'],true,32,JSON_THROW_ON_ERROR);}
        if($attempt['status']!=='PENDING'){throw new HttpError(409,'ATTEMPT_NOT_PENDING');}
        if($fixture['minor']!==(string)$attempt['expected_minor']||$fixture['currency']!==$attempt['currency']){throw new HttpError(409,'FIXTURE_AMOUNT_CURRENCY_MISMATCH');}
        if($fixture['status']==='FAILURE'){$this->db->execute("UPDATE payment_attempts SET status='FAILED',version=version+1 WHERE id=?",[$attempt['id']]);$result=['status'=>'FAILED'];}
        elseif($fixture['status']==='SUCCESS'){$money=new Money($fixture['minor'],new Currency($fixture['currency']));$payment=$this->finance->recordTestCollection($attempt['customer_id'],$money,$fixture['external_reference'],$actor,$trace);$result=['status'=>'SUCCEEDED','payment_id'=>$payment]+$this->settle($attempt,$payment,$money,$actor,$trace);}
        else{throw new HttpError(422,'INVALID_TEST_FIXTURE_STATUS');}
        $this->db->execute('INSERT INTO payment_fixture_callbacks(external_reference,attempt_id,fingerprint,result) VALUES (?,?,?,?)',[$fixture['external_reference'],$attempt['id'],$fingerprint,json_encode($result,JSON_THROW_ON_ERROR)]);return $result;
    }
}
