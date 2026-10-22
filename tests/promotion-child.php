<?php
declare(strict_types=1);
require __DIR__.'/database.php'; require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Catalog\Promotions;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Id;
$db=integrationDb(); $input=json_decode(fgets(STDIN),true,32,JSON_THROW_ON_ERROR);
echo "READY\n"; fflush(STDOUT); fgets(STDIN);
try {$db->transaction(fn()=>(new Promotions($db))->redeem($input['promotions'],Id::uuid(),$input['customer'],false,new DateTimeImmutable($input['now'],new DateTimeZone('UTC'))));echo '200';}
catch(HttpError $e){echo (string)$e->status;}
