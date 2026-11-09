<?php
declare(strict_types=1);
namespace Hostider\Migration;

final readonly class SourceEnvelope
{
    private function __construct(public SourceIdentity $identity, public array $metadata, public array $payload, public string $contentHash, public string $fingerprint) {}

    public static function fromArray(array $data): self
    {
        Contract::fields($data, ['classification', 'contract', 'transform', 'instance', 'entity', 'source_id', 'schema_sha256', 'snapshot', 'extracted_at', 'watermark', 'timezone', 'encoding', 'provenance', 'uncertainty', 'payload', 'content_sha256']);
        Contract::require($data['classification'] === 'SYNTHETIC' && $data['contract'] === Contract::VERSION && $data['transform'] === Contract::TRANSFORM, 'CONTRACT_VERSION');
        Contract::require(is_string($data['entity']), 'ENTITY');
        $entity = Entity::tryFrom($data['entity']);
        Contract::require($entity !== null, 'ENTITY');
        $identity = new SourceIdentity(Contract::id($data['instance']), $entity, Contract::id($data['source_id']));
        Contract::sha($data['schema_sha256']); Contract::id($data['snapshot']);
        Contract::timestamp($data['extracted_at']); Contract::timestamp($data['watermark']);
        Contract::require($data['watermark'] <= $data['extracted_at'], 'WATERMARK');
        Contract::require(is_array($data['timezone']), 'TIMEZONE');
        $zone = Fact::fromArray($data['timezone']);
        if ($zone->state === Knowledge::Known) { Contract::require(in_array($zone->value, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true), 'TIMEZONE'); }
        Contract::require($data['encoding'] === 'UTF-8', 'ENCODING');
        Contract::require(is_array($data['provenance']), 'PROVENANCE');
        Contract::fields($data['provenance'], ['kind', 'evidence_sha256']);
        Contract::require($data['provenance']['kind'] === 'SYNTHETIC_GENERATOR', 'PROVENANCE');
        Contract::sha($data['provenance']['evidence_sha256']);
        Contract::require(is_array($data['uncertainty']) && array_is_list($data['uncertainty']), 'UNCERTAINTY');
        foreach ($data['uncertainty'] as $flag) {
            Contract::require(is_string($flag) && in_array($flag, ['UNKNOWN', 'CONFLICTING', 'MISSING', 'UNVERIFIABLE'], true), 'UNCERTAINTY');
        }
        Contract::require(is_array($data['payload']) && !array_is_list($data['payload']), 'PAYLOAD');
        // CanonicalJson rejects floats, invalid UTF-8 and unsupported values recursively.
        $contentHash = Contract::hash($data['payload']);
        Contract::require(hash_equals(Contract::sha($data['content_sha256']), $contentHash), 'CONTENT_MISMATCH');
        $metadata = $data; unset($metadata['payload'], $metadata['content_sha256']);
        return new self($identity, $metadata, $data['payload'], $contentHash, Contract::hash($data));
    }

    public function contextHash(): string
    {
        $context = $this->metadata;
        unset($context['entity'], $context['source_id'], $context['provenance'], $context['uncertainty']);
        return Contract::hash($context);
    }
}
