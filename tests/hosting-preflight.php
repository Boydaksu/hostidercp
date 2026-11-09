<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/hosting-preflight-fixture.php';
use Hostider\HostingPreflight\{Catalog,Report,Observation,Evaluator,Environment,Metadata};

test('F6-G 00 a non-files session backend stays UNKNOWN and its callbacks are never invoked',function():void{$handler=new class implements SessionHandlerInterface{public int $calls=0;public function open(string $path,string $name):bool{++$this->calls;return true;}public function close():bool{++$this->calls;return true;}public function read(string $id):string|false{++$this->calls;return '';}public function write(string $id,string $data):bool{++$this->calls;return true;}public function destroy(string $id):bool{++$this->calls;return true;}public function gc(int $max_lifetime):int|false{++$this->calls;return 0;}};same(true,session_set_save_handler($handler));try{$f=Environment::capture();same(['UNKNOWN','UNVERIFIED'],$f->filesystem['SESSION_PATH']);same(0,$handler->calls);}finally{session_module_name('files');}});

test('F6-G 01 full native CLI metadata preflight is safe and inside checked scope',function():void{$f=hgFixture();$GLOBALS['hg_source_before']=hgSource();$result=hgFull();same(0,$result['exit']);same('PREFLIGHT_PASS_WITHIN_CHECKED_SCOPE',$result['report']['status']);same(count(Catalog::definitions()),count($result['report']['checks']));foreach(['PDO_MYSQL','SODIUM','MBSTRING','SESSION','ARGON2ID','DB_CONNECTION','DB_SCHEMA','DB_MARIADB','DB_INNODB','DB_CHARSET','DB_DISKS'] as $code){hgRow($result['report'],$code,'PASS');}hgGates($result['report']);$GLOBALS['hg']['full']=$result;$local=Environment::capture($f['deployment']);$db=Metadata::inspect(fn($sql)=>$f['admin']->query($sql)->fetch()?:[]);$GLOBALS['hg']['observation']=new Observation($local->runtime,$local->filesystem,$db,PHP_VERSION_ID);});

test('F6-G 02 missing extension in a real php no-ini process blocks safely',function():void{$r=hgFull(['-n']);same(1,$r['exit']);same('PREFLIGHT_BLOCKED',$r['report']['status']);hgRow($r['report'],'PDO_MYSQL','FAIL');hgGates($r['report']);});

test('F6-G 03 actually disabled native mb function blocks required capability',function():void{$r=hgFull(['-d','disable_functions=mb_strlen']);same(1,$r['exit']);hgRow($r['report'],'CORE_FUNCTIONS','FAIL');hgRow($r['report'],'MBSTRING','FAIL');});

test('F6-G 04 missing POSIX and fsync do not block a cPanel core installation observation',function():void{$r=hgFull(['-d','disable_functions=posix_geteuid,fsync,proc_open,shell_exec,exec,system,passthru']);same(0,$r['exit']);hgRow($r['report'],'POSIX_BACKUP','FAIL');hgRow($r['report'],'POSIX_DISCOVERY','FAIL');hgRow($r['report'],'FSYNC_BACKUP','FAIL');hgRow($r['report'],'PDO_MYSQL','PASS');hgRow($r['report'],'DB_CONNECTION','PASS');same('BLOCKED_BY_OBSERVED_PREREQUISITE',$r['report']['feature_prerequisites']['offline_backup']);hgGates($r['report']);});

test('F6-G 05 actual open_basedir denial cannot become a path PASS or leak a path',function():void{$root=dirname(__DIR__);$allowed=implode(PATH_SEPARATOR,[$root.'/src',$root.'/bootstrap',$root.'/bin','/proc',$GLOBALS['hg']['private_root']]);$r=hgFull(['-d','open_basedir='.$allowed]);same(true,$r['report']['status']!=='PREFLIGHT_PASS_WITHIN_CHECKED_SCOPE');same(true,in_array(hgCheck($r['report'],'STORAGE_PATH')['status'],['FAIL','UNKNOWN'],true));same(false,str_contains($r['raw'],$GLOBALS['hg']['private_root']));hgGates($r['report']);});

