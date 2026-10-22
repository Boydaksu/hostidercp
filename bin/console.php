<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/autoload.php';
use Hostider\Core\{Config, Id, SafeLogger};
use Hostider\Core\Database\{Database, Migrator};
use Hostider\Core\Queue\{Queue, Outbox, Worker};
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
    if (!in_array($command, ['migrate','outbox:dispatch','queue:work','health:check'], true)) {
        fwrite(STDERR, 'Commands: key:generate, migrate, outbox:dispatch, queue:work --once, health:check' . PHP_EOL);
        exit(2);
    }
    $db = Database::connect(Config::fromEnvironment());
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
        // Internal no-side-effect probe only. Commerce handlers belong to later phases.
        $worker->register('core.probe', static fn(array $payload): string => 'SUCCEEDED');
        echo json_encode(['worked' => $worker->once()], JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        $db->execute('SELECT 1');
        echo json_encode(['database' => 'ok', 'jobs' => $db->execute('SELECT status,COUNT(*) AS count FROM jobs GROUP BY status')->fetchAll()], JSON_THROW_ON_ERROR) . PHP_EOL;
    }
} catch (Throwable $e) {
    (new SafeLogger(static fn(string $line): int|false => fwrite(STDERR, $line . PHP_EOL)))->failure(Id::uuid(), $e);
    exit(1);
}
