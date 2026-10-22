<?php
declare(strict_types=1);
namespace Hostider\Orders;
use Hostider\Billing\BillingService;
use Hostider\Catalog\Promotions;
use Hostider\Core\Id;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Core\Security\{Audit,AuditEvent,Principal};
use Hostider\Finance\Money;
use Hostider\Pricing\QuoteService;
final readonly class OrderService
{
    public Repository $repository;
    public function __construct(private Database $db,private BillingService $billing,private QuoteService $quotes) { $this->repository=new Repository($db); }
    private function mutation(Principal $actor): void
    {
        $this->db->requireTransaction(); if($actor->impersonating){throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN');}
    }
    public function submit(string $quoteId,string $customer,array $customerSnapshot,Principal $actor,string $trace,\DateTimeImmutable $now): array
    {
        $this->mutation($actor); $quote=$this->quotes->get($quoteId,$customer,$now,true);
        $old=$this->db->execute('SELECT * FROM orders WHERE quote_id=? FOR UPDATE',[$quoteId])->fetch();
        if($old){return $old;}
        $id=Id::uuid(); $this->db->execute("INSERT INTO orders(id,customer_id,quote_id,status) VALUES (?,?,?,'AWAITING_PAYMENT')",[$id,$customer,$quoteId]);
        foreach($quote['lines'] as $line){$this->db->execute('INSERT INTO order_items(id,order_id,product_id,snapshot) VALUES (?,?,?,?)',[Id::uuid(),$id,$line['product_id'],json_encode($line,JSON_THROW_ON_ERROR)]);}
        (new Promotions($this->db))->redeem($quote['promotions'],$id,$customer,$quote['renewal'],$now);
        $request=$this->billing->issue($id,$quote,$customerSnapshot,$actor,$trace);
        $this->db->execute('UPDATE orders SET payment_request_id=? WHERE id=?',[$request,$id]);
        $this->event($id,'submitted',$actor,$trace);
        if($quote['total_minor']==='0'){$this->paid($id,$customer,$actor,$trace);}
        return $this->repository->find($id,$customer,true);
    }
    public function fund(string $order,string $customer,Money $amount,string $source,?string $payment,string $reference,Principal $actor,string $trace): array
    {
        $this->mutation($actor); $row=$this->repository->find($order,$customer,true);
        if(!in_array($row['status'],['AWAITING_PAYMENT','PARTIALLY_PAID'],true)){throw new HttpError(409,'ORDER_NOT_FUNDABLE');}
        $result=$this->billing->fund($row['payment_request_id'],$customer,$amount,$source,$payment,$reference,$actor,$trace);
        if($result['status']==='PAID'){$this->paid($order,$customer,$actor,$trace);}
        else{$this->db->execute("UPDATE orders SET status='PARTIALLY_PAID',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?",[$order]);}
        return ['order'=>$this->repository->find($order,$customer,true),'payment_request'=>$result];
    }
    private function paid(string $order,string $customer,Principal $actor,string $trace): void
    {
        $row=$this->repository->find($order,$customer,true);
        $request=$this->billing->repository->request($row['payment_request_id'],$customer,true);
        if($request['status']!=='PAID'){throw new HttpError(409,'FULL_PAYMENT_REQUIRED');}
        $manual=false;foreach($this->db->execute('SELECT snapshot FROM order_items WHERE order_id=?',[$order])->fetchAll() as $item){if(json_decode($item['snapshot'],true,64,JSON_THROW_ON_ERROR)['activation_policy']==='MANUAL'){$manual=true;}}
        $status=$manual?'REQUIRES_APPROVAL':'READY_FOR_PROVISIONING';
        $this->db->execute('UPDATE orders SET status=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$status,$order]);
        $this->event($order,$manual?'approval_required':'ready',$actor,$trace);
    }
    public function cancel(string $order,string $customer,Principal $actor,string $trace): array
    {
        $this->mutation($actor); $row=$this->repository->find($order,$customer,true);
        if($row['status']==='CANCELLED'){return $row;}
        if($row['status']!=='AWAITING_PAYMENT'){throw new HttpError(409,'ORDER_CANCELLATION_REQUIRES_POLICY');}
        $this->billing->void($row['payment_request_id'],$customer,$actor,$trace);
        $this->db->execute("UPDATE orders SET status='CANCELLED',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?",[$order]);
        $this->event($order,'cancelled',$actor,$trace); return $this->repository->find($order,$customer,true);
    }
    private function event(string $order,string $kind,Principal $actor,string $trace): void
    {
        $id=Id::uuid();$this->db->execute('INSERT INTO order_events(id,order_id,kind) VALUES (?,?,?)',[$id,$order,$kind]);
        (new Audit($this->db))->record($actor,AuditEvent::OrderChanged,$order,$trace);
        // Durable F2 fact only. No provisioning handler is registered in F2.
        (new Outbox($this->db,new Queue($this->db)))->append($id,'orders.'.$kind,$order,['order_id'=>$order],$trace);
    }
}
