<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/migration-recovery-target-fixture.php';
use Hostider\Core\CanonicalJson;
use Hostider\Core\Security\Crypto;
use Hostider\Migration\Contract;
use Hostider\Migration\Synthetic\Sandbox;
use Hostider\Migration\Operations\{RecoveryContext,RecoveryTargetPolicy,RecoveryInspector};

test('F6-D-R2 actual restored target reconciles in a fresh process after source loss and Vault expiry',function(): void{
    $f=targetFixture();recoveryExpireVault($f);if(preg_match('/^f6b_[a-f0-9]{24}$/D',$f['schema'])!==1){throw new RuntimeException('R2_OWNED_SOURCE');}$f['root']->execute('DROP DATABASE `'.$f['schema'].'`');$r=targetChildren(targetChildInput($f))[0];same(0,$r['exit']);same(true,$r['stderr_empty']);same('PASS',$r['report']['content']['status']);same('MATCHED',$r['report']['content']['immutable_evidence']);same($f['backup']['logical_sha256'],$r['report']['content']['observed_target_evidence_sha256']);same('PRESERVED_ENROLMENT_EVIDENCE_NOT_RECOMPUTED',$r['report']['content']['original_hmac']);same('NOT_GRANTED',$r['report']['content']['runtime_authority']);
});
test('F6-D-R2 wrong schema and account cannot reuse the target policy',function(): void{
    $f=targetFixture();$ctx=targetContext($f);foreach(['schema'=>'f6d_'.str_repeat('0',24),'account'=>'r2_'.str_repeat('0',16)] as $field=>$value){raises(RuntimeException::class,fn()=>RecoveryInspector::open($ctx,targetPolicy($f,[$field=>$value]),$f['target_password']));}
});
test('F6-D-R2 source backup snapshot and encryption key mismatches are blocked',function(): void{
    $f=targetFixture();foreach(['backup','roots','snapshot'] as $field){raises(InvalidArgumentException::class,fn()=>targetContext($f,[$field=>str_repeat('0',64)]));}raises(RuntimeException::class,fn()=>RecoveryContext::open($f['capsule'],new Crypto(random_bytes(32),'synthetic-v1'),recoveryPolicy($f)));
    raises(InvalidArgumentException::class,fn()=>RecoveryInspector::open(targetContext($f),RecoveryTargetPolicy::allow($f['binding'],str_repeat('0',64),1,time()+200),$f['target_password']));
});
test('F6-D-R2 revoked or forged recovery issuer cannot establish a context',function(): void{
    $f=targetFixture();$keys=$f['trust'];$keys['recovery-v1']['revoked']=true;raises(InvalidArgumentException::class,fn()=>RecoveryContext::open($f['capsule'],$f['crypto'],recoveryPolicy($f,[],$keys)));$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);$o['signature']=base64_encode(str_repeat('x',64));raises(InvalidArgumentException::class,fn()=>RecoveryContext::open(CanonicalJson::encode($o),$f['crypto'],recoveryPolicy($f)));
});
test('F6-D-R2 wrong purpose stale epoch and revoked target policy are denied',function(): void{
    $f=targetFixture();raises(InvalidArgumentException::class,fn()=>targetPolicy($f,[],null,1,false,'ACTIVATE_NATIVE'));raises(InvalidArgumentException::class,fn()=>targetPolicy($f,[],null,2));raises(InvalidArgumentException::class,fn()=>targetPolicy($f,[],null,1,true));
});
test('F6-D-R2 expired authorization is checked again before reporting',function(): void{
    $f=targetFixture();$p=targetPolicy($f,[],time()+2);$i=RecoveryInspector::open(targetContext($f),$p,$f['target_password']);usleep(2100000);same('BLOCKED',$i->inspect()->content['status']);raises(InvalidArgumentException::class,fn()=>targetPolicy($f,[],time()-1));
});
test('F6-D-R2 stale or expired restore marker blocks verification',function(): void{
    $f=targetFixture();$i=RecoveryInspector::open(targetContext($f),targetPolicy($f),$f['target_password']);$f['target']['setup']->execute('UPDATE f6d_restore_marker SET token_hash=REPEAT(\'0\',64)');same('BLOCKED',$i->inspect()->content['status']);
});
test('F6-D-R2 restored payload tamper changes actual evidence despite unchanged aggregate counts',function(): void{
    $f=targetFixture();$one=targetInspect($f);$f['target']['setup']->execute("UPDATE f6b_entities SET payload_hash=REPEAT('0',64) ORDER BY target_id LIMIT 1");$two=targetInspect($f);same('REVIEW_REQUIRED',$two['content']['status']);same(false,$one['fingerprint']===$two['fingerprint']);$f['target']['setup']->execute("UPDATE f6b_entities SET payload_hash=REPEAT('1',64) ORDER BY target_id LIMIT 1");$three=targetInspect($f);same($two['content']['source_target_counts'],$three['content']['source_target_counts']);same(false,$two['fingerprint']===$three['fingerprint']);
});
test('F6-D-R2 mapping provenance history and checkpoint mismatches cannot pass',function(): void{
    foreach(["UPDATE f6b_mappings SET evidence_hash=REPEAT('0',64)","UPDATE f6b_provenance SET envelope_hash=REPEAT('0',64)","UPDATE f6b_history SET event_hash=REPEAT('0',64) ORDER BY id LIMIT 1","UPDATE f6b_runs SET checkpoint_hash=REPEAT('0',64)"] as $sql){$f=targetFixture();$f['target']['setup']->execute($sql);same('REVIEW_REQUIRED',targetInspect($f)['content']['status']);}
});
test('F6-D-R2 customer and private attachment ownership changes are blocked',function(): void{
    foreach(['ticket','attachment'] as $type){$f=targetFixture();$f['target']['setup']->execute('UPDATE f6b_entities SET owner_key=REPEAT(\'0\',64) WHERE entity_type=?',[$type]);same('BLOCKED',targetInspect($f)['content']['status']);}
});
test('F6-D-R2 private message visibility cannot become public in recovery',function(): void{
    $f=targetFixture();$row=$f['target']['setup']->execute("SELECT target_id,payload_json FROM f6b_entities WHERE entity_type='message' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.kind'))='PUBLIC_REPLY' ORDER BY target_id LIMIT 1")->fetch();$p=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);$p['visibility']=['state'=>'KNOWN','value'=>'PRIVATE'];$p['kind']='INTERNAL_NOTE';$f['target']['setup']->execute('UPDATE f6b_entities SET payload_json=? WHERE target_id=?',[CanonicalJson::encode($p),$row['target_id']]);same('BLOCKED',targetInspect($f)['content']['status']);
});
test('F6-D-R2 finance amount and currency mutations require review without ledger writes',function(): void{
    foreach(['amount','currency'] as $fault){$f=targetFixture();$row=$f['target']['setup']->execute("SELECT target_id,payload_json FROM f6b_entities WHERE entity_type='wallet' LIMIT 1")->fetch();$p=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);$p['liability'][$fault==='amount'?'minor':'currency']=$fault==='amount'?'9999':'USD';$f['target']['setup']->execute('UPDATE f6b_entities SET payload_json=? WHERE target_id=?',[CanonicalJson::encode($p),$row['target_id']]);$r=targetInspect($f)['content'];same('REVIEW_REQUIRED',$r['status']);same(false,$r['finance']['equal']);same(0,$r['native_writes']);}
});
test('F6-D-R2 changed schema or unique index and missing migration are blocked',function(): void{
    foreach(['ALTER TABLE f6b_entities ADD extra INT NULL','ALTER TABLE f6b_entities DROP INDEX source_key','DELETE FROM schema_migrations'] as $sql){$f=targetFixture();$f['target']['setup']->execute($sql);try{$r=targetInspect($f);same('BLOCKED',$r['content']['status']);}catch(RuntimeException $e){same('R2_TARGET_DENIED',$e->getMessage());}}
});
test('F6-D-R2 unexpected and missing target rows are detected by actual queries',function(): void{
    foreach(['extra','missing'] as $fault){$f=targetFixture();if($fault==='extra'){$f['target']['setup']->execute("INSERT INTO f6b_entities VALUES (?,?,?,NULL,'{}',?)",[str_repeat('a',64),str_repeat('b',64),'customer',str_repeat('c',64)]);}else{$f['target']['setup']->execute('DELETE FROM f6b_history ORDER BY id DESC LIMIT 1');}same('REVIEW_REQUIRED',targetInspect($f)['content']['status']);}
});
test('F6-D-R2 partial restore and corrupt marker cannot claim PASS',function(): void{
    $f=targetFixture(false);raises(RuntimeException::class,fn()=>RecoveryInspector::open(targetContext($f),targetPolicy($f),$f['target_password']));
});
test('F6-D-R2 SELECT-only reader denies DML DDL native and sibling schema reads',function(): void{
    $f=targetFixture();$db=targetReader($f);foreach(['UPDATE f6b_entities SET owner_key=owner_key','DELETE FROM f6b_history','CREATE TABLE forbidden(id INT)','SELECT * FROM mysql.user','SELECT * FROM `'.$f['schema'].'`.f6b_entities'] as $sql){raises(PDOException::class,fn()=>$db->execute($sql));}same('PASS',targetInspect($f)['content']['status']);
});
test('F6-D-R2 added write execute or trigger privilege blocks reader authority',function(): void{
    foreach(['UPDATE','EXECUTE','TRIGGER'] as $right){$f=targetFixture();$f['root']->execute('GRANT '.$right.' ON `'.str_replace('_','\\_',$f['target']['schema'])."`.* TO '".$f['binding']['account']."'@'".$f['host']."'");raises(RuntimeException::class,fn()=>RecoveryInspector::open(targetContext($f),targetPolicy($f),$f['target_password']));}
});
test('F6-D-R2 views are denied and ungranted triggers routines are never executed',function(): void{
    $f=targetFixture();$f['target']['setup']->execute('CREATE TRIGGER r2_probe AFTER INSERT ON f6b_entities FOR EACH ROW SET @r2_probe=1');$f['target']['setup']->execute('CREATE PROCEDURE r2_probe() SELECT 1');$db=targetReader($f);raises(PDOException::class,fn()=>$db->execute('CALL r2_probe()'));raises(PDOException::class,fn()=>$db->execute("INSERT INTO f6b_entities SELECT * FROM f6b_entities"));$r=targetInspect($f)['content'];same('PASS',$r['status']);same('NOT_VERIFIED_SELECT_ONLY',$r['executable_object_inventory']);$f['target']['setup']->execute('CREATE VIEW r2_unapproved AS SELECT * FROM f6b_entities');raises(RuntimeException::class,fn()=>RecoveryInspector::open(targetContext($f),targetPolicy($f),$f['target_password']));
});
test('F6-D-R2 1 4 8 independent processes reconcile deterministically without writes',function(): void{
    $f=targetFixture();$before=targetInspect($f);foreach([1,4,8] as $n){foreach(targetChildren(targetChildInput($f),$n) as $r){same(0,$r['exit']);same(true,$r['stderr_empty']);same($before['fingerprint'],$r['report']['fingerprint']);}}same($before['fingerprint'],targetInspect($f)['fingerprint']);
});
test('F6-D-R2 real process crashes never publish a verified report or alter target evidence',function(): void{
    $f=targetFixture();$before=targetInspect($f)['fingerprint'];foreach(['CRASH_BEFORE','CRASH_CAPTURE','CRASH_REPORT'] as $fault){$r=targetChildren(targetChildInput($f,['fault'=>$fault]))[0];same(73,$r['exit']);same(null,$r['report']);same(true,$r['stderr_empty']);same($before,targetInspect($f)['fingerprint']);}
});
test('F6-D-R2 reports redact credentials bodies identifiers paths signatures and ciphertext',function(): void{
    $f=targetFixture();$report=targetInspect($f);$text=json_encode($report,JSON_THROW_ON_ERROR);$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);foreach([$f['secret'],$f['target_password'],$f['target']['schema'],$f['binding']['account'],base64_encode($f['key']),base64_encode($f['recovery_secret']),$o['signature'],$o['ciphertext'],operationsPath($f['vault'])] as $secret){same(false,str_contains($text,$secret));}same(false,$report['content']['effects_allowed']);same(['CREATE'=>0,'RENEW'=>0,'TERMINATE'=>0],$report['content']['provider_operations']);raises(TypeError::class,fn()=>$f['target']['target']->restore($report));raises(InvalidArgumentException::class,fn()=>Sandbox::open(...$f['target']['params']));
});
test('F6-D-R2 oversized row and disconnected target cannot produce PASS',function(): void{
    $f=targetFixture();$i=RecoveryInspector::open(targetContext($f),targetPolicy($f),$f['target_password']);$f['target']['setup']->execute("UPDATE f6b_entities SET payload_json=JSON_OBJECT('oversize',REPEAT('x',66000)) ORDER BY target_id LIMIT 1");same('BLOCKED',$i->inspect()->content['status']);foreach($f['root']->execute('SELECT ID FROM information_schema.PROCESSLIST WHERE USER=?',[$f['binding']['account']])->fetchAll(PDO::FETCH_COLUMN) as $id){$f['root']->execute('KILL CONNECTION '.(int)$id);}same('BLOCKED',$i->inspect()->content['status']);
});
require __DIR__.'/migration-recovery-target-boundary.php';
require __DIR__.'/migration-recovery-target-definer.php';
try{$exit=executeTests();}finally{targetCleanup();}exit($exit);
