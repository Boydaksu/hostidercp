<?php
declare(strict_types=1);
namespace Hostider\Finance;
use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;

/** Trusted application boundary. Every mutation joins a caller-owned local transaction. */
final readonly class FinanceService
{
    public Repository $repository;
    private Ledger $ledger;
    public function __construct(private Database $db,private Config $config)
    {
        $this->repository=new Repository($db); $this->ledger=new Ledger($db);
    }
    private function mutation(Principal $actor): void
    {
        $this->db->requireTransaction();
        if ($actor->impersonating) { throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN'); }
    }
    private function positive(Money $amount): void
    {
        if ((new Decimal($amount->minor))->compare(new Decimal('0'))<=0) { throw new HttpError(422,'POSITIVE_AMOUNT_REQUIRED'); }
    }
    public function reconcile(string $subject,string $code,Principal $actor,string $trace): string
    {
        $this->mutation($actor);
        if(!in_array($code,['LATE_OR_EXCESS_PAYMENT','FX_REFUND_POLICY_REQUIRED','PAYMENT_MISMATCH'],true)){throw new HttpError(422,'INVALID_RECONCILIATION_CODE');}
        $id=Id::uuid();$this->db->execute('INSERT INTO finance_reconciliations(id,subject_id,code,actor_id,trace_id) VALUES (?,?,?,?,?)',[$id,$subject,$code,$actor->actorId,$trace]);
        (new \Hostider\Core\Security\Audit($this->db))->record($actor,\Hostider\Core\Security\AuditEvent::FinancialReconciled,$id,$trace);return $id;
    }
    public function recordBankCollection(string $customer,Money $amount,string $approvalReference,Principal $actor,string $trace): string
    {
        $this->mutation($actor);
        if($this->config->environment==='production'){throw new HttpError(403,'PRODUCTION_FINANCE_RELEASE_GATE_OPEN');}
        if(!$actor->staff){throw new HttpError(403,'STAFF_BANK_APPROVAL_REQUIRED');}
        (new \Hostider\Core\Security\Authorization())->require($actor,'finance.approve',$customer,privileged:true);
        return $this->collection($customer,$amount,'BANK',$approvalReference,$actor,$trace);
    }
    public function openWallet(string $customer,Currency $currency,Principal $actor): string
    {
        $this->mutation($actor);
        // The caller gets customer/currency from Identity, never from untrusted request ownership fields.
        $existing=$this->db->execute('SELECT * FROM finance_wallets WHERE customer_id=? FOR UPDATE',[$customer])->fetch();
        if ($existing) {
            if ($existing['currency']!==$currency->code) { throw new HttpError(409,'WALLET_CURRENCY_CONFLICT'); }
            return $existing['id'];
        }
        $id=Id::uuid(); $this->db->execute('INSERT INTO finance_wallets(id,customer_id,currency) VALUES (?,?,?)',[$id,$customer,$currency->code]); return $id;
    }
    public function recordTestCollection(string $customer,Money $amount,string $external,Principal $actor,string $trace,?Money $original=null,?FxSnapshot $fx=null): string
    {
        $this->mutation($actor);
        if ($this->config->environment!=='test') { throw new HttpError(403,'TEST_COLLECTION_FORBIDDEN'); }
        return $this->collection($customer,$amount,'TEST',$external,$actor,$trace,$original,$fx);
    }
    private function collection(string $customer,Money $amount,string $method,string $external,Principal $actor,string $trace,?Money $original=null,?FxSnapshot $fx=null): string
    {
        $this->positive($amount);
        $original??=$amount; $this->positive($original);
        if ($original->currency->code!==$amount->currency->code) {
            if ($fx===null || $fx->convert($original)->jsonSerialize()!==$amount->jsonSerialize()) { throw new HttpError(422,'FX_CONVERSION_MISMATCH'); }
        } elseif ($fx!==null || $original->minor!==$amount->minor) { throw new HttpError(422,'FX_CONVERSION_MISMATCH'); }
        $wallet=$this->repository->wallet($customer);
        if ($wallet['currency']!==$amount->currency->code) { throw new HttpError(409,'COLLECTION_CURRENCY_CONFLICT'); }
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$external)) { throw new HttpError(422,'INVALID_EXTERNAL_REFERENCE'); }
        $fingerprint=hash('sha256',CanonicalJson::encode(['customer'=>$customer,'money'=>$amount->jsonSerialize(),'method'=>$method,'external'=>$external,'original'=>$original->jsonSerialize(),'fx'=>$fx?->jsonSerialize()]));
        $old=$this->db->execute('SELECT * FROM finance_payments WHERE method=? AND external_reference=? FOR UPDATE',[$method,$external])->fetch();
        if ($old) {
            if (!hash_equals($old['fingerprint'],$fingerprint)) { throw new HttpError(409,'COLLECTION_REFERENCE_CONFLICT'); }
            return $old['id'];
        }
        $journal=$this->transfer('collection:'.$method.':'.$external,$amount,'cash','unallocated',$actor,$trace);
        $id=Id::uuid(); $fxId=null;
        if ($fx!==null) {
            $fxId=Id::uuid(); $data=$fx->jsonSerialize();
            $this->db->execute('INSERT INTO finance_fx_snapshots(id,source_currency,target_currency,rate,spread,source_reference,observed_at,rounding) VALUES (?,?,?,?,?,?,?,?)',[$fxId,$data['source'],$data['target'],$data['rate'],$data['spread'],$data['reference'],$data['observed_at'],$data['rounding']]);
        }
        $this->db->execute('INSERT INTO finance_payments(id,customer_id,method,external_reference,amount_minor,currency,original_minor,original_currency,fingerprint,journal_id,fx_snapshot_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$id,$customer,$method,$external,$amount->minor,$amount->currency->code,$original->minor,$original->currency->code,$fingerprint,$journal,$fxId]);
        return $id;
    }
    private function transfer(string $reference,Money $amount,string $debit,string $credit,Principal $actor,string $trace): string
    {
        return $this->ledger->post($reference,$amount->currency,[['account'=>$debit,'side'=>'DEBIT','minor'=>$amount->minor],['account'=>$credit,'side'=>'CREDIT','minor'=>$amount->minor]],$actor,$trace);
    }
    private function funds(array $payment,Money $amount,string $customer): void
    {
        $this->positive($amount);
        if ($payment['customer_id']!==$customer || $payment['currency']!==$amount->currency->code) { throw new HttpError(403,'PAYMENT_SCOPE_CURRENCY'); }
        if ((new Decimal($this->repository->available($payment)))->compare(new Decimal($amount->minor))<0) { throw new HttpError(409,'INSUFFICIENT_UNALLOCATED_FUNDS'); }
    }
    public function creditWallet(string $customer,string $paymentId,Money $amount,string $reference,Principal $actor,string $trace): string
    {
        $this->mutation($actor); $wallet=$this->repository->wallet($customer);
        if ($wallet['currency']!==$amount->currency->code) { throw new HttpError(409,'WALLET_CURRENCY_CONFLICT'); }
        $old=$this->db->execute('SELECT * FROM finance_wallet_entries WHERE reference=? FOR UPDATE',[$reference])->fetch();
        if ($old) {
            if ($old['wallet_id']!==$wallet['id'] || $old['payment_id']!==$paymentId || (string)$old['amount_minor']!==$amount->minor) { throw new HttpError(409,'WALLET_REFERENCE_CONFLICT'); }
            return $old['id'];
        }
        $payment=$this->repository->payment($paymentId); $this->funds($payment,$amount,$customer);
        // A wallet cannot overflow the storage domain even though its balance is a derived SUM.
        (new Money($this->repository->balance($wallet['id'],true),$amount->currency))->add($amount);
        $journal=$this->transfer('wallet-credit:'.$reference,$amount,'unallocated','wallet_liability',$actor,$trace); $id=Id::uuid();
        $this->db->execute('INSERT INTO finance_wallet_entries(id,wallet_id,amount_minor,reference,journal_id,payment_id) VALUES (?,?,?,?,?,?)',[$id,$wallet['id'],$amount->minor,$reference,$journal,$paymentId]); return $id;
    }
    public function spendWallet(string $customer,string $request,Money $amount,string $reference,Principal $actor,string $trace): string
    {
        $this->mutation($actor); $this->positive($amount); $wallet=$this->repository->wallet($customer);
        if ($wallet['currency']!==$amount->currency->code) { throw new HttpError(409,'WALLET_CURRENCY_CONFLICT'); }
        $old=$this->allocationReplay($reference,$customer,$request,$amount,null);
        if ($old!==null) { return $old; }
        if ((new Decimal($this->repository->balance($wallet['id'],true)))->compare(new Decimal($amount->minor))<0) { throw new HttpError(409,'INSUFFICIENT_WALLET_FUNDS'); }
        $journal=$this->transfer('wallet-spend:'.$reference,$amount,'wallet_liability','receivable',$actor,$trace); $entry=Id::uuid();
        $this->db->execute('INSERT INTO finance_wallet_entries(id,wallet_id,amount_minor,reference,journal_id) VALUES (?,?,?,?,?)',[$entry,$wallet['id'],'-'.$amount->minor,$reference,$journal]);
        return $this->allocation($customer,$request,$amount,$reference,$journal,null,$entry);
    }
    public function allocatePayment(string $customer,string $request,string $paymentId,Money $amount,string $reference,Principal $actor,string $trace): string
    {
        $this->mutation($actor); $payment=$this->repository->payment($paymentId);
        $old=$this->allocationReplay($reference,$customer,$request,$amount,$paymentId);
        if ($old!==null) { return $old; }
        $this->funds($payment,$amount,$customer);
        $journal=$this->transfer('allocate:'.$reference,$amount,'unallocated','receivable',$actor,$trace);
        return $this->allocation($customer,$request,$amount,$reference,$journal,$paymentId,null);
    }
    private function allocationReplay(string $reference,string $customer,string $request,Money $amount,?string $payment): ?string
    {
        $old=$this->db->execute('SELECT * FROM finance_allocations WHERE reference=? FOR UPDATE',[$reference])->fetch();
        if (!$old) { return null; }
        if ($old['customer_id']!==$customer || $old['payment_request_id']!==$request || (string)$old['amount_minor']!==$amount->minor || $old['currency']!==$amount->currency->code || $old['payment_id']!==$payment) { throw new HttpError(409,'ALLOCATION_REFERENCE_CONFLICT'); }
        return $old['id'];
    }
    private function allocation(string $customer,string $request,Money $amount,string $reference,string $journal,?string $payment,?string $walletEntry): string
    {
        $id=Id::uuid();
        $this->db->execute('INSERT INTO finance_allocations(id,customer_id,payment_request_id,payment_id,wallet_entry_id,amount_minor,currency,reference,journal_id) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$customer,$request,$payment,$walletEntry,$amount->minor,$amount->currency->code,$reference,$journal]); return $id;
    }
    /** Caller must first approve the applicable product refund policy and billing correction. */
    public function creditWalletSpend(string $customer,string $originalEntry,Money $amount,string $reference,Principal $actor,string $trace): string
    {
        $this->mutation($actor); $this->positive($amount); $wallet=$this->repository->wallet($customer);
        if ($wallet['currency']!==$amount->currency->code) { throw new HttpError(409,'WALLET_CURRENCY_CONFLICT'); }
        $entry=$this->db->execute('SELECT * FROM finance_wallet_entries WHERE id=? FOR UPDATE',[$originalEntry])->fetch();
        if (!$entry || $entry['wallet_id']!==$wallet['id'] || (new Decimal((string)$entry['amount_minor']))->compare(new Decimal('0'))>=0) { throw new HttpError(404,'REFUNDABLE_WALLET_SPEND_NOT_FOUND'); }
        $old=$this->db->execute('SELECT * FROM finance_credits WHERE reference=? FOR UPDATE',[$reference])->fetch();
        if ($old) {
            if ($old['original_entry_id']!==$originalEntry || (string)$old['amount_minor']!==$amount->minor) { throw new HttpError(409,'CREDIT_REFERENCE_CONFLICT'); }
            return $old['id'];
        }
        $credited=$this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_credits WHERE original_entry_id=? FOR UPDATE',[$originalEntry])->fetchColumn();
        $remaining=(new Decimal((string)$entry['amount_minor']))->negate()->subtract(new Decimal($credited));
        if ($remaining->compare(new Decimal($amount->minor))<0) { throw new HttpError(409,'CREDIT_EXCEEDS_ORIGINAL_SPEND'); }
        (new Money($this->repository->balance($wallet['id'],true),$amount->currency))->add($amount);
        $journal=$this->ledger->post('wallet-refund:'.$reference,$amount->currency,[['account'=>'receivable','side'=>'DEBIT','minor'=>$amount->minor],['account'=>'wallet_liability','side'=>'CREDIT','minor'=>$amount->minor]],$actor,$trace,$entry['journal_id']);
        $credit=Id::uuid(); $id=Id::uuid();
        $this->db->execute('INSERT INTO finance_wallet_entries(id,wallet_id,amount_minor,reference,journal_id) VALUES (?,?,?,?,?)',[$credit,$wallet['id'],$amount->minor,$reference,$journal]);
        $this->db->execute('INSERT INTO finance_credits(id,original_entry_id,credit_entry_id,amount_minor,reference) VALUES (?,?,?,?,?)',[$id,$originalEntry,$credit,$amount->minor,$reference]); return $id;
    }
    /** Reserves only unallocated funds. External completion must be reconciled separately; no gateway call. */
    public function requestOriginalRefund(string $paymentId,Money $amount,string $reference,Principal $actor,string $trace): string
    {
        $this->mutation($actor); $payment=$this->repository->payment($paymentId);
        if ($payment['currency']!==$payment['original_currency']) { throw new HttpError(409,'FX_REFUND_REQUIRES_RECONCILIATION'); }
        $old=$this->db->execute('SELECT * FROM finance_refunds WHERE reference=? FOR UPDATE',[$reference])->fetch();
        if ($old) {
            if ($old['payment_id']!==$paymentId || (string)$old['amount_minor']!==$amount->minor || $old['currency']!==$amount->currency->code) { throw new HttpError(409,'REFUND_REFERENCE_CONFLICT'); }
            return $old['id'];
        }
        $this->funds($payment,$amount,$payment['customer_id']);
        $journal=$this->transfer('refund-request:'.$reference,$amount,'unallocated','refund_payable',$actor,$trace); $id=Id::uuid();
        $this->db->execute("INSERT INTO finance_refunds(id,payment_id,amount_minor,currency,destination,reference,journal_id) VALUES (?,?,?,?,'ORIGINAL',?,?)",[$id,$paymentId,$amount->minor,$amount->currency->code,$reference,$journal]); return $id;
    }
}
