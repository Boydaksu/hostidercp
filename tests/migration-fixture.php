<?php
declare(strict_types=1);
use Hostider\Migration\{Contract, SourceEnvelope};

/** SYNTHETIC ONLY. No real source schema, password/hash, PII or file reads. */
function migrationKnown(string $value): array { return ['state' => 'KNOWN', 'value' => $value]; }
function migrationMoney(string $minor, string $currency = 'TRY'): array { return ['minor' => $minor, 'currency' => $currency]; }
function migrationRow(string $entity, string $id, array $payload, string $instance = 'synthetic-whmcs-a'): array
{
    return ['classification' => 'SYNTHETIC', 'contract' => Contract::VERSION, 'transform' => Contract::TRANSFORM,
        'instance' => $instance, 'entity' => $entity, 'source_id' => $id, 'schema_sha256' => hash('sha256', 'SYNTHETIC schema v1'),
        'snapshot' => 'synthetic-snapshot-1', 'extracted_at' => '2026-10-27T10:00:00Z', 'watermark' => '2026-10-27T09:59:00Z',
        'timezone' => migrationKnown('UTC'), 'encoding' => 'UTF-8',
        'provenance' => ['kind' => 'SYNTHETIC_GENERATOR', 'evidence_sha256' => hash('sha256', 'SYNTHETIC fixture generator v1')],
        'uncertainty' => [], 'payload' => $payload, 'content_sha256' => Contract::hash($payload)];
}
function migrationFixture(): array
{
    $rows = [];
    foreach (['customer-a', 'customer-b'] as $id) {
        $rows[] = migrationRow('customer', $id, ['status' => migrationKnown('ACTIVE'), 'currency' => 'TRY']);
    }
    foreach (['user-a' => 'CUSTOMER', 'user-b' => 'CUSTOMER', 'staff-a' => 'STAFF'] as $id => $kind) {
        $rows[] = migrationRow('user', $id, ['kind' => $kind, 'status' => migrationKnown('ACTIVE'), 'email' => $id.'@synthetic.example.invalid',
            'password_format' => 'NONE', 'reset_required' => true, 'verification_required' => true, 'target_admin' => false, 'target_mfa' => false]);
    }
    foreach (['a', 'b'] as $suffix) {
        $rows[] = migrationRow('membership', 'membership-'.$suffix, ['customer' => 'customer-'.$suffix, 'user' => 'user-'.$suffix, 'status' => migrationKnown('ACTIVE')]);
    }
    $rows[] = migrationRow('order', 'order-a', ['customer' => 'customer-a', 'payment_state' => migrationKnown('PARTIAL'), 'total' => migrationMoney('10000')]);
    foreach (['cpanel' => 'CPANEL', 'voyar' => 'VOYAR', 'rc' => 'RESELLERCLUB', 'dna' => 'DOMAINNAMEAPI'] as $id => $provider) {
        $rows[] = migrationRow('service', 'service-'.$id, ['customer' => 'customer-a', 'order' => 'order-a', 'status' => migrationKnown('SUSPENDED'), 'origin' => 'IMPORTED', 'provider' => $provider, 'account' => 'account-'.$id]);
        $rows[] = migrationRow('resource', 'resource-'.$id, ['customer' => 'customer-a', 'service' => 'service-'.$id, 'provider' => $provider, 'account' => 'account-'.$id,
            'external_id' => 'external-'.$id, 'status' => migrationKnown('SUSPENDED'), 'origin' => 'IMPORTED', 'create_allowed' => false]);
    }
    $rows[] = migrationRow('payment', 'payment-a', ['customer' => 'customer-a', 'amount' => migrationMoney('6000'), 'state' => migrationKnown('CONFIRMED'),
        'external_ref' => 'synthetic-payment-a', 'evidence_sha256' => hash('sha256', 'SYNTHETIC receipt'), 'source_currency' => 'TRY', 'historical_fx' => migrationKnown('1')]);
    $rows[] = migrationRow('allocation', 'allocation-a', ['customer' => 'customer-a', 'payment' => 'payment-a', 'order' => 'order-a', 'amount' => migrationMoney('4000')]);
    $rows[] = migrationRow('wallet', 'wallet-a', ['customer' => 'customer-a', 'liability' => migrationMoney('2000'), 'evidence_sha256' => hash('sha256', 'SYNTHETIC liability'), 'source_currency' => 'TRY', 'historical_fx' => migrationKnown('1')]);
    foreach (['PROFORMA', 'OFFICIAL_PDF'] as $kind) {
        $rows[] = migrationRow('document', strtolower($kind), ['customer' => 'customer-a', 'order' => 'order-a', 'kind' => $kind, 'number' => 'synthetic-'.$kind,
            'amount' => migrationMoney('10000'), 'created_at' => '2026-10-26T09:00:00Z', 'sha256' => hash('sha256', 'SYNTHETIC document '.$kind)]);
    }
    $rows[] = migrationRow('ticket', 'ticket-a', ['customer' => 'customer-a', 'requester' => 'user-a', 'created_at' => '2026-10-26T09:00:00Z']);
    foreach (['public-a' => ['CUSTOMER', 'user-a', 'PUBLIC_REPLY', 'PUBLIC', 1], 'private-a' => ['STAFF', 'staff-a', 'INTERNAL_NOTE', 'PRIVATE', 2]] as $id => $values) {
        $rows[] = migrationRow('message', $id, ['customer' => 'customer-a', 'ticket' => 'ticket-a', 'author' => $values[1], 'author_kind' => $values[0],
            'kind' => $values[2], 'visibility' => migrationKnown($values[3]), 'sequence' => $values[4], 'created_at' => '2026-10-26T09:01:00Z', 'body' => 'SYNTHETIC message '.$id]);
        $rows[] = migrationRow('attachment', 'attachment-'.$id, ['customer' => 'customer-a', 'ticket' => 'ticket-a', 'message' => $id,
            'sha256' => hash('sha256', 'SYNTHETIC manifest only '.$id), 'size' => 100, 'mime' => 'application/pdf', 'observed_mime' => 'application/pdf',
            'relative_path' => 'synthetic/'.$id.'.pdf', 'symlink' => false, 'file_state' => migrationKnown('PRESENT')]);
    }
    return $rows;
}
function migrationEnvelopes(array $rows): array { return array_map(SourceEnvelope::fromArray(...), $rows); }
function migrationChange(array $rows, string $id, Closure $change, bool $rehash = true): array
{
    foreach ($rows as &$row) {
        if ($row['source_id'] === $id) { $change($row); if ($rehash) { $row['content_sha256'] = Contract::hash($row['payload']); } }
    }
    return $rows;
}
