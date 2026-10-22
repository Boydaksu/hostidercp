<?php
declare(strict_types=1);
namespace Hostider\Core\Queue;
use Hostider\Core\CanonicalJson;
use Hostider\Core\Database\Database;

final readonly class Retention
{
    public function __construct(private Database $db) {}

    /** Only expired technical windows, never active or unknown legacy buckets. */
    public function pruneRateLimits(int $limit = 100): int
    {
        self::bound($limit);
        return $this->db->execute('DELETE FROM rate_limits WHERE expires_at IS NOT NULL AND expires_at<=UNIX_TIMESTAMP() ORDER BY expires_at LIMIT ' . $limit)->rowCount();
    }

    /** Opt-in payload minimization. Retains dedupe tombstones, trace, statuses and attempts. */
    public function redactSucceededPayloads(int $olderThanSeconds, int $limit = 100): int
    {
        self::bound($limit);
        if ($olderThanSeconds < 86400 || $olderThanSeconds > 315360000) {
            throw new \InvalidArgumentException('Retention age must be explicit and at least one day');
        }
        return $this->db->transaction(function () use ($olderThanSeconds, $limit): int {
            $rows = $this->db->execute("SELECT id,payload FROM jobs WHERE status='SUCCEEDED' AND payload_purged_at IS NULL AND updated_at<TIMESTAMPADD(SECOND,-?,UTC_TIMESTAMP(6)) ORDER BY updated_at LIMIT " . $limit . ' FOR UPDATE SKIP LOCKED', [$olderThanSeconds])->fetchAll();
            foreach ($rows as $row) {
                $fingerprint = hash('sha256', CanonicalJson::fromJson($row['payload']));
                $this->db->execute("UPDATE jobs SET payload='{}',payload_fingerprint=?,payload_purged_at=UTC_TIMESTAMP(6) WHERE id=? AND status='SUCCEEDED'", [$fingerprint, $row['id']]);
            }
            return count($rows);
        });
    }

    private static function bound(int $limit): void
    {
        if ($limit < 1 || $limit > 1000) { throw new \InvalidArgumentException('Invalid cleanup batch'); }
    }
}
