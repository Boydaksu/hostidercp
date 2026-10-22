<?php
declare(strict_types=1);
namespace Hostider\Payments;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
final readonly class Repository
{
    public function __construct(public Database $db) {}
    public function attempt(string $id,string $customer,bool $lock=true): array
    {
        if($lock){$this->db->requireTransaction();}
        $row=$this->db->execute('SELECT * FROM payment_attempts WHERE id=? AND customer_id=?'.($lock?' FOR UPDATE':''),[$id,$customer])->fetch();
        if(!$row){throw new HttpError(404,'PAYMENT_ATTEMPT_NOT_FOUND');}return $row;
    }
    public function notice(string $id,string $customer,bool $lock=true): array
    {
        if($lock){$this->db->requireTransaction();}
        $row=$this->db->execute('SELECT n.*,a.customer_id,a.expected_minor,a.currency,a.order_id,a.purpose FROM payment_bank_notices n JOIN payment_attempts a ON a.id=n.attempt_id WHERE n.id=? AND a.customer_id=?'.($lock?' FOR UPDATE':''),[$id,$customer])->fetch();
        if(!$row){throw new HttpError(404,'BANK_NOTICE_NOT_FOUND');}return $row;
    }
}
