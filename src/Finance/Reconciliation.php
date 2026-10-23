<?php
declare(strict_types=1);
namespace Hostider\Finance;
use Hostider\Core\{Config,Id,Validation};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Authorization,Principal,Crypto,Audit,AuditEvent};
/** Resolution is an immutable fact; it never edits the case or its original collection. */
final readonly class Reconciliation
{
    public function __construct(private Database $db,private Config $config,private FinanceService $finance) {}
    public function detail(string $id,string $customer,Principal $actor): array
    {
        (new Authorization())->require($actor,'finance.view',$customer,privileged:true);
        if(!$actor->staff||$actor->impersonating){throw new HttpError(403,'STAFF_RECONCILIATION_REQUIRED');}
        $row=$this->db->execute('SELECT r.id,r.subject_id,r.code,p.customer_id,p.currency,CAST(p.amount_minor AS CHAR) payment_minor,p.original_currency,CAST(p.original_minor AS CHAR) original_minor,x.id resolution_id,x.action,x.evidence_encrypted,x.evidence_sha256,x.effect_id,x.actor_id,x.trace_id,x.created_at FROM finance_reconciliations r JOIN finance_payments p ON p.id=r.subject_id LEFT JOIN finance_reconciliation_resolutions x ON x.case_id=r.id WHERE r.id=? AND p.customer_id=?',[$id,$customer])->fetch();
        if(!$row){throw new HttpError(404,'RECONCILIATION_NOT_FOUND');}
        $row['status']=$row['resolution_id']===null?'OPEN':'RESOLVED';$row['evidence']=$row['resolution_id']===null?null:(new Crypto(base64_decode($this->config->key,true)))->decrypt($row['evidence_encrypted'],'reconciliation:'.$row['resolution_id']);unset($row['evidence_encrypted']);return $row;
    }
    public function plan(string $id,string $customer,string $action,string $evidence,Principal $actor): array
    {
        $this->db->requireTransaction();(new Authorization())->require($actor,'finance.approve',$customer,privileged:true);
        if(!$actor->staff||$actor->impersonating){throw new HttpError(403,'STAFF_RECONCILIATION_REQUIRED');}
        Validation::text($evidence,4000);if(strlen(trim($evidence))<20){throw new HttpError(422,'RECONCILIATION_EVIDENCE_REQUIRED');}
        if(!in_array($action,['CREDIT_WALLET','RESERVE_ORIGINAL_REFUND','REVIEW_NO_FUNDS'],true)){throw new HttpError(422,'UNSUPPORTED_RECONCILIATION_ACTION');}
        // Match ownership before exposing or locking another customer's case.
        $case=$this->db->execute('SELECT r.* FROM finance_reconciliations r JOIN finance_payments p ON p.id=r.subject_id WHERE r.id=? AND p.customer_id=?',[$id,$customer])->fetch();if(!$case){throw new HttpError(404,'RECONCILIATION_NOT_FOUND');}
        $wallet=$this->finance->repository->wallet($customer);$payment=$this->finance->repository->payment($case['subject_id']);$case=$this->db->execute('SELECT * FROM finance_reconciliations WHERE id=? FOR UPDATE',[$id])->fetch();
        if($this->db->execute('SELECT 1 FROM finance_reconciliation_resolutions WHERE case_id=?',[$id])->fetchColumn()){throw new HttpError(409,'RECONCILIATION_ALREADY_RESOLVED');}
        if($case['code']!=='LATE_OR_EXCESS_PAYMENT'){throw new HttpError(409,'RECONCILIATION_POLICY_GATE_OPEN');}
        $available=$this->finance->repository->available($payment);
        if($action==='REVIEW_NO_FUNDS') {if($available!=='0'){throw new HttpError(409,'UNALLOCATED_FUNDS_STILL_OPEN');}}
        elseif((new Decimal($available))->compare(new Decimal('0'))<=0){throw new HttpError(409,'NO_UNALLOCATED_FUNDS');}
        if($action==='CREDIT_WALLET'&&$wallet['currency']!==$payment['currency']){throw new HttpError(409,'RECONCILIATION_FX_GATE_OPEN');}
        if($action==='RESERVE_ORIGINAL_REFUND'&&$payment['original_currency']!==$payment['currency']){throw new HttpError(409,'RECONCILIATION_FX_GATE_OPEN');}
        return ['case_id'=>$id,'customer_id'=>$customer,'payment_id'=>$payment['id'],'code'=>$case['code'],'action'=>$action,'minor'=>$available,'currency'=>$payment['currency'],'evidence'=>$evidence,'effect'=>'All remaining unallocated funds; original records stay unchanged. External refund is reserved only, never sent.'];
    }
    public function resolve(string $id,string $customer,string $action,string $evidence,Principal $actor,string $trace): array
    {
        if($this->config->environment==='production'){throw new HttpError(403,'PRODUCTION_FINANCE_RELEASE_GATE_OPEN');}
        $plan=$this->plan($id,$customer,$action,$evidence,$actor);$amount=new Money($plan['minor'],new Currency($plan['currency']));$effect=match($action){'CREDIT_WALLET'=>$this->finance->creditWallet($customer,$plan['payment_id'],$amount,'reconciliation:'.$id,$actor,$trace),'RESERVE_ORIGINAL_REFUND'=>$this->finance->requestOriginalRefund($plan['payment_id'],$amount,'reconciliation:'.$id,$actor,$trace),default=>null};
        $resolution=Id::uuid();$this->db->execute('INSERT INTO finance_reconciliation_resolutions(id,case_id,customer_id,action,amount_minor,currency,evidence_encrypted,evidence_sha256,effect_id,actor_id,trace_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$resolution,$id,$customer,$action,$amount->minor,$amount->currency->code,(new Crypto(base64_decode($this->config->key,true)))->encrypt($evidence,'reconciliation:'.$resolution),hash('sha256',$evidence),$effect,$actor->actorId,$trace]);
        (new Audit($this->db))->record($actor,AuditEvent::FinancialReconciled,$resolution,$trace);return ['id'=>$resolution,'case_id'=>$id,'status'=>'RESOLVED','action'=>$action,'effect_id'=>$effect,'external_refund_sent'=>false];
    }
}