test('F6-G 06 declared CLI web PHP mismatch blocks while matching declaration is unverified',function():void{$d=$GLOBALS['hg']['deployment'];$d['web_php_version']=80200;$p=hgPrivate('web-mismatch.json',$d);$r=hgCli([],[],$p,$GLOBALS['hg']['db_file']);same(1,$r['exit']);hgRow($r['report'],'WEB_PHP','FAIL');hgRow($GLOBALS['hg']['full']['report'],'WEB_PHP','UNKNOWN');same('OPERATOR_DECLARATION',hgCheck($GLOBALS['hg']['full']['report'],'WEB_PHP')['reason']);hgGates($r['report']);});

test('F6-G 07 real read-only account without FILE cannot attest optional DISKS metadata',function():void{$c=$GLOBALS['hg']['config'];$c['user']=$GLOBALS['hg']['limited_user'];$p=hgPrivate('limited-db.json',$c);$r=hgCli([],[],$GLOBALS['hg']['deployment_file'],$p);same(0,$r['exit']);hgRow($r['report'],'DB_CONNECTION','PASS');hgRow($r['report'],'DB_DISKS','UNKNOWN');same('UNVERIFIED',hgCheck($r['report'],'DB_DISKS')['reason']);hgGates($r['report']);});

test('F6-G 08 no explicit descriptor means UNKNOWN and no environment DB fallback',function():void{$r=hgCli();same(2,$r['exit']);hgRow($r['report'],'DB_CONNECTION','UNKNOWN');same('NO_CONNECTION',hgCheck($r['report'],'DB_CONNECTION')['reason']);hgRow($r['report'],'DB_MARIADB','UNKNOWN');hgGates($r['report']);$d=$GLOBALS['hg']['deployment'];unset($d['config_file']);$env=hgCli([],[],hgPrivate('environment-config-layout.json',$d),$GLOBALS['hg']['db_file']);same(0,$env['exit']);hgRow($env['report'],'CONFIG_FILE','UNKNOWN');same('OPERATIONAL_VALIDATION',hgCheck($env['report'],'CONFIG_FILE')['classification']);hgGates($env['report']);});

test('F6-G 09 closed private descriptor is blocked without raw diagnostics',function():void{$r=hgCli(['--db-config-fd=63']);same(1,$r['exit']);same('PREFLIGHT_BLOCKED',$r['report']['status']);same('INPUT_CONTRACT',$r['report']['checks'][0]['check_code']);hgGates($r['report']);});

test('F6-G 10 writable config descriptor is rejected without changing its bytes',function():void{$p=$GLOBALS['hg']['db_file'];$before=hash_file('sha256',$p);$r=hgCli([],[],$GLOBALS['hg']['deployment_file'],$p,'r+');same(1,$r['exit']);same($before,hash_file('sha256',$p));hgGates($r['report']);});

test('F6-G 11 malformed private config never prints secret payload or parser details',function():void{$p=hgPrivate('bad-json','{"password":"F6G_SECRET_INVALID_JSON_CANARY"');$r=hgCli([],[],null,$p);same(1,$r['exit']);same(false,str_contains($r['raw'],'F6G_SECRET_INVALID_JSON_CANARY'));hgGates($r['report']);});

test('F6-G 12 stdout redacts config credentials hosts usernames private paths and file contents',function():void{$r=hgFull();foreach([$GLOBALS['hg']['password'],$GLOBALS['hg']['full_user'],$GLOBALS['hg']['config']['dsn'],$GLOBALS['hg']['private_root'],$GLOBALS['hg']['secret_file'],'F6G_SECRET_CONFIG_CANARY'] as $s){same(false,str_contains($r['raw'],$s));}same(true,str_contains($r['raw'],'operator_action'));hgGates($r['report']);});

test('F6-G 13 actual symlink configuration path is rejected without following it',function():void{$p=$GLOBALS['hg']['private_root'].'/linked-secret';same(true,symlink($GLOBALS['hg']['secret_file'],$p));$d=$GLOBALS['hg']['deployment'];$d['config_file']=$p;$r=hgCli([],[],hgPrivate('linked-layout.json',$d),$GLOBALS['hg']['db_file']);same(1,$r['exit']);hgRow($r['report'],'CONFIG_FILE','FAIL');same('F6G_SECRET_CONFIG_CANARY',file_get_contents($GLOBALS['hg']['secret_file']));});

