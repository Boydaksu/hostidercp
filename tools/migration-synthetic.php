<?php
declare(strict_types=1);
// Development-only demonstration. Accepts no source path/DB/URL or credentials.
require dirname(__DIR__).'/bootstrap/autoload.php';
require dirname(__DIR__).'/tests/migration-fixture.php';
use Hostider\Migration\{MappingEvidence, SnapshotValidator};
$rows = migrationFixture(); $envelopes = migrationEnvelopes($rows);
$report = (new SnapshotValidator())->validate($envelopes);
$mappings = [];
foreach ($envelopes as $source) {
    $mappings[] = MappingEvidence::compare($source, $source->identity->entity->value, 'synthetic-target-'.$source->identity->id, null);
}
echo json_encode(['classification' => 'SYNTHETIC', 'scope' => 'CONTRACT_DEMO_ONLY',
    'manifest' => $report, 'entities' => $rows, 'mapping_proposals' => $mappings,
    'real_files_verified' => false, 'effects_allowed' => false], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
