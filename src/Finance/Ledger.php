<?php
declare(strict_types=1);
namespace Hostider\Finance;
use Hostider\Core\{CanonicalJson,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Core\Security\{Audit,AuditEvent,Principal};

final readonly class Ledger
{
    public function __construct(private Database $db) {}
    /** Caller owns the transaction; entries are opaque account codes and positive minor strings. */
    public function post(string $reference,Currency $currency,array $lines,Principal $actor,string $trace,?string $reversal=null): string
    {
        $this->db->requireTransaction();
        if ($actor->impersonating) { throw new HttpError(403,'FINANCIAL_IMPERSONATION_FORBIDDEN'); }
        if (!preg_match('/^[A-Za-z0-9:_-]{1,160}$/D',$reference) || count($lines)<2 || count($lines)>100) { throw new \InvalidArgumentException('Invalid journal'); }
        $totals=['DEBIT'=>new Decimal('0'),'CREDIT'=>new Decimal('0')];
        $accounts=['cash','unallocated','wallet_liability','receivable','revenue','tax_payable','refund_payable','expense','provider_cost','provider_payable','credit_liability','fx_clearing'];
        foreach ($lines as $line) {
            if (!in_array($line['account'],$accounts,true) || !isset($totals[$line['side']])) { throw new \InvalidArgumentException('Invalid ledger account'); }
            $money=new Money($line['minor'],$currency);
            if ((new Decimal($money->minor))->compare(new Decimal('0'))<=0) { throw new \InvalidArgumentException('Positive ledger amount required'); }
            $totals[$line['side']]=$totals[$line['side']]->add(new Decimal($money->minor));
        }
        if ($totals['DEBIT']->compare($totals['CREDIT'])!==0) { throw new \InvalidArgumentException('Unbalanced journal'); }
        $fingerprint=hash('sha256',CanonicalJson::encode(['currency'=>$currency->code,'lines'=>$lines,'reversal'=>$reversal,'actor'=>$actor->actorId,'customer'=>$actor->customerId]));
        $old=$this->db->execute('SELECT id,fingerprint FROM finance_journals WHERE reference=?',[$reference])->fetch();
        if ($old) {
            if (!hash_equals($old['fingerprint'],$fingerprint)) { throw new HttpError(409,'FINANCIAL_REFERENCE_CONFLICT'); }
            return $old['id'];
        }
        $id=Id::uuid();
        $this->db->execute('INSERT INTO finance_journals(id,reference,fingerprint,currency,actor_id,trace_id,reversal_of) VALUES (?,?,?,?,?,?,?)',[$id,$reference,$fingerprint,$currency->code,$actor->actorId,$trace,$reversal]);
        foreach ($lines as $line) { $this->db->execute('INSERT INTO finance_lines(journal_id,account,side,amount_minor) VALUES (?,?,?,?)',[$id,$line['account'],$line['side'],$line['minor']]); }
        (new Audit($this->db))->record($actor,AuditEvent::FinancialPosted,$id,$trace);
        (new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'finance.posted',$id,['journal_id'=>$id],$trace);
        return $id;
    }
}
