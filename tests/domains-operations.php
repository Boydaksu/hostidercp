<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';
require __DIR__ . '/database.php';
require_once __DIR__ . '/domains-fixture.php';

use Hostider\Core\{CanonicalJson,Id};
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Domains\{DomainEngine,DomainReconciliation,DomainRunner,DomainWorker,Registry,TldPolicy};
use Hostider\Finance\{Currency,Money};
use Hostider\Providers\Contract\{Operation,ProviderResult};
use Hostider\Providers\Mock\DurableRegistrar;

test('domain operation enqueue requires canonical paid order, item and queued service', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();

    // 1. Initial valid enqueue
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);
    same(true, is_string($opId) && strlen($opId) === 36);

    $op = $db->execute('SELECT * FROM domain_operations WHERE id=?', [$opId])->fetch();
    same('PENDING', $op['status']);
    same('REGISTER', $op['kind']);
    same(1, (int)$op['generation']);
    same($domainId, $op['domain_id']);

    $attempt = $db->execute('SELECT * FROM domain_registrar_attempts WHERE operation_id=?', [$opId])->fetch();
    same(1, (int)$attempt['attempt_no']);
    same('PENDING', $attempt['status']);

    $domain = $db->execute('SELECT status FROM domains WHERE id=?', [$domainId])->fetchColumn();
    same('REGISTERING', $domain);

    // 2. Idempotent re-enqueue returns existing operation
    $reOpId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);
    same($opId, $reOpId);
});

test('domain operation claim locks funding source, assigns lease token and fences race', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    // 1. First claim succeeds
    $claim = $engine->claim($f['actor'], $trace);
    same(true, $claim !== null);
    same($opId, $claim['operation_id']);
    same(1, $claim['attempt_no']);
    same(true, is_string($claim['lease_token']));

    // 2. Concurrent second claim gets null (already RUNNING)
    $secondClaim = $engine->claim($f['actor'], $trace);
    same(null, $secondClaim);

    // 3. If source billing is refunded before claim, claim throws 409 DOMAIN_SOURCE_CHANGED
    $f2 = domainFixture($db);
    $domainId2 = $f2['attach']();
    $opId2 = $engine->enqueueRegister($domainId2, $f2['customer'], $f2['actor'], $trace);

    $db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=(SELECT payment_request_id FROM orders WHERE id=?)", [$f2['order']['id']]);
    same(null, $engine->claim($f2['actor'], $trace, $opId2));
    same('DOMAIN_SOURCE_CHANGED', $db->execute('SELECT runner_hold_code FROM domain_operations WHERE id=?', [$opId2])->fetchColumn());
});

test('domain operation register success: verified proof creates domain_evidence and transitions domain to REGISTERED', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    $claim = $engine->claim($f['actor'], $trace);
    same(true, $claim !== null);

    // Run provider mock
    $registrar = new DurableRegistrar($db, $f['config'], $claim['account']['id'], 'SUCCESS');
    $op = new Operation($claim['attempt_id'], 'register', null, $claim['payload']['configuration']);
    $result = $registrar->execute($op);
    same('SUCCESS', $result->status);
    same('MOCK_OK', $result->safeErrorCode);

    $proof = $registrar->evidence($claim['payload']['configuration']['domain']);
    same(true, $proof !== null);

    $engine->finishAttempt($opId, $claim['lease_token'], $result, $proof, $f['actor'], $trace);

    // Check DB state
    $opRow = $db->execute('SELECT * FROM domain_operations WHERE id=?', [$opId])->fetch();
    same('SUCCEEDED', $opRow['status']);

    $domainRow = $db->execute('SELECT * FROM domains WHERE id=?', [$domainId])->fetch();
    same('REGISTERED', $domainRow['status']);
    same($result->externalResourceId, $domainRow['provider_external_id']);
    same(true, $domainRow['expires_at'] !== null);
    same(1, (int)$domainRow['locked']);

    $svcRow = $db->execute('SELECT * FROM svc_services WHERE id=?', [$domainRow['service_id']])->fetch();
    same('ACTIVE', $svcRow['status']);

    $evidenceRow = $db->execute('SELECT * FROM domain_evidence WHERE operation_id=?', [$opId])->fetch();
    same(true, $evidenceRow !== false);
    same($claim['attempt_id'], $evidenceRow['attempt_id']);
});

