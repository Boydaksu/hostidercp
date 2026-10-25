<?php
declare(strict_types=1);
namespace Hostider\ProviderPanel;
use Hostider\Commerce\Services;
/** Allowlisted native evidence preview; never decrypts or returns a provider DTO itself. */
final readonly class Evidence
{
    public function __construct(private Services $c){}
    public function read(array $u,array $s,string $customer,string $service,string $operation,string $evidence): array
    {
        $access=new Access($this->c);$a=$this->c->identity->principal($u,$s);$access->staff($a);
        $v=$access->service($u,$s,$customer,$service,'services.reconcile',true);$access->service($u,$s,$customer,$service,'services.manage',true);
        Access::id($operation);Access::id($evidence);(new Commands($this->c))->operation($v['product_type'],$operation,$service,$customer);
        return $v['product_type']==='DOMAIN'
            ?(new \Hostider\Domains\DomainReconciliation($this->c->db,$this->c->config))->summary($operation,$evidence,$a)
            :(new \Hostider\Cloud\Engine($this->c->db,$this->c->config))->summary($operation,$evidence,$a);
    }
}
