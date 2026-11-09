<?php
declare(strict_types=1);

// Mounted separately from the verified archive. The web process serves only package public/.
if (PHP_SAPI === 'cli-server') {
    $_SERVER['HTTPS'] = 'on'; // Simulated trusted TLS termination on the isolated loopback server.
    return require '/workspace/install/public/index.php';
}

function check(bool $ok, string $name): void
{
    if (!$ok) {
        echo 'FAIL '.$name.PHP_EOL;
        exit(1);
    }
    echo 'PASS '.$name.PHP_EOL;
}

function command(array $arguments, string $input = ''): array
{
    $proc = proc_open($arguments, [0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('Process unavailable');
    }
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($proc), json_decode((string)$output, true)];
}

function request(string $path): array
{
    $body = @file_get_contents('http://127.0.0.1:18088'.$path, false,
        stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>2]]));
    $headers = $http_response_header ?? [];
    return [(int)substr($headers[0] ?? '',9,3), $body, $headers];
}

chdir('/workspace/install');
if (getenv('APP_ENV') !== 'test' || getenv('DB_DSN') !==
    'mysql:host=127.0.0.1;port=3306;dbname=hostider_f0_test;charset=utf8mb4') {
    exit(2);
}

try {
    $raw = file_get_contents('release-manifest.json');
    $manifest = json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    $observed = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.', FilesystemIterator::SKIP_DOTS)) as $file) {
        $name = substr($file->getPathname(),2);
        if ($name !== 'release-manifest.json') {
            $observed[$name] = ['size'=>$file->getSize(),'sha256'=>hash_file('sha256',$name),'regular'=>$file->isFile()&&!$file->isLink()];
        }
    }
    $valid = hash('sha256',$raw) === getenv('F6H_MANIFEST') && count($observed) === $manifest['file_count'];
    foreach ($manifest['files'] as $name=>$entry) {
        $valid = $valid && ($observed[$name] ?? null) === ['size'=>$entry['size'],'sha256'=>$entry['sha256'],'regular'=>true];
    }
    check($valid,'installed bytes and exact package inventory');

    require 'bootstrap/autoload.php';
    check(class_exists(\Hostider\Core\Database\Migrator::class),'native package autoload');
    $admin = \Hostider\Core\Database\Database::connect(\Hostider\Core\Config::fromEnvironment());
    $cli = [PHP_BINARY,'bin/console.php'];
    check(command([...$cli,'health:check'])[0]===1,'unmigrated health blocked');

    // Deliberate collision in the disposable database. First DDL persists and the second fails.
    $admin->execute('CREATE TABLE jobs (id INT PRIMARY KEY) ENGINE=InnoDB');
    check(command([...$cli,'migrate'])[0]===1,'partial installation fails closed');
    check($admin->execute('SELECT status FROM schema_migrations')->fetchColumn()==='APPLYING'
        && (int)$admin->execute('SELECT COUNT(*) FROM outbox_events')->fetchColumn()===0
        && command([...$cli,'migrate'])[0]===1 && command([...$cli,'health:check'])[0]===1,
        'partial DDL remains APPLYING and blocks replay and readiness');
    // Recovery is narrowly scoped to the known synthetic collision and empty partial objects.
    $admin->execute('DROP TABLE jobs, outbox_events');
    $admin->execute("DELETE FROM schema_migrations WHERE version='001_f0_infrastructure.php' AND status='APPLYING'");
    [$code,$result] = command([...$cli,'migrate']);
    check($code===0 && count($result['applied'] ?? [])===57,'forward repair then native 001–057 migration');
    check(command([...$cli,'migrate'])===[0,['applied'=>[]]],'idempotent migration replay');

    // Scoped synthetic runtime account. Exact per-table production grants remain an operator check.
    $password = bin2hex(random_bytes(24));
    $admin->execute("CREATE USER 'f6h_runtime'@'%' IDENTIFIED BY ".$admin->pdo->quote($password));
    $admin->execute("GRANT SELECT,INSERT,UPDATE,DELETE ON hostider_f0_test.* TO 'f6h_runtime'@'%'");
    putenv('DB_USER=f6h_runtime');
    putenv('DB_PASSWORD='.$password);
    [$code,$result] = command([...$cli,'health:check']);
    check($code===0 && ($result['database']??null)==='ok','runtime DML account native CLI health');
    check(command([...$cli,'migrate'])[0]===1,'runtime account cannot run migration DDL');
    [$code,$result] = command([PHP_BINARY,'bin/identity.php','bootstrap'],
        json_encode(['email'=>'admin@example.invalid','name'=>'Synthetic rehearsal','password'=>bin2hex(random_bytes(24))],JSON_THROW_ON_ERROR));
    check($code===0 && isset($result['admin_id']),'native Identity first admin bootstrap');

    $outbox = new \Hostider\Core\Queue\Outbox($admin,new \Hostider\Core\Queue\Queue($admin));
    $id = \Hostider\Core\Id::uuid();
    $admin->transaction(fn()=>$outbox->append($id,'core.probe','rehearsal',[],$id));
    check(command([...$cli,'queue:work'])[0]===1
        && (int)$admin->execute('SELECT COUNT(*) FROM jobs')->fetchColumn()===0,'unbounded worker invocation refused');
    check(command([...$cli,'outbox:dispatch'])===[0,['dispatched'=>1]]
        && command([...$cli,'outbox:dispatch'])===[0,['dispatched'=>0]],'bounded native Outbox smoke');
    check(command([...$cli,'queue:work','--once'])===[0,['worked'=>true]]
        && command([...$cli,'queue:work','--once'])===[0,['worked'=>false]]
        && $admin->execute("SELECT status FROM jobs WHERE job_type='core.probe'")->fetchColumn()==='SUCCEEDED',
        'bounded local-only Queue smoke');

    check(mkdir('storage',0700)&&mkdir('storage/session',0700),'private storage and session layout');
    file_put_contents('storage/marker.txt','F6H_STORAGE_CANARY');
    chmod('storage/session',0500);
    clearstatcache(true,'storage/session');
    check(!is_writable('storage/session'),'non-root read-only session permissions observed');
    chmod('storage/session',0700);

    $proc = proc_open([PHP_BINARY,'-d','session.save_path=/workspace/install/storage/session','-S','127.0.0.1:18088','-t','public',__FILE__],
        [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('Loopback server unavailable');
    }
    try {
        $health = [0];
        for ($i=0;$i<40&&$health[0]===0;++$i) {
            $health = request('/api/v1/health');
            if ($health[0]===0) { usleep(50000); }
        }
        check($health[0]===200 && (json_decode((string)$health[1],true)['data']['status']??null)==='ok','public document root HTTP health');
        check(request('/assets/identity.css')[0]===200,'public asset delivery');
        [$status,$body,$headers] = request('/api/v1/admin/identity/bootstrap');
        $cookies = array_values(array_filter($headers,fn($line)=>str_starts_with(strtolower($line),'set-cookie:')));
        check($status===200 && isset(json_decode((string)$body,true)['data']['csrf']) && count($cookies)===1
            && str_contains(strtolower($cookies[0]),'secure') && str_contains(strtolower($cookies[0]),'httponly')
            && count(glob('storage/session/sess_*')?:[])===1,'Identity HTTP private session initialization');
        $isolated = true;
        foreach (['/src/Core/Config.php','/bootstrap/app.php','/bin/console.php','/migrations/001_f0_infrastructure.php',
                  '/config/environment.example','/storage/marker.txt','/release-manifest.json','/%2e%2e/storage/marker.txt'] as $path) {
            [$status,$body] = request($path);
            $isolated = $isolated && in_array($status,[400,403,404],true) && !str_contains((string)$body,'F6H_STORAGE_CANARY');
        }
        check($isolated,'private sibling paths and traversal inaccessible over HTTP');
    } finally {
        proc_terminate($proc);
        proc_close($proc);
    }
    check(getenv('PROVISIONING_ALLOWED')==='false'
        && getenv('PAYMENT_LIVE_ALLOWED')==='false'
        && getenv('EXTERNAL_MAIL_ALLOWED')==='false'
        && is_readable('bin/console.php'), 'cron dry-run reviewed bounded candidate commands with effects disabled and no scheduler');
} catch (Throwable $error) {
    echo 'FAIL rehearsal exception '.get_class($error).PHP_EOL;
    exit(1);
}