test('DOMAIN-001: registrar timeout produces UNKNOWN, forbids retry and alternative dispatch', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    $claim = $engine->claim($f['actor'], $trace);

    // Simulate TIMEOUT_BEFORE_EFFECT
    $registrar = new DurableRegistrar($db, $f['config'], $claim['account']['id'], 'TIMEOUT_BEFORE_EFFECT');
    $op = new Operation($claim['attempt_id'], 'register', null, $claim['payload']['configuration']);
    $result = $registrar->execute($op);
    same('UNKNOWN', $result->status);

    $engine->finishAttempt($opId, $claim['lease_token'], $result, null, $f['actor'], $trace);

    $opRow = $db->execute('SELECT * FROM domain_operations WHERE id=?', [$opId])->fetch();
    same('UNKNOWN', $opRow['status']);

    $domainRow = $db->execute('SELECT * FROM domains WHERE id=?', [$domainId])->fetch();
    same('UNKNOWN', $domainRow['status']);

    $svcRow = $db->execute('SELECT * FROM svc_services WHERE id=?', [$domainRow['service_id']])->fetch();
    same('UNKNOWN', $svcRow['status']);

    // Attempt count must strictly remain 1 (no automatic alternative registrar dispatch!)
    $attemptsCount = (int)$db->execute('SELECT COUNT(*) FROM domain_registrar_attempts WHERE operation_id=?', [$opId])->fetchColumn();
    same(1, $attemptsCount);
});

test('DOMAIN-001 reconciliation: independent inspect verifies external registrar; rejects forged proof', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    $claim = $engine->claim($f['actor'], $trace);

    // Simulate TIMEOUT_AFTER_EFFECT (external resource created, but timeout returned)
    $registrar = new DurableRegistrar($db, $f['config'], $claim['account']['id'], 'TIMEOUT_AFTER_EFFECT');
    $op = new Operation($claim['attempt_id'], 'register', null, $claim['payload']['configuration']);
    $result = $registrar->execute($op);
    same('UNKNOWN', $result->status);

    $engine->finishAttempt($opId, $claim['lease_token'], $result, null, $f['actor'], $trace);

    // Staff reconciliation
    $reconciliation = new DomainReconciliation($db, $f['config']);
    $staff = new Principal('staff-reconciler', ['services.manage'], staff: true, mfa: true, expiresAt: time()+3600, verifiedAt: time());

    // 1. Independent inspect directly against registrar instance
    $inspectResult = $reconciliation->inspect($opId, $registrar, $staff, $trace);
    same(true, $inspectResult['found']);
    same(false, $inspectResult['definitive_no_effect']);

    // Operation transitioned to MANUAL_REVIEW
    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('MANUAL_REVIEW', $opRow);

    // 2. Reject CONFIRMED_FAILURE when resource actually exists!
    raises(HttpError::class, fn () => $reconciliation->resolve($opId, $inspectResult['inspection_id'], 'CONFIRMED_FAILURE', $staff, $trace));

    // 3. Confirm SUCCESS succeeds with independent proof
    $reconciliation->resolve($opId, $inspectResult['inspection_id'], 'CONFIRMED_SUCCESS', $staff, $trace);

    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('SUCCEEDED', $opRow);

    $domainRow = $db->execute('SELECT status, provider_external_id FROM domains WHERE id=?', [$domainId])->fetch();
    same('REGISTERED', $domainRow['status']);
    same(true, $domainRow['provider_external_id'] !== null);

    $svcRow = $db->execute('SELECT status FROM svc_services WHERE id=?', [$f['service']['id']])->fetchColumn();
    same('ACTIVE', $svcRow);
});

test('DOMAIN-001 reconciliation with funding revoked decouples physical domain from active service', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    $claim = $engine->claim($f['actor'], $trace);

    $registrar = new DurableRegistrar($db, $f['config'], $claim['account']['id'], 'TIMEOUT_AFTER_EFFECT');
    $op = new Operation($claim['attempt_id'], 'register', null, $claim['payload']['configuration']);
    $result = $registrar->execute($op);
    $engine->finishAttempt($opId, $claim['lease_token'], $result, null, $f['actor'], $trace);

    // Funding revoked after timeout
    $db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=(SELECT payment_request_id FROM orders WHERE id=?)", [$f['order']['id']]);

    $reconciliation = new DomainReconciliation($db, $f['config']);
    $staff = new Principal('staff-reconciler', ['services.manage'], staff: true, mfa: true, expiresAt: time()+3600, verifiedAt: time());
    $inspectResult = $reconciliation->inspect($opId, $registrar, $staff, $trace);

    // Resolve detects revoked funding: domain marked REGISTERED physically, but service SUSPENDED!
    $reconciliation->resolve($opId, $inspectResult['inspection_id'], 'CONFIRMED_SUCCESS', $staff, $trace);

    $domainRow = $db->execute('SELECT status FROM domains WHERE id=?', [$domainId])->fetchColumn();
    same('REGISTERED', $domainRow);

    $svcRow = $db->execute('SELECT status FROM svc_services WHERE id=?', [$f['service']['id']])->fetchColumn();
    same('SUSPENDED', $svcRow);
});

