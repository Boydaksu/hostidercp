<?php
declare(strict_types=1);
namespace Hostider\Support;
use Hostider\Commerce\Services;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Authorization,Principal};
final readonly class Access
{
    public function __construct(public Services $c){}
    public static function id(mixed $v): string
    {
        if(!is_string($v)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$v)){throw new HttpError(422,'INVALID_SUPPORT_ID');}return $v;
    }
    public function actor(array $u,array $s): Principal
    {
        $a=$this->c->identity->principal($u,$s);if($a->impersonating){throw new HttpError(403,'SUPPORT_IMPERSONATION_FORBIDDEN');}return $a;
    }
    public function department(string $id): array
    {
        $this->c->db->requireTransaction();$d=$this->c->db->execute('SELECT * FROM support_departments WHERE id=? FOR UPDATE',[$id])->fetch();if(!$d){throw new HttpError(404,'SUPPORT_NOT_FOUND');}return $d;
    }
    public function require(array $u,array $s,string $customer,string $department,string $permission='support.ticket.view'): Principal
    {
        $a=$this->actor($u,$s);
        if(!$a->staff){
            if($permission!=='support.ticket.view'&&$permission!=='support.ticket.reply'){throw new HttpError(404,'SUPPORT_NOT_FOUND');}
            try{(new Authorization())->require($a,'support.view',$customer);}catch(HttpError){throw new HttpError(404,'SUPPORT_NOT_FOUND');}return $a;
        }
        try{
            if(!$this->c->db->execute('SELECT 1 FROM support_department_members WHERE department_id=? AND user_id=?',[$department,$u['id']])->fetchColumn()){throw new HttpError(403,'ACCESS_DENIED');}
            $this->c->identity->authorize($u,$s,'support.ticket.view',$customer,department:$department);
            return $this->c->identity->authorize($u,$s,$permission,$customer,department:$department);
        }catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}throw new HttpError(404,'SUPPORT_NOT_FOUND');}
    }
    public function ticket(array $u,array $s,string $id,string $permission='support.ticket.view'): array
    {
        $this->actor($u,$s);
        $candidate=$this->c->db->execute('SELECT department_id FROM support_tickets WHERE id=?',[$id])->fetchColumn();if(!$candidate){throw new HttpError(404,'SUPPORT_NOT_FOUND');}
        $this->department($candidate);$row=$this->c->db->execute('SELECT * FROM support_tickets WHERE id=? FOR UPDATE',[$id])->fetch();if(!$row||$row['department_id']!==$candidate){throw new HttpError(409,'SUPPORT_VERSION_CONFLICT');}
        $this->require($u,$s,$row['customer_id'],$row['department_id'],$permission);return $row;
    }
    public function management(array $u,array $s): Principal
    {
        $a=$this->actor($u,$s);(new Authorization())->require($a,'identity.staff.manage',privileged:true,administrative:true);return $a;
    }
}
