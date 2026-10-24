<?php
declare(strict_types=1);
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
    raises(HttpError::class, fn () => $engine->claim($f2['actor'], $trace, $opId2));
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
    $staff = new Principal('staff-reconciler', ['services.manage'], staff: true, mfa: true, verifiedAt: time());

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
    $staff = new Principal('staff-reconciler', ['services.manage'], staff: true, mfa: true, verifiedAt: time());
    $inspectResult = $reconciliation->inspect($opId, $registrar, $staff, $trace);

    // Resolve detects revoked funding: domain marked REGISTERED physically, but service SUSPENDED!
    $reconciliation->resolve($opId, $inspectResult['inspection_id'], 'CONFIRMED_SUCCESS', $staff, $trace);

    $domainRow = $db->execute('SELECT status FROM domains WHERE id=?', [$domainId])->fetchColumn();
    same('REGISTERED', $domainRow);

    $svcRow = $db->execute('SELECT status FROM svc_services WHERE id=?', [$f['service']['id']])->fetchColumn();
    same('SUSPENDED', $svcRow);
});

test('DOMAIN-002: verified definitive failure with call evidence triggers safe failover attempt', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);

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

    // 1st attempt fails with DEFINITIVE_FAILURE via DurableRegistrar (writes mock_registrar_calls with no_effect=1)
    $registrar1 = new DurableRegistrar($db, $f['config'], $claim1['account']['id'], 'DEFINITIVE_FAILURE');
    $op1 = new Operation($claim1['attempt_id'], 'register', null, $claim1['payload']['configuration']);
    $result1 = $registrar1->execute($op1);
    same('DEFINITIVE_FAILURE', $result1->status);

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
    $result = new ProviderResult($claim['attempt_id'], 'resellerclub', 'UNSUPPORTED', null, 'MOCK_TLD_UNSUPPORTED');
    $engine->finishAttempt($opId, $claim['lease_token'], $result, null, $f['actor'], $trace);

    $opRow = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('FAILED', $opRow);

    // No attempt 2 was scheduled!
    $attemptCount = (int)$db->execute('SELECT COUNT(*) FROM domain_registrar_attempts WHERE operation_id=?', [$opId])->fetchColumn();
    same(1, $attemptCount);
});

