<?php
declare(strict_types=1);
require __DIR__.'/database.php';require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\Config;
use Hostider\Core\Http\HttpError;
use Hostider\Identity\{IdentityService,CriticalActions};
$f=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);$db=integrationDb();
$config=new Config('test','https://test.example.invalid',$f['key'],getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');$identity=new IdentityService($db,$config);
try{(new CriticalActions($identity))->execute($f['reference'],'admin','role-save',$f['payload'],(object)['id'=>$f['confirmation'],'accepted'=>true],fn():array=>$identity->manageRole($f['reference'],'admin',null,$f['payload']['name'],$f['payload']['permissions']));echo '200';}
catch(HttpError $e){echo (string)$e->status;}
