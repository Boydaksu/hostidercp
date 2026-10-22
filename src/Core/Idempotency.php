<?php
declare(strict_types=1);
namespace Hostider\Core;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
final readonly class Idempotency
{
    public function __construct(private Database $db) {}

    public static function fingerprint(array $request): string
    {
        $normalize = static function (mixed $value) use (&$normalize): mixed {
            if (is_float($value)) {
                throw new \InvalidArgumentException('Use integer or decimal strings');
            }
            if (is_array($value)) {
                if (!array_is_list($value)) {
                    ksort($value, SORT_STRING);
                }
                return array_map($normalize, $value);
            }
            if (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Unsupported request value');
            }
            return $value;
        };
        return hash('sha256', json_encode($normalize($request), JSON_THROW_ON_ERROR));
    }

    public function run(string $actor, string $scope, string $key, array $request, \Closure $work): array
    {
        if ($actor === '' || $scope === '' || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $key)) {
            throw new HttpError(422, 'INVALID_IDEMPOTENCY_KEY');
        }
        $scopeHash = hash('sha256', json_encode([$actor, $scope, $key], JSON_THROW_ON_ERROR));
        $fingerprint = self::fingerprint($request);
        // This callback is local DB work only. No provider dispatch may occur here.
        return $this->db->transaction(function () use ($scopeHash, $fingerprint, $work): array {
            $this->db->execute('INSERT INTO idempotency_keys (scope_hash,fingerprint) VALUES (?,?) ON DUPLICATE KEY UPDATE scope_hash=scope_hash', [$scopeHash, $fingerprint]);
            $row = $this->db->execute('SELECT * FROM idempotency_keys WHERE scope_hash=? FOR UPDATE', [$scopeHash])->fetch();
            if (!hash_equals($row['fingerprint'], $fingerprint)) {
                throw new HttpError(409, 'IDEMPOTENCY_CONFLICT');
            }
            if ($row['response_json'] !== null) {
                return json_decode($row['response_json'], true, 32, JSON_THROW_ON_ERROR);
            }
            $result = $work();
            if (!is_array($result)) {
                throw new \LogicException('Idempotent result must be an array');
            }
            $this->db->execute('UPDATE idempotency_keys SET response_json=? WHERE scope_hash=?', [json_encode($result, JSON_THROW_ON_ERROR), $scopeHash]);
            return $result;
        });
    }
}
