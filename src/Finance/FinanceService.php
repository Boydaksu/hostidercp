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
        $originalJournal=$fx===null?null:$this->transfer('collection-source:'.$method.':'.$external,$original,'cash','fx_clearing',$actor,$trace);
        $journal=$this->transfer('collection:'.$method.':'.$external,$amount,$fx===null?'cash':'fx_clearing','unallocated',$actor,$trace);
        $originalJournal??=$journal;
        $id=Id::uuid(); $fxId=null;
        if ($fx!==null) {
            $fxId=Id::uuid(); $data=$fx->jsonSerialize();
            $this->db->execute('INSERT INTO finance_fx_snapshots(id,source_currency,target_currency,rate,spread,source_reference,observed_at,rounding) VALUES (?,?,?,?,?,?,?,?)',[$fxId,$data['source'],$data['target'],$data['rate'],$data['spread'],$data['reference'],$data['observed_at'],$data['rounding']]);
        }
        $this->db->execute('INSERT INTO finance_payments(id,customer_id,method,external_reference,amount_minor,currency,original_minor,original_currency,fingerprint,journal_id,fx_snapshot_id,original_journal_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$customer,$method,$external,$amount->minor,$amount->currency->code,$original->minor,$original->currency->code,$fingerprint,$journal,$fxId,$originalJournal]);
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
    public function creditWalletSpend(string $customer,string $originalEntry,Money $amount,string $reference,Principal $actor,string $trace,?FxSnapshot $fx=null): string
    {
        $this->mutation($actor); $this->positive($amount); $wallet=$this->repository->wallet($customer);
        $entry=$this->db->execute('SELECT e.*,j.currency FROM finance_wallet_entries e JOIN finance_journals j ON j.id=e.journal_id WHERE e.id=? FOR UPDATE',[$originalEntry])->fetch();
        if (!$entry || $entry['wallet_id']!==$wallet['id'] || $entry['currency']!==$amount->currency->code || (new Decimal((string)$entry['amount_minor']))->compare(new Decimal('0'))>=0) { throw new HttpError(404,'REFUNDABLE_WALLET_SPEND_NOT_FOUND'); }
        if($wallet['currency']!==$amount->currency->code){if($fx===null||$fx->target->code!==$wallet['currency']){throw new HttpError(409,'WALLET_REFUND_FX_POLICY_REQUIRED');}}elseif($fx!==null){throw new HttpError(422,'UNEXPECTED_REFUND_FX');}
        $creditAmount=$fx?->convert($amount)??$amount;if($creditAmount->minor==='0'){throw new HttpError(409,'POSITIVE_BALANCE_CONVERSION_ROUNDS_TO_ZERO');}
        $old=$this->db->execute('SELECT * FROM finance_credits WHERE reference=? FOR UPDATE',[$reference])->fetch();
        if ($old) {
            if ($old['original_entry_id']!==$originalEntry || (string)$old['amount_minor']!==$amount->minor || ($old['fx_snapshot_id']===null)!==($fx===null)) { throw new HttpError(409,'CREDIT_REFERENCE_CONFLICT'); }
            if($fx!==null){$stored=$this->db->execute('SELECT * FROM finance_fx_snapshots WHERE id=?',[$old['fx_snapshot_id']])->fetch();$snapshot=new FxSnapshot(new Currency($stored['source_currency']),new Currency($stored['target_currency']),new Decimal($stored['rate']),new Decimal($stored['spread']),$stored['source_reference'],new \DateTimeImmutable($stored['observed_at'],new \DateTimeZone('UTC')),Rounding::from($stored['rounding']));if($snapshot->jsonSerialize()!==$fx->jsonSerialize()){throw new HttpError(409,'CREDIT_REFERENCE_CONFLICT');}}
            return $old['id'];
        }
        $credited=$this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_credits WHERE original_entry_id=? FOR UPDATE',[$originalEntry])->fetchColumn();
        $remaining=(new Decimal((string)$entry['amount_minor']))->negate()->subtract(new Decimal($credited));
        if ($remaining->compare(new Decimal($amount->minor))<0) { throw new HttpError(409,'CREDIT_EXCEEDS_ORIGINAL_SPEND'); }
        (new Money($this->repository->balance($wallet['id'],true),$creditAmount->currency))->add($creditAmount);
        $sourceJournal=$this->ledger->post('wallet-refund:'.$reference,$amount->currency,[['account'=>'receivable','side'=>'DEBIT','minor'=>$amount->minor],['account'=>$fx===null?'wallet_liability':'fx_clearing','side'=>'CREDIT','minor'=>$amount->minor]],$actor,$trace,$entry['journal_id']);$journal=$sourceJournal;$fxId=null;
        if($fx!==null){$journal=$this->transfer('wallet-refund-fx:'.$reference,$creditAmount,'fx_clearing','wallet_liability',$actor,$trace);$fxId=Id::uuid();$data=$fx->jsonSerialize();$this->db->execute('INSERT INTO finance_fx_snapshots(id,source_currency,target_currency,rate,spread,source_reference,observed_at,rounding) VALUES (?,?,?,?,?,?,?,?)',[$fxId,$data['source'],$data['target'],$data['rate'],$data['spread'],$data['reference'],$data['observed_at'],$data['rounding']]);}
        $credit=Id::uuid(); $id=Id::uuid();
        $this->db->execute('INSERT INTO finance_wallet_entries(id,wallet_id,amount_minor,reference,journal_id) VALUES (?,?,?,?,?)',[$credit,$wallet['id'],$creditAmount->minor,$reference,$journal]);
        $this->db->execute('INSERT INTO finance_credits(id,original_entry_id,credit_entry_id,amount_minor,reference,fx_snapshot_id,source_journal_id) VALUES (?,?,?,?,?,?,?)',[$id,$originalEntry,$credit,$amount->minor,$reference,$fxId,$sourceJournal]); return $id;
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
    public function refundableAllocations(string $request,string $customer): array
    {
        $this->db->requireTransaction();$this->repository->wallet($customer);$rows=$this->db->execute('SELECT * FROM finance_allocations WHERE payment_request_id=? AND customer_id=? ORDER BY id FOR UPDATE',[$request,$customer])->fetchAll();$out=[];
        foreach($rows as $row){
            if($row['payment_id']!==null){$payment=$this->repository->payment($row['payment_id']);if($payment['currency']!==$payment['original_currency']){throw new HttpError(409,'FX_REFUND_REQUIRES_RECONCILIATION');}$used=$this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_allocation_reversals WHERE allocation_id=? FOR UPDATE',[$row['id']])->fetchColumn();}
            else{$used=$this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_credits WHERE original_entry_id=? FOR UPDATE',[$row['wallet_entry_id']])->fetchColumn();}
            $remaining=(new Decimal((string)$row['amount_minor']))->subtract(new Decimal($used));if($remaining->compare(new Decimal('0'))>0){$out[]=['allocation_id'=>$row['id'],'payment_id'=>$row['payment_id'],'wallet_entry_id'=>$row['wallet_entry_id'],'available_minor'=>(string)$remaining,'currency'=>$row['currency']];}
        }return $out;
    }
    public function reversePaymentAllocation(string $allocation,string $customer,Money $amount,string $reference,Principal $actor,string $trace): string
    {
        $this->mutation($actor);$this->positive($amount);$row=$this->db->execute('SELECT * FROM finance_allocations WHERE id=? AND customer_id=? FOR UPDATE',[$allocation,$customer])->fetch();if(!$row||$row['payment_id']===null||$row['currency']!==$amount->currency->code){throw new HttpError(404,'REFUNDABLE_ALLOCATION_NOT_FOUND');}$this->repository->payment($row['payment_id']);
        $old=$this->db->execute('SELECT * FROM finance_allocation_reversals WHERE reference=? FOR UPDATE',[$reference])->fetch();if($old){if($old['allocation_id']!==$allocation||(string)$old['amount_minor']!==$amount->minor){throw new HttpError(409,'ALLOCATION_REVERSAL_CONFLICT');}return $old['id'];}
        $used=$this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_allocation_reversals WHERE allocation_id=? FOR UPDATE',[$allocation])->fetchColumn();if((new Decimal((string)$row['amount_minor']))->subtract(new Decimal($used))->compare(new Decimal($amount->minor))<0){throw new HttpError(409,'ALLOCATION_REFUND_LIMIT');}
        $journal=$this->ledger->post('allocation-refund:'.$reference,$amount->currency,[['account'=>'receivable','side'=>'DEBIT','minor'=>$amount->minor],['account'=>'unallocated','side'=>'CREDIT','minor'=>$amount->minor]],$actor,$trace,$row['journal_id']);$id=Id::uuid();$this->db->execute('INSERT INTO finance_allocation_reversals(id,allocation_id,amount_minor,reference,journal_id) VALUES (?,?,?,?,?)',[$id,$allocation,$amount->minor,$reference,$journal]);return $id;
    }
    public function convertWallet(string $customer,FxSnapshot $fx,Principal $actor,string $trace): array
    {
        $this->mutation($actor);$wallet=$this->repository->wallet($customer);if($wallet['currency']!==$fx->source->code){throw new HttpError(409,'WALLET_CURRENCY_CONFLICT');}$original=new Money($this->repository->balance($wallet['id'],true),$fx->source);$converted=$fx->convert($original);if($original->minor!=='0'&&$converted->minor==='0'){throw new HttpError(409,'POSITIVE_BALANCE_CONVERSION_ROUNDS_TO_ZERO');}
        $id=Id::uuid();$fromJournal=null;$toJournal=null;
        if($original->minor!=='0'){
            $fromJournal=$this->transfer('wallet-fx-source:'.$id,$original,'wallet_liability','fx_clearing',$actor,$trace);$toJournal=$this->transfer('wallet-fx-target:'.$id,$converted,'fx_clearing','wallet_liability',$actor,$trace);
            $this->db->execute('INSERT INTO finance_wallet_entries(id,wallet_id,amount_minor,reference,journal_id) VALUES (?,?,?,?,?)',[Id::uuid(),$wallet['id'],'-'.$original->minor,'fx-source:'.$id,$fromJournal]);
            $this->db->execute('INSERT INTO finance_wallet_entries(id,wallet_id,amount_minor,reference,journal_id) VALUES (?,?,?,?,?)',[Id::uuid(),$wallet['id'],$converted->minor,'fx-target:'.$id,$toJournal]);
        }
        $snapshot=Id::uuid();$data=$fx->jsonSerialize();$this->db->execute('INSERT INTO finance_fx_snapshots(id,source_currency,target_currency,rate,spread,source_reference,observed_at,rounding) VALUES (?,?,?,?,?,?,?,?)',[$snapshot,$data['source'],$data['target'],$data['rate'],$data['spread'],$data['reference'],$data['observed_at'],$data['rounding']]);
        $this->db->execute('INSERT INTO finance_wallet_conversions(id,wallet_id,source_currency,target_currency,original_minor,converted_minor,fx_snapshot_id,source_journal_id,target_journal_id,actor_id) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$wallet['id'],$fx->source->code,$fx->target->code,$original->minor,$converted->minor,$snapshot,$fromJournal,$toJournal,$actor->actorId]);$this->db->execute('UPDATE finance_wallets SET currency=?,last_conversion_id=? WHERE id=?',[$fx->target->code,$id,$wallet['id']]);
        (new \Hostider\Core\Security\Audit($this->db))->record($actor,\Hostider\Core\Security\AuditEvent::WalletConverted,$id,$trace);
        (new \Hostider\Core\Queue\Outbox($this->db,new \Hostider\Core\Queue\Queue($this->db)))->append(Id::uuid(),'finance.wallet_converted',$id,['conversion_id'=>$id,'wallet_id'=>$wallet['id']],$trace);return ['id'=>$id,'original'=>$original->jsonSerialize(),'converted'=>$converted->jsonSerialize(),'fx'=>$data];
    }
}
