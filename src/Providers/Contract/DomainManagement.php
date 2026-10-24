<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
/** Optional TLD capabilities; presence of the interface does not imply TLD support. */
interface DomainManagement extends DomainRegistrar
{
    public function nameservers(Operation $operation): ProviderResult;
    public function dns(Operation $operation): ProviderResult;
    public function epp(Operation $operation): ProviderResult;
    public function lock(Operation $operation): ProviderResult;
    public function privacy(Operation $operation): ProviderResult;
}