test('F6-G 14 traversal is rejected even when it names an existing private file',function():void{$d=$GLOBALS['hg']['deployment'];$d['config_file']=$GLOBALS['hg']['private_root'].'/../'.basename($GLOBALS['hg']['private_root']).'/app-config';$r=hgCli([],[],hgPrivate('traversal-layout.json',$d),$GLOBALS['hg']['db_file']);same(1,$r['exit']);hgRow($r['report'],'CONFIG_FILE','FAIL');});

test('F6-G 15 source root cannot be declared as the public webroot',function():void{$d=$GLOBALS['hg']['deployment'];$d['webroot']=dirname(__DIR__);$r=hgCli([],[],hgPrivate('bad-webroot.json',$d),$GLOBALS['hg']['db_file']);same(1,$r['exit']);hgRow($r['report'],'WEBROOT_PATH','FAIL');});

test('F6-G 16 configuration under public is never treated as safe',function():void{$d=$GLOBALS['hg']['deployment'];$d['config_file']=dirname(__DIR__).'/public/index.php';$r=hgCli([],[],hgPrivate('public-config.json',$d),$GLOBALS['hg']['db_file']);same(1,$r['exit']);hgRow($r['report'],'CONFIG_FILE','FAIL');});

test('F6-G 17 actual nonroot read-only native storage permission blocks without a write probe',function():void{$p=dirname(__DIR__).'/storage';same(true,chmod($p,0500));clearstatcache(true,$p);same(false,is_writable($p));try{$r=hgFull();same(1,$r['exit']);hgRow($r['report'],'STORAGE_PATH','FAIL');}finally{same(true,chmod($p,0700));}});

test('F6-G 18 real tmpfs space pressure disables F6-F rather than inventing a core capacity floor',function():void{$p=dirname(__DIR__).'/storage/f6g-space-pressure';$h=fopen($p,'x+b');same(true,is_resource($h));$written=0;try{while(disk_free_space(dirname($p))>=134217728){same(true,$written<469762048);same(2097152,fwrite($h,str_repeat("\0",2097152)));$written+=2097152;}same(true,disk_free_space(dirname($p))<134217728);$r=hgFull();same(0,$r['exit']);hgRow($r['report'],'BACKUP_HEADROOM','FAIL');hgRow($r['report'],'DISK_CAPACITY','UNKNOWN');hgRow($r['report'],'STORAGE_PATH','PASS');hgGates($r['report']);}finally{fclose($h);same(true,unlink($p));}});

test('F6-G 19 missing MariaDB DISKS is a feature failure and does not block core metadata',function():void{$b=$GLOBALS['hg']['observation'];$db=$b->database;$db['plugin']=[];$db['disks']=null;$r=hgObserved(database:$db);same('PREFLIGHT_PASS_WITHIN_CHECKED_SCOPE',$r['status']);hgRow($r,'DB_DISKS','FAIL');hgGates($r);});

test('F6-G 20 minute cron permission remains UNKNOWN even after all local checks pass',function():void{$r=$GLOBALS['hg']['full']['report'];hgRow($r,'CRON','UNKNOWN');same('UNVERIFIED',hgCheck($r,'CRON')['verification']);hgRow($r,'BACKUP_PRIVILEGES','UNKNOWN');hgRow($r,'PITR_OFFSITE','UNKNOWN');hgGates($r);});

test('F6-G 21 duplicate checks cannot overwrite an earlier result',function():void{$r=new Report();$r->add('PHP_BITS','PASS','OBSERVED');raises(InvalidArgumentException::class,fn()=>$r->add('PHP_BITS','FAIL','MISSING'));});

test('F6-G 22 unknown checks cannot enter a report',function():void{$r=new Report();raises(InvalidArgumentException::class,fn()=>$r->add('PRODUCTION_GO','PASS','OBSERVED'));});

test('F6-G 23 missing check inventory fails closed',function():void{$r=new Report();$r->add('PHP_BITS','PASS','OBSERVED');raises(LogicException::class,fn()=>$r->jsonSerialize());});

