<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
interface ComputeProvider extends Provider
{
    public function quote(Operation $operation): ProviderResult;
    public function create(Operation $operation): ProviderResult;
    public function inspect(Operation $operation): ProviderResult;
    public function power(Operation $operation): ProviderResult;
    public function reinstall(Operation $operation): ProviderResult;
    public function terminate(Operation $operation): ProviderResult;
}
