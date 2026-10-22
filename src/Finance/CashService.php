<?php
declare(strict_types=1);
namespace Hostider\Finance;
use Hostider\Core\{Config,Id,Validation};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Crypto,Principal};
use Hostider\Catalog\BillingCycle;
final readonly class CashService
{
    private Crypto $crypto;
    public function __construct(private Database $db,Config $config){$this->crypto=new Crypto(base64_decode($config->key,true));}
    private function mutation(Principal $actor): void {$this->db->requireTransaction();if($actor->impersonating){throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN');}}
    private function event(string $id,Principal $actor): void
    {
        $trace=Id::uuid();(new \Hostider\Core\Security\Audit($this->db))->record($actor,\Hostider\Core\Security\AuditEvent::CashChanged,$id,$trace);
        (new \Hostider\Core\Queue\Outbox($this->db,new \Hostider\Core\Queue\Queue($this->db)))->append(Id::uuid(),'finance.cash_changed',$id,['entity_id'=>$id],$trace);
    }
    public function category(string $name,string $kind,Principal $actor): string
    {
        $this->mutation($actor);Validation::text($name,120);if(!in_array($kind,['EXPENSE','PROVIDER_COST'],true)){throw new HttpError(422,'INVALID_CASH_CATEGORY');}
        $id=Id::uuid();$this->db->execute('INSERT INTO finance_cash_categories(id,name,kind) VALUES (?,?,?)',[$id,$name,$kind]);$this->event($id,$actor);return $id;
    }
    public function expense(string $category,Money $amount,string $reference,string $description,Principal $actor,string $trace): string
    {
        $this->mutation($actor);Validation::text($description,2000);if(!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$reference)||(new Decimal($amount->minor))->compare(new Decimal('0'))<=0){throw new HttpError(422,'INVALID_EXPENSE');}
        $row=$this->db->execute('SELECT * FROM finance_cash_categories WHERE id=? FOR UPDATE',[$category])->fetch();if(!$row){throw new HttpError(404,'CASH_CATEGORY_NOT_FOUND');}
        $old=$this->db->execute('SELECT * FROM finance_cash_entries WHERE source_reference=? FOR UPDATE',[$reference])->fetch();
        if($old){if($old['category_id']!==$category||(string)$old['amount_minor']!==$amount->minor||$old['currency']!==$amount->currency->code||$this->crypto->decrypt($old['description_encrypted'],'cash-description:'.$old['id'])!==$description){throw new HttpError(409,'EXPENSE_REFERENCE_CONFLICT');}return $old['id'];}
        $account=$row['kind']==='PROVIDER_COST'?'provider_cost':'expense';
        $journal=(new Ledger($this->db))->post('expense:'.$reference,$amount->currency,[['account'=>$account,'side'=>'DEBIT','minor'=>$amount->minor],['account'=>'cash','side'=>'CREDIT','minor'=>$amount->minor]],$actor,$trace);$id=Id::uuid();
        $this->db->execute('INSERT INTO finance_cash_entries(id,category_id,journal_id,amount_minor,currency,source_reference,description_encrypted) VALUES (?,?,?,?,?,?,?)',[$id,$category,$journal,$amount->minor,$amount->currency->code,$reference,$this->crypto->encrypt($description,'cash-description:'.$id)]);return $id;
    }
    public function recurring(string $category,Money $amount,string $kind,int $interval,\DateTimeImmutable $firstDue,Principal $actor): string
    {
        $this->mutation($actor);BillingCycle::next($firstDue,$kind,$interval);if((new Decimal($amount->minor))->compare(new Decimal('0'))<=0){throw new HttpError(422,'POSITIVE_AMOUNT_REQUIRED');}
        $id=Id::uuid();$this->db->execute('INSERT INTO finance_recurring_rules(id,category_id,amount_minor,currency,cycle_kind,interval_count,anchor_day,next_due_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$category,$amount->minor,$amount->currency->code,$kind,$interval,(int)$firstDue->format('d'),$firstDue->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')]);$this->event($id,$actor);return $id;
    }
    public function materialize(string $rule,\DateTimeImmutable $now,Principal $actor): ?string
    {
        $this->mutation($actor);$row=$this->db->execute('SELECT * FROM finance_recurring_rules WHERE id=? FOR UPDATE',[$rule])->fetch();if(!$row){throw new HttpError(404,'RECURRING_RULE_NOT_FOUND');}
        $due=new \DateTimeImmutable($row['next_due_at'],new \DateTimeZone('UTC'));if($due>$now){return null;}$id=Id::uuid();
        $this->db->execute('INSERT INTO finance_due_expenses(id,rule_id,due_at,amount_minor,currency) VALUES (?,?,?,?,?)',[$id,$rule,$row['next_due_at'],(string)$row['amount_minor'],$row['currency']]);
        $next=BillingCycle::next($due,$row['cycle_kind'],(int)$row['interval_count'],(int)$row['anchor_day']);$this->db->execute('UPDATE finance_recurring_rules SET next_due_at=?,version=version+1 WHERE id=?',[$next->format('Y-m-d H:i:s.u'),$rule]);$this->event($id,$actor);return $id;
    }
    public function payDue(string $id,Principal $actor,string $trace): string
    {
        $this->mutation($actor);$row=$this->db->execute('SELECT d.*,r.category_id FROM finance_due_expenses d JOIN finance_recurring_rules r ON r.id=d.rule_id WHERE d.id=? FOR UPDATE',[$id])->fetch();if(!$row){throw new HttpError(404,'DUE_EXPENSE_NOT_FOUND');}if($row['cash_entry_id']!==null){return $row['cash_entry_id'];}
        $entry=$this->expense($row['category_id'],new Money((string)$row['amount_minor'],new Currency($row['currency'])),'recurring_'.$id,'Recurring expense',$actor,$trace);$this->db->execute('UPDATE finance_due_expenses SET cash_entry_id=? WHERE id=?',[$entry,$id]);return $entry;
    }
    public function report(): array
    {
        $rows=$this->db->execute("SELECT j.currency,l.account,CAST(SUM(CASE WHEN l.side='DEBIT' THEN l.amount_minor ELSE -l.amount_minor END) AS CHAR) net FROM finance_lines l JOIN finance_journals j ON j.id=l.journal_id GROUP BY j.currency,l.account ORDER BY j.currency,l.account")->fetchAll();$currencies=[];
        foreach($rows as $row){$account=$row['account'];$value=new Decimal($row['net']);if(in_array($account,['revenue','wallet_liability','tax_payable','unallocated','refund_payable','credit_liability'],true)){$value=$value->negate();}$currencies[$row['currency']][$account.'_minor']=(string)$value;}
        return ['report_type'=>'INTERNAL_FINANCE_NOT_STATUTORY_ACCOUNTING','currencies'=>$currencies];
    }
}
