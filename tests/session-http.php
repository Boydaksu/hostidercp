<?php
declare(strict_types=1);
require __DIR__.'/harness.php';

function sessionHttp(int $port,string $path,?string $cookie=null): array {
    $curl=curl_init('http://127.0.0.1:'.$port.$path);
    $headers=[];
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>2,
        CURLOPT_HEADERFUNCTION=>static function ($curl,string $line) use (&$headers): int { $headers[]=$line; return strlen($line); }]);
    if ($cookie!==null) { curl_setopt($curl,CURLOPT_COOKIE,'__Host-hostider_admin='.$cookie); }
    $ok=curl_exec($curl)!==false; $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    $cookies=array_values(array_filter($headers,fn(string $line): bool => str_starts_with(strtolower($line),'set-cookie:')));
    return [$ok,$status,$cookies];
}
function cookieValue(string $header): string {
    if (!preg_match('/__Host-hostider_admin=([^;]+)/',$header,$match)) { throw new RuntimeException('Missing test cookie'); }
    return $match[1];
}
test('F01-SESSION HTTP reads and rejected stale SIDs never overwrite browser cookie', function (): void {
    $root=dirname(__DIR__); $dir=$root.'/storage/session-http-'.bin2hex(random_bytes(8)); mkdir($dir,0700,true);
    $env=array_merge(getenv(),['APP_ENV'=>'test','SESSION_TEST_DIR'=>$dir]); $pipes=[]; $port=33319;
    $p=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,__DIR__.'/session-http-router.php'],
        [0=>['pipe','r'],1=>['file',$root.'/storage/session-http.stdout','a'],2=>['file',$root.'/storage/session-http.stderr','a']],$pipes,$root,$env);
    if (!is_resource($p)) { throw new RuntimeException('Cannot launch test server'); }
    fclose($pipes[0]);
    try {
        $initial=null;
        for ($n=0;$n<50;++$n) { $initial=sessionHttp($port,'/read'); if ($initial[0]) { break; } usleep(100000); }
        same(200,$initial[1]); same(1,count($initial[2])); $old=cookieValue($initial[2][0]);
        foreach (['secure','httponly','samesite=strict'] as $flag) { same(true,str_contains(strtolower($initial[2][0]),$flag)); }
        $read=sessionHttp($port,'/read',$old); same(200,$read[1]); same([],$read[2]);
        $rotated=sessionHttp($port,'/rotate',$old); same(200,$rotated[1]); same(1,count($rotated[2]));
        $new=cookieValue($rotated[2][0]); same(false,$old===$new);
        $stale=sessionHttp($port,'/read',$old); same(401,$stale[1]); same([],$stale[2]);
        $unknown=sessionHttp($port,'/read',str_repeat('x',26)); same(401,$unknown[1]); same([],$unknown[2]);
        same(200,sessionHttp($port,'/read',$new)[1]);
        same(200,sessionHttp($port,'/end',$new)[1]);
        $afterLogout=sessionHttp($port,'/read',$new); same(401,$afterLogout[1]); same([],$afterLogout[2]);
    } finally {
        proc_terminate($p); proc_close($p);
        foreach (glob($dir.'/*') as $file) { unlink($file); } rmdir($dir);
    }
});
runTests();
