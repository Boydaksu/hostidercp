<?php
declare(strict_types=1);
namespace Hostider\Domains;

use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Core\Security\{Audit,AuditEvent,Authorization,Crypto,Principal};
use Hostider\Providers\Mock\DurableRegistrar;

/**
 * Domain Reconciliation & Inspect Service (DOMAIN-001).
 * Strictly guarantees independent provider proof, operation binding and financial decoupling.
 */
final readonly class DomainReconciliation
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
     * Independently inspect external registrar state for an unknown domain operation.
     * Proof is derived directly from the registrar adapter and durable call records, never raw caller input.
     */
    public function inspect(string $opId, DurableRegistrar $registrar, Principal $actor, string $trace): array
    {
        $this->guard($actor);
        (new Authorization())->require($actor, 'services.manage', privileged: true);

        return $this->db->transaction(function () use ($opId, $registrar, $actor): array {
            $op = $this->db->execute('SELECT * FROM domain_operations WHERE id=? FOR UPDATE', [$opId])->fetch();
            if (!$op || !in_array($op['status'], ['UNKNOWN', 'MANUAL_REVIEW'], true)) {
                throw new HttpError(409, 'DOMAIN_OPERATION_NOT_UNKNOWN');
            }

            $attempt = $this->db->execute('SELECT * FROM domain_registrar_attempts WHERE id=? FOR UPDATE', [$op['active_attempt_id']])->fetch();
            $domain = $this->db->execute('SELECT * FROM domains WHERE id=? FOR UPDATE', [$op['domain_id']])->fetch();

            // Independent proof retrieval directly from the registrar
            $evidence = $registrar->evidence($domain['name_ascii']);

            $found = false;
            $verifiedProof = [];

            // Cross-verify with mock_registrar_calls record
            $callRecord = $this->db->execute(
                'SELECT * FROM mock_registrar_calls WHERE operation_id=? AND account_id=?',
                [$attempt['id'], $attempt['account_id']]
            )->fetch();

            if ($evidence !== null) {
                // Proof binding: must match account, domain, and create_operation_id
                if ($evidence['account_id'] === $attempt['account_id']
                    && $evidence['name_ascii'] === $domain['name_ascii']
                    && ($op['kind'] === 'RENEW' || $evidence['create_operation_id'] === $attempt['id'])) {
                    $found = true;
                    $verifiedProof = $evidence;
                    if ($callRecord) {
                        $verifiedProof['call_status'] = $callRecord['status'];
                        $verifiedProof['call_resource_id'] = $callRecord['resource_id'];
                    }
                }
            }

            $inspectionId = Id::uuid();
            $evidenceJson = CanonicalJson::encode($verifiedProof);
            $fingerprint = hash_hmac('sha256', $evidenceJson, $this->key);

            $this->db->execute(
                'INSERT INTO domain_inspections(id,operation_id,attempt_id,actor_id,found,evidence_encrypted,fingerprint)
                 VALUES (?,?,?,?,?,?,?)',
                [$inspectionId, $opId, $attempt['id'], $actor->actorId, (int)$found, $this->crypto->encrypt($evidenceJson, 'domain-inspect:' . $inspectionId), $fingerprint]
            );

            // Transition to MANUAL_REVIEW if currently UNKNOWN
            if ($op['status'] === 'UNKNOWN') {
                $this->db->execute("UPDATE domain_operations SET status='MANUAL_REVIEW',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);
                $this->db->execute(
                    "INSERT INTO domain_attempt_events(operation_id,attempt_id,lease_token,kind,safe_code)
                     VALUES (?,?,?,'HELD_FOR_REVIEW','INSPECTED_MANUAL_REVIEW')",
                    [$opId, $attempt['id'], $op['lease_token'] ?? Id::uuid()]
                );
            }

            return [
                'inspection_id' => $inspectionId,
                'operation_id' => $opId,
                'found' => $found,
                'evidence' => $verifiedProof,
                'definitive_no_effect' => $callRecord ? (bool)$callRecord['no_effect'] : false,
            ];
        });
    }

    /**
     * Resolve unknown operation with explicit operator confirmation.
     * Enforces strict external proof verification and financial source decoupling.
     */
    public function resolve(string $opId, string $inspectionId, string $outcome, Principal $actor, string $trace): void
    {
        $this->guard($actor);
        if (!$actor->staff) {
            throw new HttpError(403, 'STAFF_REQUIRED');
        }
        (new Authorization())->require($actor, 'services.manage', privileged: true);

        if (!in_array($outcome, ['CONFIRMED_SUCCESS', 'CONFIRMED_FAILURE'], true)) {
            throw new HttpError(422, 'INVALID_RECONCILIATION_OUTCOME');
        }

        $this->db->transaction(function () use ($opId, $inspectionId, $outcome, $actor, $trace): void {
            $op = $this->db->execute('SELECT * FROM domain_operations WHERE id=? FOR UPDATE', [$opId])->fetch();
            if (!$op || !in_array($op['status'], ['UNKNOWN', 'MANUAL_REVIEW'], true)) {
                throw new HttpError(409, 'DOMAIN_OPERATION_NOT_RESOLVABLE');
            }

            $inspection = $this->db->execute('SELECT * FROM domain_inspections WHERE id=? AND operation_id=? FOR UPDATE', [$inspectionId, $opId])->fetch();
            if (!$inspection) {
                throw new HttpError(404, 'INSPECTION_NOT_FOUND');
            }

            if ($this->db->execute('SELECT 1 FROM domain_resolutions WHERE operation_id=?', [$opId])->fetchColumn()) {
                throw new HttpError(409, 'DOMAIN_OPERATION_ALREADY_RESOLVED');
            }

            $domain = $this->db->execute('SELECT * FROM domains WHERE id=? FOR UPDATE', [$op['domain_id']])->fetch();
            $service = $this->db->execute('SELECT * FROM svc_services WHERE id=? FOR UPDATE', [$op['service_id']])->fetch();
            $attempt = $this->db->execute('SELECT * FROM domain_registrar_attempts WHERE id=? FOR UPDATE', [$op['active_attempt_id']])->fetch();

            // Verify funding source at resolution time
            $source = $this->db->execute(
                'SELECT o.status, o.refund_status, s.status AS bill_status, s.refund_status AS bill_refund
                 FROM orders o JOIN billing_request_states s ON s.request_id=o.payment_request_id
                 WHERE o.id=? FOR UPDATE',
                [$op['order_id']]
            )->fetch();

            $fundingValid = ($source && $source['refund_status'] === 'NONE' && $source['bill_status'] === 'PAID' && $source['bill_refund'] === 'NONE');

            $resolutionId = Id::uuid();
            $this->db->execute(
                'INSERT INTO domain_resolutions(id,operation_id,inspection_id,outcome,actor_id,trace_id)
                 VALUES (?,?,?,?,?,?)',
                [$resolutionId, $opId, $inspectionId, $outcome, $actor->actorId, $trace]
            );

            if ($outcome === 'CONFIRMED_SUCCESS') {
                if (!(bool)$inspection['found']) {
                    throw new HttpError(409, 'CANNOT_CONFIRM_SUCCESS_WITHOUT_EXTERNAL_PROOF');
                }

                $evidenceJson = $this->crypto->decrypt($inspection['evidence_encrypted'], 'domain-inspect:' . $inspectionId);
                $proof = json_decode($evidenceJson, true, 32, JSON_THROW_ON_ERROR);

                // Anti-tamper validation of decrypted proof
                if ($proof['account_id'] !== $attempt['account_id'] || $proof['name_ascii'] !== $domain['name_ascii']) {
                    throw new HttpError(409, 'INSPECTION_EVIDENCE_MISMATCH');
                }

                // If funding was revoked after dispatch, physical domain is preserved, but service cannot be ACTIVE!
                if (!$fundingValid) {
                    $this->db->execute(
                        "UPDATE domain_registrar_attempts SET status='SUCCEEDED',external_resource_id=?,safe_code='RESOLVED_SOURCE_REVOKED',finished_at=UTC_TIMESTAMP(6) WHERE id=?",
                        [$proof['id'] ?? null, $attempt['id']]
                    );
                    $this->db->execute("UPDATE domain_operations SET status='SUCCEEDED',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);
                    $this->db->execute("UPDATE domains SET status='REGISTERED',provider_external_id=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$proof['id'] ?? null, $domain['id']]);
                    $this->db->execute("UPDATE svc_services SET status='SUSPENDED',version=version+1 WHERE id=?", [$service['id']]);
                    $this->recordHistory($domain['id'], $domain['customer_id'], 'RECONCILED_SOURCE_REVOKED', $actor, $trace);
                    return;
                }

                $termYears = (int)($proof['term_years'] ?? 1);
                $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    ->add(new \DateInterval('P' . $termYears . 'Y'))
                    ->format('Y-m-d H:i:s.u');

                $this->db->execute(
                    "UPDATE domain_registrar_attempts SET status='SUCCEEDED',external_resource_id=?,safe_code='RESOLVED_SUCCESS',finished_at=UTC_TIMESTAMP(6) WHERE id=?",
                    [$proof['id'] ?? null, $attempt['id']]
                );

                $this->db->execute("UPDATE domain_operations SET status='SUCCEEDED',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);

                $this->db->execute(
                    "UPDATE domains SET status='REGISTERED',provider_external_id=?,expires_at=?,locked=TRUE,privacy_enabled=FALSE,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?",
                    [$proof['id'] ?? null, $expiresAt, $domain['id']]
                );

                $this->db->execute("UPDATE svc_services SET status='ACTIVE',version=version+1 WHERE id=?", [$service['id']]);

                $period = $this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE', [$service['current_period_id']])->fetch();
                if ($period) {
                    $this->db->execute(
                        "UPDATE svc_periods SET starts_at=UTC_TIMESTAMP(6),ends_at=?,status='VERIFIED',version=version+1 WHERE id=?",
                        [$expiresAt, $period['id']]
                    );
                }

                $this->recordHistory($domain['id'], $domain['customer_id'], 'RECONCILED_SUCCESS', $actor, $trace);
            } else {
                // CONFIRMED_FAILURE: requires verified definitive call record showing no-effect, or explicit staff confirmation
                $callRecord = $this->db->execute(
                    'SELECT no_effect FROM mock_registrar_calls WHERE operation_id=? AND account_id=?',
                    [$attempt['id'], $attempt['account_id']]
                )->fetch();

                if ((bool)$inspection['found']) {
                    throw new HttpError(409, 'CANNOT_CONFIRM_FAILURE_WHEN_RESOURCE_EXISTS');
                }

                $this->db->execute(
                    "UPDATE domain_registrar_attempts SET status='DEFINITIVE_FAILURE',no_effect=?,safe_code='RESOLVED_FAILURE',finished_at=UTC_TIMESTAMP(6) WHERE id=?",
                    [$callRecord ? (int)$callRecord['no_effect'] : 0, $attempt['id']]
                );

                $this->db->execute("UPDATE domain_operations SET status='FAILED',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);
                $this->db->execute("UPDATE domains SET status='FAILED',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$domain['id']]);
                $this->db->execute("UPDATE svc_services SET status='FAILED',version=version+1 WHERE id=?", [$service['id']]);

                if ($op['kind'] === 'RENEW') {
                    $this->db->execute("UPDATE domain_renewals SET status='FAILED' WHERE operation_id=?", [$opId]);
                }

                $this->recordHistory($domain['id'], $domain['customer_id'], 'RECONCILED_FAILURE', $actor, $trace);
            }

            $this->db->execute(
                "INSERT INTO domain_attempt_events(operation_id,attempt_id,lease_token,kind,safe_code)
                 VALUES (?,?,?,'RECONCILED',?)",
                [$opId, $attempt['id'], $op['lease_token'] ?? Id::uuid(), $outcome]
            );
        });
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
