<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
/** Optional synthetic capability; this does not assert a WHM renewal endpoint exists. */
interface HostingRenewal
{
    public function renewAccount(Operation $operation): ProviderResult;
}
