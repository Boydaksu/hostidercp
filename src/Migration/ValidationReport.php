<?php
declare(strict_types=1);
namespace Hostider\Migration;

final readonly class ValidationReport implements \JsonSerializable
{
    public array $reasons;
    public function __construct(array $reasons, public int $entities, public string $snapshotFingerprint)
    {
        $codes = array_values(array_unique($reasons)); sort($codes, SORT_STRING); $this->reasons = $codes;
    }
    public function eligible(): bool { return $this->reasons === []; }
    public function jsonSerialize(): array
    {
        return ['classification' => 'SYNTHETIC', 'contract' => Contract::VERSION, 'entities' => $this->entities,
            'snapshot_fingerprint' => $this->snapshotFingerprint, 'status' => $this->eligible() ? 'VALID_SYNTHETIC_CONTRACT' : 'MANUAL_REVIEW',
            'reasons' => $this->reasons, 'effects_allowed' => false];
    }
}
