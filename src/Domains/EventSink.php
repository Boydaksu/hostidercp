<?php
declare(strict_types=1);
namespace Hostider\Domains;

use Hostider\Core\{CanonicalJson,Config};
use Hostider\Core\Database\Database;
use Hostider\Core\Queue\{Outbox,Queue,Worker};
use Hostider\Core\Security\Principal;

/**
 * Domain Outbox Event Consumer (F4-C).
 * Idempotently processes outbox events and dispatches domain operations.
 */
final readonly class EventSink
{
    public function __construct(private Database $db, private Config $config) {}

    public function register(Worker $worker): void
    {
        foreach (['domains.changed', 'domains.execute'] as $type) {
            $worker->register($type, fn(array $payload): string => $this->consume($type, $payload));
        }
    }

    public function consume(string $type, array $payload): string
    {
        if ($this->config->environment !== 'test') {
            return 'FAILED';
        }

        $field = $type === 'domains.changed' ? 'domain_id' : 'operation_id';
        if (array_keys($payload) !== [$field] || !is_string($payload[$field]) || !preg_match('/^[a-f0-9-]{36}$/D', $payload[$field])) {
            return 'FAILED';
        }

        $events = $this->db->execute(
            'SELECT event_uuid, payload FROM outbox_events WHERE event_type=? AND aggregate_id=? ORDER BY id',
            [$type, $payload[$field]]
        )->fetchAll();

        $matched = [];
        foreach ($events as $event) {
            if (CanonicalJson::fromJson($event['payload']) === CanonicalJson::encode($payload)) {
                $matched[] = $event['event_uuid'];
            }
        }
        if ($matched === []) {
            return 'FAILED';
        }

        if ($type === 'domains.execute') {
            $runner = new DomainRunner($this->db, $this->config);
            $runner->once(new Principal('domain-outbox-worker', []), $payload[$field]);
            $status = $this->db->execute('SELECT status FROM domain_operations WHERE id=?', [$payload[$field]])->fetchColumn();
            if ($status !== 'SUCCEEDED') {
                return in_array($status, ['UNKNOWN','MANUAL_REVIEW'], true) ? 'UNKNOWN' : 'FAILED';
            }
        }

        $outbox = new Outbox($this->db, new Queue($this->db));
        foreach ($matched as $event) {
            $outbox->consume('domains.delivery.v1', $event, static fn(): null => null);
        }

        return 'SUCCEEDED';
    }
}
