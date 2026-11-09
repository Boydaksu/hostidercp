<?php
declare(strict_types=1);
namespace Hostider\Migration\Discovery;
use Hostider\Core\CanonicalJson;
use Hostider\Migration\Contract;

final class Collector
{
    public static function blocked(string $reason): array
    {
        return ['status' => 'BLOCKED', 'reasons' => [$reason], ...self::gates()];
    }
    private static function gates(): array
    {
        return ['G-O03' => 'OPEN', 'source_truth' => 'UNVERIFIED', 'operator_authority' => 'UNVERIFIED',
            'cutover_allowed' => false, 'effects_allowed' => false, 'real_gate_status' => 'BLOCKED'];
    }
    private static function name(#[\SensitiveParameter] mixed $v): string
    {
        Contract::require(is_string($v) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $v) === 1, 'GO_IDENTIFIER');
        return $v;
    }
    private static function capture(MetadataSession $s, #[\SensitiveParameter] string $schema): array
    {
        $out = ['schema' => $s->read('schema', ['schema' => $schema]), 'tables' => []];
        foreach ($s->read('tables', ['schema' => $schema]) as $t) {
            Contract::require($t['TABLE_TYPE'] === 'BASE TABLE', 'GO_UNSUPPORTED_TABLE_KIND');
            $name = self::name($t['TABLE_NAME']);
            $t['columns'] = $s->read('columns', ['schema' => $schema, 'table' => $name]);
            $t['indexes'] = $s->read('indexes', ['schema' => $schema, 'table' => $name]);
            Contract::require($t['columns'] !== [], 'GO_PARTIAL_METADATA'); $out['tables'][] = $t;
        }
        Contract::require($out['tables'] !== [], 'GO_EMPTY_INVENTORY'); return $out;
    }
    private static function charset(mixed $v): string
    {
        if ($v === null) { return 'NONE'; }
        return match ($v) { 'utf8mb4' => 'UTF8MB4', 'ascii' => 'ASCII', 'latin1' => 'LATIN1', default => 'UNKNOWN' };
    }
    private static function collation(mixed $v): string
    {
        if ($v === null) { return 'NONE'; }
        return match ($v) { 'utf8mb4_bin' => 'UTF8MB4_BIN', 'utf8mb4_unicode_ci' => 'UTF8MB4_UNICODE_CI',
            'ascii_bin' => 'ASCII_BIN', 'latin1_bin' => 'LATIN1_BIN', default => 'UNKNOWN' };
    }
    private static function export(#[\SensitiveParameter] array $raw, string $source, string $snapshot, string $at): array
    {
        Contract::require(count($raw['schema']) === 1, 'GO_SCHEMA_UNAVAILABLE');
        $schema = self::name($raw['schema'][0]['SCHEMA_NAME']); $b = ['source_id' => $source, 'snapshot_id' => $snapshot];
        $tables = [];
        foreach ($raw['tables'] as $t) {
            Contract::require($t['columns'] !== [] && count($t['columns']) <= 64, 'GO_PARTIAL_METADATA');
            $columns = []; $indexes = []; $position = 0;
            foreach ($t['columns'] as $c) {
                Contract::require((string)$c['ORDINAL_POSITION'] === (string)++$position && in_array($c['IS_NULLABLE'], ['YES', 'NO'], true), 'GO_COLUMN_ORDER');
                $type = match ($c['DATA_TYPE']) {
                    'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => 'INTEGER',
                    'decimal' => 'DECIMAL', 'char', 'varchar' => 'STRING',
                    'tinytext', 'text', 'mediumtext', 'longtext' => 'TEXT',
                    'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob' => 'BINARY',
                    'date', 'datetime', 'timestamp' => 'DATETIME', default => 'UNKNOWN',
                };
                $columns[] = [...$b, 'name' => self::name($c['COLUMN_NAME']), 'type' => $type,
                    'nullable' => $c['IS_NULLABLE'] === 'YES', 'charset' => self::charset($c['CHARACTER_SET_NAME']), 'collation' => self::collation($c['COLLATION_NAME'])];
            }
            foreach ($t['indexes'] as $i) {
                Contract::require($i['SUB_PART'] === null && $i['INDEX_TYPE'] === 'BTREE' && $i['COLLATION'] === 'A'
                    && $i['IGNORED'] === 'NO' && in_array((string)$i['NON_UNIQUE'], ['0', '1'], true), 'GO_UNSUPPORTED_INDEX');
                $name = self::name($i['INDEX_NAME']); $kind = $name === 'PRIMARY' ? 'PRIMARY' : ((string)$i['NON_UNIQUE'] === '0' ? 'UNIQUE' : 'INDEX');
                $indexes[$name] ??= [...$b, 'name' => $name, 'kind' => $kind, 'columns' => []];
                Contract::require($indexes[$name]['kind'] === $kind && (string)$i['SEQ_IN_INDEX'] === (string)(count($indexes[$name]['columns'])+1), 'GO_INDEX_ORDER');
                $indexes[$name]['columns'][] = self::name($i['COLUMN_NAME']);
            }
            $collation = self::collation($t['TABLE_COLLATION']);
            $charset = match ($collation) { 'UTF8MB4_BIN', 'UTF8MB4_UNICODE_CI' => 'UTF8MB4', 'ASCII_BIN' => 'ASCII', 'LATIN1_BIN' => 'LATIN1', default => 'UNKNOWN' };
            $tables[] = [...$b, 'name' => self::name($t['TABLE_NAME']), 'charset' => $charset, 'collation' => $collation,
                'columns_state' => 'KNOWN', 'columns' => $columns, 'indexes_state' => 'KNOWN', 'indexes' => array_values($indexes)];
        }
        return [...$b, 'contract' => InventoryAdapter::EXPORT_VERSION, 'classification' => 'OPERATOR_PREPARED_EXPORT',
            'encoding' => 'UTF-8', 'system' => 'WHMCS', 'extracted_at' => $at, 'watermark' => $at, 'schema_state' => 'KNOWN',
            'schemas' => [[...$b, 'name' => $schema, 'tables_state' => 'KNOWN', 'tables' => $tables]]];
    }
    private static function consistent(array $before, array $after, #[\SensitiveParameter] array $a, #[\SensitiveParameter] array $b): void
    { Contract::require($before === $after && hash_equals(Contract::hash($a), Contract::hash($b)), 'GO_METADATA_CHANGED'); }
    public function collect(
        MetadataSession $s, #[\SensitiveParameter] string $schema, string $source, string $snapshot,
        string $privateDirectory, #[\SensitiveParameter] string $key,
    ): array {
        try {
            PrivateExport::assertRoot($privateDirectory);
            Contract::require(preg_match('/^source-[a-f0-9]{64}$/D', $source) === 1
                && preg_match('/^snapshot-[a-f0-9]{64}$/D', $snapshot) === 1 && strlen($key) === 32, 'GO_CONTEXT');
            $at = gmdate('Y-m-d\TH:i:s\Z'); $before = $s->fence($schema); $a = self::capture($s, $schema);
            $b = self::capture($s, $schema); $after = $s->fence($schema);
            self::consistent($before, $after, $a, $b);
            $json = CanonicalJson::encode(self::export($a, $source, $snapshot, $at));
            $p3 = PrivateExport::adapt($privateDirectory, $json, $source, $snapshot, hash('sha256', $json), $key);
            Contract::require($p3['status'] === 'OFFLINE_INVENTORY_ADAPTED', 'GO_ADAPTER_BLOCKED');
            $m = $p3['manifest']; $tables = $m['schema']['schemas'][0]['tables']; $columns = 0; $indexes = 0;
            foreach ($tables as $t) { $columns += count($t['columns']); $indexes += count($t['indexes']); }
            $complete = $p3['category_coverage']['schema'] === 'KNOWN_METADATA';
            return [
                'status' => $complete ? 'METADATA_CAPTURE_COMPLETE_WITHIN_LIMITS' : 'METADATA_CAPTURE_PARTIAL',
                'source_fingerprint' => hash_hmac('sha256', CanonicalJson::encode(['go03.source.v1', $source, $schema]), $key),
                'snapshot_fingerprint' => hash_hmac('sha256', CanonicalJson::encode(['go03.snapshot.v1', $source, $snapshot]), $key),
                'metadata_fingerprint' => $p3['p2_report']['metadata_fingerprint'], 'captured_at' => $at,
                'inventory' => ['schemas' => 1, 'tables' => count($tables), 'columns' => $columns, 'indexes' => $indexes],
                'observed_consistency' => 'MATCHED_TWO_READS', 'global_snapshot_atomicity' => 'NOT_VERIFIED',
                'index_column_integrity' => 'VALIDATED_BY_P2_P3', 'metadata_compatibility' => $p3['category_coverage'],
                'p2_status' => $p3['p2_report']['status'], 'p3_status' => $p3['status'], 'query_count' => $s->count(),
                'missing_information' => ['WHMCS_APPLICATION_VERSION', 'CUSTOM_FIELDS', 'PASSWORD_ALGORITHM',
                    'ATTACHMENT_LOCATIONS', 'FILE_INVENTORY', 'TYPE_PRECISION_AND_DEFAULTS', 'SOURCE_ROW_INTEGRITY', 'OPERATOR_AUTHORITY'],
                'privilege_scope' => 'OBSERVED_DEDICATED_SCHEMA_SELECT_ONLY_NO_VISIBLE_ROLES_OR_ROUTINES',
                'reasons' => [], ...self::gates(),
            ];
        } catch (\Throwable $e) {
            return self::blocked(preg_match('/^GO_[A-Z_]+$/D', $e->getMessage()) === 1 ? $e->getMessage() : 'GO_DISCOVERY_UNAVAILABLE');
        }
    }
}
