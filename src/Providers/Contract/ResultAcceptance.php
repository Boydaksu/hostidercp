<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
use Hostider\Core\Config;

/** Mandatory boundary before a provider result may drive a real business state transition. */
final readonly class ResultAcceptance
{
    public function __construct(private Config $config) {}
    public function assertAcceptable(ProviderResult $result): void
    {
        if ($this->config->environment === 'production' || $result->environment !== 'mock') {
            // F0.1 has no verified live results in any environment.
            throw new \RuntimeException('Provider result not eligible for live state');
        }
    }
}
