<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\Security\Crypto;
use Hostider\Migration\Operations\{RecoveryAuthorization,ColdRecovery};
ini_set('zend.exception_ignore_args','1');
try{
    $input=stream_get_contents(STDIN,6291457);if(strlen($input)>6291456){throw new RuntimeException('R1_CHILD_BOUND');}$v=json_decode($input,true,32,JSON_THROW_ON_ERROR);
    if($v['fault']==='CRASH_BEFORE_AUTH'){exit(73);}
    $a=RecoveryAuthorization::allow($v['trust'],$v['backup'],$v['roots'],$v['snapshot'],$v['key_id'],'synthetic-recovery-operator',$v['expires']);
    $report=ColdRecovery::verify($v['capsule'],new Crypto(base64_decode($v['key'],true),$v['key_id']),$a);
    if($v['fault']==='CRASH_AFTER_VERIFY'){exit(73);}
    echo json_encode($report,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,'R1_CHILD_DENIED'.PHP_EOL);exit(1);}
