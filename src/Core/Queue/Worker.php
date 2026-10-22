<?php
declare(strict_types=1);
namespace Hostider\Core\Queue;
use Hostider\Core\Database\Database;
final class Worker
{
    private array $handlers = [];
    public function __construct(private readonly Database $db, private readonly Queue $queue) {}

    public function register(string $type, \Closure $handler): void
    {
        if (isset($this->handlers[$type])) {
            throw new \LogicException('Duplicate handler');
        }
        $this->handlers[$type] = $handler;
    }

    public function once(): bool
    {
        if ($this->db->pdo->inTransaction()) {
            throw new \LogicException('Worker cannot execute inside transaction');
        }
        $job = $this->queue->claim();
        if ($job === null) {
            return false;
        }
        if (!isset($this->handlers[$job['job_type']])) {
            $this->queue->finish($job, 'FAILED');
            return true;
        }
        try {
            $outcome = ($this->handlers[$job['job_type']])(json_decode($job['payload'], true, 32, JSON_THROW_ON_ERROR));
            if (!in_array($outcome, ['SUCCEEDED', 'FAILED', 'UNKNOWN'], true)) {
                $outcome = 'UNKNOWN';
            }
        } catch (\Throwable) {
            $outcome = (bool) $job['retry_safe'] ? 'FAILED' : 'UNKNOWN';
        }
        $this->queue->finish($job, $outcome);
        return true;
    }
}
