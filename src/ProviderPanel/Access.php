<?php
declare(strict_types=1);
namespace Hostider\ProviderPanel;
use Hostider\Commerce\Services;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
/** Native Identity ownership and grants precede provider-specific identifiers. */
final readonly class Access
{
    public function __construct(public Services $c){}
    public static function id(mixed $id): string
    {
        if(!is_string($id)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$id)){throw new HttpError(422,'INVALID_PANEL_IDENTIFIER');}return $id;
    }
    public function customer(array $u,array $s,mixed $requested): ?string
    {
        $a=$this->c->identity->principal($u,$s);if($requested!==null){self::id($requested);}
        if(!$a->staff||$a->impersonating){if($requested!==null&&$requested!==$a->customerId){throw new HttpError(403,'CUSTOMER_OVERRIDE_FORBIDDEN');}return $a->customerId;}return $requested;
    }
    public function service(array $u,array $s,string $customer,string $id,string $permission='services.view',bool $critical=false): array
    {
        self::id($id);$a=$this->c->identity->authorize($u,$s,$permission,$customer,$id,privileged:$critical);
        if($a->impersonating){throw new HttpError(403,'PANEL_IMPERSONATION_FORBIDDEN');}
        $row=$this->c->db->execute('SELECT s.*,p.product_type FROM svc_services s JOIN catalog_products p ON p.id=s.product_id WHERE s.id=? AND s.customer_id=?',[$id,$customer])->fetch();
        if(!$row||!in_array($row['product_type'],['DOMAIN','CLOUD'],true)){throw new HttpError(404,'PROVIDER_SERVICE_NOT_FOUND');}return $row;
    }
    public function staff(Principal $a): void{if(!$a->staff||$a->impersonating){throw new HttpError(403,'STAFF_REQUIRED');}}
}
