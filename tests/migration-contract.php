<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
require __DIR__.'/migration-fixture.php';
use Hostider\Migration\{Contract, Entity, Fact, Knowledge, MappingEvidence, SnapshotValidator, SourceEnvelope, SourceIdentity};

function migrationValidate(array $rows): Hostider\Migration\ValidationReport { return (new SnapshotValidator())->validate(migrationEnvelopes($rows)); }
function migrationReject(array $rows, string $code): void
{
    same($code, raises(InvalidArgumentException::class, fn() => migrationValidate($rows))->getMessage());
}
function migrationReview(array $rows, string $code): void
{
    $report = migrationValidate($rows); same(false, $report->eligible()); same(true, in_array($code, $report->reasons, true)); same(false, $report->jsonSerialize()['effects_allowed']);
}

test('F6-A 01 same identity fingerprint deterministic replay and input order', function(): void {
    $rows = migrationFixture(); $report = migrationValidate($rows); same(true, $report->eligible());
    same($report->jsonSerialize(), migrationValidate(array_reverse($rows))->jsonSerialize());
    $e = SourceEnvelope::fromArray($rows[0]); $mapping = MappingEvidence::propose($e, 'customer', 'target-a');
    $result = MappingEvidence::compare($e, 'customer', 'target-a', MappingEvidence::fromArray($mapping->data));
    same('NOOP_REPLAY', $result['status']); same(true, $result['replay_eligible']); same(false, $result['effects_allowed']);
    same($result, MappingEvidence::compare(SourceEnvelope::fromArray($rows[0]), 'customer', 'target-a', $mapping));
});
test('F6-A 02 same identity changed content conflicts without overwrite', function(): void {
    $row = migrationFixture()[0]; $e = SourceEnvelope::fromArray($row); $mapping = MappingEvidence::propose($e, 'customer', 'target-a');
    $row['payload']['status'] = migrationKnown('INACTIVE'); $row['content_sha256'] = Contract::hash($row['payload']);
    same('SOURCE_CONTENT_CHANGED', MappingEvidence::compare(SourceEnvelope::fromArray($row), 'customer', 'target-a', $mapping)['manual_review_reason']);
    same($e->contentHash, $mapping->data['content_sha256']);
});
test('F6-A 03 source instances are isolated even with identical IDs', function(): void {
    $a = migrationFixture(); $b = array_map(static function(array $r): array { $r['instance'] = 'synthetic-whmcs-b'; return $r; }, $a);
    same(true, migrationValidate([...$a, ...$b])->eligible());
    $left = SourceEnvelope::fromArray($a[0]); $right = SourceEnvelope::fromArray($b[0]); same(false, $left->identity->key() === $right->identity->key());
    same('SOURCE_IDENTITY_MISMATCH', MappingEvidence::compare($right, 'customer', 'target-a', MappingEvidence::propose($left, 'customer', 'target-a'))['manual_review_reason']);
});
test('F6-A 04 missing immutable source ID fails closed', function(): void {
    migrationReject(migrationChange(migrationFixture(), 'customer-a', static function(array &$r): void { $r['source_id'] = ''; }), 'IDENTITY');
});
test('F6-A 05 invalid versions entity and extra envelope fields rejected', function(): void {
    foreach (['contract' => 'v999', 'transform' => 'v999', 'classification' => 'REAL', 'entity' => 'unknown'] as $field => $value) {
        $rows = migrationFixture(); $rows[0][$field] = $value; raises(InvalidArgumentException::class, fn() => migrationValidate($rows));
    }
    $rows = migrationFixture(); $rows[0]['unexpected'] = true; migrationReject($rows, 'FIELDS');
});
test('F6-A 06 mixed snapshots schema watermark and timezone rejected', function(): void {
    foreach (['snapshot' => 'snapshot-2', 'schema_sha256' => str_repeat('a', 64), 'watermark' => '2026-10-27T09:58:00Z', 'timezone' => migrationKnown('Europe/Istanbul')] as $field => $value) {
        $rows = migrationFixture(); $rows[0][$field] = $value; migrationReject($rows, 'SNAPSHOT_MISMATCH');
    }
});
test('F6-A 07 missing parents never invented or linked across instance', function(): void {
    migrationReject(migrationChange(migrationFixture(), 'service-cpanel', static function(array &$r): void { $r['payload']['order'] = 'absent'; }), 'MISSING_RELATION');
    $rows = migrationFixture(); $rows[0]['instance'] = 'other-instance'; migrationReject($rows, 'MISSING_RELATION');
});
test('F6-A 08 duplicate external resources conflict for every provider', function(): void {
    foreach (['cpanel', 'voyar', 'rc', 'dna'] as $provider) {
        $rows = migrationFixture(); $resource = array_values(array_filter($rows, static fn(array $r): bool => $r['source_id'] === 'resource-'.$provider))[0];
        $resource['source_id'] = 'duplicate-resource'; $rows[] = $resource; migrationReject($rows, 'EXTERNAL_RESOURCE_CONFLICT');
    }
});
test('F6-A 09 customer service and provider account ownership fail closed', function(): void {
    foreach (['customer' => 'customer-b', 'account' => 'other-account', 'provider' => 'VOYAR'] as $field => $value) {
        $rows = migrationChange(migrationFixture(), 'resource-cpanel', static function(array &$r) use ($field, $value): void { $r['payload'][$field] = $value; });
        migrationReject($rows, $field === 'customer' ? 'OWNERSHIP_CONFLICT' : 'PROVIDER_ACCOUNT_CONFLICT');
    }
});
test('F6-A 10 unknown payment cannot establish PAID or allocations', function(): void {
    migrationReject(migrationChange(migrationFixture(), 'order-a', static function(array &$r): void { $r['payload']['payment_state'] = migrationKnown('PAID'); }), 'PAID_WITHOUT_EVIDENCE');
    migrationReject(migrationChange(migrationFixture(), 'payment-a', static function(array &$r): void { $r['payload']['state'] = ['state' => 'UNKNOWN', 'value' => null]; }), 'PAYMENT_UNVERIFIED');
    migrationReview(migrationChange(migrationFixture(), 'order-a', static function(array &$r): void { $r['payload']['payment_state'] = ['state' => 'UNKNOWN', 'value' => null]; }), 'VALUE_UNKNOWN');
});
test('F6-A 11 unknown or inflight resource never promoted ACTIVE', function(): void {
    $rows = migrationChange(migrationFixture(), 'resource-voyar', static function(array &$r): void { $r['payload']['status'] = ['state' => 'UNKNOWN', 'value' => null]; });
    migrationReview($rows, 'VALUE_UNKNOWN'); same(null, migrationEnvelopes($rows)[11]->payload['status']['value'] ?? null);
    migrationReview(migrationChange(migrationFixture(), 'resource-voyar', static function(array &$r): void { $r['payload']['status'] = migrationKnown('IN_FLIGHT'); }), 'PROVIDER_IN_FLIGHT');
});
test('F6-A 12 currencies never combined or silently converted', function(): void {
    migrationReject(migrationChange(migrationFixture(), 'allocation-a', static function(array &$r): void { $r['payload']['amount']['currency'] = 'USD'; }), 'CURRENCY_MISMATCH');
    raises(InvalidArgumentException::class, fn() => migrationValidate(migrationChange(migrationFixture(), 'wallet-a', static function(array &$r): void { $r['payload']['liability']['currency'] = 'ZZZ'; })));
});
test('F6-A 13 unknown historical FX remains review without current rate', function(): void {
    $rows = migrationChange(migrationFixture(), 'wallet-a', static function(array &$r): void { $r['payload']['source_currency'] = 'USD'; $r['payload']['historical_fx'] = ['state' => 'UNVERIFIABLE', 'value' => null]; });
    migrationReview($rows, 'HISTORICAL_FX_UNVERIFIED'); migrationReview($rows, 'CROSS_CURRENCY_RECONCILIATION');
});
test('F6-A 14 private note cannot become public or uncertain visibility', function(): void {
    foreach ([migrationKnown('PUBLIC'), ['state' => 'UNKNOWN', 'value' => null]] as $visibility) {
        migrationReject(migrationChange(migrationFixture(), 'private-a', static function(array &$r) use ($visibility): void { $r['payload']['visibility'] = $visibility; }), 'PRIVATE_NOTE_EXPOSURE');
    }
});
test('F6-A 15 wrong author membership or kind requires review', function(): void {
    foreach (['user-b', 'staff-a'] as $id) {
        migrationReview(migrationChange(migrationFixture(), 'public-a', static function(array &$r) use ($id): void { $r['payload']['author'] = $id; }), 'AUTHOR_REVIEW');
    }
});
test('F6-A 16 attachment missing malformed checksum rejected', function(): void {
    migrationReject(migrationChange(migrationFixture(), 'attachment-public-a', static function(array &$r): void { unset($r['payload']['sha256']); }), 'FIELDS');
    migrationReject(migrationChange(migrationFixture(), 'attachment-public-a', static function(array &$r): void { $r['payload']['sha256'] = ''; }), 'CHECKSUM');
});
test('F6-A 17 traversal absolute encoded backslash and symlink paths reviewed without reads', function(): void {
    foreach (['../outside.pdf', '/etc/private.pdf', 'C:\\private.pdf', 'safe/../../secret.pdf', '%2e%2e/secret.pdf', 'safe\\file.pdf', 'https://example.invalid/file.pdf'] as $path) {
        migrationReview(migrationChange(migrationFixture(), 'attachment-public-a', static function(array &$r) use ($path): void { $r['payload']['relative_path'] = $path; }), 'UNSAFE_ATTACHMENT_PATH');
    }
    migrationReview(migrationChange(migrationFixture(), 'attachment-public-a', static function(array &$r): void { $r['payload']['symlink'] = true; }), 'UNSAFE_ATTACHMENT_PATH');
});
test('F6-A 18 source staff cannot inherit admin MFA roles sessions or tokens', function(): void {
    foreach (['target_admin', 'target_mfa', 'roles', 'session', 'api_token', 'credential'] as $field) {
        raises(InvalidArgumentException::class, fn() => migrationValidate(migrationChange(migrationFixture(), 'staff-a', static function(array &$r) use ($field): void { $r['payload'][$field] = true; })));
    }
    migrationReject(migrationChange(migrationFixture(), 'membership-a', static function(array &$r): void { $r['payload']['user'] = 'staff-a'; }), 'STAFF_MEMBERSHIP');
});
test('F6-A 19 legacy password format rejected and reset verification mandatory', function(): void {
    migrationReject(migrationChange(migrationFixture(), 'user-a', static function(array &$r): void { $r['payload']['password_format'] = 'UNVERIFIED_LEGACY'; }), 'ENUM_VALUE');
    foreach (['reset_required', 'verification_required'] as $field) {
        migrationReject(migrationChange(migrationFixture(), 'user-a', static function(array &$r) use ($field): void { $r['payload'][$field] = false; }), 'BOOLEAN_POLICY');
    }
});
test('F6-A 20 imported CREATE forbidden all providers and pure module has no effect dependencies', function(): void {
    foreach (['cpanel', 'voyar', 'rc', 'dna'] as $provider) {
        migrationReject(migrationChange(migrationFixture(), 'resource-'.$provider, static function(array &$r): void { $r['payload']['create_allowed'] = true; }), 'BOOLEAN_POLICY');
    }
    foreach (glob(dirname(__DIR__).'/src/Migration/*.php') as $path) {
        $code = file_get_contents($path);
        same(0, preg_match('/\b(?:PDO|Database|curl_exec|file_get_contents|fopen|file_put_contents|mail|proc_open|shell_exec)\b|Hostider\\\\(?:Providers|Payments|Support|Identity|Services|Cloud|Domains)\\\\/', $code));
    }
    same(false, migrationValidate(migrationFixture())->jsonSerialize()['effects_allowed']);
});
test('F6-A 21 malformed types floats overflow UTF8 and unknown fields fail closed', function(): void {
    foreach ([1, '01', '-1', '9223372036854775808'] as $minor) {
        raises(Throwable::class, fn() => migrationValidate(migrationChange(migrationFixture(), 'allocation-a', static function(array &$r) use ($minor): void { $r['payload']['amount']['minor'] = $minor; })));
    }
    raises(InvalidArgumentException::class, fn() => Contract::hash(['minor' => 1.5]));
    raises(JsonException::class, fn() => Contract::hash(['text' => "\xFF"]));
    migrationReject(migrationChange(migrationFixture(), 'public-a', static function(array &$r): void { $r['payload']['sql'] = 'SYNTHETIC'; }), 'FIELDS');
});
test('F6-A 22 duplicate identity and stale content SHA rejected', function(): void {
    $rows = migrationFixture(); $rows[] = $rows[0]; migrationReject($rows, 'DUPLICATE_IDENTITY');
    migrationReject(migrationChange(migrationFixture(), 'customer-a', static function(array &$r): void { $r['payload']['currency'] = 'USD'; }, false), 'CONTENT_MISMATCH');
});
test('F6-A 23 knowledge states distinct and never carry guessed values', function(): void {
    foreach (['UNKNOWN', 'CONFLICTING', 'MISSING', 'UNVERIFIABLE'] as $state) {
        same($state, Fact::fromArray(['state' => $state, 'value' => null])->state->value);
        raises(InvalidArgumentException::class, fn() => Fact::fromArray(['state' => $state, 'value' => 'guessed']));
    }
    raises(InvalidArgumentException::class, fn() => Fact::fromArray(['state' => 'KNOWN', 'value' => null]));
});
test('F6-A 24 mapping target tamper metadata and snapshot conflict', function(): void {
    $row = migrationFixture()[0]; $e = SourceEnvelope::fromArray($row); $mapping = MappingEvidence::propose($e, 'customer', 'target-a');
    same('TARGET_CONFLICT', MappingEvidence::compare($e, 'customer', 'target-b', $mapping)['manual_review_reason']);
    $tampered = $mapping->data; $tampered['target_id'] = 'target-b'; same('MAPPING_EVIDENCE', raises(InvalidArgumentException::class, fn() => MappingEvidence::fromArray($tampered))->getMessage());
    $row['snapshot'] = 'snapshot-2'; $changed = MappingEvidence::compare(SourceEnvelope::fromArray($row), 'customer', 'target-a', $mapping);
    same('MANUAL_REVIEW', $changed['status']); same(false, $changed['replay_eligible']);
    raises(InvalidArgumentException::class, fn() => MappingEvidence::propose($e, 'user', 'target-a'));
});
test('F6-A 25 partial payment limits refunds and immutable provenance', function(): void {
    migrationReject(migrationChange(migrationFixture(), 'allocation-a', static function(array &$r): void { $r['payload']['amount']['minor'] = '6001'; }), 'PAYMENT_LIMIT');
    $rows = migrationFixture(); $rows[] = migrationRow('refund', 'refund-a', ['customer' => 'customer-a', 'payment' => 'payment-a', 'amount' => migrationMoney('7000'), 'evidence_sha256' => hash('sha256', 'SYNTHETIC refund')]);
    migrationReject($rows, 'PAYMENT_LIMIT');
    $rows[count($rows)-1]['payload']['amount']['minor'] = '1000'; $rows[count($rows)-1]['content_sha256'] = Contract::hash($rows[count($rows)-1]['payload']);
    migrationReview($rows, 'REFUND_RECONCILIATION_REQUIRED');
});
test('F6-A 26 original message order time visibility and attachment relations preserved', function(): void {
    migrationReject(migrationChange(migrationFixture(), 'private-a', static function(array &$r): void { $r['payload']['sequence'] = 1; }), 'MESSAGE_SEQUENCE');
    migrationReject(migrationChange(migrationFixture(), 'public-a', static function(array &$r): void { $r['payload']['created_at'] = '2026-10-25T09:00:00Z'; }), 'MESSAGE_TIME');
    migrationReject(migrationChange(migrationFixture(), 'attachment-private-a', static function(array &$r): void { $r['payload']['customer'] = 'customer-b'; }), 'OWNERSHIP_CONFLICT');
    migrationReview(migrationChange(migrationFixture(), 'public-a', static function(array &$r): void { $r['payload']['visibility'] = ['state' => 'UNKNOWN', 'value' => null]; }), 'VALUE_UNKNOWN');
});
test('F6-A 27 attachment missing corrupt MIME size and reported file evidence', function(): void {
    foreach (['MISSING', 'CORRUPT'] as $state) {
        migrationReview(migrationChange(migrationFixture(), 'attachment-public-a', static function(array &$r) use ($state): void { $r['payload']['file_state'] = migrationKnown($state); }), 'ATTACHMENT_UNAVAILABLE');
    }
    migrationReview(migrationChange(migrationFixture(), 'attachment-public-a', static function(array &$r): void { $r['payload']['observed_mime'] = 'text/html'; }), 'ATTACHMENT_MIME_REVIEW');
    migrationReject(migrationChange(migrationFixture(), 'attachment-public-a', static function(array &$r): void { $r['payload']['size'] = -1; }), 'POSITIVE_INTEGER');
});
test('F6-A 28 timezone uncertainty email conflicts and invalid timestamps explicit', function(): void {
    $rows = migrationFixture(); foreach ($rows as &$row) { $row['timezone'] = ['state' => 'UNKNOWN', 'value' => null]; } unset($row);
    migrationReview($rows, 'TIMEZONE_UNVERIFIED');
    migrationReview(migrationChange(migrationFixture(), 'user-b', static function(array &$r): void { $r['payload']['email'] = 'USER-A@synthetic.example.invalid'; }), 'EMAIL_CONFLICT');
    $rows = migrationFixture(); $rows[0]['extracted_at'] = '2026-02-30T10:00:00Z'; migrationReject($rows, 'TIMESTAMP');
});
test('F6-A 29 fully evidenced PAID remains distinct from suspended imported resource', function(): void {
    $rows = migrationFixture();
    foreach (['payment-a', 'allocation-a', 'order-a'] as $id) {
        $rows = migrationChange($rows, $id, static function(array &$r) use ($id): void {
            if ($id === 'order-a') { $r['payload']['payment_state'] = migrationKnown('PAID'); }
            else { $r['payload']['amount']['minor'] = '10000'; }
        });
    }
    same(true, migrationValidate($rows)->eligible());
    foreach ($rows as $r) { if ($r['entity'] === 'resource') { same('SUSPENDED', $r['payload']['status']['value']); same(false, $r['payload']['create_allowed']); } }
});
test('F6-A 30 published synthetic fixture and manifest match generator exactly', function(): void {
    $demo = json_decode(file_get_contents(__DIR__.'/fixtures/migration-synthetic-v1.json'), true, 64, JSON_THROW_ON_ERROR);
    same('SYNTHETIC', $demo['classification']); same(false, $demo['effects_allowed']); same(false, $demo['real_files_verified']);
    same(migrationFixture(), $demo['entities']); same(migrationValidate($demo['entities'])->jsonSerialize(), $demo['manifest']);
    foreach ($demo['mapping_proposals'] as $mapping) { MappingEvidence::fromArray($mapping['evidence']); same('PROPOSED', $mapping['status']); same(false, $mapping['effects_allowed']); }
});
test('F6-A 31 malformed knowledge payload provenance timezone and source encoding rejected', function(): void {
    foreach (['timezone' => ['state' => 'KNOWN', 'value' => 'Invented/Zone'], 'encoding' => 'ISO-8859-9', 'provenance' => [], 'uncertainty' => ['GUESSED'], 'payload' => [], 'source_id' => 123] as $field => $value) {
        $rows = migrationFixture(); $rows[0][$field] = $value; raises(InvalidArgumentException::class, fn() => migrationValidate($rows));
    }
    migrationReject(migrationChange(migrationFixture(), 'service-cpanel', static function(array &$r): void { $r['payload']['status'] = migrationKnown('INVENTED'); }), 'FACT_VALUE');
});
runTests();
