<?php
declare(strict_types=1);
namespace Hostider\Migration\Discovery;
use Hostider\Migration\{Contract, Entity, Fact, Knowledge};

/** Metadata only: no DB, URL, pathname, credentials, raw values or operational effects. */
final class Validator
{
    public const VERSION = 'hostider.offline-discovery.v1';
    private const CATEGORIES = ['version', 'schema', 'temporal', 'password', 'custom_fields', 'files'];
    private const CHARSETS = ['UTF8MB4', 'ASCII', 'LATIN1', 'UNKNOWN'];
    private const COLLATIONS = ['UTF8MB4_BIN', 'UTF8MB4_UNICODE_CI', 'ASCII_BIN', 'LATIN1_BIN', 'UNKNOWN'];
    private const TYPES = ['INTEGER', 'DECIMAL', 'STRING', 'TEXT', 'BINARY', 'DATETIME', 'BOOLEAN', 'UNKNOWN'];

    private static function fields(mixed $value, array $names): array
    {
        Contract::require($value instanceof \stdClass, 'P2_OBJECT');
        $a = get_object_vars($value); Contract::fields($a, $names); return $a;
    }
    private static function items(mixed $v, int $max): array
    {
        Contract::require(is_array($v) && array_is_list($v) && count($v) <= $max, 'P2_LIST'); return $v;
    }
    private static function one(mixed $v, array $options): string
    {
        Contract::require(is_string($v) && in_array($v, $options, true), 'P2_ENUM'); return $v;
    }
    private static function source(mixed $v): string
    {
        Contract::id($v); Contract::require(preg_match('/^source-[a-f0-9]{64}$/D', $v) === 1, 'P2_SOURCE'); return $v;
    }
    private static function snapshot(mixed $v): string
    {
        Contract::id($v); Contract::require(preg_match('/^snapshot-[a-f0-9]{64}$/D', $v) === 1, 'P2_SNAPSHOT'); return $v;
    }
    private static function unique(string $id, array &$seen): void
    {
        Contract::require(!isset($seen[$id]), 'P2_DUPLICATE_ID'); $seen[$id] = true;
    }
    private static function fact(mixed $v): Fact
    {
        return Fact::fromArray(self::fields($v, ['state', 'value']));
    }
    private static function knowledge(Fact $f): string
    {
        return match ($f->state) {
            Knowledge::Known => 'KNOWN_METADATA', Knowledge::Conflicting => 'INCOMPATIBLE',
            Knowledge::Unverifiable => 'UNVERIFIED', default => 'UNKNOWN',
        };
    }
    private static function bound(mixed $value, string $source, string $snapshot, array $fields): array
    {
        $a = self::fields($value, ['source_id', 'snapshot_id', ...$fields]);
        Contract::require(self::source($a['source_id']) === $source
            && self::snapshot($a['snapshot_id']) === $snapshot, 'P2_CROSS_SOURCE');
        return $a;
    }
    private static function schema(array $a): string
    {
        $schemas = self::items($a['schemas'], 8); $state = $schemas === [] ? 'UNKNOWN' : 'KNOWN_METADATA';
        $schemaIds = []; $tableIds = []; $incompatible = false;
        foreach ($schemas as $schema) {
            $s = self::fields($schema, ['id', 'tables']); self::unique(Contract::sha($s['id']), $schemaIds);
            $tables = self::items($s['tables'], 64); if ($tables === []) { $state = 'UNKNOWN'; }
            foreach ($tables as $table) {
                $t = self::fields($table, ['id', 'charset', 'collation', 'columns', 'indexes']);
                self::unique(Contract::sha($t['id']), $tableIds);
                $charset = self::one($t['charset'], self::CHARSETS); $collation = self::one($t['collation'], self::COLLATIONS);
                if ($charset === 'UNKNOWN' || $collation === 'UNKNOWN') { $state = 'UNKNOWN'; }
                elseif (!str_starts_with($collation, $charset.'_')) { $incompatible = true; }
                $columns = self::items($t['columns'], 64); $cols = [];
                if ($columns === []) { $state = 'UNKNOWN'; }
                foreach ($columns as $column) {
                    $c = self::fields($column, ['id', 'type', 'nullable', 'charset', 'collation']);
                    $id = Contract::sha($c['id']); self::unique($id, $cols);
                    $type = self::one($c['type'], self::TYPES);
                    $cc = self::one($c['charset'], ['NONE', ...self::CHARSETS]);
                    $co = self::one($c['collation'], ['NONE', ...self::COLLATIONS]);
                    if ($type === 'UNKNOWN' || $cc === 'UNKNOWN' || $co === 'UNKNOWN') { $state = 'UNKNOWN'; }
                    if (in_array($type, ['STRING', 'TEXT'], true)) {
                        Contract::require($cc !== 'NONE' && $co !== 'NONE', 'P2_COLUMN_CHARSET');
                    } elseif ($type !== 'UNKNOWN') {
                        Contract::require($cc === 'NONE' && $co === 'NONE', 'P2_COLUMN_CHARSET');
                    }
                    if ($cc !== 'UNKNOWN' && $co !== 'UNKNOWN'
                        && !(($cc === 'NONE' && $co === 'NONE') || str_starts_with($co, $cc.'_'))) { $incompatible = true; }
                    Contract::require(is_bool($c['nullable']), 'P2_TYPE'); $cols[$id] = $c['nullable'];
                }
                $indexes = self::items($t['indexes'], 64); $ids = []; $primary = false;
                foreach ($indexes as $index) {
                    $i = self::fields($index, ['id', 'kind', 'columns']);
                    self::unique(Contract::sha($i['id']), $ids); $kind = self::one($i['kind'], ['PRIMARY', 'UNIQUE', 'INDEX']);
                    Contract::require($kind !== 'PRIMARY' || !$primary, 'P2_MULTIPLE_PRIMARY');
                    if ($kind === 'PRIMARY') { $primary = true; }
                    $refs = self::items($i['columns'], 16); Contract::require($refs !== [], 'P2_INDEX'); $seen = [];
                    foreach ($refs as $ref) {
                        $id = Contract::sha($ref); self::unique($id, $seen);
                        Contract::require(array_key_exists($id, $cols), 'P2_INDEX_REFERENCE');
                        Contract::require($kind !== 'PRIMARY' || $cols[$id] === false, 'P2_PRIMARY_NULLABLE');
                    }
                }
            }
        }
        return $incompatible ? 'INCOMPATIBLE' : $state;
    }
    private static function report(string $status, array $assessment, array $reasons, ?string $fingerprint): array
    {
        return [
            'contract' => self::VERSION, 'status' => $status, 'assessment' => $assessment,
            'reasons' => $reasons, 'metadata_fingerprint' => $fingerprint,
            'source_truth' => 'UNVERIFIED', 'operator_authority' => 'UNVERIFIED',
            'cryptographic_attestation' => 'NOT_VERIFIED', 'mapping_readiness' => 'NOT_ASSESSED',
            'G-O03' => 'OPEN', 'cutover_allowed' => false, 'effects_allowed' => false,
            'real_gate_status' => 'BLOCKED',
        ];
    }
    public function validate(
        #[\SensitiveParameter] string $json, string $expectedSource, string $expectedSnapshot,
        array $allowedCustomFields = [],
    ): array {
        try {
            self::source($expectedSource); self::snapshot($expectedSnapshot);
            $allowed = []; foreach (self::items($allowedCustomFields, 64) as $id) { self::unique(Contract::sha($id), $allowed); }
            $object = StrictJson::decode($json);
            $a = self::fields($object, ['contract', 'classification', 'encoding', 'source', 'snapshot',
                'provenance', ...self::CATEGORIES, 'uncertainties', 'content_sha256']);
            Contract::require($a['contract'] === self::VERSION && $a['encoding'] === 'UTF-8', 'P2_CONTRACT');
            $classification = self::one($a['classification'], ['SYNTHETIC', 'SANITIZED_METADATA']);
            $source = self::fields($a['source'], ['id', 'system']);
            Contract::require($source['system'] === 'WHMCS' && self::source($source['id']) === $expectedSource, 'P2_SOURCE');
            $snap = self::fields($a['snapshot'], ['id', 'source_id', 'extracted_at', 'watermark', 'schema_sha256']);
            Contract::require(self::snapshot($snap['id']) === $expectedSnapshot && self::source($snap['source_id']) === $expectedSource, 'P2_SNAPSHOT');
            Contract::timestamp($snap['extracted_at']); Contract::timestamp($snap['watermark']);
            Contract::require($snap['watermark'] <= $snap['extracted_at'], 'P2_TIME_ORDER');
            $prov = self::bound($a['provenance'], $expectedSource, $expectedSnapshot, ['kind', 'artifact_sha256', 'collected_at']);
            Contract::require($prov['kind'] === ($classification === 'SYNTHETIC' ? 'SYNTHETIC_FIXTURE' : 'OPERATOR_SUPPLIED_UNVERIFIED'), 'P2_PROVENANCE');
            Contract::sha($prov['artifact_sha256']); Contract::timestamp($prov['collected_at']);
            Contract::require($prov['collected_at'] === $snap['extracted_at'], 'P2_PROVENANCE_TIME');
            Contract::require(hash_equals(Contract::sha($snap['schema_sha256']), Contract::hash($a['schema'])), 'P2_SCHEMA_DIGEST');
            $v = self::bound($a['version'], $expectedSource, $expectedSnapshot, ['fact']); $version = self::fact($v['fact']);
            if ($version->state === Knowledge::Known) {
                Contract::require(preg_match('/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,3}$/D', $version->value ?? '') === 1, 'P2_VERSION_FORMAT');
            }
            $assessment = ['version' => $version->state === Knowledge::Known ? 'UNVERIFIED' : self::knowledge($version)];
            $assessment['schema'] = self::schema(self::bound($a['schema'], $expectedSource, $expectedSnapshot, ['schemas']));
            $t = self::bound($a['temporal'], $expectedSource, $expectedSnapshot, ['timezone', 'date_policy']);
            $zone = self::fact($t['timezone']); $policy = self::one($t['date_policy'], ['UTC', 'LOCAL_UNVERIFIED', 'UNKNOWN']);
            if ($zone->state === Knowledge::Known) {
                Contract::require(in_array($zone->value, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true), 'P2_TIMEZONE');
            }
            $assessment['temporal'] = self::knowledge($zone);
            if ($assessment['temporal'] !== 'INCOMPATIBLE' && $policy === 'UNKNOWN') { $assessment['temporal'] = 'UNKNOWN'; }
            elseif ($assessment['temporal'] !== 'INCOMPATIBLE' && $policy === 'LOCAL_UNVERIFIED') { $assessment['temporal'] = 'UNVERIFIED'; }
            elseif ($zone->state === Knowledge::Known && $zone->value !== 'UTC') { $assessment['temporal'] = 'INCOMPATIBLE'; }
            $pw = self::bound($a['password'], $expectedSource, $expectedSnapshot, ['algorithm']);
            $algorithm = self::one($pw['algorithm'], ['ARGON2ID', 'BCRYPT', 'LEGACY', 'MIXED', 'UNKNOWN', 'UNVERIFIABLE']);
            $assessment['password'] = in_array($algorithm, ['MIXED', 'UNKNOWN'], true) ? 'UNKNOWN' : 'UNVERIFIED';
            $custom = self::bound($a['custom_fields'], $expectedSource, $expectedSnapshot, ['items']);
            $assessment['custom_fields'] = 'KNOWN_METADATA'; $seen = [];
            foreach (self::items($custom['items'], 64) as $item) {
                $f = self::fields($item, ['id', 'entity', 'kind']); $id = Contract::sha($f['id']); self::unique($id, $seen);
                self::one($f['entity'], array_column(Entity::cases(), 'value')); self::one($f['kind'], ['STRING', 'INTEGER', 'BOOLEAN', 'UNKNOWN']);
                if (!isset($allowed[$id])) { $assessment['custom_fields'] = 'INCOMPATIBLE'; }
                elseif ($f['kind'] === 'UNKNOWN' && $assessment['custom_fields'] !== 'INCOMPATIBLE') { $assessment['custom_fields'] = 'UNKNOWN'; }
            }
            $files = self::bound($a['files'], $expectedSource, $expectedSnapshot, ['state', 'items']);
            $state = self::one($files['state'], ['COMPLETE', 'MISSING', 'UNKNOWN']);
            $assessment['files'] = $state === 'COMPLETE' ? 'KNOWN_METADATA' : 'UNKNOWN'; $seen = [];
            foreach (self::items($files['items'], 128) as $item) {
                $f = self::fields($item, ['id', 'entity', 'size_bytes', 'content_sha256', 'storage']);
                self::unique(Contract::sha($f['id']), $seen); Contract::sha($f['content_sha256']);
                self::one($f['entity'], ['attachment', 'document']);
                Contract::require(is_int($f['size_bytes']) && $f['size_bytes'] >= 0 && $f['size_bytes'] <= 1099511627776, 'P2_FILE_SIZE');
                if (self::one($f['storage'], ['PRIVATE', 'UNKNOWN']) === 'UNKNOWN') { $assessment['files'] = 'UNKNOWN'; }
            }
            Contract::require($state === 'COMPLETE' || $files['items'] === [], 'P2_FILE_INVENTORY_CONFLICT');
            $uncertainties = self::items($a['uncertainties'], 6); $seen = [];
            foreach ($uncertainties as $flag) {
                self::one($flag, ['VERSION_UNVERIFIED', 'SCHEMA_INCOMPLETE', 'TIMEZONE_UNKNOWN', 'PASSWORD_UNVERIFIED', 'FILES_INCOMPLETE', 'OPERATOR_AUTHORITY_UNVERIFIED']);
                self::unique($flag, $seen);
            }
            $bindings = ['VERSION_UNVERIFIED' => 'version', 'SCHEMA_INCOMPLETE' => 'schema', 'TIMEZONE_UNKNOWN' => 'temporal', 'PASSWORD_UNVERIFIED' => 'password', 'FILES_INCOMPLETE' => 'files'];
            foreach ($bindings as $flag => $category) {
                if (isset($seen[$flag]) && $assessment[$category] === 'KNOWN_METADATA') { $assessment[$category] = 'UNVERIFIED'; }
            }
            $body = clone $object; unset($body->content_sha256);
            $fingerprint = Contract::hash($body);
            Contract::require(hash_equals(Contract::sha($a['content_sha256']), $fingerprint), 'P2_FINGERPRINT');
            // These are integration prerequisites, never inferred from metadata counts/digests.
            $assessment['F6_A_identity_values'] = 'UNVERIFIED';
            $assessment['F6_B_rows_and_files'] = 'UNVERIFIED';
            $assessment['F6_C_finance_visibility_delta'] = 'UNVERIFIED';
            $blocked = in_array('INCOMPATIBLE', $assessment, true);
            return self::report($blocked ? 'BLOCKED' : 'OFFLINE_DISCOVERY_CONTRACT_VALIDATED', $assessment,
                $blocked ? ['P2_METADATA_CONFLICT'] : [], $fingerprint);
        } catch (\Throwable $e) {
            $code = preg_match('/^P2_[A-Z_]+$/D', $e->getMessage()) === 1 ? $e->getMessage() : 'P2_INVALID_METADATA';
            return self::report('BLOCKED', array_fill_keys(self::CATEGORIES, 'BLOCKED'), [$code], null);
        }
    }
    /** Caller supplies an already opened regular file handle, never a path or URL. No handle ownership transfer. */
    public function validateStream(
        #[\SensitiveParameter] mixed $handle, string $source, string $snapshot, array $allowedCustomFields = [],
    ): array {
        try {
            Contract::require(is_resource($handle) && get_resource_type($handle) === 'stream', 'P2_FILE_HANDLE');
            $meta = stream_get_meta_data($handle); $stat = fstat($handle);
            Contract::require($meta['stream_type'] === 'STDIO' && $stat !== false
                && ($stat['mode'] & 0170000) === 0100000 && ftell($handle) === 0, 'P2_FILE_HANDLE');
            Contract::require($stat['size'] > 0 && $stat['size'] <= StrictJson::MAX_BYTES, 'P2_SIZE');
            $json = stream_get_contents($handle, StrictJson::MAX_BYTES+1);
            Contract::require(is_string($json) && strlen($json) === $stat['size'] && feof($handle), 'P2_FILE_CHANGED');
            return $this->validate($json, $source, $snapshot, $allowedCustomFields);
        } catch (\Throwable) { return self::report('BLOCKED', array_fill_keys(self::CATEGORIES, 'BLOCKED'), ['P2_FILE_DENIED'], null); }
    }
}
