<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('zend.exception_ignore_args','1');
use Hostider\Migration\Synthetic\{Sandbox,Fixture,Loader,Fault};
try{$input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$sandbox=Sandbox::open(...$input['params']);$result=(new Loader())->load(Fixture::named($input['fixture']),$sandbox,$input['batch'],null,Fault::from($input['fault']));echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;}
catch(Throwable){echo json_encode(['status'=>'FAILED','reasons'=>['F6B_CHILD_FAILURE']],JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
