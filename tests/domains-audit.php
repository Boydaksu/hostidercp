<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/domains-fixture.php';
use Hostider\Core\{CanonicalJson,Id};
use Hostider\Core\Database\{Database,Migrator};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Domains\{AttemptEvidence,DomainEngine,DomainRunner,DomainReconciliation,EventSink};
use Hostider\Providers\Contract\{Operation,ProviderResult};
use Hostider\Providers\Mock\DurableRegistrar;
$db=isolatedDomainDb();
$upgradeDir=dirname(__DIR__).'/storage/f4-audit-upgrade-'.bin2hex(random_bytes(6));mkdir($upgradeDir,0700);
$copied=[];
try {
    foreach(glob(dirname(__DIR__).'/migrations/*.php') as $source){if(basename($source)<'043'){$target=$upgradeDir.'/'.basename($source);copy($source,$target);$copied[]=$target;}}
    same(42,count((new Migrator($db,$upgradeDir))->migrate()));
    $migrator=new Migrator($db,dirname(__DIR__).'/migrations');same(['043_f4_domain_audit_safety.php','044_f4_cloud_operations.php','045_cloud_purchase_containment.php','046_cloud_metering_costs.php','047_cloud_cost_admission.php','048_provider_panel.php','049_support_core.php','050_support_sla_assignment.php','051_support_sla_availability.php','052_support_mail.php'],$migrator->migrate());same([],$migrator->migrate());$migrator->assertReady();
    echo 'MIGRATION 001-042 preserved; 042 to 043 additive upgrade; replay no-op PASS'.PHP_EOL;
} finally {foreach($copied as $target){unlink($target);}rmdir($upgradeDir);}
function auditDomain($db,bool $registered=false): array {
    $f=domainFixture($db);$id=$f['attach']();$engine=new DomainEngine($db,$f['config']);$op=$engine->enqueueRegister($id,$f['customer'],$f['actor'],Id::uuid());
    if($registered){same(true,(new DomainRunner($db,$f['config']))->once($f['actor'],$op));}
    return $f+compact('id','engine','op');
}
function auditStaff(): Principal {return new Principal('audit-reconciler',['services.manage'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time());}
function auditUnknown($db,array $f,string $scenario): array {
    $claim=$f['engine']->claim($f['actor'],Id::uuid(),$f['op']);$registrar=new DurableRegistrar($db,$f['config'],$claim['account']['id'],$scenario);
    $result=$registrar->execute(new Operation($claim['attempt_id'],$claim['payload']['action'],$claim['payload']['resource'],$claim['payload']['configuration']));
    $f['engine']->finishAttempt($f['op'],$claim['lease_token'],$result,null,$f['actor'],Id::uuid());return compact('claim','registrar');
}
test('F4-C missing resource and UNKNOWN call never prove no effect; replay stays held',function()use($db): void{
    $f=auditDomain($db);$u=auditUnknown($db,$f,'TIMEOUT_BEFORE_EFFECT');$r=new DomainReconciliation($db,$f['config']);
    $alt=$f['registry']->configure('example','domainnameapi',$f['policy']);$wrong=new DurableRegistrar($db,$f['config'],$f['registry']->policy($alt)['account_id']);
    same('INSPECTION_ACCOUNT_MISMATCH',raises(HttpError::class,fn()=>$r->inspect($f['op'],$wrong,auditStaff(),Id::uuid()))->errorCode);
    $i=$r->inspect($f['op'],$u['registrar'],auditStaff(),Id::uuid());same(false,$i['found']);same(false,$i['definitive_no_effect']);
    same('DEFINITIVE_NO_EFFECT_REQUIRED',raises(HttpError::class,fn()=>$r->resolve($f['op'],$i['inspection_id'],'CONFIRMED_FAILURE',auditStaff(),Id::uuid()))->errorCode);
    same('MANUAL_REVIEW',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$f['op']])->fetchColumn());
    same(false,(new DomainRunner($db,$f['config']))->once($f['actor'],$f['op']));
    same('UNKNOWN',(new EventSink($db,$f['config']))->consume('domains.execute',['operation_id'=>$f['op']]));
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=?',[$u['claim']['attempt_id']])->fetchColumn());
});
test('F4-C crash before call cannot be confirmed failed; expiry fences old worker',function()use($db): void{
    $f=auditDomain($db);$claim=$f['engine']->claim($f['actor'],Id::uuid(),$f['op']);
    $db->execute('UPDATE domain_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$f['op']]);
    same(1,$f['engine']->expire($f['actor'],Id::uuid(),$f['op']));
    $r=new DomainReconciliation($db,$f['config']);$i=$r->inspect($f['op'],new DurableRegistrar($db,$f['config'],$claim['account']['id']),auditStaff(),Id::uuid());
    same(false,$i['definitive_no_effect']);raises(HttpError::class,fn()=>$r->resolve($f['op'],$i['inspection_id'],'CONFIRMED_FAILURE',auditStaff(),Id::uuid()));
    raises(HttpError::class,fn()=>$f['engine']->finishAttempt($f['op'],$claim['lease_token'],new ProviderResult($claim['attempt_id'],$claim['account']['provider'],'SUCCESS',Id::uuid()),null,$f['actor'],Id::uuid()));
    same(0,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=?',[$claim['attempt_id']])->fetchColumn());
});
test('F4-C independently recorded definitive no effect permits explicit failure only',function()use($db): void{
    $f=auditDomain($db);$u=auditUnknown($db,$f,'DEFINITIVE_FAILURE');$r=new DomainReconciliation($db,$f['config']);$i=$r->inspect($f['op'],$u['registrar'],auditStaff(),Id::uuid());
    same(true,$i['definitive_no_effect']);$r->resolve($f['op'],$i['inspection_id'],'CONFIRMED_FAILURE',auditStaff(),Id::uuid());
    same('FAILED',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$f['op']])->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM domain_registrar_attempts WHERE operation_id=?',[$f['op']])->fetchColumn());
});
test('F4-C mismatched call fingerprint status account and resource fail closed',function()use($db): void{
    foreach(['fingerprint','account','status','resource'] as $bad){
        $f=auditDomain($db);$c=$f['engine']->claim($f['actor'],Id::uuid(),$f['op']);
        $hash=hash_hmac('sha256',CanonicalJson::encode(['account'=>$c['account']['id'],'action'=>'register','resource'=>null,'configuration'=>$c['payload']['configuration']]),base64_decode($f['config']->key,true));
        $account=$c['account']['id'];if($bad==='account'){$rule=$f['registry']->configure('example','domainnameapi',$f['policy']);$account=$f['registry']->policy($rule)['account_id'];}
        $db->execute('INSERT INTO mock_registrar_calls(operation_id,account_id,action,fingerprint,status,resource_id,safe_code,no_effect) VALUES (?,?,?,?,?,?,?,1)',[$c['attempt_id'],$account,'register',$bad==='fingerprint'?str_repeat('0',64):$hash,$bad==='status'?'UNKNOWN':'DEFINITIVE_FAILURE',$bad==='resource'?Id::uuid():null,'SYNTHETIC_NEGATIVE']);
        $validator=new AttemptEvidence($db,$f['config']);
        if(in_array($bad,['fingerprint','account'],true)){raises(HttpError::class,fn()=>$validator->call($db->execute('SELECT * FROM domain_registrar_attempts WHERE id=?',[$c['attempt_id']])->fetch()));}
        else{same(false,$validator->noEffect($validator->call($db->execute('SELECT * FROM domain_registrar_attempts WHERE id=?',[$c['attempt_id']])->fetch())));}
        $f['engine']->finishAttempt($f['op'],$c['lease_token'],new ProviderResult($c['attempt_id'],$c['account']['provider'],'UNKNOWN',null,'SYNTHETIC_UNKNOWN'),null,$f['actor'],Id::uuid());
    }
});
test('F4-C stale inspection generation domain version and newer inspection fence resolution',function()use($db): void{
    $f=auditDomain($db);$u=auditUnknown($db,$f,'TIMEOUT_AFTER_EFFECT');$r=new DomainReconciliation($db,$f['config']);$first=$r->inspect($f['op'],$u['registrar'],auditStaff(),Id::uuid());
    $second=$r->inspect($f['op'],$u['registrar'],auditStaff(),Id::uuid());raises(HttpError::class,fn()=>$r->resolve($f['op'],$first['inspection_id'],'CONFIRMED_SUCCESS',auditStaff(),Id::uuid()));
    $db->execute('UPDATE domains SET version=version+1 WHERE id=?',[$f['id']]);raises(HttpError::class,fn()=>$r->resolve($f['op'],$second['inspection_id'],'CONFIRMED_SUCCESS',auditStaff(),Id::uuid()));
    $third=$r->inspect($f['op'],$u['registrar'],auditStaff(),Id::uuid());$r->resolve($f['op'],$third['inspection_id'],'CONFIRMED_SUCCESS',auditStaff(),Id::uuid());
    same('REGISTERED',$db->execute('SELECT status FROM domains WHERE id=?',[$f['id']])->fetchColumn());
});
test('F4-C hold timestamp and reason commit together and roll back together',function()use($db): void{
    $f=auditDomain($db);$db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$f['order']['payment_request_id']]);
    $before=$db->execute('SELECT runner_checked_at,runner_hold_code FROM domain_operations WHERE id=?',[$f['op']])->fetch();
    $db->execute("CREATE TRIGGER synthetic_hold_rollback BEFORE UPDATE ON domain_operations FOR EACH ROW BEGIN IF NEW.id='".$f['op']."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic rollback'; END IF; END");
    try{raises(PDOException::class,fn()=>$f['engine']->claim($f['actor'],Id::uuid(),$f['op']));}finally{$db->execute('DROP TRIGGER synthetic_hold_rollback');}
    same($before,$db->execute('SELECT runner_checked_at,runner_hold_code FROM domain_operations WHERE id=?',[$f['op']])->fetch());
    same(null,$f['engine']->claim($f['actor'],Id::uuid(),$f['op']));$after=$db->execute('SELECT runner_checked_at,runner_hold_code FROM domain_operations WHERE id=?',[$f['op']])->fetch();
    same(true,$after['runner_checked_at']!==null);same('DOMAIN_SOURCE_CHANGED',$after['runner_hold_code']);
});
test('F4-C SKIP LOCKED bypasses contended first operation without losing its state',function()use($db): void{
    $a=auditDomain($db);$b=auditDomain($db);$other=Database::connect($a['config']);$other->pdo->beginTransaction();
    try{$other->execute('SELECT id FROM domain_operations WHERE id=? FOR UPDATE',[$a['op']]);same(false,(new DomainRunner($db,$a['config']))->once($a['actor'],$a['op']));same(true,(new DomainRunner($db,$b['config']))->once($b['actor'],$b['op']));}finally{$other->pdo->rollBack();}
    same('PENDING',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$a['op']])->fetchColumn());same(true,(new DomainRunner($db,$a['config']))->once($a['actor'],$a['op']));
});
test('F4-C 1 4 and 8 independent PHP runners produce one physical registration per domain',function()use($db): void{
    foreach([1,4,8] as $count){$f=auditDomain($db);$children=[];$env=array_replace(getenv(),['APP_ENV'=>'test','APP_URL'=>$f['config']->url,'APP_KEY'=>$f['config']->key,'DB_DSN'=>$f['config']->dsn,'DB_USER'=>$f['config']->dbUser,'DB_PASSWORD'=>$f['config']->dbPassword]);
        for($i=0;$i<$count;++$i){$pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/domains-concurrency-child.php',$f['op']],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);if(!is_resource($p)){throw new RuntimeException('Child unavailable');}fclose($pipes[0]);$children[]=[$p,$pipes];}
        $worked=0;foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($p));same('',$err);$worked+=(int)json_decode($out,true,32,JSON_THROW_ON_ERROR)['worked'];}
        same(1,$worked);same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_domains WHERE name_ascii=?',[$f['name']])->fetchColumn());
        same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=(SELECT active_attempt_id FROM domain_operations WHERE id=?)',[$f['op']])->fetchColumn());
    }
});
test('F4-C renewal finance rechecked at claim and result; refund never dispatches or extends again',function()use($db): void{
    foreach(['claim','result'] as $stage){$f=auditDomain($db,true);$r=paidDomainRenewal($db,$f);$op=$f['engine']->enqueueRenewal($f['id'],$r['order_id'],$r['period_id'],1,$f['actor'],Id::uuid());
        if($stage==='result'){$c=$f['engine']->claim($f['actor'],Id::uuid(),$op);$p=new DurableRegistrar($db,$f['config'],$c['account']['id']);$res=$p->execute(new Operation($c['attempt_id'],'renew',$c['payload']['resource'],$c['payload']['configuration']));$proof=$p->evidence($f['name']);}
        $order=$f['orders']->repository->find($r['order_id'],$f['customer']);$db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$order['payment_request_id']]);
        if($stage==='claim'){same(null,$f['engine']->claim($f['actor'],Id::uuid(),$op));same('DOMAIN_SOURCE_CHANGED',$db->execute('SELECT runner_hold_code FROM domain_operations WHERE id=?',[$op])->fetchColumn());}
        else{$f['engine']->finishAttempt($op,$c['lease_token'],$res,$proof,$f['actor'],Id::uuid());same('UNKNOWN',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$op])->fetchColumn());same(false,(new DomainRunner($db,$f['config']))->once($f['actor'],$op));}
    }
});
test('F4-C manual approval and immutable renewal binding cannot be bypassed',function()use($db): void{
    $f=auditDomain($db,true);$r=paidDomainRenewal($db,$f);
    $db->execute("UPDATE orders SET status='REQUIRES_APPROVAL' WHERE id=?",[$r['order_id']]);
    raises(HttpError::class,fn()=>$f['engine']->enqueueRenewal($f['id'],$r['order_id'],$r['period_id'],1,$f['actor'],Id::uuid()));
    $db->execute("UPDATE orders SET status='READY_FOR_PROVISIONING' WHERE id=?",[$r['order_id']]);
    $op=$f['engine']->enqueueRenewal($f['id'],$r['order_id'],$r['period_id'],1,$f['actor'],Id::uuid());
    raises(PDOException::class,fn()=>$db->execute('UPDATE domain_renewals SET term_years=2 WHERE operation_id=?',[$op]));
    raises(PDOException::class,fn()=>$db->execute('UPDATE svc_renewals SET order_id=? WHERE id=?',[$f['order']['id'],$r['id']]));
    raises(PDOException::class,fn()=>$db->execute('UPDATE svc_periods SET billing_request_id=? WHERE id=?',[$f['order']['payment_request_id'],$r['period_id']]));
    $db->execute("UPDATE orders SET status='REQUIRES_APPROVAL' WHERE id=?",[$r['order_id']]);
    same(null,$f['engine']->claim($f['actor'],Id::uuid(),$op));
    same(0,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=(SELECT active_attempt_id FROM domain_operations WHERE id=?)',[$op])->fetchColumn());
});
test('F4-C crash after effect and duplicate queue job cannot replay a registration',function()use($db): void{
    $f=auditDomain($db);$c=$f['engine']->claim($f['actor'],Id::uuid(),$f['op']);$p=new DurableRegistrar($db,$f['config'],$c['account']['id']);
    same('SUCCESS',$p->execute(new Operation($c['attempt_id'],'register',null,$c['payload']['configuration']))->status);
    // Worker disappears before finish; the next CLI expires its lease and never dispatches again.
    $db->execute('UPDATE domain_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$f['op']]);
    domainCli($f,['domains:expire']);same('UNKNOWN',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$f['op']])->fetchColumn());
    same(false,domainCli($f,['domains:work','--once'])['worked']);
    $queue=new \Hostider\Core\Queue\Queue($db);$db->transaction(fn()=>$queue->enqueue('domains.execute',['operation_id'=>$f['op']],'synthetic-duplicate:'.$f['op'],Id::uuid()));
    // Consume actual queue jobs until the duplicate event is reached, respecting boolean Worker::once().
    $worker=new \Hostider\Core\Queue\Worker($db,$queue);(new EventSink($db,$f['config']))->register($worker);for($n=0;$n<100&&$worker->once();++$n){}
    same('UNKNOWN',$db->execute('SELECT status FROM jobs WHERE dedupe_key=?',['synthetic-duplicate:'.$f['op']])->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_domains WHERE name_ascii=?',[$f['name']])->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=?',[$c['attempt_id']])->fetchColumn());
});
test('F4-C parallel canonical renewal requests converge and eight runners extend only once',function()use($db): void{
    $f=auditDomain($db,true);
    $staff=new Principal('policy',['services.policy'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time(),administrative:true);
    $db->transaction(fn()=>(new \Hostider\Services\Policies($db,$f['config']))->set('SERVICE',$f['service']['id'],['price_protected'=>false,'price_notice_required'=>false],0,$staff));
    $env=array_replace(getenv(),['APP_ENV'=>'test','APP_URL'=>$f['config']->url,'APP_KEY'=>$f['config']->key,'DB_DSN'=>$f['config']->dsn,'DB_USER'=>$f['config']->dbUser,'DB_PASSWORD'=>$f['config']->dbPassword]);$children=[];
    for($n=0;$n<2;++$n){$pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/domains-concurrency-child.php','renew-request',$f['service']['id'],$f['customer']],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);if(!is_resource($p)){throw new RuntimeException('Child unavailable');}fclose($pipes[0]);$children[]=[$p,$pipes];}
    $ids=[];foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($p));same('',$err);$ids[]=json_decode($out,true,32,JSON_THROW_ON_ERROR)['renewal'];}same($ids[0],$ids[1]);
    $r=paidDomainRenewal($db,$f);$op=$f['engine']->enqueueRenewal($f['id'],$r['order_id'],$r['period_id'],1,$f['actor'],Id::uuid());$children=[];
    for($n=0;$n<8;++$n){$pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/domains-concurrency-child.php',$op],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);if(!is_resource($p)){throw new RuntimeException('Child unavailable');}fclose($pipes[0]);$children[]=[$p,$pipes];}
    $worked=0;foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($p));same('',$err);$worked+=(int)json_decode($out,true,32,JSON_THROW_ON_ERROR)['worked'];}same(1,$worked);
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=(SELECT active_attempt_id FROM domain_operations WHERE id=?)',[$op])->fetchColumn());
    same(2,(int)$db->execute('SELECT term_years FROM mock_registrar_domains WHERE name_ascii=?',[$f['name']])->fetchColumn());
});
runTests();
