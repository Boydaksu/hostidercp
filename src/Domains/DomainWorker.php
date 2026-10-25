<?php
declare(strict_types=1);
namespace Hostider\Domains;

use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Security\Principal;
use Hostider\Providers\Contract\Operation;
use Hostider\Providers\Mock\DurableRegistrar;

/**
 * Domain Operations Worker (F4-C).
 * Processes queued domain operations with bounded batch and explicit time budget.
 */
final readonly class DomainWorker
{
    private DomainEngine $engine;

    public function __construct(private Database $db, private Config $config, private string $scenario = 'SUCCESS')
    {
        $this->engine = new DomainEngine($db, $config);
    }

    /**
     * Run bounded work batch. Never exceeds 100 operations or 20s time limit.
     */
    public function work(Principal $actor, string $trace, int $maxOps = 100, int $maxSeconds = 20): array
    {
        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $unknown = 0;
        $failovers = 0;

        $deadline = hrtime(true) + ($maxSeconds * 1_000_000_000);

        // First clean up expired leases
        $this->engine->expire($actor, $trace);

        while ($processed < $maxOps && hrtime(true) < $deadline) {
            $claim = $this->engine->claim($actor, $trace);
            if ($claim === null) {
                break;
            }

            ++$processed;
            $opId = $claim['operation_id'];
            $token = $claim['lease_token'];
            $account = $claim['account'];
            $payload = $claim['payload'];

            // Durable mock registrar instance
            $registrar = new DurableRegistrar($this->db, $this->config, $account['id'], $this->scenario);

            $operation = new Operation(
                $claim['attempt_id'],
                $payload['action'],
                $payload['resource'],
                $payload['configuration']
            );

            // Execute provider OUTSIDE transaction
            try {
                $result = $registrar->execute($operation);
            } catch (\Throwable $e) {
                // Provider crash/exception is UNKNOWN
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

            if ($result->status === 'SUCCESS') {
                ++$succeeded;
            } elseif ($result->status === 'UNKNOWN') {
                ++$unknown;
            } else {
                // Could be DEFINITIVE_FAILURE, check if failover was scheduled
                $opStatus = $this->db->execute('SELECT status FROM domain_operations WHERE id=?', [$opId])->fetchColumn();
                if ($opStatus === 'PENDING') {
                    ++$failovers;
                } else {
                    ++$failed;
                }
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'unknown' => $unknown,
            'failovers' => $failovers,
        ];
    }
}
