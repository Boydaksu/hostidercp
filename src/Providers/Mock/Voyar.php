<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\Security\EffectPolicy;
use Hostider\Providers\Contract\{ComputeProvider, Operation, ProviderResult};
final class Voyar extends MockAdapter implements ComputeProvider
{
    public function __construct(EffectPolicy $policy, string $scenario = 'SUCCESS') { parent::__construct('voyar', 'provisioning', $policy, ['quote','create','inspect','power','reinstall','terminate'], $scenario); }
    public function quote(Operation $operation): ProviderResult { return $this->action($operation, 'quote'); }
    public function create(Operation $operation): ProviderResult { return $this->action($operation, 'create'); }
    public function inspect(Operation $operation): ProviderResult { return $this->action($operation, 'inspect'); }
    public function power(Operation $operation): ProviderResult { return $this->action($operation, 'power'); }
    public function reinstall(Operation $operation): ProviderResult { return $this->action($operation, 'reinstall'); }
    public function terminate(Operation $operation): ProviderResult { return $this->action($operation, 'terminate'); }
}
