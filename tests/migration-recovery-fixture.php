<?php
declare(strict_types=1);
require_once __DIR__.'/migration-operations-fixture.php';
use Hostider\Core\CanonicalJson;
use Hostider\Migration\Contract;
use Hostider\Migration\Operations\{RecoverySigner,RecoveryAuthorization};

function recoveryFixture(): array
{
    putenv('SYNTHETIC_RECOVERY_ALLOWED=1');$f=operationsFixture();
    $pair=sodium_crypto_sign_keypair();$f['recovery_secret']=sodium_crypto_sign_secretkey($pair);
    $f['signer']=new RecoverySigner('recovery-v1',$f['recovery_secret']);
    $f['trust']=['recovery-v1'=>['public_key'=>$f['signer']->publicKey(),'revoked'=>false]];
    $report=$f['reader']->inspect(\Hostider\Migration\Synthetic\Fixture::named('baseline'))->jsonSerialize();
    $f['roots']=Contract::hash($report['content']['source_snapshot_roots']);
    $f['backup']=$f['vault']->backup($f['source'],$f['crypto'],'synthetic-v1');
    $f['capsule']=$f['vault']->recoveryCapsule($f['backup']['backup_id'],$f['crypto'],'synthetic-v1',$f['signer']);
    return $f;
}
function recoveryPolicy(array $f,array $overrides=[],?array $keys=null): RecoveryAuthorization
{
    $p=array_replace(['backup'=>$f['backup']['backup_id'],'roots'=>$f['roots'],'snapshot'=>$f['snapshot'],'key'=>'synthetic-v1','operator'=>'synthetic-recovery-operator','expires'=>time()+600],$overrides);
    return RecoveryAuthorization::allow($keys??$f['trust'],$p['backup'],$p['roots'],$p['snapshot'],$p['key'],$p['operator'],$p['expires']);
}
function recoveryExpireVault(array $f): void
{
    $path=operationsPath($f['vault'],'lease.json');$lease=json_decode(file_get_contents($path),true,8,JSON_THROW_ON_ERROR);$lease['expires']=time()-1;
    file_put_contents($path,CanonicalJson::encode($lease));
}
/** Fully owned cryptographic malformed fixture; not an application signing API. */
function recoveryResign(array $f,array $outer): string
{
    $outer['signature']=base64_encode(sodium_crypto_sign_detached(RecoverySigner::CONTEXT.CanonicalJson::encode($outer['attestation']),$f['recovery_secret']));return CanonicalJson::encode($outer);
}
function recoveryChildInput(array $f,array $overrides=[]): array
{
    return array_replace(['capsule'=>$f['capsule'],'key'=>base64_encode($f['key']),'trust'=>$f['trust'],'backup'=>$f['backup']['backup_id'],'roots'=>$f['roots'],'snapshot'=>$f['snapshot'],'key_id'=>'synthetic-v1','expires'=>time()+600,'fault'=>'NONE'],$overrides);
}
function recoveryChildren(array $input,int $count=1): array
{
    $env=['APP_ENV'=>'test','SYNTHETIC_IMPORT_ALLOWED'=>'1','SYNTHETIC_BACKUP_ALLOWED'=>'1','SYNTHETIC_RECOVERY_ALLOWED'=>'1'];
    foreach(['PATH','SystemRoot','WINDIR','TEMP','TMP','PHP_INI_SCAN_DIR','LD_LIBRARY_PATH'] as $key){if(getenv($key)!==false){$env[$key]=getenv($key);}}
    $children=[];for($i=0;$i<$count;++$i){$cmd=[PHP_BINARY];if(php_ini_loaded_file()!==false){array_push($cmd,'-c',php_ini_loaded_file());}$cmd[]=__DIR__.'/migration-recovery-child.php';$pipes=[];$p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);if(!is_resource($p)){throw new RuntimeException('R1_CHILD');}
        fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$p,$pipes];}
    $out=[];foreach($children as [$p,$pipes]){$raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);$out[]=['exit'=>$exit,'stderr_empty'=>$err==='','report'=>$raw===''?null:json_decode($raw,true,64,JSON_THROW_ON_ERROR)];}return $out;
}
