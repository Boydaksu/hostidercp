<?php
declare(strict_types=1);
namespace Hostider\Orders;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
final readonly class Repository
{
    public function __construct(public Database $db) {}
    public function find(string $id,string $customer,bool $lock=false): array
    {
        if($lock){$this->db->requireTransaction();}
        $row=$this->db->execute('SELECT * FROM orders WHERE id=? AND customer_id=?'.($lock?' FOR UPDATE':''),[$id,$customer])->fetch();
        if(!$row){throw new HttpError(404,'ORDER_NOT_FOUND');}return $row;
    }
    public function list(string $customer,int $offset=0): array
    {
        if($offset<0||$offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}
        return $this->db->execute('SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC,id LIMIT 50 OFFSET '.$offset,[$customer])->fetchAll();
    }
}
