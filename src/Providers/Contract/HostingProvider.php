<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
interface HostingProvider extends Provider
{
    public function createAccount(Operation $operation): ProviderResult;
    public function inspectAccount(Operation $operation): ProviderResult;
    public function suspendAccount(Operation $operation): ProviderResult;
    public function unsuspendAccount(Operation $operation): ProviderResult;
    public function changePackage(Operation $operation): ProviderResult;
    public function terminateAccount(Operation $operation): ProviderResult;
}