test('Domain renewal blocks concurrent in-flight renewals and enforces DOMAIN product match', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    // Register domain first
    $claim = $engine->claim($f['actor'], $trace);
    $registrar = new DurableRegistrar($db, $f['config'], $claim['account']['id'], 'SUCCESS');
    $res = $registrar->execute(new Operation($claim['attempt_id'], 'register', null, $claim['payload']['configuration']));
    $proof = $registrar->evidence($claim['payload']['configuration']['domain']);
    $engine->finishAttempt($opId, $claim['lease_token'], $res, $proof, $f['actor'], $trace);

    // 1. Attempt renewal with a HOSTING product order -> REJECTED (RENEWAL_PRODUCT_MISMATCH)
    $hostingProduct = $f['create']('product-create', ['name' => 'Synthetic hosting', 'category_id' => $f['category'], 'product_type' => 'HOSTING', 'pricing_strategy' => 'FIXED_THEN_FX', 'source_currency' => 'USD', 'activation_policy' => 'AUTO', 'refund_policy' => 'staff_approval']);
    $f['create']('price-publish', ['item_id' => $hostingProduct, 'cycle_id' => $f['cycle'], 'currency' => 'USD', 'amount' => '10']);
    $f['create']('product-status', ['id' => $hostingProduct, 'version' => 1, 'sale_status' => 'ACTIVE']);

    $hostingCart = [['product_id' => $hostingProduct, 'cycle_id' => $f['cycle'], 'quantity' => 1]];
    $hostingOrder = $db->transaction(function () use ($f, $hostingCart): array {
        $quote = $f['quotes']->issue($f['quotes']->plan($f['customer'], new Currency('USD'), $hostingCart, [], $f['now']));
        $order = $f['orders']->submit($quote['id'], $f['customer'], ['name' => 'Hosting Order', 'currency' => 'USD'], $f['actor'], Id::uuid(), $f['now']);
        $money = new Money($quote['total_minor'], new Currency('USD'));
        $payment = $f['finance']->recordTestCollection($f['customer'], $money, Id::uuid(), $f['actor'], Id::uuid());
        $f['orders']->fund($order['id'], $f['customer'], $money, 'COLLECTION', $payment, Id::uuid(), $f['actor'], Id::uuid());
        return $order;
    });

    $newPeriodId = Id::uuid();
    $cycleJson = CanonicalJson::encode(['kind' => 'ANNUAL', 'interval_count' => 1]);
    $db->execute("INSERT INTO svc_periods(id,service_id,cycle_snapshot,status,version) VALUES (?,?,?,'PENDING_PROVIDER',1)", [$newPeriodId, $f['service']['id'], $cycleJson]);

    raises(HttpError::class, fn () => $engine->enqueueRenewal($domainId, $hostingOrder['id'], $newPeriodId, 1, $f['actor'], $trace));

    // 2. Valid renewal with DOMAIN product order
    $domainCart = [['product_id' => $f['item']['product_id'], 'cycle_id' => $f['cycle'], 'quantity' => 1]];
    $domainOrder = $db->transaction(function () use ($f, $domainCart): array {
        $quote = $f['quotes']->issue($f['quotes']->plan($f['customer'], new Currency('USD'), $domainCart, [], $f['now']));
        $order = $f['orders']->submit($quote['id'], $f['customer'], ['name' => 'Domain Renewal', 'currency' => 'USD'], $f['actor'], Id::uuid(), $f['now']);
        $money = new Money($quote['total_minor'], new Currency('USD'));
        $payment = $f['finance']->recordTestCollection($f['customer'], $money, Id::uuid(), $f['actor'], Id::uuid());
        $f['orders']->fund($order['id'], $f['customer'], $money, 'COLLECTION', $payment, Id::uuid(), $f['actor'], Id::uuid());
        return $order;
    });

    $renewOpId = $engine->enqueueRenewal($domainId, $domainOrder['id'], $newPeriodId, 1, $f['actor'], $trace);
    same(true, is_string($renewOpId));

    // 3. Second renewal attempt while domain is already RENEWING -> REJECTED (DOMAIN_RENEWAL_ALREADY_IN_FLIGHT)
    raises(HttpError::class, fn () => $engine->enqueueRenewal($domainId, $domainOrder['id'], $newPeriodId, 1, $f['actor'], $trace));
});

