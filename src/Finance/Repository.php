<?php
declare(strict_types=1);
namespace Hostider\Finance;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;

final readonly class Repository
{
    public function __construct(public Database $db) {}
    public function wallet(string $customer,bool $lock=true): array
    {
        if ($lock) { $this->db->requireTransaction(); }
        $row=$this->db->execute('SELECT * FROM finance_wallets WHERE customer_id=?'.($lock?' FOR UPDATE':''),[$customer])->fetch();
        if (!$row) { throw new HttpError(404,'WALLET_NOT_FOUND'); } return $row;
    }
    public function balance(string $wallet,bool $lock=false): string
    {
        if ($lock) { $this->db->requireTransaction(); }
        return $this->db->execute('SELECT CAST(COALESCE(SUM(e.amount_minor),0) AS CHAR) FROM finance_wallet_entries e JOIN finance_journals j ON j.id=e.journal_id JOIN finance_wallets w ON w.id=e.wallet_id WHERE e.wallet_id=? AND j.currency=w.currency'.($lock?' FOR UPDATE':''),[$wallet])->fetchColumn();
    }
    public function payment(string $id): array
    {
        $this->db->requireTransaction();
        $row=$this->db->execute('SELECT * FROM finance_payments WHERE id=? FOR UPDATE',[$id])->fetch();
        if (!$row) { throw new HttpError(404,'PAYMENT_NOT_FOUND'); } return $row;
    }
    public function available(array $payment): string
    {
        // Wallet credits, allocations, and original-method refunds consume disjoint collection funds.
        $id=$payment['id'];
        $this->db->requireTransaction();
        $allocated=$this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_allocations WHERE payment_id=? FOR UPDATE',[$id])->fetchColumn();
        $credited=$this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_wallet_entries WHERE payment_id=? FOR UPDATE',[$id])->fetchColumn();
        $refunded=$this->db->execute("SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_refunds WHERE payment_id=? AND destination='ORIGINAL' FOR UPDATE",[$id])->fetchColumn();
        $reversed=$this->db->execute('SELECT CAST(COALESCE(SUM(r.amount_minor),0) AS CHAR) FROM finance_allocation_reversals r JOIN finance_allocations a ON a.id=r.allocation_id WHERE a.payment_id=? FOR UPDATE',[$id])->fetchColumn();
        return (string)(new Decimal((string)$payment['amount_minor']))->subtract(new Decimal($allocated))->add(new Decimal($reversed))->subtract(new Decimal($credited))->subtract(new Decimal($refunded));
    }
    public function allocated(string $request,bool $lock=false): string
    {
        if($lock){$this->db->requireTransaction();}
        return $this->db->execute('SELECT CAST(COALESCE(SUM(amount_minor),0) AS CHAR) FROM finance_allocations WHERE payment_request_id=?'.($lock?' FOR UPDATE':''),[$request])->fetchColumn();
    }
}
