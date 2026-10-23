<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/support/HttpTestServer.php';require __DIR__.'/support/FixtureDirectory.php';
if(($argv[1]??'')==='hold-fixture'){$path=trim((string)fgets(STDIN));$handle=fopen($path,'w+b');echo "READY\n";fflush(STDOUT);fgets(STDIN);usleep(150000);fclose($handle);exit(0);}
$root=dirname(__DIR__);$environment=array_merge(getenv(),['APP_ENV'=>'test']);
test('HTTP harness ephemeral listeners prove ownership and cleanly stop independently',function()use($root,$environment): void {
    $one=new HttpTestServer($root,$environment,__DIR__.'/commerce-router.php');$two=new HttpTestServer($root,$environment,__DIR__.'/commerce-router.php');try{same(true,$one->diagnostics()['port']>0);same(false,$one->diagnostics()['port']===$two->diagnostics()['port']);same(200,$one->request('/__http_test_ready')[0]);$one->stop();same(200,$two->request('/__http_test_ready')[0]);}finally{$one->stop();$two->stop();}
});
test('HTTP harness unexpected termination reports first process error without credentials',function()use($root,$environment): void {
    $server=new HttpTestServer($root,$environment,__DIR__.'/commerce-router.php');$server->stop();$error=raises(HttpTestTransportFailure::class,fn()=>$server->request('/some-path?token=DO_NOT_LOG_SENTINEL'));same(false,str_contains($error->getMessage(),'DO_NOT_LOG_SENTINEL'));same(true,str_contains($error->getMessage(),'SERVER_EXIT'));
});
test('HTTP harness occupied explicit port fails readiness and does not kill its owner',function()use($root,$environment): void {
    $owner=new HttpTestServer($root,$environment,__DIR__.'/commerce-router.php');try{raises(HttpTestTransportFailure::class,fn()=>new HttpTestServer($root,$environment,__DIR__.'/commerce-router.php',$owner->diagnostics()['port']));same(200,$owner->request('/__http_test_ready')[0]);}finally{$owner->stop();}
});
test('Fixture cleanup refuses paths outside its dedicated generated test directories',function()use($root): void {raises(LogicException::class,fn()=>FixtureDirectory::remove($root.'/storage'));});
test('Windows delete pending handle reproduces invisible nonempty directory then bounded cleanup succeeds',function()use($root): void {
    $directory=$root.'/storage/harness-test-'.bin2hex(random_bytes(8));mkdir($directory,0700);$path=$directory.'/held';$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'hold-fixture'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],$path."\n");same("READY\n",fgets($pipes[1]));same(true,unlink($path));same(false,file_exists($path));set_error_handler(static fn(): bool=>true);try{$removed=rmdir($directory);}finally{restore_error_handler();}if(PHP_OS_FAMILY==='Windows'){same(false,$removed);}else{same(true,$removed);}fwrite($pipes[0],"RELEASE\n");fclose($pipes[0]);$result=FixtureDirectory::remove($directory);if(PHP_OS_FAMILY==='Windows'){same(true,$result['attempts']>1);}same('',stream_get_contents($pipes[1]));same('',stream_get_contents($pipes[2]));fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($process));same(false,is_dir($directory));
});
runTests();