test('DOMAIN-002: verified definitive failure holds failover without current commercial evidence', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db,3);

    // Configure alternative registrar rule for .example (DomainNameApi)
    $altPolicy = new TldPolicy(['availability', 'register', 'renew', 'initiateTransfer', 'status', 'nameservers'], ['name', 'email', 'country'], null, null);
    $altRule = $f['registry']->configure('example', 'domainnameapi', $altPolicy);

    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    // Claim 1st attempt (ResellerClub)
    $claim1 = $engine->claim($f['actor'], $trace);
    same(1, $claim1['attempt_no']);
    same(3, $claim1['payload']['configuration']['years']);

    // 1st attempt fails with DEFINITIVE_FAILURE via DurableRegistrar (writes mock_registrar_calls with no_effect=1)
    $registrar1 = new DurableRegistrar($db, $f['config'], $claim1['account']['id'], 'DEFINITIVE_FAILURE');
    $op1 = new Operation($claim1['attempt_id'], 'register', null, $claim1['payload']['configuration']);
    $result1 = $registrar1->execute($op1);
    same('DEFINITIVE_FAILURE', $result1->status);

    $engine->finishAttempt($opId, $claim1['lease_token'], $result1, null, $f['actor'], $trace);

    // No current quote/balance evidence: never silently change term or issue another create.
    same('MANUAL_REVIEW', $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn());
    same('FAILOVER_COMMERCIAL_EVIDENCE_REQUIRED', $db->execute('SELECT runner_hold_code FROM domain_operations WHERE id=?', [$opId])->fetchColumn());
    same(1, (int)$db->execute('SELECT COUNT(*) FROM domain_registrar_attempts WHERE operation_id=?', [$opId])->fetchColumn());
    same(false, (new DomainRunner($db,$f['config']))->once($f['actor']));
    same(0, (int)$db->execute('SELECT COUNT(*) FROM mock_registrar_domains')->fetchColumn());
    same(1, (int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls')->fetchColumn());
});

test('DOMAIN-002: unsupported capability does not trigger failover and rejects operation', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    $claim = $engine->claim($f['actor'], $trace);

    // DurableRegistrar returns UNSUPPORTED for actions like dns/epp
    $registrar = new DurableRegistrar($db,$f['config'],$claim['account']['id'],'UNSUPPORTED');
    $result = $registrar->execute(new Operation($claim['attempt_id'],'register',null,$claim['payload']['configuration']));
    same('UNSUPPORTED',$result->status);
    $engine->finishAttempt($opId, $claim['lease_token'], $result, null, $f['actor'], $trace);

    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('FAILED', $opRow);

    // No attempt 2 was scheduled!
    $attemptCount = (int)$db->execute('SELECT COUNT(*) FROM domain_registrar_attempts WHERE operation_id=?', [$opId])->fetchColumn();
    same(1, $attemptCount);
});

