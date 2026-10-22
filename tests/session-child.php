<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\Security\Session;
use Hostider\Core\Http\HttpError;
$fixture=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);
session_save_path($fixture['directory']);
$_SERVER['HTTPS']='on'; $_COOKIE['__Host-hostider_admin']=$fixture['sid'];
$mode=$argv[2]; $slot=$argv[3] ?? '0';
function signalWait(array $fixture,string $slot): void {
    file_put_contents($fixture['directory'].'/ready-'.$slot,'ready');
    $deadline=hrtime(true)+5_000_000_000;
    while (!file_exists($fixture['directory'].'/release')) {
        if (hrtime(true)>$deadline) { throw new RuntimeException('Session concurrency barrier timed out'); }
        usleep(10000);
    }
}
try {
    if ($mode==='hold') {
        Session::start('admin'); signalWait($fixture,$slot);
    } elseif ($mode==='increment') {
        for ($i=0;$i<20;++$i) { Session::mutate('admin',fn(array $d): array => ['count'=>($d['count'] ?? 0)+1]); }
    } elseif ($mode==='stale') {
        $snapshot=Session::start('admin'); signalWait($fixture,$slot);
        Session::mutate('admin',fn(array $d): array => ['count'=>($d['count'] ?? 0)+1],$snapshot->version);
    }
    echo 'OK';
} catch (HttpError $e) { echo (string)$e->status; }
