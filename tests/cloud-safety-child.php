<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
try{$c=\Hostider\Core\Config::fromEnvironment();if($c->environment!=='test'||!str_contains($c->dsn,'dbname=hostider_f0_test')){throw new RuntimeException('Isolated only');}$db=\Hostider\Core\Database\Database::connect($c);$op=(new \Hostider\Cloud\Containment($db,$c))->execute($argv[1],$argv[2],getenv('SYNTHETIC_SAFETY_SESSION'),true);echo json_encode(['status'=>200,'operation'=>$op],JSON_THROW_ON_ERROR);}catch(\Hostider\Core\Http\HttpError $e){echo json_encode(['status'=>$e->status],JSON_THROW_ON_ERROR);}catch(Throwable){echo '{"status":500}';exit(1);}
