<?php
declare(strict_types=1);
namespace Hostider\Core\Queue;
use Hostider\Core\Database\Database;
use Hostider\Core\Id;
use Hostider\Core\CanonicalJson;
final readonly class Queue
{
    public function __construct(private Database $db) {}

    public function enqueue(string $type, array $payload, string $dedupe, string $trace, bool $retrySafe = false): void
    {
        $this->db->requireTransaction();
        $json = CanonicalJson::encode($payload);
        $fingerprint = hash('sha256', $json);
        $this->db->execute('INSERT INTO jobs (job_type,payload,dedupe_key,correlation_id,retry_safe,payload_fingerprint) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
            [$type, $json, $dedupe, $trace, (int) $retrySafe, $fingerprint]);
        $existing = $this->db->execute('SELECT job_type,payload,retry_safe,payload_fingerprint,payload_purged_at FROM jobs WHERE dedupe_key=? FOR UPDATE', [$dedupe])->fetch();
        $samePayload = $existing && ($existing['payload_purged_at'] === null
            ? CanonicalJson::fromJson($existing['payload']) === $json
            : ($existing['payload_fingerprint'] !== null && hash_equals($existing['payload_fingerprint'], $fingerprint)));
        if (!$existing || $existing['job_type'] !== $type || !$samePayload || (bool) $existing['retry_safe'] !== $retrySafe) {
            throw new \LogicException('Job dedupe conflict');
        }
    }

    public function claim(int $leaseSeconds = 60): ?array
    {
        if ($leaseSeconds < 1 || $leaseSeconds > 300) {
            throw new \InvalidArgumentException('Invalid lease');
        }
        // Retry only a rolled-back local claim transaction, never a job handler or external effect.
        for ($attempt = 0; ; ++$attempt) {
            try { return $this->claimTransaction($leaseSeconds); }
            catch (\PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1213 || $attempt >= 4) { throw $e; }
                usleep(($attempt + 1) * 25_000);
            }
        }
    }

    private function claimTransaction(int $leaseSeconds): ?array
    {
        return $this->db->transaction(function () use ($leaseSeconds): ?array {
            // Expired external/unsafe work is never automatically dispatched again.
            $expired = $this->db->execute("SELECT * FROM jobs WHERE status='RUNNING' AND leased_until<=UTC_TIMESTAMP(6) ORDER BY id LIMIT 100 FOR UPDATE SKIP LOCKED")->fetchAll();
            foreach ($expired as $job) {
                $status = !(bool) $job['retry_safe'] ? 'UNKNOWN' : ((int) $job['attempts'] >= (int) $job['max_attempts'] ? 'FAILED' : 'PENDING');
                $this->db->execute('UPDATE jobs SET status=?,lease_token=NULL,leased_until=NULL,last_error_safe=\'LEASE_EXPIRED\',updated_at=UTC_TIMESTAMP(6) WHERE id=?', [$status, $job['id']]);
                $this->db->execute("UPDATE job_attempts SET outcome='LEASE_EXPIRED',finished_at=UTC_TIMESTAMP(6) WHERE lease_token=? AND outcome='RUNNING'", [$job['lease_token']]);
                if ($status === 'FAILED') {
                    $this->deadLetter((int) $job['id'], 'LEASE_EXHAUSTED');
                }
            }
            $job = $this->db->execute("SELECT * FROM jobs WHERE status='PENDING' AND run_after<=UTC_TIMESTAMP(6) ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
            if (!$job) {
                return null;
            }
            $token = Id::uuid();
            $this->db->execute("UPDATE jobs SET status='RUNNING',attempts=attempts+1,lease_token=?,leased_until=TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)),updated_at=UTC_TIMESTAMP(6) WHERE id=?", [$token, $leaseSeconds, $job['id']]);
            $this->db->execute('INSERT INTO job_attempts (job_id,lease_token) VALUES (?,?)', [$job['id'], $token]);
            $job['lease_token'] = $token;
            $job['attempts'] = (int) $job['attempts'] + 1;
            return $job;
        });
    }

    public function finish(array $lease, string $outcome): void
    {
        if (!in_array($outcome, ['SUCCEEDED', 'FAILED', 'UNKNOWN'], true)) {
            throw new \InvalidArgumentException('Invalid job outcome');
        }
        $this->db->transaction(function () use ($lease, $outcome): void {
            $job = $this->db->execute("SELECT * FROM jobs WHERE id=? AND status='RUNNING' AND lease_token=? AND leased_until>UTC_TIMESTAMP(6) FOR UPDATE", [$lease['id'], $lease['lease_token']])->fetch();
            if (!$job) {
                throw new \RuntimeException('Stale worker fence');
            }
            $status = $outcome;
            if ($outcome === 'FAILED' && (bool) $job['retry_safe'] && (int) $job['attempts'] < (int) $job['max_attempts']) {
                $status = 'PENDING';
            }
            $delay = min(300, 2 ** (int) $job['attempts']);
            $this->db->execute('UPDATE jobs SET status=?,lease_token=NULL,leased_until=NULL,run_after=TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)),last_error_safe=?,updated_at=UTC_TIMESTAMP(6) WHERE id=?',
                [$status, $delay, $outcome === 'SUCCEEDED' ? null : $outcome, $job['id']]);
            $this->db->execute('UPDATE job_attempts SET outcome=?,finished_at=UTC_TIMESTAMP(6) WHERE lease_token=?', [$outcome, $lease['lease_token']]);
            if ($status === 'FAILED') {
                $this->deadLetter((int) $job['id'], 'DEFINITIVE_FAILURE');
            }
        });
    }

    private function deadLetter(int $id, string $reason): void
    {
        $this->db->execute('INSERT INTO dead_letters (job_id,reason) VALUES (?,?) ON DUPLICATE KEY UPDATE job_id=job_id', [$id, $reason]);
    }
}