test('F6-G 24 arbitrary status or raw diagnostic reason cannot be emitted',function():void{$r=new Report();raises(InvalidArgumentException::class,fn()=>$r->add('PHP_BITS','ACCEPTED','OBSERVED'));raises(InvalidArgumentException::class,fn()=>$r->add('PHP_BITS','PASS','F6G_SECRET_ERROR_MESSAGE'));});

test('F6-G 25 repeated real CLI observations have byte-identical deterministic sanitized JSON',function():void{$a=hgFull();$b=hgFull();same($a['raw'],$b['raw']);hgGates($a['report']);});

test('F6-G 26 tool leaves every native source byte and private input unchanged',function():void{$before=hgSource();$files=[$GLOBALS['hg']['deployment_file'],$GLOBALS['hg']['db_file'],$GLOBALS['hg']['secret_file']];$hashes=array_map(fn($p)=>hash_file('sha256',$p),$files);$entries=scandir($GLOBALS['hg']['private_root']);hgFull();same($before,hgSource());same($GLOBALS['hg_source_before'],hgSource());same($hashes,array_map(fn($p)=>hash_file('sha256',$p),$files));same($entries,scandir($GLOBALS['hg']['private_root']));});

test('F6-G 27 live install or mutation options are rejected before metadata use',function():void{foreach(['--install','--migrate','--chmod=700','--production=true','--cron=enable'] as $arg){$r=hgFull(extra:[$arg]);same(1,$r['exit']);hgGates($r['report']);}});

test('F6-G 28 non-readonly and SQL-smuggling descriptor configs are rejected',function():void{$base=$GLOBALS['hg']['config'];$a=$base;$a['read_only']=false;$b=$base;$b['dsn'].=';MYSQL_INIT_COMMAND=DROP_DATABASE_live';$c=$base;$c['init_command']='DELETE FROM native_data';foreach([$a,$b,$c] as $i=>$value){$r=hgCli([],[],$GLOBALS['hg']['deployment_file'],hgPrivate('unsafe-db-'.$i.'.json',$value));same(1,$r['exit']);hgGates($r['report']);}});

test('F6-G 29 openssl zip curl imagick absence is not an invented global core requirement',function():void{$r=hgObserved(runtime:['extensions'=>['openssl'=>false,'zip'=>false,'curl'=>false,'imagick'=>false]]);same('PREFLIGHT_PASS_WITHIN_CHECKED_SCOPE',$r['status']);hgRow($r,'OPENSSL_OBSERVED','NOT_APPLICABLE');hgGates($r);});

test('F6-G 30 session SQL mode and UTC mismatches stay unverified without SET repair',function():void{$db=$GLOBALS['hg']['observation']->database;$db['server']['sql_mode']='';$db['server']['utc_offset']='3600';$r=hgObserved(database:$db);same('PREFLIGHT_PASS_WITHIN_CHECKED_SCOPE',$r['status']);hgRow($r,'DB_SQL_MODE','UNKNOWN');hgRow($r,'DB_UTC','UNKNOWN');hgGates($r);});

test('F6-G 31 denied required engine metadata leaves incomplete rather than guessing PASS',function():void{$db=$GLOBALS['hg']['observation']->database;$db['engine']=null;$r=hgObserved(database:$db);same('PREFLIGHT_INCOMPLETE',$r['status']);hgRow($r,'DB_INNODB','UNKNOWN');});

test('F6-G 32 MySQL or unsupported MariaDB baseline is not silently accepted',function():void{foreach(['8.4.0-MySQL','10.5.25-MariaDB','not-a-version'] as $version){$db=$GLOBALS['hg']['observation']->database;$db['server']['version']=$version;$r=hgObserved(database:$db);same('PREFLIGHT_BLOCKED',$r['status']);hgRow($r,'DB_MARIADB','FAIL');}});

test('F6-G 33 older PHP and 32-bit observations block the native baseline',function():void{foreach([['version'=>80200],['bits'=>32]] as $runtime){$r=hgObserved(runtime:$runtime);same('PREFLIGHT_BLOCKED',$r['status']);hgGates($r);}});

