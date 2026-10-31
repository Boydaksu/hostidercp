<?php
declare(strict_types=1);
require_once __DIR__.'/migration-reconciliation-fixture.php';
use Hostider\Core\{Config,CanonicalJson};
use Hostider\Core\Database\{Database,Migrator};
use Hostider\Core\Security\Crypto;
use Hostider\Migration\Synthetic\Sandbox;
use Hostider\Migration\Operations\{Snapshot,Vault,Destination};

function operationsFixture(): array
{
    putenv('SYNTHETIC_BACKUP_ALLOWED=1');$f=reconciliationFixture();$f['source']=Snapshot::open($f['sandbox'],...$f['reader_params']);$GLOBALS['f6d_vaults'][]=$f['source']->samples->identity;$f['vault']=Vault::create();$GLOBALS['f6d_vaults'][]=$f['vault']->identity;$f['key']=random_bytes(32);$f['crypto']=new Crypto($f['key'],'synthetic-v1');return $f;
}
function operationsTarget(array $f): array
{
    $schema='f6d_'.bin2hex(random_bytes(12));$user='f6d_'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(32));$secret=bin2hex(random_bytes(32));$host=$f['host'];$root=$f['root'];
    $GLOBALS['f6d_owned'][]=[$root,$schema,$user,$host];$root->execute('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    $root->execute("CREATE USER '".$user."'@'".$host."' IDENTIFIED BY '".$password."'");
    $dsn='mysql:host=127.0.0.1;port='.$f['params'][0].';dbname='.$schema.';charset=utf8mb4';$setup=Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),$dsn,getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:''));
    (new Migrator($setup,Sandbox::directory()))->migrate();$setup->execute('DELETE FROM schema_migrations');
    $setup->execute('CREATE TABLE f6d_restore_marker(id INT PRIMARY KEY,schema_name VARCHAR(64),account_name VARCHAR(64),token_hash CHAR(64),vault_id CHAR(24),expires_at DATETIME(6)) ENGINE=InnoDB');
    $files=Vault::create();$GLOBALS['f6d_vaults'][]=$files->identity;
    $setup->execute('INSERT INTO f6d_restore_marker VALUES(1,?,?,?,?,TIMESTAMPADD(MINUTE,30,UTC_TIMESTAMP(6)))',[$schema,$user,hash('sha256',$secret),$files->identity]);
    $root->execute("GRANT SELECT ON `".str_replace('_','\\_',$schema)."`.* TO '".$user."'@'".$host."'");foreach(Sandbox::TABLES as $table){$root->execute("GRANT INSERT ON `".$schema.'`.`'.$table."` TO '".$user."'@'".$host."'");}
    $params=[$f['params'][0],$schema,$user,$password,$secret];$target=Destination::open(...[...$params,$files,$setup]);return compact('schema','user','setup','files','params','target');
}
function operationsPath(Vault $vault,string $suffix=''): string {return dirname(__DIR__).'/storage/f6d-'.$vault->identity.($suffix===''?'':'/'.$suffix);}
function operationsCleanup(): void
{
    foreach($GLOBALS['f6d_owned']??[] as [$root,$schema,$user,$host]){if(preg_match('/^f6d_[a-f0-9]{24}$/D',$schema)!==1||preg_match('/^f6d_[a-f0-9]{16}$/D',$user)!==1){throw new RuntimeException('F6D_CLEANUP');}$root->execute('DROP DATABASE `'.$schema.'`');$root->execute("DROP USER '".$user."'@'".$host."'");}
    foreach($GLOBALS['f6d_vaults']??[] as $id){if(preg_match('/^[a-f0-9]{24}$/D',$id)!==1){throw new RuntimeException('F6D_CLEANUP');}$root=dirname(__DIR__).'/storage/f6d-'.$id;if(is_link($root)){throw new RuntimeException('F6D_CLEANUP_LINK');}foreach(new DirectoryIterator($root) as $file){if($file->isDot()){continue;}if($file->isDir()&&!$file->isLink()){throw new RuntimeException('F6D_CLEANUP_DIRECTORY');}unlink($file->getPathname());}rmdir($root);}
    $GLOBALS['f6d_owned']=[];$GLOBALS['f6d_vaults']=[];reconciliationCleanup();
}
function operationsChildren(array $f,int $count,string $mode='BACKUP',string $fault='NONE',?array $target=null): array
{
    $env=['APP_ENV'=>'test','SYNTHETIC_IMPORT_ALLOWED'=>'1','SYNTHETIC_BACKUP_ALLOWED'=>'1'];foreach(['PATH','SystemRoot','WINDIR','TEMP','TMP','PHP_INI_SCAN_DIR','LD_LIBRARY_PATH'] as $key){if(getenv($key)!==false){$env[$key]=getenv($key);}}
    $children=[];for($i=0;$i<$count;++$i){$cmd=[PHP_BINARY];if(php_ini_loaded_file()!==false){array_push($cmd,'-c',php_ini_loaded_file());}$cmd[]=__DIR__.'/migration-operations-child.php';$pipes=[];$p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);if(!is_resource($p)){throw new RuntimeException('F6D_CHILD');}fwrite($pipes[0],json_encode(['authority'=>$f['params'],'reader'=>$f['reader_params'],'samples'=>$f['source']->samples->capability(),'vault'=>$f['vault']->capability(),'key'=>base64_encode($f['key']),'fault'=>$fault,'mode'=>$mode,'backup_id'=>$f['backup']['backup_id']??null,'target'=>$target?['params'=>$target['params'],'vault'=>$target['files']->capability(),'witness_user'=>getenv('TEST_DB_USER')?:'root','witness_password'=>getenv('TEST_DB_PASSWORD')?:'']:null],JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$p,$pipes];}
    $out=[];foreach($children as [$p,$pipes]){$raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);$out[]=['exit'=>$exit,'stderr_empty'=>$err==='','result'=>$raw===''?null:json_decode($raw,true,64,JSON_THROW_ON_ERROR)];}return $out;
}
