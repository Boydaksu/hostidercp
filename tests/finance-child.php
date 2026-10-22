<?php
declare(strict_types=1);
require __DIR__.'/database.php'; require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\{Config,Id};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Finance\{Currency,FinanceService,Money};
$db=integrationDb();
$finance=new FinanceService($db,new Config('test','https://test.example.invalid',base64_encode(random_bytes(32)),getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:''));
$input=json_decode(fgets(STDIN),true,32,JSON_THROW_ON_ERROR);
echo 'READY'.PHP_EOL; fflush(STDOUT); fgets(STDIN);
try {
    $db->transaction(function()use($input,$finance): void {
        $actor=new Principal('finance-test',[],customerId:$input['customer']); $money=new Money($input['minor'],new Currency('TRY'));
        if ($input['mode']==='spend') { $finance->spendWallet($input['customer'],Id::uuid(),$money,Id::uuid(),$actor,Id::uuid()); }
        elseif ($input['mode']==='refund') { $finance->requestOriginalRefund($input['payment'],$money,Id::uuid(),$actor,Id::uuid()); }
        else { $finance->recordTestCollection($input['customer'],$money,$input['external'],$actor,Id::uuid()); }
    });
    echo '200';
} catch(HttpError $e) { echo (string)$e->status; }
