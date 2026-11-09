<?php
declare(strict_types=1);
use Hostider\HostingPreflight\{Environment,Observation,Evaluator,Metadata,Report,Catalog};
function hgPrivate(string $name,array|string $value): string{
 $root=$GLOBALS['hg']['private_root'];$p=$root.'/'.$name;$bytes=is_array($value)?json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES):$value;$h=fopen($p,'x+b');same(true,is_resource($h));same(true,chmod($p,0600));same(strlen($bytes),fwrite($h,$bytes));fclose($h);return $p;
}
function hgFixture():array{
 same('test',getenv('APP_ENV'));same(1,preg_match('/\Amysql:host=127\.0\.0\.1;port=([0-9]+);dbname=hostider_f0_test;charset=utf8mb4\z/D',getenv('TEST_DB_DSN'),$m));
 $admin=new PDO(getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
 $base=sys_get_temp_dir().'/f6g-'.bin2hex(random_bytes(10));same(true,mkdir($base,0700));$id=bin2hex(random_bytes(6));$full='f6g_ro_'.$id;$limited='f6g_min_'.$id;$password=bin2hex(random_bytes(32));
 foreach([$full,$limited] as $name){$admin->exec('CREATE USER '.$admin->quote($name)."@'%' IDENTIFIED BY ".$admin->quote($password));$admin->exec('GRANT SELECT ON hostider_f0_test.* TO '.$admin->quote($name)."@'%'");}
 $admin->exec('GRANT FILE ON *.* TO '.$admin->quote($full)."@'%'");
 $GLOBALS['hg']=['admin'=>$admin,'private_root'=>$base,'full_user'=>$full,'limited_user'=>$limited,'password'=>$password];
 $secret=hgPrivate('app-config','F6G_SECRET_CONFIG_CANARY');$deployment=['webroot'=>dirname(__DIR__).'/public','config_file'=>$secret,'storage'=>dirname(__DIR__).'/storage','session_path'=>$base,'web_php_version'=>PHP_VERSION_ID];
 $config=['dsn'=>getenv('TEST_DB_DSN'),'user'=>$full,'password'=>$password,'read_only'=>true];
 $GLOBALS['hg']+=['deployment'=>$deployment,'config'=>$config,'deployment_file'=>hgPrivate('deployment.json',$deployment),'db_file'=>hgPrivate('db.json',$config),'secret_file'=>$secret];
 $admin->exec("SET GLOBAL log_output='TABLE'");$admin->exec('SET GLOBAL general_log=1');
 register_shutdown_function(static function()use($admin):void{try{$admin->exec('SET GLOBAL general_log=0');}catch(Throwable){}});
 return $GLOBALS['hg'];
}
function hgCli(array $args=[],array $ini=[],?string $deployment=null,?string $db=null,string $dbMode='r'):array{
 $cmd=[PHP_BINARY,...$ini,dirname(__DIR__).'/bin/hosting-preflight.php'];$spec=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
 if($deployment!==null){$cmd[]='--deployment-fd=3';$spec[3]=['file',$deployment,'r'];}if($db!==null){$cmd[]='--db-config-fd=4';$spec[4]=['file',$db,$dbMode];}
 $pipes=[];$proc=proc_open([...$cmd,...$args],$spec,$pipes,null,getenv());same(true,is_resource($proc));fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);same('',$stderr);$j=json_decode($stdout,true,32,JSON_THROW_ON_ERROR);return ['exit'=>$exit,'raw'=>$stdout,'report'=>$j];
}
function hgFull(array $ini=[],array $extra=[]):array{return hgCli($extra,$ini,$GLOBALS['hg']['deployment_file'],$GLOBALS['hg']['db_file']);}
function hgCheck(array $report,string $code):array{foreach($report['checks'] as $c){if($c['check_code']===$code){return $c;}}throw new RuntimeException('Missing expected check');}
function hgRow(array $report,string $code,string $status):void{same($status,hgCheck($report,$code)['status']);}
function hgGates(array $report):void{same(false,$report['production_allowed']);same(false,$report['effects_allowed']);same('BLOCKED',$report['real_gate_status']);same(['G-O01'=>'OPEN','G-O02'=>'OPEN','OPS-002'=>'OPEN','OPS-003'=>'OPEN'],$report['gates']);}
function hgObserved(array $runtime=[],array $filesystem=[],?array $database=null,?int $web=null):array{$b=$GLOBALS['hg']['observation'];return Evaluator::evaluate(new Observation(array_replace_recursive($b->runtime,$runtime),array_replace($b->filesystem,$filesystem),$database??$b->database,$web))->jsonSerialize();}
function hgSource():array{$out=[];foreach(['src','tests','bootstrap','bin','public','migrations','tools','.github'] as $dir){$base=dirname(__DIR__).'/'.$dir;if(!is_dir($base)){continue;}foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS)) as $p){if(!$p->isFile()||str_contains($p->getPathname(),'/tools/local/')||str_contains($p->getPathname(),'/vendor/')||str_contains($p->getPathname(),'/node_modules/')){continue;}$out[substr($p->getPathname(),strlen(dirname(__DIR__))+1)]=hash_file('sha256',$p->getPathname());}}ksort($out);return $out;}
