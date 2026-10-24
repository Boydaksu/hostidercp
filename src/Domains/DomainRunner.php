<?php
declare(strict_types=1);
namespace Hostider\Domains;

use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Providers\Contract\Operation;
use Hostider\Providers\Mock\DurableRegistrar;

/**
 * Domain Operations Runner (F4-C).
 * Guarantees bounded batch admission, starvation avoidance and safe CLI integration.
 */
final readonly class DomainRunner
{
    private DomainEngine $engine;

    public function __construct(private Database $db, private Config $config, private string $scenario = 'SUCCESS')
    {
        $this->engine = new DomainEngine($db, $config);
    }

    /**
     * Execute one pending domain operation with starvation avoidance.
     */
    public function once(?Principal $actor = null, ?string $id = null, ?int $deadline = null): bool
    {
        if ($this->config->environment !== 'test' || $this->db->pdo->inTransaction()) {
            throw new HttpError(403, 'F4_TEST_ONLY');
        }
        $actor ??= new Principal('domain-worker', []);
        $deadline ??= hrtime(true) + 20_000_000_000;
        $trace = Id::uuid();

        // Expire stale leases first
        $this->engine->expire($actor, $trace);

        $rows = $this->db->execute(
            "SELECT id FROM domain_operations WHERE status='PENDING'" .
            ($id !== null ? ' AND id=?' : '') .
            ' ORDER BY runner_checked_at, created_at, id LIMIT 100',
            $id !== null ? [$id] : []
        )->fetchAll();

        foreach ($rows as $row) {
            if (hrtime(true) >= $deadline) {
                return false;
            }

            try {
                $claim = $this->engine->claim($actor, $trace, $row['id']);
                if ($claim === null) {
                    continue;
                }

                $opId = $claim['operation_id'];
                $token = $claim['lease_token'];
                $account = $claim['account'];
                $payload = $claim['payload'];

                $registrar = new DurableRegistrar($this->db, $this->config, $account['id'], $this->scenario);
                $operation = new Operation(
                    $claim['attempt_id'],
                    $payload['action'],
                    $payload['resource'],
                    $payload['configuration']
                );

                try {
                    $result = $registrar->execute($operation);
                } catch (\Throwable) {
                    $result = new \Hostider\Providers\Contract\ProviderResult(
                        $claim['attempt_id'],
                        $account['provider'],
                        'UNKNOWN',
                        null,
                        'WORKER_TRANSPORT_EXCEPTION'
                    );
                }

                $proof = null;
                if ($result->status === 'SUCCESS' && $result->externalResourceId !== null) {
                    $proof = $registrar->evidence($payload['configuration']['domain']);
                }

                $this->engine->finishAttempt($opId, $token, $result, $proof, $actor, $trace);
                return true;
            } catch (HttpError $e) {
                if ($id !== null || !in_array($e->errorCode, ['DOMAIN_SOURCE_CHANGED', 'DOMAIN_CLAIM_CONFLICT', 'DOMAIN_NOT_PENDING'], true)) {
                    throw $e;
                }
            }
        }

        return false;
    }

    /**
     * Bounded CLI worker budget (maximum 100 ops, 20 seconds limit).
     */
    public function run(Principal $actor, int $maximum = 100, int $seconds = 20): array
    {
        if ($maximum < 1 || $maximum > 100 || $seconds < 1 || $seconds > 20) {
            throw new \InvalidArgumentException('Invalid domain worker budget');
        }
        $deadline = hrtime(true) + ($seconds * 1_000_000_000);
        $worked = 0;

        $limit = ini_get('memory_limit');
        $bytes = (int)$limit;
        $bytes *= match (strtolower(substr($limit, -1))) { 'g' => 1024 * 1024 * 1024, 'm' => 1024 * 1024, 'k' => 1024, default => 1 };
        $memoryBudget = $bytes > 0 ? min(64 * 1024 * 1024, max(0, $bytes - 8 * 1024 * 1024)) : 64 * 1024 * 1024;

        while ($worked < $maximum && hrtime(true) < $deadline && memory_get_usage(true) < $memoryBudget) {
            if (!$this->once($actor, deadline: $deadline)) {
                break;
            }
            ++$worked;
        }

        return ['worked' => $worked, 'limit' => $maximum, 'budget_seconds' => $seconds];
    }
}
