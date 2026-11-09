<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Migration\Contract;

/** Invented metadata shapes only; no actual WHMCS schema, customers, hashes or files. */
function discoveryId(string $label): string { return Contract::hash(['SYNTHETIC_DISCOVERY_ONLY', $label]); }
function discoveryFixture(): array
{
    $source = 'source-'.discoveryId('instance-a'); $snapshot = 'snapshot-'.discoveryId('snapshot-a');
    $bind = ['source_id' => $source, 'snapshot_id' => $snapshot];
    $a = [
        'contract' => 'hostider.offline-discovery.v1', 'classification' => 'SYNTHETIC', 'encoding' => 'UTF-8',
        'source' => ['id' => $source, 'system' => 'WHMCS'],
        'snapshot' => ['id' => $snapshot, 'source_id' => $source, 'extracted_at' => '2026-10-29T00:00:00Z',
            'watermark' => '2026-10-28T23:00:00Z', 'schema_sha256' => ''],
        'provenance' => [...$bind, 'kind' => 'SYNTHETIC_FIXTURE', 'artifact_sha256' => discoveryId('metadata-artifact'),
            'collected_at' => '2026-10-29T00:00:00Z'],
        // UNKNOWN deliberately avoids inventing supported real WHMCS versions.
        'version' => [...$bind, 'fact' => ['state' => 'UNKNOWN', 'value' => null]],
        'schema' => [...$bind, 'schemas' => [
            ['id' => discoveryId('synthetic-schema'), 'tables' => [
                ['id' => discoveryId('synthetic-table'), 'charset' => 'UTF8MB4', 'collation' => 'UTF8MB4_BIN',
                    'columns' => [['id' => discoveryId('synthetic-column'), 'type' => 'INTEGER', 'nullable' => false, 'charset' => 'NONE', 'collation' => 'NONE']],
                    'indexes' => [['id' => discoveryId('synthetic-index'), 'kind' => 'PRIMARY', 'columns' => [discoveryId('synthetic-column')]]]],
            ]],
        ]],
        'temporal' => [...$bind, 'timezone' => ['state' => 'KNOWN', 'value' => 'UTC'], 'date_policy' => 'UTC'],
        'password' => [...$bind, 'algorithm' => 'UNKNOWN'],
        'custom_fields' => [...$bind, 'items' => []],
        'files' => [...$bind, 'state' => 'COMPLETE', 'items' => [
            ['id' => discoveryId('synthetic-attachment'), 'entity' => 'attachment', 'size_bytes' => 0,
                'content_sha256' => discoveryId('not-actual-file-bytes'), 'storage' => 'PRIVATE'],
        ]],
        'uncertainties' => ['VERSION_UNVERIFIED', 'PASSWORD_UNVERIFIED', 'OPERATOR_AUTHORITY_UNVERIFIED'],
    ];
    return discoverySeal($a);
}
function discoverySeal(array $a): array
{
    $a['snapshot']['schema_sha256'] = Contract::hash($a['schema']);
    unset($a['content_sha256']); $a['content_sha256'] = Contract::hash($a); return $a;
}
function discoveryJson(array $a): string { return json_encode($a, JSON_THROW_ON_ERROR); }
