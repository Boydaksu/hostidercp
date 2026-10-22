<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Database\Database;
final readonly class Audit
{
    public function __construct(private Database $db) {}
    public function record(Principal $actor, string $action, string $subjectId, string $trace, ?string $cause = null): void
    {
        $this->db->requireTransaction();
        // Controlled vocabulary prevents caller-supplied descriptions/PII entering logs.
        if (!in_array($action, ['access.denied', 'job.reconciled', 'session.ended', 'impersonation.started', 'impersonation.ended', 'configuration.changed'], true)
            || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $subjectId)
            || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $actor->actorId)
            || ($actor->customerId !== null && !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $actor->customerId))) {
            throw new \InvalidArgumentException('Invalid audit fields');
        }
        $this->db->execute('INSERT INTO audit_logs (actor_id,effective_customer_id,action,subject_id,correlation_id,causation_id) VALUES (?,?,?,?,?,?)',
            [$actor->actorId, $actor->customerId, $action, $subjectId, $trace, $cause]);
    }
}
