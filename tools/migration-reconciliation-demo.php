<?php
declare(strict_types=1);
// Development-only: controlled fixture, ephemeral schemas; no arbitrary source input.
require dirname(__DIR__).'/bootstrap/autoload.php';
require dirname(__DIR__).'/tests/migration-reconciliation-fixture.php';
use Hostider\Migration\Synthetic\Sandbox;
Sandbox::flags();
try{$fixture=reconciliationFixture();echo json_encode(reconciliationResult($fixture),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;}
finally{reconciliationCleanup();}
