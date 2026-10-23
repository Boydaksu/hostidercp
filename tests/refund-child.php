<?php
declare(strict_types=1);
require __DIR__.'/database.php';require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\{Config,Id};use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{AccessGrant,Principal};use Hostider\Finance\{Currency,Money};
$db=integrationDb();$config=new Config('test','https://test.example.invalid',base64_encode(random_bytes(32)),getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');$services=new \Hostider\Commerce\Services($db,$config);$input=json_decode(fgets(STDIN),true,32,JSON_THROW_ON_ERROR);echo "READY\n";fflush(STDOUT);fgets(STDIN);
$actor=new Principal('refund-test',['finance.approve'],$input['customer'],staff:true,mfa:true,expiresAt:time()+600,verifiedAt:time(),grants:[new AccessGrant('finance.approve',[$input['customer']])]);
try{$db->transaction(fn()=>$services->credits->issue($input['request'],$input['customer'],new Money('280',new Currency('USD')),Id::uuid(),$actor,Id::uuid()));echo '200';}catch(HttpError $e){echo (string)$e->status;}
