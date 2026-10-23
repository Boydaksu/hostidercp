<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';require __DIR__.'/database.php';
use Hostider\Core\{Config,Id};use Hostider\Core\Security\Principal;use Hostider\Services\Engine;
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');
try{
    $f=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);$db=integrationDb();
    $config=new Config('test','https://test.example.invalid',$f['key'],getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');
    $engine=new Engine($db,$config);$actor=new Principal('service-test-worker',[]);
    $result=($argv[2]==='discover')?$engine->discover($f['order'],$actor,Id::uuid()):$engine->claim($actor,Id::uuid(),$f['operation']);
    echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(Throwable){fwrite(STDERR,'SERVICE_CHILD_FAILED');exit(1);}
