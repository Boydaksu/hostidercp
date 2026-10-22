<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\Security\EffectPolicy;
use Hostider\Providers\Contract\{HostingProvider, Operation, ProviderResult};
final class Cpanel extends MockAdapter implements HostingProvider
{
    public function __construct(EffectPolicy $policy, string $scenario = 'SUCCESS') { parent::__construct('cpanel', 'provisioning', $policy, ['createAccount','inspectAccount','suspendAccount','unsuspendAccount','changePackage','terminateAccount'], $scenario); }
    public function createAccount(Operation $operation): ProviderResult { return $this->action($operation, 'createAccount'); }
    public function inspectAccount(Operation $operation): ProviderResult { return $this->action($operation, 'inspectAccount'); }
    public function suspendAccount(Operation $operation): ProviderResult { return $this->action($operation, 'suspendAccount'); }
    public function unsuspendAccount(Operation $operation): ProviderResult { return $this->action($operation, 'unsuspendAccount'); }
    public function changePackage(Operation $operation): ProviderResult { return $this->action($operation, 'changePackage'); }
    public function terminateAccount(Operation $operation): ProviderResult { return $this->action($operation, 'terminateAccount'); }
}