test('Domain renewal blocks concurrent in-flight renewals and rejects another paid DOMAIN order', function (): void {
    $db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();$f=domainFixture($db);$id=$f['attach']();
    $engine=new DomainEngine($db,$f['config']);$trace=Id::uuid();$engine->enqueueRegister($id,$f['customer'],$f['actor'],$trace);
    same(true,(new DomainRunner($db,$f['config']))->once($f['actor']));
    $renewal=paidDomainRenewal($db,$f);
    // A valid paid order for a different domain (same customer) is not this immutable renewal source.
    $otherCart=[['product_id'=>$f['item']['product_id'],'cycle_id'=>$f['cycle'],'quantity'=>1]];
    $other=$db->transaction(function()use($f,$otherCart): array{
        $q=$f['quotes']->issue($f['quotes']->plan($f['customer'],new Currency('USD'),$otherCart,[],$f['now']));
        $o=$f['orders']->submit($q['id'],$f['customer'],['name'=>'Other domain','currency'=>'USD'],$f['actor'],Id::uuid(),$f['now']);
        $m=new Money($q['total_minor'],new Currency('USD'));$p=$f['finance']->recordTestCollection($f['customer'],$m,Id::uuid(),$f['actor'],Id::uuid());
        $f['orders']->fund($o['id'],$f['customer'],$m,'COLLECTION',$p,Id::uuid(),$f['actor'],Id::uuid());return $o;
    });
    same('PAID',$f['billing']->detail($other['payment_request_id'],$f['customer'])['status']);
    $otherItem=$db->execute('SELECT * FROM order_items WHERE order_id=?',[$other['id']])->fetch();
    $otherLine=json_decode($otherItem['snapshot'],true,64,JSON_THROW_ON_ERROR);$other['currency']='USD';
    $otherService=$db->transaction(fn()=>$f['repository']->materialize($other,$otherItem,$otherLine,1,$f['category'],$f['actor'],Id::uuid()));
    $otherActor=new Principal('other-domain-owner',['services.manage','services.view'],customerId:$f['customer'],expiresAt:time()+3600,verifiedAt:time(),grants:[new \Hostider\Core\Security\AccessGrant('services.manage',[$f['customer']],[$otherService['id']])]);
    $otherDomain=$db->transaction(fn()=>$f['registry']->attach($otherService['id'],$f['customer'],'other'.str_replace('-','',Id::uuid()).'.example',$f['rule'],$f['contact'],$f['ns'],$otherActor,Id::uuid()));
    same(true,$otherDomain!==$id);
    raises(HttpError::class,fn()=>$engine->enqueueRenewal($id,$other['id'],$renewal['period_id'],1,$f['actor'],$trace));
    // Initial hosting invoice, original registration and wrong purchased term are also rejected.
    raises(HttpError::class,fn()=>$engine->enqueueRenewal($id,$f['hostingOrder']['id'],$renewal['period_id'],1,$f['actor'],$trace));
    raises(HttpError::class,fn()=>$engine->enqueueRenewal($id,$f['order']['id'],$renewal['period_id'],1,$f['actor'],$trace));
    raises(HttpError::class,fn()=>$engine->enqueueRenewal($id,$renewal['order_id'],$renewal['period_id'],2,$f['actor'],$trace));
    $op=$engine->enqueueRenewal($id,$renewal['order_id'],$renewal['period_id'],1,$f['actor'],$trace);
    raises(HttpError::class,fn()=>$engine->enqueueRenewal($id,$other['id'],$renewal['period_id'],1,$f['actor'],$trace));
    same(true,(new DomainRunner($db,$f['config']))->once($f['actor'],$op));
    same(1,(int)$db->execute("SELECT COUNT(*) FROM mock_registrar_calls WHERE action='renew'")->fetchColumn());
});

