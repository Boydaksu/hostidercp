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
    if (!in_array($command, ['migrate','outbox:dispatch','queue:work','queue:attention','health:check','retention:rates','retention:job-payloads','services:discover','services:expire','services:work','services:schedule','domains:work','domains:expire'], true)) {
        fwrite(STDERR, 'Commands: key:generate, migrate, outbox:dispatch, queue:work --once, queue:attention, health:check, retention:rates, retention:job-payloads <age-seconds>, domains:work, domains:expire' . PHP_EOL);
        exit(2);
    }
    $config=Config::fromEnvironment();
    $db = Database::connect($config);
    if ($command !== 'migrate') {
        (new Migrator($db, dirname(__DIR__) . '/migrations'))->assertReady();
    }
    $queue = new Queue($db);
    if ($command === 'migrate') {
        echo json_encode(['applied' => (new Migrator($db, dirname(__DIR__) . '/migrations'))->migrate()], JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command==='services:schedule') {
        echo json_encode((new \Hostider\Services\Scheduler($db,$config))->run(),JSON_THROW_ON_ERROR).PHP_EOL;
    } elseif ($command==='services:work') {
        // A contended admission transaction must not consume an entire cron interval.
        $db->execute('SET SESSION innodb_lock_wait_timeout=2');
        $runner=new \Hostider\Services\Runner($db,$config);$actor=new \Hostider\Core\Security\Principal('service-worker',[]);
        if(array_slice($argv,2)===['--once']){$result=['worked'=>$runner->once($actor)];}
        else{
            $options=[];foreach(array_slice($argv,2) as $option){if(!preg_match('/^--(max-operations|max-seconds)=([1-9][0-9]*)$/D',$option,$match)||isset($options[$match[1]])){throw new InvalidArgumentException('Invalid service worker option');}$options[$match[1]]=(int)$match[2];}
            $result=$runner->run($actor,$options['max-operations']??100,$options['max-seconds']??20);
        }
        echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
    } elseif (in_array($command,['services:discover','services:expire'],true)) {
        $engine=new \Hostider\Services\Engine($db,$config);$actor=new \Hostider\Core\Security\Principal('service-cron',[]);$trace=Id::uuid();
        echo json_encode(['processed'=>$command==='services:discover'?$engine->discoverBatch($actor,$trace):$engine->expire($actor,$trace)],JSON_THROW_ON_ERROR).PHP_EOL;
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
        (new \Hostider\Services\EventSink($db,$config))->register($worker);
        (new \Hostider\Domains\EventSink($db,$config))->register($worker);
        echo json_encode(['worked' => $worker->once()], JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command === 'domains:work') {
        $db->execute('SET SESSION innodb_lock_wait_timeout=2');
        $runner = new \Hostider\Domains\DomainRunner($db, $config);
        $actor = new \Hostider\Core\Security\Principal('domain-worker', []);
        if (array_slice($argv, 2) === ['--once']) {
            $result = ['worked' => $runner->once($actor)];
        } else {
            $options = [];
            foreach (array_slice($argv, 2) as $option) {
                if (!preg_match('/^--(max-operations|max-seconds)=([1-9][0-9]*)$/D', $option, $match) || isset($options[$match[1]])) {
                    throw new InvalidArgumentException('Invalid domain worker option');
                }
                $options[$match[1]] = (int)$match[2];
            }
            $result = $runner->run($actor, $options['max-operations'] ?? 100, $options['max-seconds'] ?? 20);
        }
        echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command === 'domains:expire') {
        $engine = new \Hostider\Domains\DomainEngine($db, $config);
        $actor = new \Hostider\Core\Security\Principal('domain-cron', []);
        $trace = Id::uuid();
        echo json_encode(['processed' => $engine->expire($actor, $trace)], JSON_THROW_ON_ERROR) . PHP_EOL;
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
