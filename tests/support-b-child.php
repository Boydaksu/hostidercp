<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
try{
    $p=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$config=\Hostider\Core\Config::fromEnvironment();if($config->environment!=='test'||!str_contains($config->dsn,'dbname=hostider_f0_test')){exit(2);}
    if(isset($p['url'])){if(!preg_match('~^http://127\.0\.0\.1:[0-9]+/api/v1/admin/support-work/execute$~D',$p['url'])){exit(2);}$h=curl_init($p['url']);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>$p['cookie'],CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.$p['csrf'],'Idempotency-Key: '.$p['key']],CURLOPT_POSTFIELDS=>json_encode($p['body'],JSON_THROW_ON_ERROR)]);$raw=curl_exec($h);$status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);$r=is_string($raw)?json_decode($raw,true):null;echo json_encode(['status'=>$status,'data'=>$r['data']??null,'code'=>$r['errors'][0]['code']??null],JSON_THROW_ON_ERROR);exit($status===0?1:0);}
    $db=\Hostider\Core\Database\Database::connect($config);$db->execute('SET SESSION innodb_lock_wait_timeout=2');$e=new \Hostider\Support\Work\Engine(new \Hostider\Commerce\Services($db,$config));$worked=0;foreach($p['ids'] as $id){if($e->tick($id)){++$worked;}}echo json_encode(['worked'=>$worked],JSON_THROW_ON_ERROR);
}catch(Throwable){echo '{"error":"SUPPORT_CHILD_FAILED"}';exit(1);}
