<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
require dirname(__DIR__).'/tests/migration-recovery-fixture.php';
try{
    $f=recoveryFixture();recoveryExpireVault($f);
    if(preg_match('/^f6b_[a-f0-9]{24}$/D',$f['schema'])!==1){throw new RuntimeException('R1_OWNED_SOURCE');}
    $f['root']->execute('DROP DATABASE `'.$f['schema'].'`');$r=recoveryChildren(recoveryChildInput($f))[0];
    if($r['exit']!==0||!$r['stderr_empty']){throw new RuntimeException('R1_DEMO_DENIED');}
    echo json_encode(['classification'=>'SYNTHETIC','source_schema_actually_removed'=>true,'old_vault_lease_actually_expired'=>true,'fresh_php_process'=>true,'source_signing_secret_supplied_to_child'=>false,'result'=>$r['report'],'production_cold_restore'=>'NOT_VERIFIED'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{operationsCleanup();}