test('F6-G 34 missing fileinfo or intl closes only the dependent feature',function():void{$r=hgObserved(runtime:['extensions'=>['fileinfo'=>false,'intl'=>false],'functions'=>['idn_to_ascii'=>false],'finfo'=>false]);same('PREFLIGHT_PASS_WITHIN_CHECKED_SCOPE',$r['status']);hgRow($r,'FILEINFO','FAIL');hgRow($r,'INTL_IDN','FAIL');same('BLOCKED_BY_OBSERVED_PREREQUISITE',$r['feature_prerequisites']['documents']);same('BLOCKED_BY_OBSERVED_PREREQUISITE',$r['feature_prerequisites']['domains']);});

test('F6-G 35 disabled PHP file writes and flock close managed files and F6-F without a write attempt',function():void{$r=hgFull(['-d','disable_functions=fwrite,flock']);same(0,$r['exit']);hgRow($r['report'],'FILE_LOCKS','FAIL');hgRow($r['report'],'BACKUP_FILE_FUNCTIONS','FAIL');same('BLOCKED_BY_OBSERVED_PREREQUISITE',$r['report']['feature_prerequisites']['offline_backup']);hgGates($r['report']);});

test('F6-G 36 descriptor privacy cannot be guessed when UID and proc metadata are unavailable',function():void{$r=hgFull(['-d','disable_functions=posix_geteuid,file_get_contents']);same(1,$r['exit']);hgGates($r['report']);});

test('F6-G 37 permissive private descriptor mode is rejected without modification',function():void{$p=$GLOBALS['hg']['db_file'];$hash=hash_file('sha256',$p);same(true,chmod($p,0644));try{$r=hgFull();same(1,$r['exit']);same($hash,hash_file('sha256',$p));}finally{same(true,chmod($p,0600));}});

test('F6-G 38 unrelated storage directory cannot substitute for the native storage root',function():void{$d=$GLOBALS['hg']['deployment'];$d['storage']=$GLOBALS['hg']['private_root'];$r=hgCli([],[],hgPrivate('fake-native-storage.json',$d),$GLOBALS['hg']['db_file']);same(1,$r['exit']);hgRow($r['report'],'STORAGE_PATH','FAIL');});

test('F6-G 39 explicit remote DSN remains unverified without any remote connector',function():void{$c=$GLOBALS['hg']['config'];$c['dsn']='mysql:host=hosting.example.invalid;port=3306;dbname=private_database;charset=utf8mb4';$r=hgCli([],[],$GLOBALS['hg']['deployment_file'],hgPrivate('remote-db.json',$c));same(2,$r['exit']);hgRow($r['report'],'DB_CONNECTION','UNKNOWN');same(false,str_contains($r['raw'],'hosting.example.invalid'));hgGates($r['report']);});

test('F6-G 40 caller flags cannot create production permission',function():void{$d=$GLOBALS['hg']['deployment'];$d['production_allowed']=true;$r=hgCli([],[],hgPrivate('forged-gate.json',$d),$GLOBALS['hg']['db_file']);same(1,$r['exit']);hgGates($r['report']);});

test('F6-G 41 hard-linked private descriptor is rejected and original bytes survive',function():void{$p=$GLOBALS['hg']['db_file'];$alias=$GLOBALS['hg']['private_root'].'/hardlinked-db';$hash=hash_file('sha256',$p);same(true,link($p,$alias));try{$r=hgFull();same(1,$r['exit']);same($hash,hash_file('sha256',$p));}finally{same(true,unlink($alias));}});

