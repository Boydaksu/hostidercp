<?php
declare(strict_types=1);
require_once __DIR__.'/migration-recovery-fixture.php';
use Hostider\Core\{Config,CanonicalJson};
use Hostider\Core\Database\Database;
use Hostider\Migration\Contract;
use Hostider\Migration\Operations\{RecoveryContext,RecoveryTargetPolicy,RecoveryInspector};

function targetFixture(bool $restore=true): array
{
    $f=recoveryFixture();$f['target']=operationsTarget($f);if($restore){$f['vault']->restore($f['backup']['backup_id'],$f['crypto'],'synthetic-v1',$f['target']['target']);}
    $name='r2_'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(32));$f['root']->execute("CREATE USER '".$name."'@'".$f['host']."' IDENTIFIED BY '".$password."'");$GLOBALS['r2_owned'][]=[$f['root'],$name,$f['host']];
    $f['root']->execute("GRANT SELECT ON `".str_replace('_','\\_',$f['target']['schema'])."`.* TO '".$name."'@'".$f['host']."'");
    $marker=$f['target']['setup']->execute('SELECT id,schema_name,account_name,token_hash,vault_id,expires_at FROM f6d_restore_marker WHERE id=1')->fetch();
    $create=$f['target']['setup']->execute('SHOW CREATE TABLE f6d_restore_marker')->fetch(PDO::FETCH_NUM);
    $f['binding']=['contract'=>'hostider.recovery-target.v1','port'=>$f['params'][0],'schema'=>$f['target']['schema'],'account'=>$name,'marker_sha256'=>Contract::hash($marker),'marker_schema_sha256'=>Contract::hash($create[1]),'epoch'=>1];$f['target_password']=$password;
    return $f;
}
function targetContext(array $f,array $override=[]): RecoveryContext {return RecoveryContext::open($f['capsule'],$f['crypto'],recoveryPolicy($f,$override));}
function targetPolicy(array $f,array $binding=[],?int $expires=null,int $epoch=1,bool $revoked=false,string $purpose='READ_ONLY_RECONCILIATION'): RecoveryTargetPolicy
{
    return RecoveryTargetPolicy::allow(array_replace($f['binding'],$binding),$f['backup']['backup_id'],$epoch,$expires??time()+240,$revoked,$purpose);
}
function targetInspect(array $f): array {return RecoveryInspector::open(targetContext($f),targetPolicy($f),$f['target_password'])->inspect()->jsonSerialize();}
function targetReader(array $f): Database
{
    return Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),'mysql:host=127.0.0.1;port='.$f['binding']['port'].';dbname='.$f['binding']['schema'].';charset=utf8mb4',$f['binding']['account'],$f['target_password']));
}
function targetCleanup(): void
{
    foreach($GLOBALS['r2_owned']??[] as [$db,$name,$host]){if(preg_match('/^r2_[a-f0-9]{16}$/D',$name)!==1||($host!=='localhost'&&filter_var($host,FILTER_VALIDATE_IP)===false)){throw new RuntimeException('R2_CLEANUP');}$db->execute("DROP USER IF EXISTS '".$name."'@'".$host."'");}$GLOBALS['r2_owned']=[];operationsCleanup();
}
function targetChildInput(array $f,array $override=[]): array {return array_replace(recoveryChildInput($f),['binding'=>$f['binding'],'target_password'=>$f['target_password'],'target_expires'=>time()+240,'epoch'=>1],$override);}
function targetChildren(array $input,int $count=1): array
{
    $env=['APP_ENV'=>'test','SYNTHETIC_IMPORT_ALLOWED'=>'1','SYNTHETIC_BACKUP_ALLOWED'=>'1','SYNTHETIC_RECOVERY_ALLOWED'=>'1'];foreach(['PATH','SystemRoot','WINDIR','TEMP','TMP','PHP_INI_SCAN_DIR','LD_LIBRARY_PATH'] as $key){if(getenv($key)!==false){$env[$key]=getenv($key);}}
    $children=[];for($i=0;$i<$count;++$i){$cmd=[PHP_BINARY];if(php_ini_loaded_file()!==false){array_push($cmd,'-c',php_ini_loaded_file());}$cmd[]=__DIR__.'/migration-recovery-target-child.php';$pipes=[];$p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);if(!is_resource($p)){throw new RuntimeException('R2_CHILD');}fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$p,$pipes];}
    $out=[];foreach($children as [$p,$pipes]){$raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);$out[]=['exit'=>$exit,'stderr_empty'=>$err==='','report'=>$raw===''?null:json_decode($raw,true,64,JSON_THROW_ON_ERROR)];}return $out;
}
