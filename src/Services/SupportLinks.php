<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Commerce\Services;
/** Read-only application boundary; support cannot mutate service or commerce state. */
final readonly class SupportLinks
{
    public function __construct(private Services $c){}
    public function owned(array $u,array $s,string $customer,string $id): string
    {
        $this->c->identity->authorize($u,$s,'services.view',$customer,$id);
        return (new Repository($this->c->db,$this->c->config))->find($id,$customer)['id'];
    }
}
