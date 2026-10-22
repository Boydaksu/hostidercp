<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Database\Database;
final readonly class Audit
{
    public function __construct(private Database $db) {}
    public function record(Principal $actor, AuditEvent|string $action, string $subjectId, string $trace, ?string $cause = null): void
    {
        $this->db->requireTransaction();
        // Controlled vocabulary prevents caller-supplied descriptions/PII entering logs.
        $event = $action instanceof AuditEvent ? $action : AuditEvent::tryFrom($action);
        if ($event === null
            || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $subjectId)
            || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $actor->actorId)
            || ($actor->customerId !== null && !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $actor->customerId))
            || !self::uuid($trace) || ($cause !== null && !self::uuid($cause))) {
            throw new \InvalidArgumentException('Invalid audit fields');
        }
        $this->db->execute('INSERT INTO audit_logs (actor_id,effective_customer_id,action,subject_id,correlation_id,causation_id) VALUES (?,?,?,?,?,?)',
            [$actor->actorId, $actor->customerId, $event->value, $subjectId, $trace, $cause]);
    }

    private static function uuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value) === 1;
    }
}
