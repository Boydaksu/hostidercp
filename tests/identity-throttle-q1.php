<?php
declare(strict_types=1);

require __DIR__.'/harness.php';
require __DIR__.'/database.php';

use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Id;
use Hostider\Core\Security\RateLimiter;
use Hostider\Identity\IdentityService;

if (getenv('APP_ENV') !== 'test' ||
    getenv('TEST_DB_DSN') !== 'mysql:host=127.0.0.1;port=3306;dbname=hostider_f0_test;charset=utf8mb4') {
    exit(2);
}

$db = integrationDb();
$boundary = 1800000000;
$raceKey = str_repeat('q', 32);

function clockAt($db, int $timestamp): void
{
    $db->execute('SET SESSION timestamp = '.$timestamp);
    same($timestamp, (int) $db->execute('SELECT UNIX_TIMESTAMP()')->fetchColumn());
}

if (($argv[1] ?? '') === 'boundary-worker') {
    $pdo = new class(getenv('TEST_DB_DSN'),getenv('TEST_DB_USER'),getenv('TEST_DB_PASSWORD'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]) extends PDO {
        public int $boundary;
        public function prepare(string $query,array $options=[]): PDOStatement|false
        {
            if (str_starts_with($query,'INSERT INTO rate_limits')) {
                // Pause before acquiring the bucket lock, then advance this session's clock.
                echo "READY\n";
                if (trim((string) fgets(STDIN)) !== 'GO') { exit(2); }
                $this->exec('SET SESSION timestamp = '.$this->boundary);
            }
            return parent::prepare($query,$options);
        }
    };
    $pdo->boundary=$boundary+300;
    $child=new \Hostider\Core\Database\Database($pdo);
    clockAt($child,$boundary+299);
    echo (new RateLimiter($child,$raceKey))->allow('q1-boundary-race',10,300) ? "ALLOW\n" : "DENY\n";
    exit(0);
}

if (($argv[1] ?? '') === 'worker') {
    clockAt($db, $boundary);
    echo "READY\n";
    if (trim((string) fgets(STDIN)) !== 'GO') { exit(2); }
    echo (new RateLimiter($db, $raceKey))->allow('q1-race', 10, 300) ? "ALLOW\n" : "DENY\n";
    exit(0);
}

(new Migrator($db, dirname(__DIR__).'/migrations'))->migrate();
$config = testConfig();
$key = base64_decode($config->key, true);
$identity = new IdentityService($db, $config);
$limiter = new RateLimiter($db, $key);
$login = static fn(string $name, string $network) =>
    $identity->login($name.'@example.invalid', 'synthetic-invalid-input', 'admin', Id::uuid(), $network);
$denied = ['denied' => true];
$rateLimited = static function (Closure $work): void {
    $error = raises(HttpError::class, $work);
    same(429, $error->status);
    same('RATE_LIMITED', $error->errorCode);
};

test('Q1 account cap survives a 60-second boundary within one 300-second window', function () use ($db,$boundary,$login,$denied,$rateLimited): void {
    clockAt($db, $boundary + 59);
    for ($i=0; $i<10; ++$i) { same($denied,$login('account', 'account-'.$i)); }
    clockAt($db, $boundary + 60);
    $rateLimited(fn() => $login('account', 'account-extra'));
    clockAt($db, $boundary + 299);
    $rateLimited(fn() => $login('account', 'account-final'));
    clockAt($db, $boundary + 300);
    same($denied,$login('account','account-reset'));
});

test('Q1 legacy 429 assertion fails when the eleventh attempt crosses the 300-second boundary', function () use ($db,$boundary,$login,$denied): void {
    clockAt($db, $boundary + 299);
    for ($i=0; $i<10; ++$i) { same($denied,$login('boundary', 'boundary-'.$i)); }
    clockAt($db, $boundary + 300);
    $failure = raises(RuntimeException::class, fn() =>
        same(429,raises(HttpError::class,fn()=>$login('boundary','boundary-extra'))->status));
    same('Expected exception: '.HttpError::class,$failure->getMessage());
});

test('Q1 network cap denies new account and resets at the exact boundary', function () use ($db,$boundary,$login,$denied,$rateLimited,$key): void {
    clockAt($db, $boundary + 599);
    for ($i=0; $i<30; ++$i) { same($denied,$login('network-'.$i,'shared-network')); }
    $rateLimited(fn()=>$login('fresh-network-account','shared-network'));
    $hash = hash_hmac('sha256','identity:login-account:fresh-network-account@example.invalid',$key);
    same(false,$db->execute('SELECT hits FROM rate_limits WHERE bucket_hash=?',[$hash])->fetchColumn());
    clockAt($db, $boundary + 600);
    same($denied,$login('fresh-network-account','shared-network'));
});

