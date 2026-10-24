<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
try{
    $c=\Hostider\Core\Config::fromEnvironment();if($c->environment!=='test'||!str_contains($c->dsn,'dbname=hostider_f0_test')){throw new RuntimeException('Isolated cost tests only');}
    $db=\Hostider\Core\Database\Database::connect($c);$db->execute('SET SESSION innodb_lock_wait_timeout=2');$e=new \Hostider\CloudCosts\Engine($db,$c);
    $started=hrtime(true);$result=match($argv[1]??''){'submit'=>['id'=>$e->submit($argv[2],$argv[3])],'work'=>['checked'=>$e->once($argv[2])],'run'=>$e->run(),'propose'=>(new \Hostider\Catalog\CloudCostProposals($db,$c))->run(100,20,'0.2'),default=>throw new RuntimeException('Invalid cost child')};$result['elapsed_us']=intdiv(hrtime(true)-$started,1000);echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['error'=>'COST_CHILD_FAILED','code'=>$e instanceof PDOException?(string)($e->errorInfo[1]??'SQL'):$e::class],JSON_THROW_ON_ERROR);exit(1);}
