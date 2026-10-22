<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\Security\EffectPolicy;
use Hostider\Providers\Contract\Operation;
use Hostider\Providers\Contract\Provider;
use Hostider\Providers\Contract\ProviderResult;
abstract class MockAdapter implements Provider
{
    public int $calls = 0;
    private array $results = [];
    public function __construct(private readonly string $name, private readonly string $effect,
        private readonly EffectPolicy $policy, private readonly array $actions,
        private readonly string $scenario = 'SUCCESS')
    {
        if (!in_array($scenario, ['SUCCESS', 'DEFINITIVE_FAILURE', 'UNKNOWN', 'UNSUPPORTED'], true)) {
            throw new \InvalidArgumentException('Invalid fixture scenario');
        }
    }
    public function capabilities(): array
    {
        return ['provider' => $this->name, 'environment' => 'mock', 'liveValidated' => false,
            'actions' => $this->actions, 'supportsVendorIdempotency' => 'UNKNOWN'];
    }
    final public function execute(Operation $operation): ProviderResult
    {
        $this->policy->assertAllowed($this->effect, true);
        if (!in_array($operation->action, $this->actions, true)) {
            return new ProviderResult($operation->id, $this->name, 'UNSUPPORTED', null, 'UNSUPPORTED_ACTION');
        }
        $fingerprint = \Hostider\Core\Idempotency::fingerprint([$operation->action, $operation->resourceId, $operation->configuration]);
        if (isset($this->results[$operation->id])) {
            if ($this->results[$operation->id][0] !== $fingerprint) {
                throw new \LogicException('Mock operation conflict');
            }
            return $this->results[$operation->id][1];
        }
        ++$this->calls;
        $result = new ProviderResult($operation->id, $this->name, $this->scenario,
            $this->scenario === 'SUCCESS' ? 'mock-' . $operation->id : null,
            $this->scenario === 'UNKNOWN' ? 'MOCK_TIMEOUT' : null);
        $this->results[$operation->id] = [$fingerprint, $result];
        return $result;
    }
    protected function action(Operation $op, string $action): ProviderResult
    {
        if ($op->action !== $action) {
            throw new \InvalidArgumentException('Operation action mismatch');
        }
        return $this->execute($op);
    }
}
