<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/cloud-fixture.php';
use Hostider\Core\{Id,CanonicalJson};use Hostider\Core\Database\Migrator;use Hostider\Core\Http\HttpError;
use Hostider\Cloud\{Worker,Containment,EventSink};use Hostider\Identity\{IdentityService,Totp};use Hostider\Providers\Mock\DurableVoyar;
$db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function finalFixture($db): array{
    $f=cloudFixture($db);activateCloud($db,$f);static $admin=null;$identity=new IdentityService($db,$f['config']);
    if($admin===null){$password=bin2hex(random_bytes(20));$email='final-audit-admin@example.invalid';$user=$identity->bootstrap($email,'Synthetic final audit admin',$password);$reference=Id::uuid();$identity->login($email,$password,'admin',$reference,Id::uuid());$factor=$identity->enroll($reference,'admin',$password);$codes=$identity->confirmEnrollment($reference,'admin',Totp::code($factor['secret'],intdiv(time(),30)))['recovery_codes'];$identity->stepUp($reference,'admin',$password,array_pop($codes));$admin=compact('user','reference');}
    $assignment=$db->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL',[$admin['user']])->fetchColumn();$db->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope,service_scope) VALUES (?,?,?,?,?)',[Id::uuid(),$assignment,'services.manage',$f['customer'],$f['service']['id']]);return $f+['admin'=>$admin];
}
function finalFinance($db): string{
    $rows=[];foreach(['finance_journals','finance_payments','billing_requests','billing_request_states','orders'] as $t){$rows[$t]=$db->execute('SELECT * FROM '.$t.' ORDER BY 1')->fetchAll();}return hash('sha256',CanonicalJson::encode($rows));
}
function finalState($db,array $f): string{return $db->execute('SELECT status FROM svc_services WHERE id=?',[$f['service']['id']])->fetchColumn();}
function finalSuspend($db,array $f): void{$db->transaction(fn()=>(new \Hostider\Services\CloudServices($db,$f['config']))->state($f['service']['id'],'SUSPENDED'));}
function finalRefund($db,array $f): void{$db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$f['order']['payment_request_id']]);}
function finalProvider($db,array $f): DurableVoyar{return new DurableVoyar($db,$f['config'],$f['engine']->store->instance($f['instance'],false)['account_id']);}
function finalStop($db,array $f,bool $unknown=true,bool $complete=true): string{
    $safety=new Containment($db,$f['config']);$c=$safety->prepare($f['instance'],'STOP','Final audit physical stop',$f['admin']['reference']);$op=$safety->execute($f['instance'],$c,$f['admin']['reference'],true);(new Worker($db,$f['config'],$unknown?'TIMEOUT_AFTER_EFFECT':'SUCCESS'))->once($op);
    if($unknown){same('UNKNOWN',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());if($complete){$p=finalProvider($db,$f);$p->advance($p->observe($op)['task']['id']);same('STOPPED',$p->observe($op)['resource']['state']);}}else{(new Worker($db,$f['config']))->once($op);}return $op;
}
function finalResolve($db,array $f,string $op): array{
    $identity=new IdentityService($db,$f['config']);$a=$identity->withStepUp($f['admin']['reference'],'admin',fn($u,$s)=>$identity->principal($u,$s));$obs=$f['engine']->observe($op,$a);$c=$f['engine']->prepare($f['instance'],'RESOLVE',['observation_id'=>$obs],$a);$f['engine']->resolve($op,$obs,$c,$a);return [$obs,$c,$a];
}
function finalSingle($db,array $f,string $op,string $service): void{
    same('STOPPED',$f['engine']->view($f['instance'],$f['actor'])['status']);same($service,finalState($db,$f));same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_effects WHERE operation_id=?',[$op])->fetchColumn());same(false,(new Worker($db,$f['config']))->once($op));
}
test('Final P0 already refunded VM STOP preserves SUSPENDED and immutable finance',function()use($db): void{
    $f=finalFixture($db);finalRefund($db,$f);finalSuspend($db,$f);$before=finalFinance($db);$op=finalStop($db,$f,false);finalSingle($db,$f,$op,'SUSPENDED');same($before,finalFinance($db));
});
test('Final P0 paid ACTIVE STOP UNKNOWN then FULL refund reconciles STOPPED SUSPENDED once',function()use($db): void{
    $f=finalFixture($db);$op=finalStop($db,$f);finalRefund($db,$f);$before=finalFinance($db);finalResolve($db,$f,$op);finalSingle($db,$f,$op,'SUSPENDED');same($before,finalFinance($db));
});
test('Final P0 cancellation after STOP UNKNOWN cannot restore historical ACTIVE',function()use($db): void{
    $f=finalFixture($db);$op=finalStop($db,$f);$db->execute("UPDATE orders SET status='CANCELLED' WHERE id=?",[$f['order']['id']]);$before=finalFinance($db);finalResolve($db,$f,$op);finalSingle($db,$f,$op,'SUSPENDED');same($before,finalFinance($db));
});
test('Final P0 pending unverified task remains UNKNOWN without another STOP',function()use($db): void{
    $f=finalFixture($db);$op=finalStop($db,$f,true,false);finalRefund($db,$f);$before=finalFinance($db);raises(HttpError::class,fn()=>finalResolve($db,$f,$op));same('UNKNOWN',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same(false,(new Worker($db,$f['config']))->once($op));same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());same(0,(int)$db->execute('SELECT COUNT(*) FROM cloud_effects WHERE operation_id=?',[$op])->fetchColumn());same($before,finalFinance($db));
});
test('Final P0 currently funded service with no suspension preserves ACTIVE independently of STOPPED',function()use($db): void{
    $f=finalFixture($db);$op=finalStop($db,$f);$before=finalFinance($db);finalResolve($db,$f,$op);finalSingle($db,$f,$op,'ACTIVE');same($before,finalFinance($db));same(false,array_key_exists('billing_stopped',$f['engine']->view($f['instance'],$f['actor'])));
});
test('Final P0 independent suspension before transport loss is never erased',function()use($db): void{
    $f=finalFixture($db);finalSuspend($db,$f);$op=finalStop($db,$f);same('SUSPENDED',finalState($db,$f));finalResolve($db,$f,$op);finalSingle($db,$f,$op,'SUSPENDED');
});
test('Final P0 independent suspension after transport loss is never reactivated',function()use($db): void{
    $f=finalFixture($db);$op=finalStop($db,$f);finalSuspend($db,$f);finalResolve($db,$f,$op);finalSingle($db,$f,$op,'SUSPENDED');
});
test('Final P0 legacy UNKNOWN never uses historical ACTIVE audit as authorization',function()use($db): void{
    $f=finalFixture($db);$op=finalStop($db,$f);$db->transaction(fn()=>(new \Hostider\Services\CloudServices($db,$f['config']))->state($f['service']['id'],'UNKNOWN'));finalResolve($db,$f,$op);finalSingle($db,$f,$op,'SUSPENDED');
});
test('Final P0 repeated reconciliation cannot duplicate financial or provider effect',function()use($db): void{
    $f=finalFixture($db);$op=finalStop($db,$f);finalRefund($db,$f);[$obs,$c,$a]=finalResolve($db,$f,$op);$before=finalFinance($db);$version=finalProvider($db,$f)->observe($op)['resource']['version'];raises(HttpError::class,fn()=>$f['engine']->resolve($op,$obs,$c,$a));raises(HttpError::class,fn()=>$f['engine']->observe($op,$a));finalSingle($db,$f,$op,'SUSPENDED');same($version,finalProvider($db,$f)->observe($op)['resource']['version']);same($before,finalFinance($db));
});
function finalQueue($db,array $f,string $type,string $op,$sink): void{
    $q=new \Hostider\Core\Queue\Queue($db);$key='final-receipt:'.$op;$db->transaction(fn()=>$q->enqueue($type,['operation_id'=>$op],$key,Id::uuid()));$w=new \Hostider\Core\Queue\Worker($db,$q);$sink->register($w);same(true,$w->once());same('SUCCEEDED',$db->execute('SELECT status FROM jobs WHERE dedupe_key=?',[$key])->fetchColumn());
}
test('Final P1 Cloud WAITING_TASK after successful receipt resumes through independent cron',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);finalQueue($db,$f,'cloud.execute',$op,new EventSink($db,$f['config'],false));same('WAITING_TASK',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same(true,domainCli($f,['cloud:work','--once'])['worked']);same('SUCCEEDED',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());
});
test('Final P1 Cloud PENDING after receipt resumes through independent cron with one CREATE',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);$db->execute("UPDATE svc_services SET status='PENDING_APPROVAL' WHERE id=?",[$f['service']['id']]);finalQueue($db,$f,'cloud.execute',$op,new EventSink($db,$f['config']));same('PENDING',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());$db->execute("UPDATE svc_services SET status='QUEUED' WHERE id=?",[$f['service']['id']]);same(true,domainCli($f,['cloud:work','--once'])['worked']);same(true,domainCli($f,['cloud:work','--once'])['worked']);same('SUCCEEDED',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());
});
test('Final P1 Domain PENDING after receipt resumes through independent cron without duplicate register',function()use($db): void{
    $f=domainFixture($db);$domain=$f['attach']();$e=new \Hostider\Domains\DomainEngine($db,$f['config']);$op=$e->enqueueRegister($domain,$f['customer'],$f['actor'],Id::uuid());$db->execute("UPDATE svc_services SET status='PENDING_APPROVAL' WHERE id=?",[$f['service']['id']]);finalQueue($db,$f,'domains.execute',$op,new \Hostider\Domains\EventSink($db,$f['config']));same('PENDING',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$op])->fetchColumn());$db->execute("UPDATE svc_services SET status='PROVISIONING' WHERE id=?",[$f['service']['id']]);same(true,domainCli($f,['domains:work','--once'])['worked']);same(false,domainCli($f,['domains:work','--once'])['worked']);same('SUCCEEDED',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$op])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=(SELECT active_attempt_id FROM domain_operations WHERE id=?)',[$op])->fetchColumn());
});
runTests();
