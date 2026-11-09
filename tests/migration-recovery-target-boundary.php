<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';require_once __DIR__.'/migration-recovery-target-fixture.php';
test('F6-D-R2 a caller supplied array comparison cannot claim actual database authority',function(): void{
    $f=targetFixture();$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);$v=json_decode($f['crypto']->decrypt($o['ciphertext'],'f6d-backup:'.$f['backup']['backup_id']),true,32,JSON_THROW_ON_ERROR);
    $r=targetContext($f)->compare($v['data']['tables'],targetPolicy($f));
    same(false,$r instanceof \Hostider\Migration\Reconciliation\Report);
    same('REFERENCE_COMPARISON_ONLY_NO_DB_AUTHORITY',$r['verification_scope']);same(false,isset($r['schema_verification']));
});
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){try{$exit=executeTests();}finally{targetCleanup();}exit($exit);}
