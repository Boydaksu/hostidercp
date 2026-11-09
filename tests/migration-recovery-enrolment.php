<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';
require_once __DIR__.'/migration-recovery-fixture.php';
use Hostider\Migration\Operations\AuthenticatedBackup;

test('F6-D-R1 cached authentication cannot enrol a cold capsule after original issuer lease expiry',function(): void{
    $f=recoveryFixture();$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);
    $p=AuthenticatedBackup::open($f['backup']['backup_id'],$o['ciphertext'],$f['crypto'],'synthetic-v1',$f['vault']);
    recoveryExpireVault($f);
    raises(InvalidArgumentException::class,fn()=>$f['signer']->attest($p,$o['ciphertext'],$f['vault']));
});
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){try{$exit=executeTests();}finally{operationsCleanup();}exit($exit);}
