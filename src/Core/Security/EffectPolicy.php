<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Config;
final readonly class EffectPolicy
{
    public function __construct(private Config $config) {}
    public function assertAllowed(string $effect, bool $mock): void
    {
        if (!in_array($effect, ['provisioning', 'payments', 'mail'], true)) {
            throw new \LogicException('Unknown effect');
        }
        if ($mock) {
            if ($this->config->environment === 'production') {
                throw new \RuntimeException('Mock effects forbidden in production');
            }
            return;
        }
        if ($this->config->environment !== 'production' || !$this->config->{$effect}) {
            throw new \RuntimeException('Live effect denied');
        }
        // F0 has no verified live adapter, even when production flags are enabled.
        throw new \RuntimeException('Provider validation gate is open');
    }
}