test('Q1 nested transaction rejects direct limiter and leaves no counter', function () use ($db,$limiter,$key): void {
    $db->transaction(function () use ($db,$limiter): void {
        raises(LogicException::class,fn()=>$limiter->allow('q1-nested',10,300));
        same(true,$db->pdo->inTransaction());
    });
    $hash=hash_hmac('sha256','q1-nested',$key);
    same(false,$db->execute('SELECT hits FROM rate_limits WHERE bucket_hash=?',[$hash])->fetchColumn());
});

test('Q1 concurrent requests cannot overspend the last slot in the same window', function () use ($db,$boundary,$raceKey): void {
    clockAt($db,$boundary);
    $limiter=new RateLimiter($db,$raceKey);
    for ($i=0; $i<9; ++$i) { same(true,$limiter->allow('q1-race',10,300)); }
    $workers=[];
    try {
        for ($i=0; $i<4; ++$i) {
            $p=proc_open([PHP_BINARY,__FILE__,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            same(true,is_resource($p));
            $workers[]=[$p,$pipes];
            stream_set_timeout($pipes[1],10);
            same("READY\n",fgets($pipes[1]));
        }
        foreach ($workers as [$p,$pipes]) { fwrite($pipes[0],"GO\n"); fflush($pipes[0]); }
        $results=[];
        foreach ($workers as [$p,$pipes]) { $results[]=trim((string) fgets($pipes[1])); }
        sort($results);
        same(['ALLOW','DENY','DENY','DENY'],$results);
    } finally {
        foreach ($workers as [$p,$pipes]) { foreach ($pipes as $pipe) { fclose($pipe); } proc_close($p); }
    }
    $hash=hash_hmac('sha256','q1-race',$raceKey);
    same(10,(int)$db->execute('SELECT hits FROM rate_limits WHERE bucket_hash=?',[$hash])->fetchColumn());
});

test('Q1 delayed old-window contender cannot reset an already exhausted new window', function () use ($db,$boundary,$raceKey): void {
    $process=proc_open([PHP_BINARY,__FILE__,'boundary-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    same(true,is_resource($process));
    try {
        stream_set_timeout($pipes[1],10);
        same("READY\n",fgets($pipes[1]));
        clockAt($db,$boundary+300);
        $limiter=new RateLimiter($db,$raceKey);
        for ($i=0; $i<10; ++$i) { same(true,$limiter->allow('q1-boundary-race',10,300)); }
        fwrite($pipes[0],"GO\n");
        fflush($pipes[0]);
        $outcome=trim((string) fgets($pipes[1]));
        $hash=hash_hmac('sha256','q1-boundary-race',$raceKey);
        $row=$db->execute('SELECT window_start,hits FROM rate_limits WHERE bucket_hash=?',[$hash])->fetch();
        if ($outcome==='ALLOW' && (int)$row['window_start']===$boundary && (int)$row['hits']===1) {
            same(true,$limiter->allow('q1-boundary-race',10,300));
            throw new RuntimeException('CROSS_WINDOW_RESET_REPRODUCED');
        }
        same('DENY',$outcome);
        same($boundary+300,(int)$row['window_start']);
        same(10,(int)$row['hits']);
        same(false,$limiter->allow('q1-boundary-race',10,300));
    } finally {
        foreach ($pipes as $pipe) { fclose($pipe); }
        proc_close($process);
    }
});

test('Q1 failed counter update rolls back provisional bucket creation', function () use ($db,$limiter,$key): void {
    $db->execute("CREATE TRIGGER q1_reject_counter BEFORE UPDATE ON rate_limits FOR EACH ROW BEGIN IF NEW.hits > OLD.hits THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Q1 synthetic rollback'; END IF; END");
    try {
        raises(PDOException::class,fn()=>$limiter->allow('q1-rollback',10,300));
        same(false,$db->pdo->inTransaction());
        $hash=hash_hmac('sha256','q1-rollback',$key);
        same(false,$db->execute('SELECT hits FROM rate_limits WHERE bucket_hash=?',[$hash])->fetchColumn());
    } finally {
        $db->execute('DROP TRIGGER q1_reject_counter');
    }
    same(true,$limiter->allow('q1-rollback',10,300));
});

exit(executeTests());
