<?php
declare(strict_types=1);
namespace Hostider\Billing;
use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Crypto,Principal};
use Hostider\Finance\{Currency,Decimal,FinanceService,Ledger,Money};

final readonly class BillingService
{
    public Repository $repository;
    private Crypto $crypto;
    public function __construct(private Database $db,Config $config,private FinanceService $finance)
    {
        $this->repository=new Repository($db); $this->crypto=new Crypto(base64_decode($config->key,true));
    }
    public function issue(string $order,array $quote,array $customerSnapshot,Principal $actor,string $trace): string
    {
        $this->db->requireTransaction(); if($actor->impersonating){throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN');}
        $id=Id::uuid(); $currency=new Currency($quote['currency']); $total=new Money($quote['total_minor'],$currency); $journal=null;
        if($total->minor!=='0') {
            $lines=[['account'=>'receivable','side'=>'DEBIT','minor'=>$total->minor]];
            foreach(['net_minor'=>'revenue','tax_minor'=>'tax_payable'] as $key=>$account){if($quote[$key]!=='0'){$lines[]=['account'=>$account,'side'=>'CREDIT','minor'=>$quote[$key]];}}
            $journal=(new Ledger($this->db))->post('bill-issued:'.$id,$currency,$lines,$actor,$trace);
        }
        $this->db->execute('INSERT INTO billing_requests(id,order_id,customer_id,currency,net_minor,tax_minor,total_minor,snapshot,customer_encrypted,journal_id) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$order,$quote['customer_id'],$currency->code,$quote['net_minor'],$quote['tax_minor'],$total->minor,json_encode($quote,JSON_THROW_ON_ERROR),$this->crypto->encrypt(json_encode($customerSnapshot,JSON_THROW_ON_ERROR),'billing-customer:'.$id),$journal]);
        $this->db->execute('INSERT INTO billing_request_states(request_id,status) VALUES (?,?)',[$id,$total->minor==='0'?'PAID':'ISSUED']); return $id;
    }
    /** Request lock fences all partial/mixed allocations. Finance owns its own tables. */
    public function fund(string $request,string $customer,Money $amount,string $source,?string $payment,string $reference,Principal $actor,string $trace): array
    {
        $this->db->requireTransaction(); if($actor->impersonating){throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN');}
        $row=$this->repository->request($request,$customer,true);
        if($amount->currency->code!==$row['currency'] || (new Decimal($amount->minor))->compare(new Decimal('0'))<=0){throw new HttpError(422,'FUNDING_CURRENCY_OR_AMOUNT');}
        if($row['status']==='VOIDED'){throw new HttpError(409,'PAYMENT_REQUEST_VOIDED');}
        $paid=$this->finance->repository->allocated($request,true);
        $remaining=(new Decimal((string)$row['total_minor']))->subtract(new Decimal($paid));
        // Replay is owned by the caller's persistent Idempotency boundary, not a new allocation.
        if($remaining->compare(new Decimal($amount->minor))<0){throw new HttpError(409,'ALLOCATION_EXCEEDS_OUTSTANDING');}
        if($source==='WALLET'){$this->finance->spendWallet($customer,$request,$amount,$reference,$actor,$trace);}
        elseif($source==='COLLECTION' && $payment!==null){$this->finance->allocatePayment($customer,$request,$payment,$amount,$reference,$actor,$trace);}
        else{throw new HttpError(422,'INVALID_FUNDING_SOURCE');}
        $paid=$this->finance->repository->allocated($request,true); $status=(new Decimal($paid))->compare(new Decimal((string)$row['total_minor']))===0?'PAID':'PARTIALLY_PAID';
        $this->db->execute('UPDATE billing_request_states SET status=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE request_id=?',[$status,$request]);
        return ['id'=>$request,'status'=>$status,'paid_minor'=>$paid,'total_minor'=>(string)$row['total_minor'],'currency'=>$row['currency']];
    }
    public function void(string $request,string $customer,Principal $actor,string $trace): void
    {
        $this->db->requireTransaction(); if($actor->impersonating){throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN');}
        $row=$this->repository->request($request,$customer,true);
        if($row['status']==='VOIDED'){return;}
        if($this->finance->repository->allocated($request,true)!=='0'){throw new HttpError(409,'FUNDED_REQUEST_REQUIRES_REFUND_POLICY');}
        $currency=new Currency($row['currency']);
        if((string)$row['total_minor']!=='0') {
            $lines=[['account'=>'receivable','side'=>'CREDIT','minor'=>(string)$row['total_minor']]];
            foreach(['net_minor'=>'revenue','tax_minor'=>'tax_payable'] as $key=>$account){if((string)$row[$key]!=='0'){$lines[]=['account'=>$account,'side'=>'DEBIT','minor'=>(string)$row[$key]];}}
            (new Ledger($this->db))->post('bill-void:'.$request,$currency,$lines,$actor,$trace,$row['journal_id']);
        }
        $this->db->execute("UPDATE billing_request_states SET status='VOIDED',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE request_id=?",[$request]);
    }
    public function detail(string $id,string $customer): array
    {
        $row=$this->repository->request($id,$customer); $paid=$this->finance->repository->allocated($id);
        return ['id'=>$id,'order_id'=>$row['order_id'],'status'=>$row['status'],'version'=>(string)$row['version'],'currency'=>$row['currency'],'total_minor'=>(string)$row['total_minor'],'paid_minor'=>$paid,'snapshot'=>json_decode($row['snapshot'],true,64,JSON_THROW_ON_ERROR),'customer_snapshot'=>json_decode($this->crypto->decrypt($row['customer_encrypted'],'billing-customer:'.$id),true,32,JSON_THROW_ON_ERROR),'document_type'=>'PROFORMA','official_invoice_issued'=>false];
    }
}
