<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/services-fixture.php';
use Hostider\Core\Database\Migrator;
use Hostider\Core\Id;
use Hostider\Services\{Engine,Runner,Worker};
use Hostider\Hosting\Pools;
$db=integrationDb();
$schema='hostider_f0_test_runner_'.bin2hex(random_bytes(6));
$db->execute('CREATE DATABASE '.$schema.' CHARACTER SET utf8mb4');
putenv('TEST_DB_DSN='.preg_replace('/dbname=[a-zA-Z0-9_]+/','dbname='.$schema,getenv('TEST_DB_DSN')));
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
register_shutdown_function(static function()use($db,$schema): void{$db->execute('DROP DATABASE '.$schema);});
$runnerConfig=new \Hostider\Core\Config('test','https://test.example.invalid',base64_encode(random_bytes(32)),getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');
function runnerFixture($db,int $quantity=1,bool $pool=true): array
{
    $f=serviceFixture($db,$quantity);$f['config']=$GLOBALS['runnerConfig'];
    if($pool){$f['pool']=(new Pools($db,$f['config']))->configure($f['product'],$quantity);}
    $f['engine']=new Engine($db,$f['config']);
    do{$f['ids']=$f['engine']->discover($f['order']['id'],$f['actor'],Id::uuid());}while(count($f['ids'])<$quantity);
    $f['operations']=array_map(fn($id)=>$f['engine']->repository->find($id,lock:false)['current_operation_id'],$f['ids']);return $f;
}
function runnerCli(array $f,array $options=[]): array
{
    $env=getenv();$env['APP_ENV']='test';$env['APP_URL']=$f['config']->url;$env['APP_KEY']=$f['config']->key;$env['DB_DSN']=$f['config']->dsn;$env['DB_USER']=$f['config']->dbUser;$env['DB_PASSWORD']=$f['config']->dbPassword;
    foreach(['PROVISIONING_ALLOWED','PAYMENT_LIVE_ALLOWED','EXTERNAL_MAIL_ALLOWED'] as $flag){$env[$flag]='false';}
    $pipes=[];$process=proc_open([PHP_BINARY,dirname(__DIR__).'/bin/console.php','services:work',...$options],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);
    if(!is_resource($process)){throw new RuntimeException('CLI unavailable');}fclose($pipes[0]);return [$process,$pipes];
}
function runnerCliResult(array $child): array
{
    [$process,$pipes]=$child;try{$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);}finally{fclose($pipes[1]);fclose($pipes[2]);}$exit=proc_close($process);same('',$err);same(0,$exit);return json_decode($out,true,32,JSON_THROW_ON_ERROR);
}