test('Domain renewal increment remains exact through second timeout-after-effect reconciliation', function (): void {
    $db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();$f=domainFixture($db);$id=$f['attach']();
    $engine=new DomainEngine($db,$f['config']);$trace=Id::uuid();$engine->enqueueRegister($id,$f['customer'],$f['actor'],$trace);
    same(true,(new DomainRunner($db,$f['config']))->once($f['actor']));
    $initial=$db->execute('SELECT expires_at FROM domains WHERE id=?',[$id])->fetchColumn();
    $oldPeriod=$db->execute('SELECT * FROM svc_periods WHERE id=(SELECT current_period_id FROM svc_services WHERE id=?)',[$f['service']['id']])->fetch();
    $first=paidDomainRenewal($db,$f);$op1=$engine->enqueueRenewal($id,$first['order_id'],$first['period_id'],1,$f['actor'],$trace);
    same(true,(new DomainRunner($db,$f['config']))->once($f['actor'],$op1));
    same((new DateTimeImmutable($initial))->modify('+1 year')->format('Y-m-d H:i:s.u'),$db->execute('SELECT expires_at FROM domains WHERE id=?',[$id])->fetchColumn());
    $second=paidDomainRenewal($db,$f);$op2=$engine->enqueueRenewal($id,$second['order_id'],$second['period_id'],1,$f['actor'],$trace);
    same(true,(new DomainRunner($db,$f['config'],'TIMEOUT_AFTER_EFFECT'))->once($f['actor'],$op2));
    same('UNKNOWN',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$op2])->fetchColumn());
    $account=$db->execute('SELECT primary_account_id FROM domain_operations WHERE id=?',[$op2])->fetchColumn();
    $reconcile=new DomainReconciliation($db,$f['config']);$staff=new Principal('reconciler',['services.manage'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time());
    $inspection=$reconcile->inspect($op2,new DurableRegistrar($db,$f['config'],$account),$staff,$trace);
    same(true,$inspection['found']);$reconcile->resolve($op2,$inspection['inspection_id'],'CONFIRMED_SUCCESS',$staff,$trace);
    $expected=(new DateTimeImmutable($initial))->modify('+2 years')->format('Y-m-d H:i:s.u');
    same($expected,$db->execute('SELECT expires_at FROM domains WHERE id=?',[$id])->fetchColumn());
    same($second['period_id'],$db->execute('SELECT current_period_id FROM svc_services WHERE id=?',[$f['service']['id']])->fetchColumn());
    same('VERIFIED',$db->execute('SELECT status FROM domain_renewals WHERE operation_id=?',[$op2])->fetchColumn());
    same($expected,$db->execute('SELECT ends_at FROM svc_periods WHERE id=?',[$second['period_id']])->fetchColumn());
    same($oldPeriod,$db->execute('SELECT * FROM svc_periods WHERE id=?',[$oldPeriod['id']])->fetch());
    raises(HttpError::class,fn()=>$reconcile->resolve($op2,$inspection['inspection_id'],'CONFIRMED_SUCCESS',$staff,$trace));
    same(false,(new DomainRunner($db,$f['config']))->once($f['actor'],$op2));
    same(2,(int)$db->execute("SELECT COUNT(*) FROM mock_registrar_calls WHERE action='renew'")->fetchColumn());
    same(3,(int)$db->execute('SELECT term_years FROM mock_registrar_domains')->fetchColumn());
});

test('Queue starvation avoidance: healthy domain operation progresses behind 101 blocked operations', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();

    // Create 101 blocked operations with refunded sources
    for ($i = 1; $i <= 101; ++$i) {
        $fi = domainFixture($db);
        $dId = $fi['attach']();
        $opId = $engine->enqueueRegister($dId, $fi['customer'], $fi['actor'], $trace);
        // Revoke funding
        $db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=(SELECT payment_request_id FROM orders WHERE id=?)", [$fi['order']['id']]);
    }

    // Now create 1 healthy operation at the back of the queue
    $healthyDomainId = $f['attach']();
    $healthyOpId = $engine->enqueueRegister($healthyDomainId, $f['customer'], $f['actor'], $trace);

    // Run DomainRunner
    $runner = new DomainRunner($db, $f['config'], 'SUCCESS');
    $worked = $runner->once($f['actor']);

    // One bounded admission examines at most 100 candidates; the next cron makes progress.
    same(false, $worked);
    same(100,(int)$db->execute("SELECT COUNT(*) FROM domain_operations WHERE runner_hold_code='DOMAIN_SOURCE_CHANGED'")->fetchColumn());
    same(true,$runner->once($f['actor']));
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_domains')->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls')->fetchColumn());

    $healthyStatus = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$healthyOpId])->fetchColumn();
    same('SUCCEEDED', $healthyStatus);
    $new=domainFixture($db);$newDomain=$new['attach']();$newOp=$engine->enqueueRegister($newDomain,$new['customer'],$new['actor'],$trace);
    same(true,$runner->once($new['actor']));
    same('SUCCEEDED',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$newOp])->fetchColumn());
    same(2,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls')->fetchColumn());
});

test('CLI and outbox integration executes domain operations idempotently', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    // Actual console subprocesses, bounded dispatch and queue drain; no event ordering assumption.
    $dispatched=domainCli($f,['outbox:dispatch']);same(true,$dispatched['dispatched']>1);
    $consumed=0;
    for($i=0;$i<100;++$i){$result=domainCli($f,['queue:work','--once']);same(true,is_bool($result['worked']));if(!$result['worked']){break;}++$consumed;}
    same(true,$consumed>1);
    same('SUCCEEDED',$db->execute('SELECT status FROM domain_operations WHERE id=?',[$opId])->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_domains')->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls')->fetchColumn());
    $sink=new \Hostider\Domains\EventSink($db,$f['config']);same('SUCCEEDED',$sink->consume('domains.execute',['operation_id'=>$opId]));
    same(false,domainCli($f,['domains:work','--once'])['worked']);
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls')->fetchColumn());
});

runTests();
