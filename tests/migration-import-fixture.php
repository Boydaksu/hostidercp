<?php
declare(strict_types=1);
require_once __DIR__.'/database.php';
use Hostider\Core\{Config,CanonicalJson};
use Hostider\Core\Database\{Database,Migrator};
use Hostider\Migration\Synthetic\{Sandbox,Fixture,Loader};

/** SYNTHETIC provisioning authority, used only by this isolated test harness. */
function importAdmin(): array
{
    $dsn=getenv('TEST_DB_DSN')?:'';
    if(getenv('APP_ENV')!=='test' || preg_match('/^mysql:host=127\.0\.0\.1;(?:port=([0-9]+);)?dbname=hostider_f0_test[a-zA-Z0-9_]*(?:;charset=utf8mb4)?$/D',$dsn,$m)!==1){throw new RuntimeException('F6B_TEST_ADMIN_DENIED');}
    return ['root'=>integrationDb(),'port'=>(int)($m[1]??3306)];
}
function importFixture(): array
{
    try{return provisionImportFixture();}catch(Throwable $error){$safe=preg_match('/^F6B_[A-Z0-9_]+$/D',$error->getMessage())===1?$error->getMessage():preg_replace('/[^A-Z0-9]/','',(string)$error->getCode());throw new RuntimeException('F6B_TEST_SETUP_FAILED_'.$safe);}
}
function provisionImportFixture(): array
{
    $a=importAdmin();$root=$a['root'];$schema='f6b_'.bin2hex(random_bytes(12));$user='f6b_'.bin2hex(random_bytes(8));$migration='f6b_m_'.bin2hex(random_bytes(8));
    $password=bin2hex(random_bytes(32));$migrationPassword=bin2hex(random_bytes(32));$secret=bin2hex(random_bytes(32));$snapshot=bin2hex(random_bytes(32));
    $owned=[$root,$schema,$user,$migration];$GLOBALS['f6b_owned'][]=$owned;
    $root->execute('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    foreach([[$user,$password],[$migration,$migrationPassword]] as [$account,$value]){$root->execute("CREATE USER '".$account."'@'127.0.0.1' IDENTIFIED BY '".$value."'");}
    $schemaPrivilege=str_replace('_','\\_',$schema);
    $root->execute("GRANT SELECT,INSERT,UPDATE,CREATE,ALTER,INDEX,REFERENCES,TRIGGER ON `".$schemaPrivilege."`.* TO '".$migration."'@'127.0.0.1'");
    $dsn='mysql:host=127.0.0.1;port='.$a['port'].';dbname='.$schema.';charset=utf8mb4';
    $setup=Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),$dsn,$migration,$migrationPassword));
    (new Migrator($setup,Sandbox::directory()))->migrate();
    $setup->execute('INSERT INTO f6b_environment VALUES (1,?,?,?,?,TIMESTAMPADD(MINUTE,30,UTC_TIMESTAMP(6)))',[$schema,$user,hash('sha256',$secret),$snapshot]);
    $schemaPrivilege=str_replace('_','\\_',$schema);
    $root->execute("GRANT SELECT ON `".$schemaPrivilege."`.* TO '".$user."'@'127.0.0.1'");
    foreach(Sandbox::INSERT_TABLES as $table){$root->execute("GRANT INSERT ON `".$schema.'`.`'.$table."` TO '".$user."'@'127.0.0.1'");}
    $root->execute("GRANT UPDATE ON `".$schema."`.`f6b_runs` TO '".$user."'@'127.0.0.1'");
    putenv('SYNTHETIC_IMPORT_ALLOWED=1');
    $params=[$a['port'],$schema,$user,$password,$secret];$sandbox=Sandbox::open(...$params);
    return compact('root','setup','schema','user','secret','snapshot','params','sandbox');
}
function importCleanup(): void
{
    foreach(array_reverse($GLOBALS['f6b_owned']??[]) as [$root,$schema,$user,$migration]){
        if(preg_match('/^f6b_[a-f0-9]{24}$/D',$schema)!==1 || preg_match('/^f6b_[a-f0-9]{16}$/D',$user)!==1 || preg_match('/^f6b_m_[a-f0-9]{16}$/D',$migration)!==1){throw new RuntimeException('F6B_TEARDOWN_DENIED');}
        $root->execute('DROP DATABASE IF EXISTS `'.$schema.'`');
        foreach([$user,$migration] as $account){$root->execute("DROP USER IF EXISTS '".$account."'@'127.0.0.1'");}
    }
    $GLOBALS['f6b_owned']=[];
}
function importCounts(Sandbox $s): array
{
    $counts=[];foreach(Sandbox::TABLES as $table){$counts[$table]=(int)$s->db->execute('SELECT COUNT(*) FROM '.$table)->fetchColumn();}return $counts;
}
function importPayload(Sandbox $s,string $type,string $id): array
{
    $identity=new Hostider\Migration\SourceIdentity('synthetic-whmcs-a',Hostider\Migration\Entity::from($type),$id);
    return json_decode($s->db->execute('SELECT payload_json FROM f6b_entities WHERE source_key=?',[$identity->key()])->fetchColumn(),true,32,JSON_THROW_ON_ERROR);
}
function importChildren(array $f,int $count,string $fixture='baseline',string $fault='NONE',int $batch=256): array
{
    $env=[];foreach(['PATH','SystemRoot','WINDIR','TEMP','TMP','PHP_INI_SCAN_DIR','LD_LIBRARY_PATH'] as $key){$value=getenv($key);if($value!==false){$env[$key]=$value;}}
    $env['APP_ENV']='test';$env['SYNTHETIC_IMPORT_ALLOWED']='1';$children=[];
    for($i=0;$i<$count;++$i){
        $command=[PHP_BINARY];$ini=php_ini_loaded_file();if($ini!==false){array_push($command,'-c',$ini);} $command[]=__DIR__.'/migration-import-child.php';
        $pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);
        if(!is_resource($process)){throw new RuntimeException('F6B_CHILD_START');}
        fwrite($pipes[0],json_encode(['params'=>$f['params'],'fixture'=>$fixture,'fault'=>$fault,'batch'=>$batch],JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$process,$pipes];
    }
    $results=[];foreach($children as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);$results[]=['exit'=>$exit,'stderr_empty'=>$err==='','result'=>$out===''?null:json_decode($out,true,64,JSON_THROW_ON_ERROR)];}return $results;
}
