<?php
declare(strict_types=1);
require_once __DIR__ . '/domains-fixture.php';

use Hostider\Core\{CanonicalJson,Id};
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Domains\{DomainEngine,DomainReconciliation,DomainWorker,Registry,TldPolicy};
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

    // 3. If source billing is refunded before claim, claim is rejected
    $f2 = domainFixture($db);
    $domainId2 = $f2['attach']();
    $opId2 = $engine->enqueueRegister($domainId2, $f2['customer'], $f2['actor'], $trace);

    $db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=(SELECT payment_request_id FROM orders WHERE id=?)", [$f2['order']['id']]);
    raises(HttpError::class, fn () => $engine->claim($f2['actor'], $trace));
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

test('DOMAIN-001 reconciliation: inspection finds domain or records not-found; staff resolve confirms success/failure', function (): void {
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
    $staff = new Principal('staff-reconciler', ['services.manage'], staff: true, mfa: true, verifiedAt: time());

    // 1. Inspect discovers the created domain on registrar
    $externalProof = $registrar->evidence($claim['payload']['configuration']['domain']);
    same(true, $externalProof !== null);

    $inspectResult = $reconciliation->inspect($opId, $externalProof, $staff, $trace);
    same(true, $inspectResult['found']);

    // Operation is now in MANUAL_REVIEW
    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('MANUAL_REVIEW', $opRow);

    // 2. Staff resolves with CONFIRMED_SUCCESS
    $reconciliation->resolve($opId, $inspectResult['inspection_id'], 'CONFIRMED_SUCCESS', $staff, $trace);

    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('SUCCEEDED', $opRow);

    $domainRow = $db->execute('SELECT status, provider_external_id FROM domains WHERE id=?', [$domainId])->fetch();
    same('REGISTERED', $domainRow['status']);
    same($externalProof['id'], $domainRow['provider_external_id']);

    $svcRow = $db->execute('SELECT status FROM svc_services WHERE id=?', [$f['service']['id']])->fetchColumn();
    same('ACTIVE', $svcRow);
});

test('DOMAIN-002: definitive failure with proven no-effect triggers safe failover attempt', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);

    // Setup an alternative registrar rule for .example (DomainNameApi)
    $altPolicy = new TldPolicy(['availability', 'register', 'renew', 'initiateTransfer', 'status', 'nameservers'], ['name', 'email', 'country'], null, null);
    $altRule = $f['registry']->configure('example', 'domainnameapi', $altPolicy);

    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    // Claim 1st attempt (ResellerClub)
    $claim1 = $engine->claim($f['actor'], $trace);
    same(1, $claim1['attempt_no']);

    // 1st attempt fails definitively with no_effect=TRUE
    $result1 = new ProviderResult($claim1['attempt_id'], 'resellerclub', 'DEFINITIVE_FAILURE', null, 'MOCK_NO_EFFECT', true);
    $engine->finishAttempt($opId, $claim1['lease_token'], $result1, null, $f['actor'], $trace);

    // Operation status reset to PENDING for failover!
    $opRow = $db->execute('SELECT * FROM domain_operations WHERE id=?', [$opId])->fetch();
    same('PENDING', $opRow['status']);

    $attempt2 = $db->execute('SELECT * FROM domain_registrar_attempts WHERE operation_id=? AND attempt_no=2', [$opId])->fetch();
    same(true, $attempt2 !== false);
    same('PENDING', $attempt2['status']);
    same(false, hash_equals($claim1['attempt_id'], $attempt2['id'])); // Distinct UUID!

    // Claim 2nd attempt (DomainNameApi)
    $claim2 = $engine->claim($f['actor'], $trace);
    same(true, $claim2 !== null);
    same(2, $claim2['attempt_no']);

    // 2nd attempt succeeds
    $registrar2 = new DurableRegistrar($db, $f['config'], $claim2['account']['id'], 'SUCCESS');
    $op2 = new Operation($claim2['attempt_id'], 'register', null, $claim2['payload']['configuration']);
    $result2 = $registrar2->execute($op2);
    same('SUCCESS', $result2->status);

    $proof2 = $registrar2->evidence($claim2['payload']['configuration']['domain']);
    $engine->finishAttempt($opId, $claim2['lease_token'], $result2, $proof2, $f['actor'], $trace);

    $finalOp = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('SUCCEEDED', $finalOp);

    $finalDomain = $db->execute('SELECT status FROM domains WHERE id=?', [$domainId])->fetchColumn();
    same('REGISTERED', $finalDomain);
});

test('DOMAIN-002: definitive failure without matching alternative registrar marks operation FAILED', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    $claim1 = $engine->claim($f['actor'], $trace);

    // No alternative registrar configured for this TLD!
    $result1 = new ProviderResult($claim1['attempt_id'], 'resellerclub', 'DEFINITIVE_FAILURE', null, 'MOCK_NO_EFFECT', true);
    $engine->finishAttempt($opId, $claim1['lease_token'], $result1, null, $f['actor'], $trace);

    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('FAILED', $opRow);

    $domainRow = $db->execute('SELECT status FROM domains WHERE id=?', [$domainId])->fetchColumn();
    same('FAILED', $domainRow);
});

