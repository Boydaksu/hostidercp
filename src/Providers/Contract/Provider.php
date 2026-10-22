<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
interface Provider
{
    /** Capability values describe only this adapter/environment, never vendor guarantees. */
    public function capabilities(): array;
    public function execute(Operation $operation): ProviderResult;
}
