<?php
declare(strict_types=1);
namespace Hostider\Domains;

use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Core\Security\{Audit,AuditEvent,Authorization,Crypto,Principal};
use Hostider\Domains\{DomainName,TldPolicy};
use Hostider\Providers\Contract\ProviderResult;

/**
 * Domain Lifecycle & Operations Engine (F4-C).
 * Strictly guarantees DOMAIN-001 (UNKNOWN/reconciliation), DOMAIN-002 (safe definitive failover),
 * lease fencing, concurrency, anti-starvation runner fairness and financial funding invariants.
 */
final readonly class DomainEngine
{
    private Crypto $crypto;
    private string $key;

    public function __construct(private Database $db, private Config $config)
    {
        if ($config->environment !== 'test') {
            throw new HttpError(403, 'F4_TEST_ONLY');
        }
        $this->key = base64_decode($config->key, true);
        $this->crypto = new Crypto($this->key);
    }

    private function guard(Principal $actor): void
    {
        if ($this->config->environment !== 'test' || $actor->impersonating) {
            throw new HttpError(403, 'F4_TEST_ONLY');
        }
    }

    /**
     * Enqueue a new domain registration operation from a canonical paid order.
     */
    public function enqueueRegister(string $domainId, string $customer, Principal $actor, string $trace): string
    {
        $this->guard($actor);
        (new Authorization())->require($actor, 'services.manage', $customer, privileged: true);

        return $this->db->transaction(function () use ($domainId, $customer, $actor, $trace): string {
            $domain = $this->db->execute('SELECT * FROM domains WHERE id=? AND customer_id=? FOR UPDATE', [$domainId, $customer])->fetch();
            if (!$domain) {
                throw new HttpError(404, 'DOMAIN_NOT_FOUND');
            }

            $serviceId = $domain['service_id'];

            // Existing in-flight or completed operation check
            $existingOp = $this->db->execute(
                "SELECT * FROM domain_operations WHERE domain_id=? AND kind='REGISTER' FOR UPDATE",
                [$domainId]
            )->fetch();
            if ($existingOp) {
                if (in_array($existingOp['status'], ['PENDING', 'RUNNING', 'SUCCEEDED'], true)) {
                    return $existingOp['id'];
                }
                throw new HttpError(409, 'DOMAIN_OPERATION_CONFLICT');
            }

            $service = (new \Hostider\Services\RegistrationEligibility($this->db, $this->config))->lock($serviceId, $customer, 'DOMAIN');
            if ($domain['status'] !== 'PENDING') { throw new HttpError(409, 'DOMAIN_NOT_PENDING'); }

            // Contact & Nameservers
            $contactRow = $this->db->execute(
                "SELECT * FROM domain_contacts WHERE domain_id=? AND customer_id=? AND role='REGISTRANT' ORDER BY version DESC LIMIT 1 FOR UPDATE",
                [$domainId, $customer]
            )->fetch();
            if (!$contactRow) {
                throw new HttpError(409, 'DOMAIN_CONTACT_MISSING');
            }
            $contactJson = $this->crypto->decrypt($contactRow['contact_encrypted'], 'domain-contact:' . $contactRow['id']);
            if (!hash_equals($contactRow['fingerprint'], hash_hmac('sha256', $contactJson, $this->key))) {
                throw new HttpError(409, 'DOMAIN_CONTACT_INTEGRITY');
            }
            $contact = json_decode($contactJson, true, 32, JSON_THROW_ON_ERROR);

            $nsRow = $this->db->execute(
                'SELECT * FROM domain_nameserver_snapshots WHERE domain_id=? ORDER BY version DESC LIMIT 1 FOR UPDATE',
                [$domainId]
            )->fetch();
            if (!$nsRow) {
                throw new HttpError(409, 'DOMAIN_NAMESERVERS_MISSING');
            }
            $nameservers = json_decode($nsRow['nameservers_json'], true, 32, JSON_THROW_ON_ERROR);

            // Rule & TLD policy
            $rule = $this->db->execute(
                'SELECT r.*, a.provider, a.enabled, a.environment FROM domain_tld_provider_rules r JOIN domain_registrar_accounts a ON a.id=r.account_id WHERE r.id=?',
                [$domain['rule_id']]
            )->fetch();
            if (!$rule || !$rule['enabled'] || $rule['environment'] !== 'mock') {
                throw new HttpError(409, 'DOMAIN_RULE_UNAVAILABLE');
            }

            $policy = TldPolicy::fromSnapshot(json_decode($rule['policy_json'], true, 32, JSON_THROW_ON_ERROR));
            $policy->requireAction('register');
            $policy->validateContact($contact);

            // Term from order item period/cycle
            $termYears = 0;
            $period = $this->db->execute('SELECT * FROM svc_periods WHERE id=? AND service_id=?', [$service['current_period_id'], $serviceId])->fetch();
            if ($period) {
                $cycle = json_decode($period['cycle_snapshot'], true, 32, JSON_THROW_ON_ERROR);
                if ($cycle['kind'] === 'MONTHS' && (int)$cycle['interval_count'] % 12 === 0) {
                    $termYears = intdiv((int)$cycle['interval_count'], 12);
                }
            }

            if ($termYears < 1 || $termYears > 10) { throw new HttpError(409, 'DOMAIN_ANNUAL_TERM_REQUIRED'); }

            $operationId = Id::uuid();
            $attemptId = Id::uuid();

            $payload = [
                'operation_id' => $attemptId,
                'action' => 'register',
                'resource' => null,
                'configuration' => [
                    'domain' => $domain['name_ascii'],
                    'rule_id' => $rule['id'],
                    'years' => $termYears,
                    'contact' => $contact,
                    'nameservers' => $nameservers,
                ],
            ];
            $requestJson = CanonicalJson::encode($payload);
            $requestFingerprint = hash_hmac('sha256', $requestJson, $this->key);
            $encryptedPayload = $this->crypto->encrypt($requestJson, 'domain-attempt:' . $attemptId);

            $this->db->execute(
                "INSERT INTO domain_operations(id,domain_id,service_id,order_id,kind,generation,request_fingerprint,status,primary_account_id,active_attempt_id)
                 VALUES (?,?,?,?,'REGISTER',1,?,'PENDING',?,?)",
                [$operationId, $domainId, $serviceId, $service['order_id'], $requestFingerprint, $rule['account_id'], $attemptId]
            );

            $this->db->execute(
                "INSERT INTO domain_registrar_attempts(id,operation_id,account_id,attempt_no,status,request_encrypted,request_fingerprint)
                 VALUES (?,?,?,1,'PENDING',?,?)",
                [$attemptId, $operationId, $rule['account_id'], $encryptedPayload, $requestFingerprint]
            );

            $this->db->execute("UPDATE domains SET status='REGISTERING',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$domainId]);
            (new \Hostider\Services\DomainPeriods($this->db, $this->config))->serviceState($serviceId, 'PROVISIONING', $operationId);

            (new Outbox($this->db, new Queue($this->db)))->append(Id::uuid(), 'domains.execute', $operationId, ['operation_id' => $operationId], $trace);

            $this->recordHistory($domainId, $customer, 'REGISTER_QUEUED', $actor, $trace);

            return $operationId;
        });
    }

    /**
     * Enqueue a domain renewal operation.
     * Enforces single in-flight renewal, strict order product matching, actor authorization and distinct financial funding.
     */
    public function enqueueRenewal(string $domainId, string $renewalOrderId, string $periodId, int $termYears, Principal $actor, string $trace): string
    {
        $this->guard($actor);
        if ($termYears < 1 || $termYears > 10) {
            throw new HttpError(422, 'DOMAIN_TERM_INVALID');
        }

        return $this->db->transaction(function () use ($domainId, $renewalOrderId, $periodId, $termYears, $actor, $trace): string {
            $domain = $this->db->execute('SELECT * FROM domains WHERE id=? FOR UPDATE', [$domainId])->fetch();
            if (!$domain) {
                throw new HttpError(404, 'DOMAIN_NOT_FOUND');
            }

            // Actor authorization
            (new Authorization())->require($actor, 'services.manage', $domain['customer_id'], privileged: true, service: $domain['service_id']);

            // Atomic in-flight renewal guard (P0): if already RENEWING or pending renewal operation exists, block!
            if ($domain['status'] === 'RENEWING') {
                throw new HttpError(409, 'DOMAIN_RENEWAL_ALREADY_IN_FLIGHT');
            }
            if (!in_array($domain['status'], ['REGISTERED', 'GRACE'], true)) {
                throw new HttpError(409, 'DOMAIN_NOT_RENEWABLE');
            }

            $inFlightOp = $this->db->execute(
                "SELECT id FROM domain_operations WHERE domain_id=? AND kind='RENEW' AND status IN ('PENDING','RUNNING') FOR UPDATE",
                [$domainId]
            )->fetchColumn();
            if ($inFlightOp) {
                throw new HttpError(409, 'DOMAIN_RENEWAL_ALREADY_IN_FLIGHT');
            }

            // Renewal order must NOT be the initial registration order!
            $service = $this->db->execute('SELECT * FROM svc_services WHERE id=? FOR UPDATE', [$domain['service_id']])->fetch();
            if ($service && $service['order_id'] === $renewalOrderId) {
                throw new HttpError(409, 'RENEWAL_CANNOT_REUSE_INITIAL_ORDER');
            }

            (new \Hostider\Services\DomainPeriods($this->db, $this->config))->renewal(
                $domain['service_id'], $domain['customer_id'], $renewalOrderId, $periodId, $termYears
            );

            // Generation calculation
            $lastGen = (int)$this->db->execute('SELECT COALESCE(MAX(generation), 0) FROM domain_operations WHERE domain_id=?', [$domainId])->fetchColumn();
            $nextGen = $lastGen + 1;

            $renewalId = Id::uuid();
            $operationId = Id::uuid();
            $attemptId = Id::uuid();

            $rule = $this->db->execute(
                'SELECT r.*, a.provider, a.enabled, a.environment FROM domain_tld_provider_rules r JOIN domain_registrar_accounts a ON a.id=r.account_id WHERE r.id=?',
                [$domain['rule_id']]
            )->fetch();
            if (!$rule || !$rule['enabled'] || $rule['environment'] !== 'mock') {
                throw new HttpError(409, 'DOMAIN_RULE_UNAVAILABLE');
            }

            $policy = TldPolicy::fromSnapshot(json_decode($rule['policy_json'], true, 32, JSON_THROW_ON_ERROR));
            $policy->requireAction('renew');

            $previousEvidence = $this->db->execute("SELECT e.* FROM domain_evidence e JOIN domain_operations o ON o.id=e.operation_id WHERE o.domain_id=? AND o.status='SUCCEEDED' ORDER BY o.generation DESC LIMIT 1", [$domainId])->fetch();
            if (!$previousEvidence) { throw new HttpError(409, 'DOMAIN_RENEWAL_PRIOR_EVIDENCE_REQUIRED'); }
            $previousJson = $this->crypto->decrypt($previousEvidence['evidence_encrypted'], 'domain-evidence:'.$previousEvidence['operation_id']);
            if (!hash_equals($previousEvidence['fingerprint'], hash_hmac('sha256', $previousJson, $this->key))) { throw new HttpError(409, 'DOMAIN_EVIDENCE_INTEGRITY'); }
            $previousProof = json_decode($previousJson, true, 64, JSON_THROW_ON_ERROR);
            $expectedTerm = (int)$previousProof['term_years'] + $termYears;
            if ($expectedTerm > 10) { throw new HttpError(409, 'DOMAIN_TERM_LIMIT'); }

            $payload = [
                'operation_id' => $attemptId,
                'action' => 'renew',
                'resource' => $domain['provider_external_id'],
                'configuration' => [
                    'domain' => $domain['name_ascii'],
                    'rule_id' => $rule['id'],
                    'years' => $termYears, // purchased renewal increment
                    'expected_term_years' => $expectedTerm,
                ],
            ];
            $requestJson = CanonicalJson::encode($payload);
            $requestFingerprint = hash_hmac('sha256', $requestJson, $this->key);
            $encryptedPayload = $this->crypto->encrypt($requestJson, 'domain-attempt:' . $attemptId);

            $this->db->execute(
                "INSERT INTO domain_operations(id,domain_id,service_id,order_id,kind,generation,request_fingerprint,status,primary_account_id,active_attempt_id)
                 VALUES (?,?,?,?,'RENEW',?,?,'PENDING',?,?)",
                [$operationId, $domainId, $domain['service_id'], $renewalOrderId, $nextGen, $requestFingerprint, $rule['account_id'], $attemptId]
            );

            $this->db->execute(
                "INSERT INTO domain_renewals(id,domain_id,customer_id,generation,order_id,period_id,operation_id,term_years,status)
                 VALUES (?,?,?,?,?,?,?,?,'PENDING_PROVIDER')",
                [$renewalId, $domainId, $domain['customer_id'], $nextGen, $renewalOrderId, $periodId, $operationId, $termYears]
            );

            $this->db->execute(
                "INSERT INTO domain_registrar_attempts(id,operation_id,account_id,attempt_no,status,request_encrypted,request_fingerprint)
                 VALUES (?,?,?,1,'PENDING',?,?)",
                [$attemptId, $operationId, $rule['account_id'], $encryptedPayload, $requestFingerprint]
            );

            (new \Hostider\Services\DomainPeriods($this->db, $this->config))->queued($periodId, $operationId);

            $this->db->execute("UPDATE domains SET status='RENEWING',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$domainId]);

            (new Outbox($this->db, new Queue($this->db)))->append(Id::uuid(), 'domains.execute', $operationId, ['operation_id' => $operationId], $trace);

            $this->recordHistory($domainId, $domain['customer_id'], 'RENEWAL_QUEUED', $actor, $trace);

            return $operationId;
        });
    }

    /**
     * Check source order & billing dispatchability.
     */
    private function sourceDispatchable(?array $source): bool
    {
        if (!$source || $source['refund_status'] !== 'NONE' || $source['bill_status'] !== 'PAID' || $source['bill_refund'] !== 'NONE') {
            return false;
        }
        return $source['status'] === 'READY_FOR_PROVISIONING';
    }

    public function fundingValid(array $op, array $domain): bool
    {
        $this->db->requireTransaction();
        if ($op['kind'] === 'RENEW') {
            $renewal = $this->db->execute('SELECT * FROM domain_renewals WHERE operation_id=? FOR UPDATE', [$op['id']])->fetch();
            if (!$renewal) { return false; }
            try {
                (new \Hostider\Services\DomainPeriods($this->db, $this->config))->renewal($op['service_id'], $domain['customer_id'], $op['order_id'], $renewal['period_id'], (int)$renewal['term_years']);
            } catch (HttpError) { return false; }
        } else {
            try {
                (new \Hostider\Services\RegistrationEligibility($this->db, $this->config))->lock($op['service_id'], $domain['customer_id'], 'DOMAIN', ['PROVISIONING','UNKNOWN']);
            } catch (HttpError) { return false; }
        }
        $source = $this->db->execute('SELECT o.status,o.refund_status,s.status AS bill_status,s.refund_status AS bill_refund FROM orders o JOIN billing_request_states s ON s.request_id=o.payment_request_id WHERE o.id=? FOR UPDATE', [$op['order_id']])->fetch();
        return $this->sourceDispatchable($source ?: null);
    }

    /**
     * Claim next pending domain operation.
     * Transaction commits BEFORE invoking any provider mutation.
     * Prevents queue starvation using runner_checked_at fairness ordering.
     */
    public function claim(Principal $actor, string $trace, ?string $id = null): ?array
    {
        $this->guard($actor);

        return $this->db->transaction(function () use ($id): ?array {
            // Anti-starvation ordering: runner_checked_at first, then created_at, id
            $candidates = $this->db->execute(
                "SELECT id, domain_id, active_attempt_id FROM domain_operations WHERE status='PENDING'" .
                ($id !== null ? ' AND id=?' : '') .
                ' ORDER BY runner_checked_at, created_at, id LIMIT 20 FOR UPDATE SKIP LOCKED',
                $id !== null ? [$id] : []
            )->fetchAll();

            foreach ($candidates as $cand) {
                $op = $this->db->execute('SELECT * FROM domain_operations WHERE id=? FOR UPDATE SKIP LOCKED', [$cand['id']])->fetch();
                if (!$op || $op['status'] !== 'PENDING') {
                    continue;
                }

                $domain = $this->db->execute('SELECT * FROM domains WHERE id=? FOR UPDATE', [$op['domain_id']])->fetch();
                if (!$domain) {
                    continue;
                }

                // Verify funding source

                if (!$this->fundingValid($op, $domain)) {
                    // Deprioritize blocked operation to avoid queue starvation!
                    $this->db->execute(
                        "UPDATE domain_operations SET runner_checked_at=UTC_TIMESTAMP(6), runner_hold_code='DOMAIN_SOURCE_CHANGED', updated_at=UTC_TIMESTAMP(6) WHERE id=?",
                        [$op['id']]
                    );

                    continue; // Skip blocked operation and process next healthy operation
                }

                $attempt = $this->db->execute(
                    'SELECT * FROM domain_registrar_attempts WHERE id=? FOR UPDATE',
                    [$op['active_attempt_id']]
                )->fetch();
                if (!$attempt || $attempt['status'] !== 'PENDING') {
                    continue;
                }

                $unfrozen = (new AttemptEvidence($this->db, $this->config))->payload($attempt);
                if ($unfrozen['configuration']['domain'] !== $domain['name_ascii']) { throw new HttpError(409, 'DOMAIN_REQUEST_INTEGRITY'); }
                $token = Id::uuid();
                $this->db->execute(
                    "UPDATE domain_operations SET status='RUNNING',lease_token=?,lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND),runner_checked_at=UTC_TIMESTAMP(6),runner_hold_code=NULL,updated_at=UTC_TIMESTAMP(6) WHERE id=?",
                    [$token, $op['id']]
                );

                $this->db->execute(
                    "UPDATE domain_registrar_attempts SET status='RUNNING' WHERE id=?",
                    [$attempt['id']]
                );

                $this->db->execute(
                    "INSERT INTO domain_attempt_events(operation_id,attempt_id,lease_token,kind,safe_code)
                     VALUES (?,?,?,'STARTED','DISPATCH_CLAIMED')",
                    [$op['id'], $attempt['id'], $token]
                );

                $account = $this->db->execute('SELECT * FROM domain_registrar_accounts WHERE id=?', [$attempt['account_id']])->fetch();
                return [
                    'operation_id' => $op['id'],
                    'attempt_id' => $attempt['id'],
                    'attempt_no' => (int)$attempt['attempt_no'],
                    'lease_token' => $token,
                    'account' => $account,
                    'payload' => $unfrozen,
                ];
            }

            return null;
        });
    }

    /**
     * Complete provider attempt processing.
     * Enforces DOMAIN-001 (UNKNOWN/reconciliation), DOMAIN-002 (safe definitive failover),
     * precise term increment calculation and operation/provider contract binding.
     */
    public function finishAttempt(string $opId, string $token, ProviderResult $result, ?array $proof, Principal $actor, string $trace): void
    {
        $this->guard($actor);

        $this->db->transaction(function () use ($opId, $token, $result, $proof, $actor, $trace): void {
            $op = $this->db->execute('SELECT *, lease_until>UTC_TIMESTAMP(6) AS live FROM domain_operations WHERE id=? FOR UPDATE', [$opId])->fetch();
            if (!$op) {
                throw new HttpError(404, 'DOMAIN_OPERATION_NOT_FOUND');
            }

            $attempt = $this->db->execute('SELECT * FROM domain_registrar_attempts WHERE id=? FOR UPDATE', [$op['active_attempt_id']])->fetch();
            if (!$attempt) {
                throw new HttpError(404, 'DOMAIN_ATTEMPT_NOT_FOUND');
            }

            // Operation and Attempt binding verification
            if ($result->operationId !== $attempt['id']) {
                throw new HttpError(409, 'PROVIDER_RESULT_ATTEMPT_MISMATCH');
            }

            $account = $this->db->execute('SELECT * FROM domain_registrar_accounts WHERE id=?', [$attempt['account_id']])->fetch();
            if (!$account || $result->provider !== $account['provider']) {
                throw new HttpError(409, 'PROVIDER_RESULT_PROVIDER_MISMATCH');
            }

            // Stale worker fencing
            if ($op['status'] !== 'RUNNING' || !hash_equals((string)$op['lease_token'], $token) || !$op['live']) {
                throw new HttpError(409, 'STALE_DOMAIN_LEASE');
            }

            $domain = $this->db->execute('SELECT * FROM domains WHERE id=? FOR UPDATE', [$op['domain_id']])->fetch();
            $service = $this->db->execute('SELECT * FROM svc_services WHERE id=? FOR UPDATE', [$op['service_id']])->fetch();

            // Check if funding changed after dispatch

            if (!$this->fundingValid($op, $domain)) {
                // Physical effect cannot be ignored; must preserve and mark UNKNOWN for reconciliation
                $this->markUnknown($op, $attempt, $domain, $service, 'SOURCE_CHANGED_AFTER_DISPATCH', $actor, $trace);
                return;
            }

            $safeCode = $result->safeErrorCode ?? 'MOCK_OK';

            // 1. UNKNOWN outcome: DOMAIN-001
            if ($result->status === 'UNKNOWN') {
                $this->markUnknown($op, $attempt, $domain, $service, 'PROVIDER_OUTCOME_UNKNOWN', $actor, $trace);
                return;
            }

            // 2. SUCCESS outcome
            if ($result->status === 'SUCCESS') {
                $evidence = new AttemptEvidence($this->db, $this->config);
                if (!$evidence->success($op, $attempt, $domain, $proof, $evidence->call($attempt)) || $result->externalResourceId !== $proof['id']) {
                    $this->markUnknown($op, $attempt, $domain, $service, 'INSPECT_PROOF_MISMATCH', $actor, $trace);
                    return;
                }

                $proofJson = CanonicalJson::encode($proof);
                $proofHash = hash_hmac('sha256', $proofJson, $this->key);

                $this->db->execute(
                    "INSERT INTO domain_evidence(operation_id,attempt_id,evidence_encrypted,fingerprint)
                     VALUES (?,?,?,?)",
                    [$opId, $attempt['id'], $this->crypto->encrypt($proofJson, 'domain-evidence:' . $opId), $proofHash]
                );

                $this->db->execute(
                    "UPDATE domain_registrar_attempts SET status='SUCCEEDED',external_resource_id=?,safe_code=?,finished_at=UTC_TIMESTAMP(6) WHERE id=?",
                    [$result->externalResourceId, $safeCode, $attempt['id']]
                );

                $this->db->execute(
                    "UPDATE domain_operations SET status='SUCCEEDED',updated_at=UTC_TIMESTAMP(6) WHERE id=?",
                    [$opId]
                );

                $this->db->execute(
                    "INSERT INTO domain_attempt_events(operation_id,attempt_id,lease_token,kind,safe_code)
                     VALUES (?,?,?,'FINISHED','MOCK_INSPECT_VERIFIED')",
                    [$opId, $attempt['id'], $token]
                );

                $expiresAt = (new \Hostider\Services\DomainPeriods($this->db, $this->config))->complete($op, $service, $domain, (int)$proof['term_years'], true);
                $this->db->execute("UPDATE domains SET status='REGISTERED',provider_external_id=?,expires_at=?,locked=TRUE,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$proof['id'], $expiresAt, $domain['id']]);
                if ($op['kind'] === 'RENEW') { $this->db->execute("UPDATE domain_renewals SET status='VERIFIED' WHERE operation_id=?", [$opId]); }
                $this->recordHistory($domain['id'], $domain['customer_id'], $op['kind'].'_SUCCEEDED', $actor, $trace);

                return;
            }

            $evidence = new AttemptEvidence($this->db, $this->config);
            $call = $evidence->call($attempt);
            if (!$evidence->noEffect($call) || $call['status'] !== $result->status) {
                $this->markUnknown($op, $attempt, $domain, $service, 'NO_DEFINITIVE_CALL_EVIDENCE', $actor, $trace);
                return;
            }
            $this->db->execute("UPDATE domain_registrar_attempts SET status=?,no_effect=1,safe_code=?,finished_at=UTC_TIMESTAMP(6) WHERE id=?", [$result->status, $safeCode, $attempt['id']]);
            if ($op['kind'] === 'REGISTER' && $result->status === 'DEFINITIVE_FAILURE') {
                // No current registrar quote/balance proof exists. Never manufacture commercial approval.
                $this->db->execute("UPDATE domain_operations SET status='MANUAL_REVIEW',runner_hold_code='FAILOVER_COMMERCIAL_EVIDENCE_REQUIRED',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);
                $this->recordHistory($domain['id'], $domain['customer_id'], 'FAILOVER_HELD', $actor, $trace);
                return;
            }
            $this->db->execute("UPDATE domain_operations SET status='FAILED',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);

            $this->db->execute(
                "INSERT INTO domain_attempt_events(operation_id,attempt_id,lease_token,kind,safe_code)
                 VALUES (?,?,?,'FINISHED',?)",
                [$opId, $attempt['id'], $token, $safeCode]
            );

            $this->db->execute("UPDATE domains SET status=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$op['kind']==='RENEW'?'REGISTERED':'FAILED', $domain['id']]);
            (new \Hostider\Services\DomainPeriods($this->db, $this->config))->serviceState($service['id'], $op['kind']==='RENEW'?'ACTIVE':'FAILED');

            if ($op['kind'] === 'RENEW') {
                $this->db->execute("UPDATE domain_renewals SET status='FAILED' WHERE operation_id=?", [$opId]);
                $periodId=$this->db->execute('SELECT period_id FROM domain_renewals WHERE operation_id=?',[$opId])->fetchColumn();
                (new \Hostider\Services\DomainPeriods($this->db,$this->config))->renewalState($periodId,'FAILED');
            }

            $this->recordHistory($domain['id'], $domain['customer_id'], 'OPERATION_FAILED', $actor, $trace);
        });
    }

    /**
     * Mark an operation and attempt UNKNOWN. Enforces DOMAIN-001 invariants.
     */
    private function markUnknown(array $op, array $attempt, array $domain, array $service, string $code, Principal $actor, string $trace): void
    {
        $this->db->execute("UPDATE domain_operations SET status='UNKNOWN',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$op['id']]);
        $this->db->execute("UPDATE domain_registrar_attempts SET status='UNKNOWN',safe_code=?,finished_at=UTC_TIMESTAMP(6) WHERE id=?", [$code, $attempt['id']]);

        $this->db->execute(
            "INSERT INTO domain_attempt_events(operation_id,attempt_id,lease_token,kind,safe_code)
             VALUES (?,?,?,'FINISHED',?)",
            [$op['id'], $attempt['id'], $op['lease_token'] ?? Id::uuid(), $code]
        );

        $this->db->execute("UPDATE domains SET status='UNKNOWN',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$domain['id']]);
        (new \Hostider\Services\DomainPeriods($this->db, $this->config))->serviceState($service['id'], 'UNKNOWN');

        if ($op['kind'] === 'RENEW') {
            $this->db->execute("UPDATE domain_renewals SET status='UNKNOWN' WHERE operation_id=?", [$op['id']]);
            $periodId=$this->db->execute('SELECT period_id FROM domain_renewals WHERE operation_id=?',[$op['id']])->fetchColumn();
            (new \Hostider\Services\DomainPeriods($this->db,$this->config))->renewalState($periodId,'UNKNOWN');
        }

        $this->recordHistory($domain['id'], $domain['customer_id'], 'OPERATION_UNKNOWN', $actor, $trace);
    }

    /**
     * Expire stale running operations and transition them to UNKNOWN (fencing).
     */
    public function expire(Principal $actor, string $trace, ?string $id = null): int
    {
        $this->guard($actor);
        $count = 0;

        $candidates = $this->db->execute(
            "SELECT id FROM domain_operations WHERE status='RUNNING' AND lease_until<=UTC_TIMESTAMP(6)" .
            ($id !== null ? ' AND id=?' : '') .
            ' ORDER BY lease_until, id LIMIT 100',
            $id !== null ? [$id] : []
        )->fetchAll();

        foreach ($candidates as $cand) {
            $count += $this->db->transaction(function () use ($cand, $actor, $trace): int {
                $op = $this->db->execute(
                    "SELECT *, lease_until<=UTC_TIMESTAMP(6) AS expired FROM domain_operations WHERE id=? AND status='RUNNING' FOR UPDATE",
                    [$cand['id']]
                )->fetch();
                if (!$op || !$op['expired']) {
                    return 0;
                }

                $attempt = $this->db->execute('SELECT * FROM domain_registrar_attempts WHERE id=? FOR UPDATE', [$op['active_attempt_id']])->fetch();
                $domain = $this->db->execute('SELECT * FROM domains WHERE id=? FOR UPDATE', [$op['domain_id']])->fetch();
                $service = $this->db->execute('SELECT * FROM svc_services WHERE id=? FOR UPDATE', [$op['service_id']])->fetch();

                $this->markUnknown($op, $attempt, $domain, $service, 'LEASE_EXPIRED', $actor, $trace);
                return 1;
            });
        }

        return $count;
    }

    private function recordHistory(string $domainId, string $customer, string $kind, Principal $actor, string $trace): void
    {
        $eventId = Id::uuid();
        $this->db->execute(
            'INSERT INTO domain_history(id,domain_id,customer_id,kind,actor_id,trace_id) VALUES (?,?,?,?,?,?)',
            [$eventId, $domainId, $customer, $kind, $actor->actorId, $trace]
        );
        (new Audit($this->db))->record($actor, AuditEvent::DomainChanged, $domainId, $trace);
        (new Outbox($this->db, new Queue($this->db)))->append($eventId, 'domains.changed', $domainId, ['domain_id' => $domainId], $trace);
    }
}
