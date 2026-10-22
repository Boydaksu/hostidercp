<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\Security\EffectPolicy;
use Hostider\Providers\Contract\{DomainRegistrar, Operation, ProviderResult};
abstract class Registrar extends MockAdapter implements DomainRegistrar
{
    public function __construct(string $provider, EffectPolicy $policy, string $scenario = 'SUCCESS') { parent::__construct($provider, 'provisioning', $policy, ['availability','register','renew','initiateTransfer','status'], $scenario); }
    public function availability(Operation $operation): ProviderResult { return $this->action($operation, 'availability'); }
    public function register(Operation $operation): ProviderResult { return $this->action($operation, 'register'); }
    public function renew(Operation $operation): ProviderResult { return $this->action($operation, 'renew'); }
    public function initiateTransfer(Operation $operation): ProviderResult { return $this->action($operation, 'initiateTransfer'); }
    public function status(Operation $operation): ProviderResult { return $this->action($operation, 'status'); }
}
