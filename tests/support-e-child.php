<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
$p=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$config=\Hostider\Core\Config::fromEnvironment();$db=\Hostider\Core\Database\Database::connect($config);$c=new \Hostider\Commerce\Services($db,$config);
try{$r=(new \Hostider\Support\UI\Commands(new \Hostider\Support\UI\Context(new \Hostider\Support\Advanced\Common($c))))->command($p['reference'],'admin','execute',$p['body'],$p['key'],\Hostider\Core\Id::uuid());echo json_encode(['status'=>200,'result'=>$r],JSON_THROW_ON_ERROR);}catch(\Hostider\Core\Http\HttpError $e){echo json_encode(['status'=>$e->status,'code'=>$e->errorCode],JSON_THROW_ON_ERROR);}
