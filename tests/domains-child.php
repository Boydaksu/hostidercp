<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';require __DIR__.'/database.php';
ini_set('zend.exception_ignore_args','1');
try{
    $data=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$db=integrationDb();
    $config=new \Hostider\Core\Config('test','https://test.example.invalid',getenv('APP_KEY'),getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');
    $provider=new \Hostider\Providers\Mock\DurableRegistrar($db,$config,$data['account']);
    $result=$provider->execute(new \Hostider\Providers\Contract\Operation($data['operation'],$data['action'],$data['resource'],$data['request']));
    echo json_encode(['status'=>$result->status,'resource'=>$result->externalResourceId],JSON_THROW_ON_ERROR);
}catch(Throwable){fwrite(STDERR,'DOMAIN_CHILD_FAILED');exit(1);}
