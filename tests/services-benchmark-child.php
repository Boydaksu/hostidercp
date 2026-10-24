<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';require __DIR__.'/database.php';
use Hostider\Core\{Config,Id};use Hostider\Core\Security\Principal;
try{
    $f=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);$db=integrationDb();$config=new Config('test','https://test.example.invalid',$f['key'],getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');$actor=new Principal('benchmark-worker',[]);$results=[];
    foreach($f['operations'] as $id){$start=hrtime(true);if($f['mode']==='claim'){$result=(new \Hostider\Services\Engine($db,$config))->claim($actor,Id::uuid(),$id,true);if(!$result){throw new RuntimeException('No claim');}}else{if(!(new \Hostider\Services\Runner($db,$config))->once($actor,$id)){throw new RuntimeException('No work');}}$results[]=(hrtime(true)-$start)/1e6;}
    echo json_encode($results,JSON_THROW_ON_ERROR);
}catch(Throwable){fwrite(STDERR,'BENCHMARK_CHILD_FAILED');exit(1);}
