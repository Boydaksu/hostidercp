<?php
declare(strict_types=1);
namespace Hostider\Core\Queue;
use Hostider\Core\Database\Database;
final readonly class Outbox
{
    public function __construct(private Database $db, private Queue $queue) {}

    public function append(string $eventId, string $type, string $aggregate, array $payload, string $trace, ?string $cause = null): void
    {
        $this->db->requireTransaction();
        $this->db->execute('INSERT INTO outbox_events (event_uuid,event_type,aggregate_id,payload,correlation_id,causation_id) VALUES (?,?,?,?,?,?)',
            [$eventId, $type, $aggregate, json_encode($payload, JSON_THROW_ON_ERROR), $trace, $cause]);
    }

    public function dispatchOne(): bool
    {
        return $this->db->transaction(function (): bool {
            $event = $this->db->execute('SELECT * FROM outbox_events WHERE published_at IS NULL ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED')->fetch();
            if (!$event) {
                return false;
            }
            // Local durable transfer only; no network call or implicit retry safety.
            $this->queue->enqueue($event['event_type'], json_decode($event['payload'], true, 32, JSON_THROW_ON_ERROR), 'outbox:' . $event['event_uuid'], $event['correlation_id']);
            $this->db->execute('UPDATE outbox_events SET published_at=UTC_TIMESTAMP(6) WHERE id=?', [$event['id']]);
            return true;
        });
    }

    public function consume(string $consumer, string $eventId, \Closure $work): bool
    {
        return $this->db->transaction(function () use ($consumer, $eventId, $work): bool {
            try {
                $this->db->execute('INSERT INTO inbox_events (consumer,event_uuid) VALUES (?,?)', [$consumer, $eventId]);
            } catch (\PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    return false;
                }
                throw $e;
            }
            $work();
            return true;
        });
    }
}
