<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
try{
    $c=\Hostider\Core\Config::fromEnvironment();if($c->environment!=='test'||!str_contains($c->dsn,'dbname=hostider_f0_test')){throw new RuntimeException('Isolated Cloud only');}
    $db=\Hostider\Core\Database\Database::connect($c);$db->execute('SET SESSION innodb_lock_wait_timeout=2');$engine=new \Hostider\Cloud\Engine($db,$c);
    if(($argv[1]??'')==='fence'){
        $claim=json_decode(getenv('SYNTHETIC_CLOUD_CLAIM'),true,32,JSON_THROW_ON_ERROR);
        try{$engine->fence($claim)->send(fn()=>null);echo '{"admitted":true}';}catch(\Hostider\Core\Http\HttpError){echo '{"admitted":false}';}
    }elseif(($argv[1]??'')==='enqueue'){
        $actor=new \Hostider\Core\Security\Principal('cloud-enqueue-child',['services.manage'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time(),grants:[new \Hostider\Core\Security\AccessGrant('services.manage',[$argv[4]],[$argv[3]])]);
        echo json_encode(['operation'=>$engine->enqueue($argv[2],'CREATE',[],$actor)],JSON_THROW_ON_ERROR);
    }else{echo json_encode(['worked'=>(new \Hostider\Cloud\Worker($db,$c))->once($argv[1]??null)],JSON_THROW_ON_ERROR);}
}catch(Throwable){echo '{"error":"CLOUD_CHILD_FAILED"}';exit(1);}
