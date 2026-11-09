<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';
try{$p=json_decode(stream_get_contents(STDIN,4096),true,16,JSON_THROW_ON_ERROR);$db=integrationDb();$cfg=\Hostider\Core\Config::fromEnvironment();$m=new \Hostider\Support\Advanced\Common(new \Hostider\Commerce\Services($db,$cfg));$r=(new \Hostider\Support\Automation\Engine($m))->consume($p);echo json_encode(['result'=>$r],JSON_THROW_ON_ERROR);}catch(\Hostider\Core\Http\HttpError $e){echo json_encode(['status'=>$e->status,'reason'=>$e->errorCode],JSON_THROW_ON_ERROR);}catch(Throwable){echo json_encode(['result'=>'ERROR'],JSON_THROW_ON_ERROR);}
