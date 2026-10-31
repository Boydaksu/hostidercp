<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';require_once __DIR__.'/migration-operations-fixture.php';
use Hostider\Core\CanonicalJson;
use Hostider\Migration\Contract;

test('F6-D encryption key alone cannot issue a forged source ownership backup',function(): void{
    $f=operationsFixture();$data=$f['source']->capture();$data['tables']['f6b_entities'][0]['owner_key']=str_repeat('f',64);
    $data['report']['observed_target_evidence_sha256']=Contract::hash($data['tables']);$id=Contract::hash($data);
    $plain=CanonicalJson::encode(['data'=>$data,'logical_sha256'=>$id,'created_at'=>'2026-10-27T10:00:00Z','key_id'=>'synthetic-v1','encoding'=>'json-base64-no-compression']);
    $path=operationsPath($f['vault'],$id.'.sealed');file_put_contents($path,$f['crypto']->encrypt($plain,'f6d-backup:'.$id));chmod($path,0600);
    raises(InvalidArgumentException::class,fn()=>$f['vault']->verify($id,$f['crypto'],'synthetic-v1'));
});
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){try{$exit=executeTests();}finally{operationsCleanup();}exit($exit);}
