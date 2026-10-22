<?php
declare(strict_types=1);
require __DIR__.'/database.php'; require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\{Config,Id,Idempotency};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Finance\{Currency,FinanceService,Money};
use Hostider\Pricing\{ExchangeRatePolicy,FxRateRepository,PriceResolver,QuoteService};
$db=integrationDb();$config=new Config('test','https://test.example.invalid',base64_encode(random_bytes(32)),getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');
$finance=new FinanceService($db,$config);$billing=new \Hostider\Billing\BillingService($db,$config,$finance);$catalog=new \Hostider\Catalog\Repository($db);$quotes=new QuoteService($db,$catalog,new PriceResolver($catalog,new FxRateRepository($db,$config),new ExchangeRatePolicy(3600)));$orders=new \Hostider\Orders\OrderService($db,$billing,$quotes);
$input=json_decode(fgets(STDIN),true,32,JSON_THROW_ON_ERROR);echo "READY\n";fflush(STDOUT);fgets(STDIN);
try{(new Idempotency($db))->run('order-test','order-child',$input['key'],$input,fn()=>$orders->fund($input['order'],$input['customer'],new Money($input['minor'],new Currency('USD')),'COLLECTION',$input['payment'],$input['reference'],new Principal('order-test',[],customerId:$input['customer']),Id::uuid()));echo '200';}
catch(HttpError $e){echo (string)$e->status;}
