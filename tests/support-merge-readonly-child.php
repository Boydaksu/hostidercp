<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
$config=Config::fromEnvironment();if($config->environment!=='test'||!str_contains($config->dsn,'dbname=hostider_f0_test')){exit(2);}$c=new \Hostider\Commerce\Services(Database::connect($config),$config);$c->db->execute('SET SESSION innodb_lock_wait_timeout=2');$p=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
try{if($p['op']==='merge'){$r=(new \Hostider\Support\Merge\Commands($c))->command($p['ref'],'execute',$p['body'],$p['key'],Id::uuid());}else{$r=(new \Hostider\Support\Advanced\Csat(new \Hostider\Support\Advanced\Common($c)))->command($p['ref'],$p['body'],$p['key'],Id::uuid());}echo json_encode(['status'=>200,'version'=>$r['version']]);}
catch(HttpError $e){echo json_encode(['status'=>$e->status,'code'=>$e->errorCode]);}
catch(PDOException $e){if(in_array($e->errorInfo[1]??0,[1205,1213],true)){echo json_encode(['status'=>409,'code'=>'DB_LOCK_CONFLICT']);}else{throw $e;}}
