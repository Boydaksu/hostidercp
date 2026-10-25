<?php
declare(strict_types=1);
namespace Hostider\Domains;

use Hostider\Core\{CanonicalJson,Config};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Crypto;

/** Validates synthetic durable call evidence only. Never a live registrar contract. */
final readonly class AttemptEvidence
{
    private string $key;
    public function __construct(private Database $db, Config $config)
    {
        $this->key = base64_decode($config->key, true);
    }

    public function payload(array $attempt): array
    {
        $json = (new Crypto($this->key))->decrypt($attempt['request_encrypted'], 'domain-attempt:' . $attempt['id']);
        if (!hash_equals($attempt['request_fingerprint'], hash_hmac('sha256', $json, $this->key))) {
            throw new HttpError(409, 'DOMAIN_REQUEST_INTEGRITY');
        }
        $payload = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (($payload['operation_id'] ?? null) !== $attempt['id']) {
            throw new HttpError(409, 'DOMAIN_REQUEST_INTEGRITY');
        }
        return $payload;
    }

    public function call(array $attempt): ?array
    {
        $payload = $this->payload($attempt);
        $call = $this->db->execute('SELECT * FROM mock_registrar_calls WHERE operation_id=?', [$attempt['id']])->fetch();
        if (!$call) { return null; }
        $fingerprint = hash_hmac('sha256', CanonicalJson::encode([
            'account' => $attempt['account_id'], 'action' => $payload['action'],
            'resource' => $payload['resource'], 'configuration' => $payload['configuration'],
        ]), $this->key);
        if ($call['account_id'] !== $attempt['account_id'] || $call['action'] !== $payload['action'] || !hash_equals($call['fingerprint'], $fingerprint)) {
            throw new HttpError(409, 'DOMAIN_CALL_INTEGRITY');
        }
        return $call;
    }

    public function noEffect(?array $call): bool
    {
        return $call !== null && in_array($call['status'], ['DEFINITIVE_FAILURE', 'UNSUPPORTED'], true)
            && (bool)$call['no_effect'] && $call['resource_id'] === null
            && $call['effect_resource_id'] === null && $call['effect_term_years'] === null;
    }

    public function success(array $op, array $attempt, array $domain, ?array $proof, ?array $call): bool
    {
        if (!$proof || !$call || (bool)$call['no_effect'] || !in_array($call['status'], ['SUCCESS', 'UNKNOWN'], true)) { return false; }
        $payload = $this->payload($attempt);
        return $proof['account_id'] === $attempt['account_id'] && $proof['name_ascii'] === $domain['name_ascii']
            && $proof['status'] === 'REGISTERED' && $proof['id'] === $call['effect_resource_id']
            && (int)$proof['term_years'] === (int)$call['effect_term_years']
            && ($call['resource_id'] === null || $call['resource_id'] === $proof['id'])
            && ($op['kind'] === 'REGISTER'
                ? $proof['create_operation_id'] === $attempt['id'] && (int)$proof['term_years'] === $payload['configuration']['years']
                : $payload['resource'] === $domain['provider_external_id'] && $proof['id'] === $payload['resource']
                    && (int)$proof['term_years'] === ($payload['configuration']['expected_term_years'] ?? null));
    }
}