test('F6-G 42 readonly native installation permissions and supplied readonly mount cannot pass storage writes',function():void{
 $copy=$GLOBALS['hg']['private_root'].'/readonly-installation';same(true,mkdir($copy,0700));foreach(['bin','bootstrap','src','src/HostingPreflight','public','storage'] as $dir){same(true,mkdir($copy.'/'.$dir,0700));}
 $files=['bin/hosting-preflight.php','bootstrap/autoload.php'];foreach(glob(dirname(__DIR__).'/src/HostingPreflight/*.php') as $file){$files[]='src/HostingPreflight/'.basename($file);}foreach($files as $file){same(true,copy(dirname(__DIR__).'/'.$file,$copy.'/'.$file));same(true,chmod($copy.'/'.$file,0400));}
 foreach(['bin','bootstrap','src/HostingPreflight','src','public','storage',''] as $dir){same(true,chmod(rtrim($copy.'/'.$dir,'/'),0500));}
 $roots=[$copy];if(is_file('/input/bin/hosting-preflight.php')&&is_dir('/input/storage')){$roots[]='/input';}
 foreach($roots as $index=>$root){same(false,is_writable($root.'/storage'));$d=$GLOBALS['hg']['deployment'];$d['webroot']=$root.'/public';$d['storage']=$root.'/storage';$path=hgPrivate('readonly-layout-'.$index.'.json',$d);$pipes=[];$proc=proc_open([PHP_BINARY,$root.'/bin/hosting-preflight.php','--deployment-fd=3','--db-config-fd=4'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w'],3=>['file',$path,'r'],4=>['file',$GLOBALS['hg']['db_file'],'r']],$pipes,null,getenv());same(true,is_resource($proc));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(1,proc_close($proc));same('',$err);$r=json_decode($out,true,32,JSON_THROW_ON_ERROR);hgRow($r,'STORAGE_PATH','FAIL');hgGates($r);}
});

test('F6-G 43 duplicate and reused descriptor options fail closed',function():void{foreach([['--db-config-fd=4'],['--deployment-fd=4']] as $extra){$r=hgFull(extra:$extra);same(1,$r['exit']);hgGates($r['report']);}});

test('F6-G 44 real metadata account queries are exactly allowlisted SELECTs with zero mutation',function():void{$admin=$GLOBALS['hg']['admin'];$users=[$GLOBALS['hg']['full_user'],$GLOBALS['hg']['limited_user']];$rows=$admin->query("SELECT user_host,argument,command_type FROM mysql.general_log WHERE command_type IN ('Query','Prepare','Execute')")->fetchAll();$checked=0;$executed=0;foreach($rows as $row){$owned=false;foreach($users as $user){$owned=$owned||str_starts_with($row['user_host'],$user.'[');}if(!$owned){continue;}++$checked;if($row['command_type']!=='Prepare'){++$executed;}if(!in_array($row['argument'],Metadata::QUERIES,true)){preg_match('/\A([A-Za-z_]+)(?:\s+([A-Za-z_]+))?/',$row['argument'],$safe);throw new RuntimeException('F6G_QUERY_NOT_ALLOWLISTED_'.strtoupper(($safe[1]??'OTHER').'_'.($safe[2]??'')).'_SHA256_'.hash('sha256',$row['argument']));}same(1,preg_match('/\ASELECT\b/',$row['argument']));same(0,preg_match('/\b(CREATE|ALTER|DROP|INSERT|UPDATE|DELETE|GRANT|REVOKE|SET|INTO\s+OUTFILE)\b/i',$row['argument']));}if($checked<40||$executed<40){throw new RuntimeException('F6G_QUERY_ACCOUNT_COUNT_'.(string)$checked.'_EXECUTED_'.(string)$executed);}same($GLOBALS['hg_source_before'],hgSource());$admin->exec('SET GLOBAL general_log=0');});

test('F6-G 45 explicit metadata query denial remains UNKNOWN and cannot leak its exception',function():void{$b=$GLOBALS['hg']['observation'];$db=Metadata::inspect(function($sql)use($b){if($sql===Metadata::QUERIES['disks']){throw new PDOException('F6G_SECRET_METADATA_DENIAL_CANARY');}return match($sql){Metadata::QUERIES['server']=>$b->database['server'],Metadata::QUERIES['engine']=>$b->database['engine'],Metadata::QUERIES['plugin']=>$b->database['plugin'],default=>throw new LogicException('Unexpected metadata query')};});$r=hgObserved(database:$db);hgRow($r,'DB_DISKS','UNKNOWN');same('DENIED',hgCheck($r,'DB_DISKS')['reason']);same(false,str_contains(json_encode($r),'F6G_SECRET_METADATA_DENIAL_CANARY'));hgGates($r);});



foreach($GLOBALS['tests'] as $name=>$work){$GLOBALS['tests'][$name]=static function()use($work):void{try{$work();}catch(Throwable $e){echo 'F6G_OBSERVATION '.json_encode(['exception'=>get_class($e),'trace'=>array_slice(array_map(static fn($f)=>['function'=>$f['function']??'','class'=>$f['class']??'','file'=>basename($f['file']??'')],$e->getTrace()),0,7)],JSON_THROW_ON_ERROR).PHP_EOL;throw $e;}};}
exit(executeTests(true));
