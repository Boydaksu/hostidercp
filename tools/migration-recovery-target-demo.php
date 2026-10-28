<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';require dirname(__DIR__).'/tests/migration-recovery-target-fixture.php';
try{$f=targetFixture();recoveryExpireVault($f);if(preg_match('/^f6b_[a-f0-9]{24}$/D',$f['schema'])!==1){throw new RuntimeException('R2_OWNED_SOURCE');}$f['root']->execute('DROP DATABASE `'.$f['schema'].'`');$r=targetChildren(targetChildInput($f))[0];if($r['exit']!==0||!$r['stderr_empty']){throw new RuntimeException('R2_DEMO');}echo json_encode(['classification'=>'SYNTHETIC','source_schema_removed'=>true,'operational_vault_expired'=>true,'fresh_php_process'=>true,'original_signing_secret_supplied'=>false,'writer_or_admin_credentials_supplied'=>false,'report'=>$r['report']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{targetCleanup();}
