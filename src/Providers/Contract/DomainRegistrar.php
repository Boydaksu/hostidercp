<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
interface DomainRegistrar extends Provider
{
    public function availability(Operation $operation): ProviderResult;
    public function register(Operation $operation): ProviderResult;
    public function renew(Operation $operation): ProviderResult;
    public function initiateTransfer(Operation $operation): ProviderResult;
    public function status(Operation $operation): ProviderResult;
}
