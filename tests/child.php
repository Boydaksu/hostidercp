<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/autoload.php';
require __DIR__ . '/database.php';
use Hostider\Core\Queue\Queue;
use Hostider\Core\Idempotency;
$db = integrationDb();
if (($argv[1] ?? '') === 'claim') {
    $job = (new Queue($db))->claim();
    echo json_encode($job ? ['id'=>$job['id'],'token'=>$job['lease_token']] : null, JSON_THROW_ON_ERROR);
} elseif (($argv[1] ?? '') === 'idempotency') {
    $result = (new Idempotency($db))->run('actor','parallel','parallel_key_123456', ['value'=>1], function () use ($db): array {
        // A DB-local write makes duplicate execution externally observable to the test.
        $db->execute("INSERT INTO inbox_events (consumer,event_uuid) VALUES ('parallel-work','00000000-0000-4000-8000-000000000001')");
        return ['ok'=>true];
    });
    echo json_encode($result, JSON_THROW_ON_ERROR);
}
