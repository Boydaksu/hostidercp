<?php
declare(strict_types=1);
namespace Hostider\Migration\Discovery;
use Hostider\Core\CanonicalJson;
use Hostider\Migration\Contract;

/** Schema-only local export transformation. No DB, SQL, network, path opening or key storage. */
final class InventoryAdapter
{
    public const EXPORT_VERSION = 'hostider.relational-inventory.v1';
    private static function fields(#[\SensitiveParameter] mixed $v, array $keys): array
    {
        Contract::require($v instanceof \stdClass, 'P3_OBJECT');
        $a = get_object_vars($v); Contract::fields($a, $keys); return $a;
    }
    private static function bound(#[\SensitiveParameter] mixed $v, array $keys, array $context): array
    {
        $a = self::fields($v, ['source_id', 'snapshot_id', ...$keys]);
        Contract::require($a['source_id'] === $context[0] && $a['snapshot_id'] === $context[1], 'P3_CONTEXT_MISMATCH');
        return $a;
    }
    private static function items(#[\SensitiveParameter] mixed $v, int $max): array
    {
        Contract::require(is_array($v) && array_is_list($v) && count($v) <= $max, 'P3_LIST'); return $v;
    }
    private static function inventory(#[\SensitiveParameter] mixed $state, array $items): bool
    {
        Contract::require(in_array($state, ['KNOWN', 'UNKNOWN', 'MISSING'], true), 'P3_INVENTORY_STATE');
        Contract::require($state === 'KNOWN' || $items === [], 'P3_INVENTORY_CONFLICT');
        return $state === 'KNOWN';
    }
    private static function name(#[\SensitiveParameter] mixed $v): string
    {
        Contract::require(is_string($v) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $v) === 1, 'P3_IDENTIFIER');
        return $v;
    }
    private static function unique(#[\SensitiveParameter] string $name, array &$seen): void
    {
        $fold = strtolower($name); Contract::require(!isset($seen[$fold]), 'P3_DUPLICATE_IDENTIFIER'); $seen[$fold] = true;
    }
    private static function opaque(string $kind, #[\SensitiveParameter] array $path, array $context, #[\SensitiveParameter] string $key): string
    {
        return hash_hmac('sha256', CanonicalJson::encode(['hostider.inventory-id.v1', ...$context, $kind, $path]), $key);
    }
    private static function sorted(array $items): array
    {
        usort($items, static fn(array $a, array $b): int => strcmp($a['id'], $b['id'])); return $items;
    }
    private static function read(#[\SensitiveParameter] mixed $handle, string $expectedSha): string
    {
        Contract::require(is_resource($handle) && get_resource_type($handle) === 'stream', 'P3_HANDLE');
        $meta = stream_get_meta_data($handle); $before = fstat($handle);
        Contract::require($meta['wrapper_type'] === 'plainfile' && $meta['stream_type'] === 'STDIO'
            && $before !== false && ($before['mode'] & 0170000) === 0100000 && ftell($handle) === 0, 'P3_HANDLE');
        Contract::require($before['size'] > 0 && $before['size'] <= StrictJson::MAX_BYTES, 'P3_SIZE');
        $json = stream_get_contents($handle, StrictJson::MAX_BYTES+1); $after = fstat($handle);
        Contract::require(is_string($json) && strlen($json) === $before['size'] && feof($handle)
            && $after !== false, 'P3_FILE_CHANGED');
        foreach (['dev', 'ino', 'size', 'mtime', 'ctime'] as $field) {
            Contract::require($before[$field] === $after[$field], 'P3_FILE_CHANGED');
        }
        Contract::require(hash_equals($expectedSha, hash('sha256', $json)), 'P3_EXPORT_PIN');
        return $json;
    }
    private static function result(string $status, ?array $manifest, ?array $p2, array $coverage, array $reasons): array
    {
        return [
            'status' => $status, 'manifest' => $manifest, 'p2_report' => $p2,
            'inventory_coverage' => $coverage,
            'category_coverage' => [
                'schema' => $p2['assessment']['schema'] ?? 'BLOCKED',
                'version' => 'UNKNOWN', 'temporal' => 'UNKNOWN', 'password' => 'UNKNOWN',
                'custom_fields' => 'UNKNOWN', 'files' => 'UNKNOWN',
            ],
            'operator_review_required' => ['SOURCE_ACCURACY', 'IDENTIFIER_CASE_POLICY', 'EXPORT_COMPLETENESS',
                'INDEX_METADATA', 'VERSION', 'TIMEZONE', 'PASSWORD', 'CUSTOM_FIELDS', 'FILES',
                'CALLER_FILE_SAFETY', 'OPERATOR_AUTHORITY'],
            'reasons' => $reasons, 'G-O03' => 'OPEN', 'source_truth' => 'UNVERIFIED',
            'operator_authority' => 'UNVERIFIED', 'cutover_allowed' => false, 'effects_allowed' => false,
            'real_gate_status' => 'BLOCKED',
        ];
    }
    public function convert(
        #[\SensitiveParameter] mixed $handle, string $expectedSource, string $expectedSnapshot,
        string $expectedExportSha256, #[\SensitiveParameter] string $operatorKey,
    ): array {
        try {
            Contract::require(strlen($operatorKey) === 32, 'P3_KEY_LENGTH');
            Contract::require(preg_match('/^source-[a-f0-9]{64}$/D', $expectedSource) === 1
                && preg_match('/^snapshot-[a-f0-9]{64}$/D', $expectedSnapshot) === 1, 'P3_EXPECTED_CONTEXT');
            Contract::sha($expectedExportSha256); $context = [$expectedSource, $expectedSnapshot];
            $json = self::read($handle, $expectedExportSha256);
            $a = self::bound(StrictJson::decode($json), ['contract', 'classification', 'encoding', 'system',
                'extracted_at', 'watermark', 'schema_state', 'schemas'], $context);
            Contract::require($a['contract'] === self::EXPORT_VERSION && $a['encoding'] === 'UTF-8', 'P3_EXPORT_CONTRACT');
            Contract::require(in_array($a['classification'], ['SYNTHETIC_EXPORT', 'OPERATOR_PREPARED_EXPORT'], true), 'P3_CLASSIFICATION');
            $schemas = self::items($a['schemas'], 8);
            $known = self::inventory($a['schema_state'], $schemas);
            $coverage = array_fill_keys(['tables', 'columns', 'indexes'], $known && $schemas !== [] ? 'KNOWN_METADATA' : 'UNKNOWN');
            $out = []; $schemaSeen = [];
            foreach ($schemas as $schema) {
                $s = self::bound($schema, ['name', 'tables_state', 'tables'], $context);
                $sn = self::name($s['name']); self::unique($sn, $schemaSeen);
                $tables = self::items($s['tables'], 64); $tableSeen = []; $tableOut = [];
                if (!self::inventory($s['tables_state'], $tables) || $tables === []) {
                    $coverage = array_fill_keys(['tables', 'columns', 'indexes'], 'UNKNOWN');
                }
                foreach ($tables as $table) {
                    $t = self::bound($table, ['name', 'charset', 'collation', 'columns_state', 'columns', 'indexes_state', 'indexes'], $context);
                    $tn = self::name($t['name']); self::unique($tn, $tableSeen); $path = [$sn, $tn];
                    $columns = self::items($t['columns'], 64); $indexes = self::items($t['indexes'], 64);
                    if (!self::inventory($t['columns_state'], $columns) || $columns === []) { $coverage['columns'] = 'UNKNOWN'; }
                    if (!self::inventory($t['indexes_state'], $indexes)) { $coverage['indexes'] = 'UNKNOWN'; }
                    $colOut = []; $colSeen = []; $map = [];
                    foreach ($columns as $column) {
                        $c = self::bound($column, ['name', 'type', 'nullable', 'charset', 'collation'], $context);
                        $cn = self::name($c['name']); self::unique($cn, $colSeen);
                        $id = self::opaque('column', [...$path, $cn], $context, $operatorKey); $map[$cn] = $id;
                        $colOut[] = ['id' => $id, 'type' => $c['type'], 'nullable' => $c['nullable'], 'charset' => $c['charset'], 'collation' => $c['collation']];
                    }
                    $indexOut = []; $indexSeen = [];
                    foreach ($indexes as $index) {
                        $i = self::bound($index, ['name', 'kind', 'columns'], $context);
                        $in = self::name($i['name']); self::unique($in, $indexSeen); $refs = [];
                        foreach (self::items($i['columns'], 16) as $ref) {
                            $cn = self::name($ref);
                            Contract::require(isset($map[$cn]), 'P3_INDEX_REFERENCE'); $refs[] = $map[$cn];
                        }
                        $indexOut[] = ['id' => self::opaque('index', [...$path, $in], $context, $operatorKey), 'kind' => $i['kind'], 'columns' => $refs];
                    }
                    $tableOut[] = ['id' => self::opaque('table', $path, $context, $operatorKey), 'charset' => $t['charset'],
                        'collation' => $t['collation'], 'columns' => self::sorted($colOut), 'indexes' => self::sorted($indexOut)];
                }
                $out[] = ['id' => self::opaque('schema', [$sn], $context, $operatorKey), 'tables' => self::sorted($tableOut)];
            }
            $bind = ['source_id' => $expectedSource, 'snapshot_id' => $expectedSnapshot];
            $schema = [...$bind, 'schemas' => self::sorted($out)];
            $synthetic = $a['classification'] === 'SYNTHETIC_EXPORT';
            $unknown = ['state' => 'UNKNOWN', 'value' => null];
            $uncertainties = ['VERSION_UNVERIFIED', 'TIMEZONE_UNKNOWN', 'PASSWORD_UNVERIFIED', 'FILES_INCOMPLETE', 'OPERATOR_AUTHORITY_UNVERIFIED'];
            if (in_array('UNKNOWN', $coverage, true)) { $uncertainties[] = 'SCHEMA_INCOMPLETE'; }
            $manifest = [
                'contract' => Validator::VERSION, 'classification' => $synthetic ? 'SYNTHETIC' : 'SANITIZED_METADATA',
                'encoding' => 'UTF-8', 'source' => ['id' => $expectedSource, 'system' => $a['system']],
                'snapshot' => ['id' => $expectedSnapshot, 'source_id' => $expectedSource, 'extracted_at' => $a['extracted_at'],
                    'watermark' => $a['watermark'], 'schema_sha256' => Contract::hash($schema)],
                'provenance' => [...$bind, 'kind' => $synthetic ? 'SYNTHETIC_FIXTURE' : 'OPERATOR_SUPPLIED_UNVERIFIED',
                    'artifact_sha256' => self::opaque('export-provenance', [$expectedExportSha256], $context, $operatorKey), 'collected_at' => $a['extracted_at']],
                'version' => [...$bind, 'fact' => $unknown], 'schema' => $schema,
                'temporal' => [...$bind, 'timezone' => $unknown, 'date_policy' => 'UNKNOWN'],
                'password' => [...$bind, 'algorithm' => 'UNKNOWN'], 'custom_fields' => [...$bind, 'items' => []],
                'files' => [...$bind, 'state' => 'MISSING', 'items' => []], 'uncertainties' => $uncertainties,
            ];
            $manifest['content_sha256'] = Contract::hash($manifest);
            // This is the sole metadata semantics validator; raw input is never returned on failure.
            $p2 = (new Validator())->validate(CanonicalJson::encode($manifest), $expectedSource, $expectedSnapshot);
            if ($p2['status'] !== 'OFFLINE_DISCOVERY_CONTRACT_VALIDATED') {
                return self::result('BLOCKED', null, $p2, $coverage, ['P3_P2_BLOCKED']);
            }
            return self::result('OFFLINE_INVENTORY_ADAPTED', $manifest, $p2, $coverage, []);
        } catch (\Throwable $e) {
            $code = preg_match('/^P3_[A-Z_]+$/D', $e->getMessage()) === 1 ? $e->getMessage() : 'P3_INVALID_EXPORT';
            return self::result('BLOCKED', null, null, array_fill_keys(['tables', 'columns', 'indexes'], 'BLOCKED'), [$code]);
        }
    }
}