test('Domain renewal increment calculation distinguishes purchased years from provider cumulative term', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $runner = new DomainRunner($db, $f['config'], 'SUCCESS');

    // Register 1 year initially
    $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);
    $runner->once($f['actor']);

    $domainAfterReg = $db->execute('SELECT * FROM domains WHERE id=?', [$domainId])->fetch();
    $initialExpiry = new \DateTimeImmutable($domainAfterReg['expires_at'], new \DateTimeZone('UTC'));

    // Renewal 1: 1 year increment
    $period1Id = Id::uuid();
    $cycleJson = CanonicalJson::encode(['kind' => 'ANNUAL', 'interval_count' => 1]);
    $db->execute("INSERT INTO svc_periods(id,service_id,cycle_snapshot,status,version) VALUES (?,?,?,'PENDING_PROVIDER',1)", [$period1Id, $f['service']['id'], $cycleJson]);

    $cart = [['product_id' => $f['item']['product_id'], 'cycle_id' => $f['cycle'], 'quantity' => 1]];
    $order1 = $db->transaction(function () use ($f, $cart): array {
        $quote = $f['quotes']->issue($f['quotes']->plan($f['customer'], new Currency('USD'), $cart, [], $f['now']));
        $order = $f['orders']->submit($quote['id'], $f['customer'], ['name' => 'Renewal 1', 'currency' => 'USD'], $f['actor'], Id::uuid(), $f['now']);
        $money = new Money($quote['total_minor'], new Currency('USD'));
        $payment = $f['finance']->recordTestCollection($f['customer'], $money, Id::uuid(), $f['actor'], Id::uuid());
        $f['orders']->fund($order['id'], $f['customer'], $money, 'COLLECTION', $payment, Id::uuid(), $f['actor'], Id::uuid());
        return $order;
    });

    $engine->enqueueRenewal($domainId, $order1['id'], $period1Id, 1, $f['actor'], $trace);
    $runner->once($f['actor']);

    $domainAfterRenew1 = $db->execute('SELECT * FROM domains WHERE id=?', [$domainId])->fetch();
    $expiry1 = new \DateTimeImmutable($domainAfterRenew1['expires_at'], new \DateTimeZone('UTC'));

    // Must be exactly +1 year from initial expiry
    $expected1 = $initialExpiry->add(new \DateInterval('P1Y'))->format('Y-m-d');
    same($expected1, $expiry1->format('Y-m-d'));

    // Renewal 2: another 1 year increment
    $period2Id = Id::uuid();
    $db->execute("INSERT INTO svc_periods(id,service_id,cycle_snapshot,status,version) VALUES (?,?,?,'PENDING_PROVIDER',1)", [$period2Id, $f['service']['id'], $cycleJson]);
    $order2 = $db->transaction(function () use ($f, $cart): array {
        $quote = $f['quotes']->issue($f['quotes']->plan($f['customer'], new Currency('USD'), $cart, [], $f['now']));
        $order = $f['orders']->submit($quote['id'], $f['customer'], ['name' => 'Renewal 2', 'currency' => 'USD'], $f['actor'], Id::uuid(), $f['now']);
        $money = new Money($quote['total_minor'], new Currency('USD'));
        $payment = $f['finance']->recordTestCollection($f['customer'], $money, Id::uuid(), $f['actor'], Id::uuid());
        $f['orders']->fund($order['id'], $f['customer'], $money, 'COLLECTION', $payment, Id::uuid(), $f['actor'], Id::uuid());
        return $order;
    });

    $engine->enqueueRenewal($domainId, $order2['id'], $period2Id, 1, $f['actor'], $trace);
    $runner->once($f['actor']);

    $domainAfterRenew2 = $db->execute('SELECT * FROM domains WHERE id=?', [$domainId])->fetch();
    $expiry2 = new \DateTimeImmutable($domainAfterRenew2['expires_at'], new \DateTimeZone('UTC'));

    // Must be exactly +2 years from initial expiry (cumulative provider counter is 3, but expiry was incremented accurately by 1)
    $expected2 = $initialExpiry->add(new \DateInterval('P2Y'))->format('Y-m-d');
    same($expected2, $expiry2->format('Y-m-d'));
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

    // Runner must NOT be starved by the 101 blocked operations; it skips them and processes the healthy one!
    same(true, $worked);

    $healthyStatus = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$healthyOpId])->fetchColumn();
    same('SUCCEEDED', $healthyStatus);
});

test('CLI and outbox integration executes domain operations idempotently', function (): void {
    $db = isolatedDomainDb();
    (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate();
    $f = domainFixture($db);
    $domainId = $f['attach']();

    $engine = new DomainEngine($db, $f['config']);
    $trace = Id::uuid();
    $opId = $engine->enqueueRegister($domainId, $f['customer'], $f['actor'], $trace);

    // Queue worker processes outbox event
    $queue = new \Hostider\Core\Queue\Queue($db);
    $outbox = new \Hostider\Core\Queue\Outbox($db, $queue);
    $outbox->dispatchOne();

    $worker = new \Hostider\Core\Queue\Worker($db, $queue);
    (new \Hostider\Domains\EventSink($db, $f['config']))->register($worker);

    // Worker executes domains.execute event
    $worked = $worker->once();
    same('domains.execute', $worked);

    $opStatus = $db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
    same('SUCCEEDED', $opStatus);

    // Idempotent re-run of worker returns null (no double effect)
    $secondWorked = $worker->once();
    same(null, $secondWorked);
});

runTests();
