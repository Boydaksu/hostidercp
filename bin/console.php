<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/autoload.php';
use Hostider\Core\{Config, Id, SafeLogger};
use Hostider\Core\Database\{Database, Migrator};
use Hostider\Core\Queue\{Queue, Outbox, Worker, Retention};
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
try {
    if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80300 || PHP_INT_SIZE !== 8) {
        throw new RuntimeException('64-bit PHP 8.3+ CLI required');
    }
    $command = $argv[1] ?? '';
    if ($command === 'key:generate') {
        echo base64_encode(random_bytes(32)) . PHP_EOL;
        exit(0);
    }
    if (!in_array($command, ['migrate','outbox:dispatch','queue:work','queue:attention','health:check','retention:rates','retention:job-payloads'], true)) {
        fwrite(STDERR, 'Commands: key:generate, migrate, outbox:dispatch, queue:work --once, queue:attention, health:check, retention:rates, retention:job-payloads <age-seconds>' . PHP_EOL);
        exit(2);
    }
    $db = Database::connect(Config::fromEnvironment());
    if ($command !== 'migrate') {
        (new Migrator($db, dirname(__DIR__) . '/migrations'))->assertReady();
    }
    $queue = new Queue($db);
    if ($command === 'migrate') {
        echo json_encode(['applied' => (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate()], JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command === 'outbox:dispatch') {
        $outbox = new Outbox($db, $queue);
        $count = 0;
        $deadline = hrtime(true) + 20_000_000_000;
        while ($count < 100 && hrtime(true) < $deadline && $outbox->dispatchOne()) {
            ++$count;
        }
        echo json_encode(['dispatched' => $count], JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command === 'queue:work') {
        if (($argv[2] ?? '') !== '--once') {
            throw new InvalidArgumentException('Only bounded --once worker supported');
        }
        $worker = new Worker($db, $queue);
        // F2 handlers only record local facts; ready orders and mail remain explicitly held.
        $worker->register('core.probe', static fn(array $payload): string => 'SUCCEEDED');
        (new \Hostider\Commerce\EventSink($db))->register($worker);
        echo json_encode(['worked' => $worker->once()], JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command === 'queue:attention') {
        echo json_encode(['jobs' => $db->execute("SELECT id,status,attempts,correlation_id,last_error_safe,updated_at FROM jobs WHERE status IN ('FAILED','UNKNOWN') ORDER BY updated_at,id LIMIT 100")->fetchAll()], JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command === 'retention:rates') {
        echo json_encode(['pruned' => (new Retention($db))->pruneRateLimits()], JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command === 'retention:job-payloads') {
        $age = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT);
        if ($age === false) { throw new InvalidArgumentException('Explicit retention age required'); }
        echo json_encode(['redacted' => (new Retention($db))->redactSucceededPayloads($age)], JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        $db->execute('SELECT 1');
        echo json_encode(['database' => 'ok', 'jobs' => $db->execute('SELECT status,COUNT(*) AS count FROM jobs GROUP BY status')->fetchAll()], JSON_THROW_ON_ERROR) . PHP_EOL;
    }
} catch (Throwable $e) {
    (new SafeLogger(static fn(string $line): int|false => fwrite(STDERR, $line . PHP_EOL)))->failure(Id::uuid(), $e);
    exit(1);
}
