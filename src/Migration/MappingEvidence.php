<?php
declare(strict_types=1);
namespace Hostider\Migration;

/** Immutable in-memory evidence proposal; not a persistent mapping record. */
final readonly class MappingEvidence implements \JsonSerializable
{
    private function __construct(public array $data) {}

    public static function propose(SourceEnvelope $source, string $targetType, string $targetId): self
    {
        Contract::require($targetType === $source->identity->entity->value, 'TARGET_TYPE'); Contract::id($targetId);
        $data = ['classification' => 'SYNTHETIC', 'contract' => Contract::VERSION, 'identity' => $source->identity->key(),
            'source_instance' => $source->identity->instance, 'source_entity' => $source->identity->entity->value, 'source_id' => $source->identity->id,
            'snapshot' => $source->metadata['snapshot'], 'schema_sha256' => $source->metadata['schema_sha256'],
            'transform' => $source->metadata['transform'], 'content_sha256' => $source->contentHash,
            'envelope_sha256' => $source->fingerprint, 'target_type' => $targetType, 'target_id' => $targetId];
        $data['evidence_sha256'] = Contract::hash($data);
        return new self($data);
    }

    public static function fromArray(array $data): self
    {
        Contract::fields($data, ['classification', 'contract', 'identity', 'source_instance', 'source_entity', 'source_id', 'snapshot', 'schema_sha256', 'transform', 'content_sha256', 'envelope_sha256', 'target_type', 'target_id', 'evidence_sha256']);
        Contract::require($data['classification'] === 'SYNTHETIC' && $data['contract'] === Contract::VERSION && $data['transform'] === Contract::TRANSFORM, 'MAPPING_VERSION');
        foreach (['identity', 'schema_sha256', 'content_sha256', 'envelope_sha256', 'evidence_sha256'] as $field) { Contract::sha($data[$field]); }
        Contract::id($data['snapshot']); Contract::id($data['target_id']);
        Contract::require(is_string($data['target_type']) && Entity::tryFrom($data['target_type']) !== null, 'TARGET_TYPE');
        Contract::require($data['source_entity'] === $data['target_type'], 'TARGET_TYPE');
        $identity = new SourceIdentity(Contract::id($data['source_instance']), Entity::from($data['source_entity']), Contract::id($data['source_id']));
        Contract::require(hash_equals($identity->key(), $data['identity']), 'MAPPING_IDENTITY');
        $unsigned = $data; unset($unsigned['evidence_sha256']);
        Contract::require(hash_equals($data['evidence_sha256'], Contract::hash($unsigned)), 'MAPPING_EVIDENCE');
        return new self($data);
    }

    public static function compare(SourceEnvelope $source, string $targetType, string $targetId, ?self $existing): array
    {
        $proposal = self::propose($source, $targetType, $targetId);
        $status = 'PROPOSED'; $reason = null;
        if ($existing !== null) {
            if ($existing->data['identity'] !== $proposal->data['identity']) { $status = 'CONFLICT'; $reason = 'SOURCE_IDENTITY_MISMATCH'; }
            elseif ($existing->data['content_sha256'] !== $proposal->data['content_sha256']) { $status = 'CONFLICT'; $reason = 'SOURCE_CONTENT_CHANGED'; }
            elseif ($existing->data['target_type'] !== $targetType || $existing->data['target_id'] !== $targetId) { $status = 'CONFLICT'; $reason = 'TARGET_CONFLICT'; }
            elseif ($existing->data['evidence_sha256'] !== $proposal->data['evidence_sha256']) { $status = 'CONFLICT'; $reason = 'SOURCE_CONTEXT_CHANGED'; }
            else { $status = 'NOOP_REPLAY'; }
        }
        return ['status' => $status, 'replay_eligible' => $status === 'NOOP_REPLAY', 'manual_review_reason' => $reason,
            'evidence' => $proposal->data, 'effects_allowed' => false];
    }

    public function jsonSerialize(): array { return $this->data; }
}
