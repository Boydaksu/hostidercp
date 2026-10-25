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
        if (!$actor->staff) { throw new HttpError(403, 'STAFF_REQUIRED'); }
        (new Authorization())->require($actor, 'services.manage', privileged: true);
        $snapshot = $this->db->transaction(function () use ($opId): array {
            $op = $this->db->execute('SELECT * FROM domain_operations WHERE id=? FOR UPDATE', [$opId])->fetch();
            if (!$op || !in_array($op['status'], ['UNKNOWN','MANUAL_REVIEW'], true)) { throw new HttpError(409, 'DOMAIN_OPERATION_NOT_UNKNOWN'); }
            $attempt = $this->db->execute('SELECT * FROM domain_registrar_attempts WHERE id=?', [$op['active_attempt_id']])->fetch();
            $domain = $this->db->execute('SELECT * FROM domains WHERE id=?', [$op['domain_id']])->fetch();
            return compact('op', 'attempt', 'domain');
        });
        // All provider I/O is outside the application transaction, including durable mock evidence().
        if ($registrar->accountId() !== $snapshot['attempt']['account_id']) { throw new HttpError(409, 'INSPECTION_ACCOUNT_MISMATCH'); }
        $proof = $registrar->evidence($snapshot['domain']['name_ascii']);
        $validator = new AttemptEvidence($this->db, $this->config);
        $call = $validator->call($snapshot['attempt']);
        $verified = $validator->success($snapshot['op'], $snapshot['attempt'], $snapshot['domain'], $proof, $call);
        if ($proof && !$verified) { throw new HttpError(409, 'INSPECTION_EVIDENCE_MISMATCH'); }
        $noEffect = !$proof && $validator->noEffect($call);
        return $this->db->transaction(function () use ($opId, $snapshot, $proof, $call, $verified, $noEffect, $actor): array {
            $op = $this->db->execute('SELECT * FROM domain_operations WHERE id=? FOR UPDATE', [$opId])->fetch();
            $domain = $this->db->execute('SELECT * FROM domains WHERE id=? FOR UPDATE', [$snapshot['domain']['id']])->fetch();
            if (!$op || $op['generation'] !== $snapshot['op']['generation'] || $op['active_attempt_id'] !== $snapshot['attempt']['id']
                || $op['status'] !== $snapshot['op']['status'] || $op['updated_at'] !== $snapshot['op']['updated_at'] || $domain['version'] !== $snapshot['domain']['version']) {
                throw new HttpError(409, 'STALE_DOMAIN_INSPECTION');
            }
            $id = Id::uuid();
            $this->db->execute("UPDATE domain_operations SET status='MANUAL_REVIEW',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);
            $stamp = $this->db->execute('SELECT updated_at FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
            $envelope = ['proof'=>$proof, 'call'=>$call, 'no_effect'=>$noEffect, 'generation'=>$op['generation'],
                'attempt_id'=>$op['active_attempt_id'], 'domain_version'=>$domain['version'], 'operation_stamp'=>$stamp];
            $json = CanonicalJson::encode($envelope);
            $this->db->execute('INSERT INTO domain_inspections(id,operation_id,attempt_id,actor_id,found,evidence_encrypted,fingerprint) VALUES (?,?,?,?,?,?,?)',
                [$id, $opId, $op['active_attempt_id'], $actor->actorId, (int)$verified, $this->crypto->encrypt($json, 'domain-inspect:'.$id), hash_hmac('sha256', $json, $this->key)]);
            return ['inspection_id'=>$id, 'operation_id'=>$opId, 'found'=>$verified, 'evidence'=>$proof??[], 'definitive_no_effect'=>$noEffect];
        });
    }

    /** Local resolution composes atomically with native HTTP confirmation; inspect remains outside TX. */
    private function local(\Closure $work): mixed{return $this->db->pdo->inTransaction()?$work():$this->db->transaction($work);}

    public function resolve(string $opId, string $inspectionId, string $outcome, Principal $actor, string $trace): void
    {
        $this->guard($actor);
        if (!$actor->staff) { throw new HttpError(403, 'STAFF_REQUIRED'); }
        (new Authorization())->require($actor, 'services.manage', privileged: true);
        if (!in_array($outcome, ['CONFIRMED_SUCCESS','CONFIRMED_FAILURE'], true)) { throw new HttpError(422, 'INVALID_RECONCILIATION_OUTCOME'); }
        $this->local(function () use ($opId, $inspectionId, $outcome, $actor, $trace): void {
            $op = $this->db->execute('SELECT * FROM domain_operations WHERE id=? FOR UPDATE', [$opId])->fetch();
            if (!$op || !in_array($op['status'], ['UNKNOWN','MANUAL_REVIEW'], true)) { throw new HttpError(409, 'DOMAIN_OPERATION_NOT_RESOLVABLE'); }
            $inspection = $this->db->execute('SELECT *,created_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE) AS fresh FROM domain_inspections WHERE id=? AND operation_id=?', [$inspectionId,$opId])->fetch();
            if (!$inspection) { throw new HttpError(404, 'INSPECTION_NOT_FOUND'); }
            $json = $this->crypto->decrypt($inspection['evidence_encrypted'], 'domain-inspect:'.$inspectionId);
            if (!hash_equals($inspection['fingerprint'], hash_hmac('sha256', $json, $this->key))) { throw new HttpError(409, 'INSPECTION_INTEGRITY'); }
            $envelope = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            $attempt = $this->db->execute('SELECT * FROM domain_registrar_attempts WHERE id=? FOR UPDATE', [$op['active_attempt_id']])->fetch();
            $domain = $this->db->execute('SELECT * FROM domains WHERE id=? FOR UPDATE', [$op['domain_id']])->fetch();
            $service = $this->db->execute('SELECT * FROM svc_services WHERE id=? FOR UPDATE', [$op['service_id']])->fetch();
            if (!$inspection['fresh'] || $inspection['attempt_id'] !== $attempt['id'] || $envelope['attempt_id'] !== $attempt['id']
                || $envelope['generation'] !== $op['generation'] || $envelope['domain_version'] !== $domain['version'] || $envelope['operation_stamp'] !== $op['updated_at']) {
                throw new HttpError(409, 'STALE_DOMAIN_INSPECTION');
            }
            $validator = new AttemptEvidence($this->db, $this->config);
            $call = $validator->call($attempt);
            if (CanonicalJson::encode($call) !== CanonicalJson::encode($envelope['call'])) { throw new HttpError(409, 'STALE_DOMAIN_CALL_EVIDENCE'); }
            $proof = $envelope['proof'];
            $funded = (new DomainEngine($this->db, $this->config))->fundingValid($op, $domain);
            if ($outcome === 'CONFIRMED_SUCCESS') {
                if (!(bool)$inspection['found'] || !$validator->success($op, $attempt, $domain, $proof, $call)) { throw new HttpError(409, 'CANNOT_CONFIRM_SUCCESS_WITHOUT_EXTERNAL_PROOF'); }
                $proofJson = CanonicalJson::encode($proof);
                $this->db->execute('INSERT INTO domain_evidence(operation_id,attempt_id,evidence_encrypted,fingerprint) VALUES (?,?,?,?)', [$opId,$attempt['id'],$this->crypto->encrypt($proofJson,'domain-evidence:'.$opId),hash_hmac('sha256',$proofJson,$this->key)]);
                $end = (new \Hostider\Services\DomainPeriods($this->db, $this->config))->complete($op, $service, $domain, (int)$proof['term_years'], $funded);
                $this->db->execute("UPDATE domains SET status='REGISTERED',provider_external_id=?,expires_at=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$proof['id'],$end,$domain['id']]);
                $this->db->execute("UPDATE domain_registrar_attempts SET status='SUCCEEDED',external_resource_id=?,safe_code='RESOLVED_SUCCESS',finished_at=UTC_TIMESTAMP(6) WHERE id=?", [$proof['id'],$attempt['id']]);
                $this->db->execute("UPDATE domain_operations SET status='SUCCEEDED',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);
                if ($op['kind'] === 'RENEW') { $this->db->execute("UPDATE domain_renewals SET status='VERIFIED' WHERE operation_id=?", [$opId]); }
            } else {
                if ((bool)$inspection['found'] || $proof !== null) { throw new HttpError(409, 'CANNOT_CONFIRM_FAILURE_WHEN_RESOURCE_EXISTS'); }
                if (!$envelope['no_effect'] || !$validator->noEffect($call)) { throw new HttpError(409, 'DEFINITIVE_NO_EFFECT_REQUIRED'); }
                $this->db->execute("UPDATE domain_registrar_attempts SET status='DEFINITIVE_FAILURE',no_effect=1,safe_code='RESOLVED_FAILURE',finished_at=UTC_TIMESTAMP(6) WHERE id=?", [$attempt['id']]);
                $this->db->execute("UPDATE domain_operations SET status='FAILED',updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$opId]);
                $this->db->execute("UPDATE domains SET status=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$op['kind']==='RENEW'?'REGISTERED':'FAILED',$domain['id']]);
                (new \Hostider\Services\DomainPeriods($this->db, $this->config))->serviceState($service['id'], $op['kind']==='RENEW'?'ACTIVE':'FAILED');
                if ($op['kind'] === 'RENEW') { $this->db->execute("UPDATE domain_renewals SET status='FAILED' WHERE operation_id=?", [$opId]); }
            }
            $this->db->execute('INSERT INTO domain_resolutions(id,operation_id,inspection_id,outcome,actor_id,trace_id) VALUES (?,?,?,?,?,?)', [Id::uuid(),$opId,$inspectionId,$outcome,$actor->actorId,$trace]);
            $this->db->execute("INSERT INTO domain_attempt_events(operation_id,attempt_id,lease_token,kind,safe_code) VALUES (?,?,?,'RECONCILED',?)", [$opId,$attempt['id'],$op['lease_token']??Id::uuid(),$outcome]);
            $this->recordHistory($domain['id'], $domain['customer_id'], $funded?'RECONCILED_SUCCESS':'RECONCILED_SOURCE_REVOKED', $actor, $trace);
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
