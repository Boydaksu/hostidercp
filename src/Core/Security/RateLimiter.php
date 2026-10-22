<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Database\Database;
final readonly class RateLimiter
{
    public function __construct(private Database $db, #[\SensitiveParameter] private string $key) {}
    public function allow(string $bucket, int $limit = 60, int $window = 60): bool
    {
        if ($limit < 1 || $window < 1 || $window > 86400) {
            throw new \InvalidArgumentException('Invalid rate policy');
        }
        $hash = hash_hmac('sha256', $bucket, $this->key);
        return $this->db->transaction(function () use ($hash, $limit, $window): bool {
            $now = (int) $this->db->execute('SELECT UNIX_TIMESTAMP()')->fetchColumn();
            $start = intdiv($now, $window) * $window;
            $this->db->execute('INSERT INTO rate_limits (bucket_hash,window_start,hits) VALUES (?,?,0) ON DUPLICATE KEY UPDATE bucket_hash=bucket_hash', [$hash, $start]);
            $row = $this->db->execute('SELECT * FROM rate_limits WHERE bucket_hash=? FOR UPDATE', [$hash])->fetch();
            $hits = (int) $row['window_start'] === $start ? (int) $row['hits'] : 0;
            if ($hits >= $limit) {
                return false;
            }
            $this->db->execute('UPDATE rate_limits SET window_start=?,hits=?,expires_at=? WHERE bucket_hash=?', [$start, $hits + 1, $start + $window, $hash]);
            return true;
        });
    }
}
