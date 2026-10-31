<?php
declare(strict_types=1);
require_once __DIR__.'/migration-import-fixture.php';
use Hostider\Core\{Config,CanonicalJson};
use Hostider\Core\Database\Database;
use Hostider\Migration\{Contract,Entity,SourceIdentity};
use Hostider\Migration\Synthetic\{Fixture,Loader,Plan,Sandbox};
use Hostider\Migration\Reconciliation\Inspector;

function reconciliationFixture(bool $load=true): array
{
    $f=importFixture();$name='f6c_'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(32));
    $GLOBALS['f6c_owned'][]=[$f['root'],$name,$f['host']];
    $f['root']->execute("CREATE USER '".$name."'@'".$f['host']."' IDENTIFIED BY '".$password."'");
    $f['root']->execute("GRANT SELECT ON `".str_replace('_','\\_',$f['schema'])."`.* TO '".$name."'@'".$f['host']."'");
    $f['reader_params']=[$f['params'][0],$name,$password];
    $f['reader']=Inspector::open($f['sandbox'],...$f['reader_params']);
    $f['read_db']=Database::connect(new Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),
        'mysql:host=127.0.0.1;port='.$f['params'][0].';dbname='.$f['schema'].';charset=utf8mb4',$name,$password));
    if($load){$f['loaded']=(new Loader())->load(Fixture::named('baseline'),$f['sandbox']);if($f['loaded']['status']!=='APPLIED'){throw new RuntimeException('F6C_TEST_LOAD');}}
    return $f;
}
function reconciliationCleanup(): void
{
    foreach($GLOBALS['f6c_owned']??[] as [$db,$name,$host]){if(preg_match('/^f6c_[a-f0-9]{16}$/D',$name)!==1 || ($host!=='localhost'&&filter_var($host,FILTER_VALIDATE_IP)===false)){throw new RuntimeException('F6C_TEST_CLEANUP');}$db->execute("DROP USER IF EXISTS '".$name."'@'".$host."'");}
    $GLOBALS['f6c_owned']=[];importCleanup();
}
function reconciliationResult(array $f,string $fixture='baseline',?array $checkpoint=null): array
{
    return $f['reader']->inspect(Fixture::named($fixture),$checkpoint)->jsonSerialize();
}
function reconciliationKey(string $type,string $id): string {return (new SourceIdentity('synthetic-whmcs-a',Entity::from($type),$id))->key();}
function reconciliationMutate(array $f,string $type,string $id,Closure $change,bool $rehash=true): void
{
    $key=reconciliationKey($type,$id);$p=importPayload($f['sandbox'],$type,$id);$p=$change($p);
    if($rehash){$f['setup']->execute('UPDATE f6b_entities SET payload_json=?,payload_hash=? WHERE source_key=?',[CanonicalJson::encode($p),Contract::hash($p),$key]);}
    else{$f['setup']->execute('UPDATE f6b_entities SET payload_json=? WHERE source_key=?',[CanonicalJson::encode($p),$key]);}
}
function reconciliationDelete(array $f,string $table,string $key): void
{
    if(!in_array($table,['f6b_claims','f6b_provenance','f6b_mappings','f6b_entities'],true)||preg_match('/^f6b_[a-f0-9]{24}$/D',$f['schema'])!==1){throw new RuntimeException('F6C_TEST_DELETE');}
    $f['root']->execute('DELETE FROM `'.$f['schema'].'`.'.$table.' WHERE source_key=?',[$key]);
}
function reconciliationState(array $f): string
{
    $all=[];foreach(Sandbox::TABLES as $table){$rows=$f['sandbox']->db->execute('SELECT * FROM '.$table)->fetchAll();usort($rows,static fn(array $a,array $b): int=>strcmp(CanonicalJson::encode($a),CanonicalJson::encode($b)));$all[$table]=$rows;}return Contract::hash($all);
}
function reconciliationChildren(array $f,int $count): array
{
    $env=['APP_ENV'=>'test','SYNTHETIC_IMPORT_ALLOWED'=>'1'];foreach(['PATH','SystemRoot','WINDIR','TEMP','TMP','PHP_INI_SCAN_DIR','LD_LIBRARY_PATH'] as $key){if(getenv($key)!==false){$env[$key]=getenv($key);}}
    $children=[];
    for($i=0;$i<$count;++$i){$cmd=[PHP_BINARY];if(php_ini_loaded_file()!==false){array_push($cmd,'-c',php_ini_loaded_file());}$cmd[]=__DIR__.'/migration-reconciliation-child.php';$pipes=[];$process=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);if(!is_resource($process)){throw new RuntimeException('F6C_CHILD');}fwrite($pipes[0],json_encode(['authority'=>$f['params'],'reader'=>$f['reader_params']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$process,$pipes];}
    $results=[];foreach($children as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);$results[]=['exit'=>$exit,'stderr_empty'=>$err==='','report'=>json_decode($out,true,64,JSON_THROW_ON_ERROR)];}return $results;
}
