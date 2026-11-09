<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
require __DIR__.'/migration-recovery-fixture.php';
use Hostider\Core\CanonicalJson;
use Hostider\Core\Security\Crypto;
use Hostider\Migration\Contract;
use Hostider\Migration\Synthetic\Sandbox;
use Hostider\Migration\Operations\{AuthenticatedBackup,ColdRecovery,RecoverySigner,RecoveryAuthorization,Vault};

test('F6-D-R1 cold verification succeeds in a fresh process after source schema loss and Vault expiry',function(): void{
    $f=recoveryFixture();recoveryExpireVault($f);same('F6D_LEASE',raises(InvalidArgumentException::class,fn()=>$f['vault']->verify($f['backup']['backup_id'],$f['crypto'],'synthetic-v1'))->getMessage());
    if(preg_match('/^f6b_[a-f0-9]{24}$/D',$f['schema'])!==1){throw new RuntimeException('R1_OWNED_SOURCE');}$f['root']->execute('DROP DATABASE `'.$f['schema'].'`');
    same(0,(int)$f['root']->execute('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[$f['schema']])->fetchColumn());
    $child=recoveryChildren(recoveryChildInput($f))[0];same(0,$child['exit']);same(true,$child['stderr_empty']);same('VERIFIED_PRE_ENROLLED_ESCROW',$child['report']['verification']);same(false,$child['report']['source_connection_required']);same(false,$child['report']['operational_vault_required']);same('BLOCKED_IDENTITY_BOUND',$child['report']['native_recovery']);
});
test('F6-D-R1 enrollment preserves original artifact issuer MAC backup identity and legacy verification',function(): void{
    $f=recoveryFixture();$path=operationsPath($f['vault'],$f['backup']['backup_id'].'.sealed');$before=file_get_contents($path);$v=$f['crypto']->decrypt($before,'f6d-backup:'.$f['backup']['backup_id']);$capsule=$f['vault']->recoveryCapsule($f['backup']['backup_id'],$f['crypto'],'synthetic-v1',$f['signer']);
    same(true,hash_equals($before,file_get_contents($path)));same(true,hash_equals($before,json_decode($capsule,true,16,JSON_THROW_ON_ERROR)['ciphertext']));same(true,hash_equals($v,$f['crypto']->decrypt($before,'f6d-backup:'.$f['backup']['backup_id'])));same('VERIFIED',$f['vault']->verify($f['backup']['backup_id'],$f['crypto'],'synthetic-v1')['state']);same($f['backup']['logical_sha256'],ColdRecovery::verify($capsule,$f['crypto'],recoveryPolicy($f))['logical_sha256']);
});
test('F6-D-R1 encryption key without a trusted issuer authority cannot verify or self-enrol',function(): void{
    $f=recoveryFixture();$rogue=new RecoverySigner('recovery-v1',sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));
    raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f,[],['recovery-v1'=>['public_key'=>$rogue->publicKey(),'revoked'=>false]])));
    raises(InvalidArgumentException::class,fn()=>recoveryPolicy($f,[],[]));raises(TypeError::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],null));
    raises(TypeError::class,fn()=>$f['signer']->attest(['data'=>[]],'fake',$f['vault']));
});
test('F6-D-R1 trusted issuer authority without the correct encryption key is denied',function(): void{
    $f=recoveryFixture();raises(RuntimeException::class,fn()=>ColdRecovery::verify($f['capsule'],new Crypto(random_bytes(32),'synthetic-v1'),recoveryPolicy($f)));
    raises(TypeError::class,fn()=>ColdRecovery::verify($f['capsule'],null,recoveryPolicy($f)));raises(RuntimeException::class,fn()=>ColdRecovery::verify($f['capsule'],new Crypto($f['key'],'rotated'),recoveryPolicy($f)));
});
test('F6-D-R1 wrong backup source root target snapshot and encryption version are denied',function(): void{
    $f=recoveryFixture();foreach(['backup','roots','snapshot'] as $field){raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f,[$field=>str_repeat('0',64)])));}
    raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f,['key'=>'another-version'])));
});
test('F6-D-R1 revoked untrusted and malformed pinned issuer keys fail closed',function(): void{
    $f=recoveryFixture();$keys=$f['trust'];$keys['recovery-v1']['revoked']=true;raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f,[],$keys)));
    raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f,[],['different-v1'=>$f['trust']['recovery-v1']])));
    raises(InvalidArgumentException::class,fn()=>recoveryPolicy($f,[],['recovery-v1'=>['public_key'=>'bad','revoked'=>false]]));
});
test('F6-D-R1 forged detached signature and wrong signing context cannot authenticate',function(): void{
    $f=recoveryFixture();$outer=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);$signature=base64_decode($outer['signature'],true);$signature[0]=chr(ord($signature[0])^1);$outer['signature']=base64_encode($signature);
    raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(CanonicalJson::encode($outer),$f['crypto'],recoveryPolicy($f)));
    $outer['signature']=base64_encode(sodium_crypto_sign_detached('wrong-context'.CanonicalJson::encode($outer['attestation']),$f['recovery_secret']));raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(CanonicalJson::encode($outer),$f['crypto'],recoveryPolicy($f)));
});
test('F6-D-R1 encryption-key forged issuer MAC cannot reuse the original recovery signature',function(): void{
    $f=recoveryFixture();$outer=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);$v=json_decode($f['crypto']->decrypt($outer['ciphertext'],'f6d-backup:'.$f['backup']['backup_id']),true,32,JSON_THROW_ON_ERROR);$v['issuer_mac']=str_repeat('0',64);$outer['ciphertext']=$f['crypto']->encrypt(CanonicalJson::encode($v),'f6d-backup:'.$f['backup']['backup_id']);
    raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(CanonicalJson::encode($outer),$f['crypto'],recoveryPolicy($f)));$outer['attestation']['cipher_sha256']=hash('sha256',$outer['ciphertext']);raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(CanonicalJson::encode($outer),$f['crypto'],recoveryPolicy($f)));
});
test('F6-D-R1 corrupt truncated and substituted ciphertext cannot verify',function(): void{
    $f=recoveryFixture();$outer=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);foreach(['synthetic-v1:AAAA',substr($outer['ciphertext'],0,-4),$outer['ciphertext'].'AAAA'] as $bad){$c=$outer;$c['ciphertext']=$bad;raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(CanonicalJson::encode($c),$f['crypto'],recoveryPolicy($f)));}
});
test('F6-D-R1 independently signed corrupt AEAD tag still fails native decryption',function(): void{
    $f=recoveryFixture();$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);[$id,$encoded]=explode(':',$o['ciphertext'],2);$bytes=base64_decode($encoded,true);$last=strlen($bytes)-1;$bytes[$last]=chr(ord($bytes[$last])^1);$o['ciphertext']=$id.':'.base64_encode($bytes);$o['attestation']['cipher_sha256']=hash('sha256',$o['ciphertext']);
    same('R1_DECRYPTION',raises(RuntimeException::class,fn()=>ColdRecovery::verify(recoveryResign($f,$o),$f['crypto'],recoveryPolicy($f)))->getMessage());
});
test('F6-D-R1 tampered manifest unknown format and embedded trust key are rejected',function(): void{
    $f=recoveryFixture();$outer=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);foreach(['contract'=>'unknown.v99','purpose'=>'ACTIVATE_NATIVE','logical_sha256'=>str_repeat('0',64),'authority_key_id'=>'unknown'] as $key=>$value){$c=$outer;$c['attestation'][$key]=$value;raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(CanonicalJson::encode($c),$f['crypto'],recoveryPolicy($f)));}
    $outer['public_key']=$f['signer']->publicKey();raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(CanonicalJson::encode($outer),$f['crypto'],recoveryPolicy($f)));
});
test('F6-D-R1 duplicate JSON keys noncanonical bytes missing parts and oversize are rejected',function(): void{
    $f=recoveryFixture();foreach(['{}',' '.$f['capsule'],substr($f['capsule'],0,-1),'{"signature":"fake",'.substr($f['capsule'],1),str_repeat('x',5242881)] as $bad){raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($bad,$f['crypto'],recoveryPolicy($f)));}
});
test('F6-D-R1 expired operational lease is distinct from expired bounded recovery authorization',function(): void{
    $f=recoveryFixture();$a=recoveryPolicy($f,['expires'=>time()+2]);usleep(2100000);raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],$a));
    recoveryExpireVault($f);same('VERIFIED_PRE_ENROLLED_ESCROW',ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f))['verification']);
    raises(InvalidArgumentException::class,fn()=>recoveryPolicy($f,['expires'=>time()-1]));raises(InvalidArgumentException::class,fn()=>recoveryPolicy($f,['expires'=>time()+901]));
});
test('F6-D-R1 another backup or source instance cannot replay a valid certificate under the current authorization',function(): void{
    $a=recoveryFixture();$b=recoveryFixture();raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($a['capsule'],$a['crypto'],recoveryPolicy($b)));
    $outer=json_decode($a['capsule'],true,16,JSON_THROW_ON_ERROR);$outer['attestation']['source_roots_sha256']=hash('sha256','foreign-source');raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(recoveryResign($a,$outer),$a['crypto'],recoveryPolicy($a)));
});
test('F6-D-R1 recovery key rotation preserves an old correct backup and enforces revocation',function(): void{
    $f=recoveryFixture();$next=new RecoverySigner('recovery-v2',sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));$keys=$f['trust'];$keys['recovery-v2']=['public_key'=>$next->publicKey(),'revoked'=>false];$new=$f['vault']->recoveryCapsule($f['backup']['backup_id'],$f['crypto'],'synthetic-v1',$next);
    same('VERIFIED_PRE_ENROLLED_ESCROW',ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f,[],$keys))['verification']);same('recovery-v2',ColdRecovery::verify($new,$f['crypto'],recoveryPolicy($f,[],$keys))['authority_key_id']);
    $keys['recovery-v1']['revoked']=true;raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f,[],$keys)));same('recovery-v2',ColdRecovery::verify($new,$f['crypto'],recoveryPolicy($f,[],$keys))['authority_key_id']);
});
test('F6-D-R1 expired Vault or wrong capability cannot enrol an artifact after issuer loss',function(): void{
    $f=recoveryFixture();raises(InvalidArgumentException::class,fn()=>Vault::open($f['vault']->identity,str_repeat('0',64)));recoveryExpireVault($f);raises(InvalidArgumentException::class,fn()=>$f['vault']->recoveryCapsule($f['backup']['backup_id'],$f['crypto'],'synthetic-v1',$f['signer']));
});
test('F6-D-R1 signer binds the exact authenticated ciphertext and cannot attest a substituted package',function(): void{
    $f=recoveryFixture();$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);$p=AuthenticatedBackup::open($f['backup']['backup_id'],$o['ciphertext'],$f['crypto'],'synthetic-v1',$f['vault']);raises(InvalidArgumentException::class,fn()=>$f['signer']->attest($p,$o['ciphertext'].'x',$f['vault']));
});
test('F6-D-R1 authentic malformed inner payload still fails immutable issuer evidence and duplicate-key checks',function(): void{
    $f=recoveryFixture();$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);$plain=$f['crypto']->decrypt($o['ciphertext'],'f6d-backup:'.$f['backup']['backup_id']);
    foreach(['duplicate','issuer'] as $fault){$c=$o;$v=json_decode($plain,true,32,JSON_THROW_ON_ERROR);$v['issuer_mac']=str_repeat('0',64);$p=$fault==='duplicate'?'{"encoding":"json-base64-no-compression",'.substr($plain,1):CanonicalJson::encode($v);$c['ciphertext']=$f['crypto']->encrypt($p,'f6d-backup:'.$f['backup']['backup_id']);$c['attestation']['cipher_sha256']=hash('sha256',$c['ciphertext']);raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify(recoveryResign($f,$c),$f['crypto'],recoveryPolicy($f)));}
});
test('F6-D-R1 recovery report redacts keys signatures ciphertext issuer MAC private bodies and paths',function(): void{
    $f=recoveryFixture();$report=ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f));$text=json_encode($report,JSON_THROW_ON_ERROR);$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);$v=json_decode($f['crypto']->decrypt($o['ciphertext'],'f6d-backup:'.$f['backup']['backup_id']),true,32,JSON_THROW_ON_ERROR);
    foreach([base64_encode($f['key']),base64_encode($f['recovery_secret']),$f['secret'],$f['vault']->capability()[1],$v['issuer_mac'],$o['signature'],$o['ciphertext'],operationsPath($f['vault'])] as $secret){same(false,str_contains($text,$secret));}same(false,$report['effects_allowed']);same('NOT_VERIFIED',$report['key_custody']);
});
test('F6-D-R1 environment and explicit recovery authorization flags remain mandatory',function(): void{
    $f=recoveryFixture();$a=recoveryPolicy($f);foreach(['APP_ENV'=>'production','SYNTHETIC_IMPORT_ALLOWED'=>'0','SYNTHETIC_BACKUP_ALLOWED'=>'0','SYNTHETIC_RECOVERY_ALLOWED'=>'0'] as $key=>$bad){$before=getenv($key);putenv($key.'='.$bad);try{raises(InvalidArgumentException::class,fn()=>ColdRecovery::verify($f['capsule'],$f['crypto'],$a));}finally{putenv($key.'='.$before);}}
});
test('F6-D-R1 real crashes before authority and after verification do not change the immutable backup',function(): void{
    $f=recoveryFixture();$path=operationsPath($f['vault'],$f['backup']['backup_id'].'.sealed');$before=hash_file('sha256',$path);
    foreach(['CRASH_BEFORE_AUTH','CRASH_AFTER_VERIFY'] as $fault){$r=recoveryChildren(recoveryChildInput($f,['fault'=>$fault]))[0];same(73,$r['exit']);same(null,$r['report']);same(true,$r['stderr_empty']);same($before,hash_file('sha256',$path));}
    same(0,recoveryChildren(recoveryChildInput($f))[0]['exit']);
});
test('F6-D-R1 duplicate and concurrent verification is read-only and grants no native activation',function(): void{
    $f=recoveryFixture();$before=reconciliationState($f);$results=recoveryChildren(recoveryChildInput($f),4);foreach($results as $r){same(0,$r['exit']);same($results[0]['report'],$r['report']);same('BLOCKED_IDENTITY_BOUND',$r['report']['native_recovery']);}same($before,reconciliationState($f));
});
test('F6-D-R1 restored schema still rejects native Sandbox authority without rewriting original evidence',function(): void{
    $f=recoveryFixture();$t=operationsTarget($f);$before=reconciliationState($f);$r=$f['vault']->restore($f['backup']['backup_id'],$f['crypto'],'synthetic-v1',$t['target']);same('VERIFIED',$r['escrow_restore']);
    raises(InvalidArgumentException::class,fn()=>Sandbox::open(...$t['params']));$wrong=$f['params'];$wrong[2]='f6b_'.str_repeat('0',16);raises(RuntimeException::class,fn()=>Sandbox::open(...$wrong));
    same($before,reconciliationState($f));same($f['schema'],$t['setup']->execute('SELECT schema_name FROM f6b_environment WHERE id=1')->fetchColumn());same('BLOCKED_IDENTITY_BOUND',$r['f6c_activation']);same(0,$r['provider_create']);
    $cold=ColdRecovery::verify($f['capsule'],$f['crypto'],recoveryPolicy($f));raises(TypeError::class,fn()=>$t['target']->restore($cold));
});
test('F6-D-R1 history mapping provenance and customer conflicts in restored targets remain fail closed',function(): void{
    $f=recoveryFixture();foreach(['history','mapping','provenance','owner'] as $fault){$t=operationsTarget($f);$f['vault']->restore($f['backup']['backup_id'],$f['crypto'],'synthetic-v1',$t['target']);$sql=match($fault){'history'=>"UPDATE f6b_history SET event_hash=REPEAT('0',64) ORDER BY id LIMIT 1",'mapping'=>"UPDATE f6b_mappings SET evidence_hash=REPEAT('0',64)",'provenance'=>"UPDATE f6b_provenance SET envelope_hash=REPEAT('0',64)",default=>"UPDATE f6b_entities SET owner_key=REPEAT('0',64) WHERE owner_key IS NOT NULL"};$t['setup']->execute($sql);raises(InvalidArgumentException::class,fn()=>$f['vault']->restore($f['backup']['backup_id'],$f['crypto'],'synthetic-v1',$t['target']));}
});
require __DIR__.'/migration-recovery-enrolment.php';
try{$exit=executeTests();}finally{operationsCleanup();}exit($exit);
