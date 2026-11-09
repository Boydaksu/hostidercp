<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
try{
    $p=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
    $config=\Hostider\Core\Config::fromEnvironment();
    if($config->environment!=='test'||!str_contains($config->dsn,'dbname=hostider_f0_test')){exit(2);}
    $db=\Hostider\Core\Database\Database::connect($config);$db->execute('SET SESSION innodb_lock_wait_timeout=2');
    $c=new \Hostider\Commerce\Services($db,$config);
    if($p['op']==='worker'){
        $w=new \Hostider\Core\Queue\Worker($db,new \Hostider\Core\Queue\Queue($db));(new \Hostider\Support\AutoClose\Engine($c))->register($w);
        echo json_encode(['worked'=>$w->once()],JSON_THROW_ON_ERROR);
    }else{
        try{$r=(new \Hostider\Support\Service($c))->command($p['ref'],'client','reply',['id'=>$p['ticket_id'],'version'=>$p['version'],'body'=>'F5F_CUSTOMER_REPLY'],\Hostider\Core\Id::uuid(),\Hostider\Core\Id::uuid());echo json_encode(['status'=>200,'version'=>$r['version']],JSON_THROW_ON_ERROR);}
        catch(\Hostider\Core\Http\HttpError $e){echo json_encode(['status'=>$e->status,'code'=>$e->errorCode],JSON_THROW_ON_ERROR);}
    }
}catch(Throwable){echo '{"error":"F5F_CHILD_FAILED"}';exit(1);}
