<?php
declare(strict_types=1);
require __DIR__.'/database.php';require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\{Config,Id};use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{AccessGrant,Principal};use Hostider\Finance\{FinanceService,Reconciliation};
$db=integrationDb();$config=new Config('test','https://test.example.invalid',base64_encode(random_bytes(32)),getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');$input=json_decode(fgets(STDIN),true,32,JSON_THROW_ON_ERROR);echo "READY\n";fflush(STDOUT);fgets(STDIN);
$actor=new Principal('review-race',['finance.approve'],staff:true,mfa:true,expiresAt:time()+600,verifiedAt:time(),grants:[new AccessGrant('finance.approve',[$input['customer']])]);
try{$db->transaction(fn()=>(new Reconciliation($db,$config,new FinanceService($db,$config)))->resolve($input['case'],$input['customer'],'CREDIT_WALLET','Synthetic bank statement checked for parallel review',$actor,Id::uuid()));echo '200';}catch(HttpError $e){echo (string)$e->status;}
