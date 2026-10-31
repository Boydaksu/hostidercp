<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/migration-recovery-target-fixture.php';
use Hostider\Migration\Contract;
use Hostider\Migration\Operations\RecoveryInspector;

test('F6-D-R2 integrity equal row multisets with different ordered root cannot match reference',function(): void{
    $f=targetFixture();$ctx=targetContext($f);$o=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);$v=json_decode($f['crypto']->decrypt($o['ciphertext'],'f6d-backup:'.$f['backup']['backup_id']),true,32,JSON_THROW_ON_ERROR);$tables=$v['data']['tables'];
    same(true,count($tables['f6b_history'])>1);$tables['f6b_history']=array_reverse($tables['f6b_history']);$r=$ctx->compare($tables,targetPolicy($f));
    foreach($r['source_target_counts'] as $count){same($count['source'],$count['target']);same($count['source'],$count['matched']);same(0,$count['unmatched']);same(0,$count['extra']);}
    same(true,$r['finance']['equal']);same(false,$r['candidate_evidence_sha256']===$ctx->verified['logical_sha256']);same(Contract::hash($tables),$r['candidate_evidence_sha256']);same('DIFFERENCES',$r['immutable_evidence']);same('REFERENCE_COMPARISON_ONLY_NO_DB_AUTHORITY',$r['verification_scope']);
    same('REFERENCE_DIFFERENCES',$r['status']);same(true,in_array('EVIDENCE_ROOT_MISMATCH',$r['reasons'],true));
});
test('F6-D-R2 integrity actual DB PASS requires matched root and same-count tamper cannot pass',function(): void{
    $f=targetFixture();$ctx=targetContext($f);$i=RecoveryInspector::open($ctx,targetPolicy($f),$f['target_password']);$before=$i->inspect()->jsonSerialize();$c=$before['content'];
    same('PASS',$c['status']);same('MATCHED',$c['immutable_evidence']);same($ctx->verified['logical_sha256'],$c['observed_target_evidence_sha256']);same(true,$c['finance']['equal']);
    foreach($c['source_target_counts'] as $count){same($count['source'],$count['target']);same($count['source'],$count['matched']);same(0,$count['unmatched']);same(0,$count['extra']);}
    same('MATCHED_BASE_TABLE_DEFINITIONS',$c['schema_verification']);same('READY',$c['migration']);same('R1_AND_TARGET_FENCES_PASSED',$c['authorization']);
    $f['target']['setup']->execute("UPDATE f6b_entities SET payload_hash=REPEAT('0',64) ORDER BY target_id LIMIT 1");$after=$i->inspect()->jsonSerialize();$a=$after['content'];
    foreach($c['source_target_counts'] as $table=>$count){same($count['target'],$a['source_target_counts'][$table]['target']);}
    same('REVIEW_REQUIRED',$a['status']);same('DIFFERENCES',$a['immutable_evidence']);same(false,$a['observed_target_evidence_sha256']===$ctx->verified['logical_sha256']);same(false,$before['fingerprint']===$after['fingerprint']);same(true,in_array('EVIDENCE_ROOT_MISMATCH',$a['reasons'],true));same('NOT_VERIFIED_SELECT_ONLY',$a['executable_object_inventory']);same(false,$a['effects_allowed']);
});
try{$exit=executeTests();}finally{targetCleanup();}exit($exit);
