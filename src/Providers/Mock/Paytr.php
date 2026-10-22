<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\Security\EffectPolicy;
use Hostider\Providers\Contract\{PaymentGateway, Operation, ProviderResult};
final class Paytr extends MockAdapter implements PaymentGateway
{
    public function __construct(EffectPolicy $policy, string $scenario = 'SUCCESS') { parent::__construct('paytr', 'payments', $policy, ['createAttempt','refund'], $scenario); }
    public function createAttempt(Operation $operation): ProviderResult { return $this->action($operation, 'createAttempt'); }
    public function refund(Operation $operation): ProviderResult { return $this->action($operation, 'refund'); }
    public function verifyCallback(array $payload): never { throw new \LogicException('Mock cannot verify real callbacks; G-P02 open'); }
}
