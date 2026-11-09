<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\Security\Crypto;
use Hostider\Migration\Operations\{RecoveryAuthorization,RecoveryContext,RecoveryTargetPolicy,RecoveryInspector};
ini_set('zend.exception_ignore_args','1');
try{$raw=stream_get_contents(STDIN,6291457);if(strlen($raw)>6291456){throw new RuntimeException('R2_BOUND');}$v=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    $a=RecoveryAuthorization::allow($v['trust'],$v['backup'],$v['roots'],$v['snapshot'],$v['key_id'],'synthetic-recovery-operator',$v['expires']);$ctx=RecoveryContext::open($v['capsule'],new Crypto(base64_decode($v['key'],true),$v['key_id']),$a);
    $p=RecoveryTargetPolicy::allow($v['binding'],$v['backup'],$v['epoch'],$v['target_expires']);$report=RecoveryInspector::open($ctx,$p,$v['target_password'])->inspect($v['fault']);echo json_encode($report,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,'R2_CHILD_DENIED'.PHP_EOL);exit(1);}
