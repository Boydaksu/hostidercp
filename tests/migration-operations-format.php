<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';require_once __DIR__.'/migration-operations-fixture.php';
use Hostider\Core\CanonicalJson;
use Hostider\Migration\Contract;
use Hostider\Migration\Operations\AuthenticatedBackup;

test('F6-D authenticated duplicate JSON archive keys are rejected before restore',function(): void{
    $f=operationsFixture();$data=$f['source']->capture();$id=Contract::hash($data);
    $plain=CanonicalJson::encode(['data'=>$data,'logical_sha256'=>$id,'created_at'=>'2026-10-27T10:00:00Z','key_id'=>'synthetic-v1','encoding'=>'json-base64-no-compression']);
    $duplicate='{"encoding":"json-base64-no-compression",'.substr($plain,1);
    $cipher=$f['crypto']->encrypt($duplicate,'f6d-backup:'.$id);
    raises(InvalidArgumentException::class,fn()=>AuthenticatedBackup::open($id,$cipher,$f['crypto'],'synthetic-v1',$f['vault']));
});
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){try{$exit=executeTests();}finally{operationsCleanup();}exit($exit);}
