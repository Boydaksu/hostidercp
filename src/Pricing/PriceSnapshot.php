<?php
declare(strict_types=1);
namespace Hostider\Pricing;
use Hostider\Core\CanonicalJson;
final readonly class PriceSnapshot implements \JsonSerializable
{
    public function __construct(private array $data) { CanonicalJson::encode($data); }
    public function jsonSerialize(): array { return $this->data; }
    public function fingerprint(): string { return hash('sha256',CanonicalJson::encode($this->data)); }
}
