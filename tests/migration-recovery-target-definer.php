<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';require_once __DIR__.'/migration-recovery-target-fixture.php';
use Hostider\Migration\Operations\RecoveryInspector;
test('F6-D-R2 marker view is rejected before any definer SELECT side effect',function(): void{
    $f=targetFixture();$i=RecoveryInspector::open(targetContext($f),targetPolicy($f),$f['target_password']);$probe='r2-probe:'.bin2hex(random_bytes(12));
    $f['target']['setup']->execute('DROP TABLE f6d_restore_marker');
    $f['target']['setup']->execute("CREATE VIEW f6d_restore_marker AS SELECT GET_LOCK('".$probe."',0) AS id,'x' AS schema_name,'x' AS account_name,'x' AS token_hash,'x' AS vault_id,TIMESTAMPADD(MINUTE,10,UTC_TIMESTAMP(6)) AS expires_at");
    same('BLOCKED',$i->inspect()->content['status']);
    same(null,$f['root']->execute('SELECT IS_USED_LOCK(?)',[$probe])->fetchColumn());
});
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){try{$exit=executeTests();}finally{targetCleanup();}exit($exit);}