test('F3 review repeated cron reaches healthy work behind 101 blocked operations',function()use($db): void{
    $blocked=runnerFixture($db,101,false);$ids=$blocked['ids'];
    // No mapping/capacity: these must remain visible and eligible for later re-evaluation.
    $db->execute("UPDATE svc_operations SET created_at='2000-01-01' WHERE order_item_id=?",[$blocked['item']['id']]);
    $healthy=runnerFixture($db);$engine=$healthy['engine'];$services=$healthy['ids'];
    $runner=new Runner($db,$blocked['config']);
    for($i=0;$i<5;++$i){$runner->once($healthy['actor']);}
    same('ACTIVE',$engine->repository->find($services[0],lock:false)['status']);
    same(101,(int)$db->execute("SELECT COUNT(*) FROM svc_operations WHERE order_item_id=? AND status='PENDING'",[$blocked['item']['id']])->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_hosting_accounts WHERE service_id=?',[$services[0]])->fetchColumn());
    same(101,(int)$db->execute("SELECT COUNT(*) FROM svc_operations WHERE order_item_id=? AND runner_hold_code='NO_VERIFIED_MOCK_CAPACITY' AND runner_checked_at IS NOT NULL",[$blocked['item']['id']])->fetchColumn());
    // Recover capacity without changing operation state or creating a replacement generation.
    (new Pools($db,$blocked['config']))->configure($blocked['product'],101);
    for($i=0;$i<4;++$i){runnerCliResult(runnerCli($blocked));}
    same(101,(int)$db->execute("SELECT COUNT(*) FROM svc_operations WHERE order_item_id=? AND status='SUCCEEDED'",[$blocked['item']['id']])->fetchColumn());
    same(101,(int)$db->execute('SELECT COUNT(*) FROM mock_hosting_accounts a JOIN svc_services s ON s.id=a.service_id WHERE s.order_item_id=?',[$blocked['item']['id']])->fetchColumn());
});

test('F3 review changed payment stays visible and UNKNOWN is never redispatched by cron',function()use($db): void{
    $f=runnerFixture($db);$db->execute("UPDATE orders SET status='CANCELLED' WHERE id=?",[$f['order']['id']]);
    $unknown=runnerFixture($db);same(true,(new Worker($db,$unknown['config']))->once($unknown['actor'],$unknown['operations'][0],'TIMEOUT_AFTER_CREATE'));
    $healthy=runnerFixture($db);for($i=0;$i<3;++$i){runnerCliResult(runnerCli($healthy));}
    same('PENDING',$db->execute('SELECT status FROM svc_operations WHERE id=?',[$f['operations'][0]])->fetchColumn());
    same('SERVICE_SOURCE_CHANGED',$db->execute('SELECT runner_hold_code FROM svc_operations WHERE id=?',[$f['operations'][0]])->fetchColumn());
    same('UNKNOWN',$db->execute('SELECT status FROM svc_operations WHERE id=?',[$unknown['operations'][0]])->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_hosting_accounts WHERE service_id=?',[$unknown['ids'][0]])->fetchColumn());
    same(1,(int)$db->execute("SELECT COUNT(*) FROM mock_hosting_calls WHERE server_id=? AND action='createAccount'",[$unknown['pool']['server']])->fetchColumn());
    same('ACTIVE',$healthy['engine']->repository->find($healthy['ids'][0],lock:false)['status']);
});

test('F3 review two overlapping bounded CLI workers fence creates and next cron continues',function()use($db): void{
    $f=runnerFixture($db,12);$children=[runnerCli($f,['--max-operations=5','--max-seconds=10']),runnerCli($f,['--max-operations=5','--max-seconds=10'])];
    $worked=0;foreach($children as $child){$result=runnerCliResult($child);same(true,$result['worked']<=5);$worked+=$result['worked'];}
    same(true,$worked>0);same(true,$worked<=10);
    for($i=0;$i<3;++$i){runnerCliResult(runnerCli($f));}
    same(12,(int)$db->execute("SELECT COUNT(*) FROM svc_operations WHERE order_item_id=? AND status='SUCCEEDED'",[$f['item']['id']])->fetchColumn());
    same(12,(int)$db->execute('SELECT COUNT(*) FROM mock_hosting_accounts WHERE server_id=?',[$f['pool']['server']])->fetchColumn());
    same(12,(int)$db->execute("SELECT COUNT(*) FROM mock_hosting_calls WHERE server_id=? AND action='createAccount'",[$f['pool']['server']])->fetchColumn());
    same(12,(int)$db->execute("SELECT COUNT(*) FROM svc_attempt_events e JOIN svc_operations o ON o.id=e.operation_id WHERE o.order_item_id=? AND e.kind='STARTED'",[$f['item']['id']])->fetchColumn());
});

test('F3 review expired admission budget makes no claim and invalid batch bounds fail closed',function()use($db): void{
    $f=runnerFixture($db);$runner=new Runner($db,$f['config']);same(false,$runner->once($f['actor'],$f['operations'][0],hrtime(true)-1));
    same('PENDING',$db->execute('SELECT status FROM svc_operations WHERE id=?',[$f['operations'][0]])->fetchColumn());
    raises(InvalidArgumentException::class,fn()=>$runner->run($f['actor'],101));raises(InvalidArgumentException::class,fn()=>$runner->run($f['actor'],1,21));
    raises(\Hostider\Core\Http\HttpError::class,fn()=>$db->transaction(fn()=>$runner->once($f['actor'])));
    same(true,(new Worker($db,$f['config']))->once($f['actor'],$f['operations'][0]));
});

test('F3 review direct worker and actual services work CLI throughput are measured separately',function()use($db): void{
    $direct=runnerFixture($db,16);$worker=new Worker($db,$direct['config']);$start=hrtime(true);foreach($direct['operations'] as $id){same(true,$worker->once($direct['actor'],$id));}$directNs=hrtime(true)-$start;
    $cli=runnerFixture($db,16);$start=hrtime(true);$worked=0;$runs=0;do{$result=runnerCliResult(runnerCli($cli));$worked+=$result['worked'];++$runs;}while($worked<16&&$runs<5);$cliNs=hrtime(true)-$start;same(16,$worked);
    $evidence=['environment'=>'local test/mock; no deployment capacity guarantee','php'=>PHP_VERSION,'mariadb'=>$db->execute('SELECT VERSION()')->fetchColumn(),'sample'=>16,'direct_worker'=>['elapsed_ns'=>$directNs,'operations_per_second'=>16*1_000_000_000/$directNs],'actual_cli'=>['elapsed_ns'=>$cliNs,'operations_per_second'=>16*1_000_000_000/$cliNs,'invocations'=>$runs],'peak_memory_bytes'=>memory_get_peak_usage(true)];
    if(getenv('F3_RUNNER_BENCHMARK_OUTPUT')){file_put_contents(getenv('F3_RUNNER_BENCHMARK_OUTPUT'),json_encode($evidence,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);}
    echo 'MEASUREMENT '.json_encode($evidence,JSON_THROW_ON_ERROR).PHP_EOL;
});
runTests();
