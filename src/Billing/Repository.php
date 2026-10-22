<?php
declare(strict_types=1);
namespace Hostider\Billing;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
final readonly class Repository
{
    public function __construct(public Database $db) {}
    public function request(string $id,string $customer,bool $lock=false): array
    {
        if($lock){$this->db->requireTransaction();}
        $row=$this->db->execute('SELECT r.*,s.status,s.version FROM billing_requests r JOIN billing_request_states s ON s.request_id=r.id WHERE r.id=? AND r.customer_id=?'.($lock?' FOR UPDATE':''),[$id,$customer])->fetch();
        if(!$row){throw new HttpError(404,'PAYMENT_REQUEST_NOT_FOUND');} return $row;
    }
    public function list(string $customer,int $offset=0): array
    {
        if($offset<0||$offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}
        return $this->db->execute('SELECT r.id,r.order_id,r.currency,CAST(r.total_minor AS CHAR) total_minor,r.created_at,s.status,s.version FROM billing_requests r JOIN billing_request_states s ON s.request_id=r.id WHERE r.customer_id=? ORDER BY r.created_at DESC,r.id LIMIT 50 OFFSET '.$offset,[$customer])->fetchAll();
    }
}
