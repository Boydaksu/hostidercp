<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';
require __DIR__ . '/database.php';
integrationDb(); // Refuse production or an unscoped database before starting a server.
$root=dirname(__DIR__);
function withServer(array $overrides, Closure $work): void
{
    global $root;
    $port=33318;
    $env=array_merge(getenv(), ['APP_ENV'=>'staging','APP_URL'=>'https://test.example.invalid',
        'APP_KEY'=>base64_encode(random_bytes(32)), 'DB_DSN'=>getenv('TEST_DB_DSN'),
        'DB_USER'=>getenv('TEST_DB_USER') ?: 'root','DB_PASSWORD'=>getenv('TEST_DB_PASSWORD') ?: '',
        'PROVISIONING_ALLOWED'=>'false','PAYMENT_LIVE_ALLOWED'=>'false','EXTERNAL_MAIL_ALLOWED'=>'false'], $overrides);
    $pipes=[];
    $process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root.'/public',$root.'/public/index.php'],
        [0=>['pipe','r'],1=>['file',$root.'/storage/http-test.stdout','a'],2=>['file',$root.'/storage/http-test.stderr','a']],$pipes,$root,$env);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start test HTTP server'); }
    fclose($pipes[0]);
    try {
        $ready=false;
        for ($i=0;$i<50;++$i) {
            $probe=curl_init('http://127.0.0.1:'.$port.'/missing');
            curl_setopt_array($probe,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>100,CURLOPT_TIMEOUT_MS=>500]);
            $ready=curl_exec($probe)!==false; curl_close($probe);
            if ($ready) { break; } usleep(100000);
        }
        if (!$ready) { throw new RuntimeException('HTTP server did not start'); }
        $work($port);
    } finally { proc_terminate($process); proc_close($process); }
}
function httpProbe(int $port, string $path, string $method='GET'): array
{
    $curl=curl_init('http://127.0.0.1:'.$port.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>3]);
    $raw=curl_exec($curl); $code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $size=curl_getinfo($curl,CURLINFO_HEADER_SIZE); curl_close($curl);
    if ($raw===false) { throw new RuntimeException('HTTP request failed'); }
    return [$code,substr($raw,0,$size),json_decode(substr($raw,$size),true,32,JSON_THROW_ON_ERROR)];
}
test('HTTP entry point health/error envelopes, headers and rate limiting', function (): void {
    $db=integrationDb(); $db->execute('DELETE FROM rate_limits');
    // The application intentionally uses aligned 60-second windows. Starting this
    // burst at xx:59 can legitimately reset the bucket before its 61st request.
    // Establish the clock precondition once; do not retry or relax a failed result.
    $seconds=(int)$db->execute('SELECT UNIX_TIMESTAMP()')->fetchColumn()%60;
    if($seconds>=45){usleep((61-$seconds)*1000000);}
    withServer([],function (int $port): void {
        [$status,$headers,$body]=httpProbe($port,'/api/v1/health'); same(200,$status); same('ok',$body['data']['status']);
        same(true,str_contains(strtolower($headers),'cache-control: no-store'));
        same(true,str_contains(strtolower($headers),'x-content-type-options: nosniff'));
        same(404,httpProbe($port,'/missing')[0]); same(405,httpProbe($port,'/api/v1/health','POST')[0]);
        for ($i=0;$i<60;++$i) { $last=httpProbe($port,'/api/v1/health'); }
        same(429,$last[0]); same('RATE_LIMITED',$last[2]['errors'][0]['code']);
    });
});
test('HTTP invalid bootstrap config returns safe 503', function (): void {
    withServer(['APP_KEY'=>''],function (int $port): void {
        [$status,,$body]=httpProbe($port,'/api/v1/health'); same(503,$status); same('UNAVAILABLE',$body['errors'][0]['code']);
        same(false,str_contains(json_encode($body),'PDO'));
    });
});
test('HTTP incomplete schema refuses to serve health as ready', function (): void {
    $db=integrationDb(); $db->execute("UPDATE schema_migrations SET status='APPLYING'");
    try {
        withServer([],function (int $port): void {
            [$status,,$body]=httpProbe($port,'/api/v1/health'); same(503,$status); same('DATABASE_UNAVAILABLE',$body['errors'][0]['code']);
        });
    } finally { $db->execute("UPDATE schema_migrations SET status='APPLIED'"); }
});
runTests();