test('Domain renewal requires separate paid order and period, increments generation', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    // Register success first
    $claim = $engine->claim($f['actor'], $trace);
    $registrar = new DurableRegistrar($db, $f['config'], $claim['account']['id'], 'SUCCESS');
    $op = new Operation($claim['attempt_id'], 'register', null, $claim['payload']['configuration']);
    $res = $registrar->execute($op);
    $proof = $registrar->evidence($claim['payload']['configuration']['domain']);
    $engine->finishAttempt($opId, $claim['lease_token'], $res, $proof, $f['actor'], $trace);

    // 1. Trying renewal with initial order is strictly FORBIDDEN
    $newPeriodId = Id::uuid();
    $cycleJson = CanonicalJson::encode(['kind' => 'ANNUAL', 'interval_count' => 1]);
    $db->execute("INSERT INTO svc_periods(id,service_id,cycle_snapshot,status,version) VALUES (?,?,?,'PENDING_PROVIDER',1)", [$newPeriodId, $f['service']['id'], $cycleJson]);

    raises(HttpError::class, fn () => $engine->enqueueRenewal($domainId, $f['order']['id'], $newPeriodId, 1, $f['actor'], $trace));

    // 2. Renewal with new paid order succeeds
    $cart = [['product_id' => $f['item']['product_id'], 'cycle_id' => $f['cycle'], 'quantity' => 1]];
    $renewalOrder = $db->transaction(function () use ($f, $cart): array {
        $quote = $f['quotes']->issue($f['quotes']->plan($f['customer'], new Currency('USD'), $cart, [], $f['now']));
        $order = $f['orders']->submit($quote['id'], $f['customer'], ['name' => 'Domain Renewal', 'currency' => 'USD'], $f['actor'], Id::uuid(), $f['now']);
        $money = new Money($quote['total_minor'], new Currency('USD'));
        $payment = $f['finance']->recordTestCollection($f['customer'], $money, Id::uuid(), $f['actor'], Id::uuid());
        $f['orders']->fund($order['id'], $f['customer'], $money, 'COLLECTION', $payment, Id::uuid(), $f['actor'], Id::uuid());
        return $order;
    });

    $renewOpId = $engine->enqueueRenewal($domainId, $renewalOrder['id'], $newPeriodId, 1, $f['actor'], $trace);
    same(true, is_string($renewOpId) && strlen($renewOpId) === 36);

    $renewOp = $db->execute('SELECT * FROM domain_operations WHERE id=?', [$renewOpId])->fetch();
    same('RENEW', $renewOp['kind']);
    same(2, (int)$renewOp['generation']);

    // Worker processes renewal
    $worker = new DomainWorker($db, $f['config'], 'SUCCESS');
    $summary = $worker->work($f['actor'], $trace);
    same(1, $summary['succeeded']);

    $renewOp = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$renewOpId])->fetchColumn();
    same('SUCCEEDED', $renewOp);

    $renewalRow = $db->execute('SELECT status FROM domain_renewals WHERE operation_id=?', [$renewOpId])->fetchColumn();
    same('VERIFIED', $renewalRow);
});

test('Lease expiration fencing: stale worker cannot finish after lease expired', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    $claim = $engine->claim($f['actor'], $trace);

    // Force lease to expire in DB
    $db->execute("UPDATE domain_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 10 SECOND) WHERE id=?", [$opId]);

    // Expire runner triggers
    $expiredCount = $engine->expire($f['actor'], $trace);
    same(1, $expiredCount);

    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('UNKNOWN', $opRow);

    // Stale worker attempts finishAttempt with expired token -> 409
    $dummyResult = new ProviderResult($claim['attempt_id'], 'resellerclub', 'SUCCESS', Id::uuid(), 'MOCK_OK');
    raises(HttpError::class, fn () => $engine->finishAttempt($opId, $claim['lease_token'], $dummyResult, null, $f['actor'], $trace));
});

test('Dispatch source change: funding revoked during execution marks UNKNOWN, preserves physical outcome', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    $claim = $engine->claim($f['actor'], $trace);

    // Provider creates domain physically
    $registrar = new DurableRegistrar($db, $f['config'], $claim['account']['id'], 'SUCCESS');
    $op = new Operation($claim['attempt_id'], 'register', null, $claim['payload']['configuration']);
    $result = $registrar->execute($op);
    $proof = $registrar->evidence($claim['payload']['configuration']['domain']);

    // Funding revoked while provider was running
    $db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=(SELECT payment_request_id FROM orders WHERE id=?)", [$f['order']['id']]);

    // finishAttempt receives result, but detects funding changed after dispatch
    $engine->finishAttempt($opId, $claim['lease_token'], $result, $proof, $f['actor'], $trace);

    // Must be UNKNOWN for reconciliation! Physical domain was created, cannot be silently dropped
    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('UNKNOWN', $opRow);

    $domainRow = $db->execute('SELECT status FROM domains WHERE id=?', [$domainId])->fetchColumn();
    same('UNKNOWN', $domainRow);
});

runTests();
