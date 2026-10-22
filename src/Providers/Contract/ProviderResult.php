<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
final readonly class ProviderResult implements \JsonSerializable
{
    public function __construct(public string $operationId, public string $provider, public string $status,
        public ?string $externalResourceId = null, public ?string $safeErrorCode = null)
    {
        new Operation($operationId, 'result');
        if (!in_array($provider, ['paytr', 'cpanel', 'resellerclub', 'domainnameapi', 'voyar'], true)
            || !in_array($status, ['SUCCESS', 'DEFINITIVE_FAILURE', 'UNKNOWN', 'UNSUPPORTED'], true)
            || ($safeErrorCode !== null && !preg_match('/^[A-Z0-9_]{1,64}$/D', $safeErrorCode))) {
            throw new \InvalidArgumentException('Invalid normalized provider result');
        }
    }
    public function jsonSerialize(): array
    {
        return ['operationId' => $this->operationId, 'provider' => $this->provider, 'status' => $this->status,
            'externalResourceId' => $this->externalResourceId, 'safeErrorCode' => $this->safeErrorCode,
            'recordedAt' => gmdate('Y-m-d\TH:i:s\Z')];
    }
}
