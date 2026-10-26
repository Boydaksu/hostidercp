<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/support-d-fixture.php';
use Hostider\Core\Id;
$f=dFixture(true);$t=$f['service']->command($f['client']['reference'],'client','create',['department_id'=>$f['departments'][0],'subject'=>'Browser <img src=x onerror=alert(1)>','body'=>'Public browser message','priority'=>'NORMAL'],Id::uuid(),Id::uuid());
$f['service']->command($f['admin']['reference'],'admin','note',['id'=>$t['id'],'version'=>1,'body'=>'BROWSER_PRIVATE_NOTE'],Id::uuid(),Id::uuid());
$server=new HttpTestServer(dirname(__DIR__),bEnvironment($f),__DIR__.'/support-e-router.php');
try{$pipes=[];$p=proc_open([getenv('F5_E_NODE')?:'node',__DIR__.'/support-e-browser.cjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,getenv());if(!is_resource($p)){throw new RuntimeException('Browser process unavailable');}fwrite($pipes[0],json_encode(['url'=>'http://127.0.0.1:'.$server->diagnostics()['port'],'client'=>$f['client'],'admin'=>$f['admin'],'ticket'=>$t,'department'=>$f['db']->execute('SELECT id,name,enabled,allow_reopen,allow_hold,version FROM support_departments WHERE id=?',[$f['departments'][0]])->fetch()],JSON_THROW_ON_ERROR));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);echo $out;if($exit!==0){echo 'Browser check failed: '.$err.PHP_EOL;}exit($exit);}finally{$server->stop();}
