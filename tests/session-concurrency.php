<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
use Hostider\Core\Security\{Session, SessionGuard, SessionSnapshot};
use Hostider\Core\Http\HttpError;
ob_start(); $_SERVER['HTTPS']='on';

function sessionFixture(Closure $work): void {
    $directory=dirname(__DIR__).'/storage/session-test-'.bin2hex(random_bytes(8)); mkdir($directory,0700,true);
    session_save_path($directory); $_COOKIE=[];
    Session::start('admin'); $sid=session_id();
    $fixture=['directory'=>$directory,'sid'=>$sid]; $file=$directory.'/fixture.json';
    file_put_contents($file,json_encode($fixture,JSON_THROW_ON_ERROR));
    try { $work($fixture,$file); }
    finally { foreach (glob($directory.'/*') as $path) { unlink($path); } rmdir($directory); $_COOKIE=[]; }
}
function sessionChild(string $file,string $mode,string $slot='0'): array {
    $pipes=[]; $p=proc_open([PHP_BINARY,__DIR__.'/session-child.php',$file,$mode,$slot],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($p)) { throw new RuntimeException('Cannot launch session child'); }
    fclose($pipes[0]); return [$p,$pipes];
}
function childResult(array $child): string {
    [$p,$pipes]=$child; $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    same(0,proc_close($p)); same('',$err); return $out;
}
function waitSignal(string $path): void {
    $deadline=hrtime(true)+4_000_000_000;
    while (!file_exists($path)) {
        if (hrtime(true)>$deadline) { throw new RuntimeException('Session barrier unavailable'); }
        usleep(10000);
    }
}
test('F01-SESSION slow request releases lock before concurrent request finishes', function (): void {
    sessionFixture(function (array $f,string $file): void {
        $holder=sessionChild($file,'hold'); waitSignal($f['directory'].'/ready-0');
        try {
            $writer=sessionChild($file,'increment'); same('OK',childResult($writer));
            // Writer completed while the first request is still waiting at the barrier.
            same(false,file_exists($f['directory'].'/release')); same(20,Session::start('admin')->data['count']);
        } finally { file_put_contents($f['directory'].'/release','go'); childResult($holder); }
    });
});
test('F01-SESSION parallel mutations serialize without losing increments', function (): void {
    sessionFixture(function (array $f,string $file): void {
        $one=sessionChild($file,'increment'); $two=sessionChild($file,'increment');
        same('OK',childResult($one)); same('OK',childResult($two)); same(40,Session::start('admin')->data['count']);
    });
});
test('F01-SESSION stale concurrent replacement gets 409', function (): void {
    sessionFixture(function (array $f,string $file): void {
        $one=sessionChild($file,'stale','1'); $two=sessionChild($file,'stale','2');
        waitSignal($f['directory'].'/ready-1'); waitSignal($f['directory'].'/ready-2');
        file_put_contents($f['directory'].'/release','go'); $results=[childResult($one),childResult($two)]; sort($results);
        same(['409','OK'],$results); same(1,Session::start('admin')->data['count']);
    });
});
foreach (['rotation','logout'] as $boundary) {
    test('F01-SESSION in-flight old request cannot resurrect after '.$boundary, function () use ($boundary): void {
        sessionFixture(function (array $f,string $file) use ($boundary): void {
            Session::mutate('admin',fn(array $d): array => ['principal'=>'fixture']);
            $stale=sessionChild($file,'stale'); waitSignal($f['directory'].'/ready-0');
            if ($boundary==='rotation') { Session::rotate('admin'); } else { Session::end('admin'); }
            file_put_contents($f['directory'].'/release','go'); same('401',childResult($stale));
            if ($boundary==='rotation') {
                same('fixture',Session::start('admin')->data['principal']);
                same(false,$f['sid']===session_id());
            } else { same([],Session::start('admin')->data); }
        });
    });
}
test('F01-SESSION failed mutation aborts and revocation hook denies subsequent write', function (): void {
    sessionFixture(function (): void {
        $initial=Session::start('admin');
        raises(RuntimeException::class,fn()=>Session::mutate('admin',fn(array $d)=>throw new RuntimeException('abort')));
        same($initial->version,Session::start('admin')->version);
        $guard=new class implements SessionGuard {
            public function assertActive(SessionSnapshot $session): void { throw new HttpError(401,'SESSION_REVOKED'); }
        };
        raises(HttpError::class,fn()=>Session::start('admin',$guard));
        raises(HttpError::class,fn()=>Session::mutate('admin',fn(array $d): array => ['principal'=>'bad'],guard:$guard));
        same([],Session::start('admin')->data); same(PHP_SESSION_NONE,session_status());
    });
});
test('F01-SESSION client SID is rejected in admin context', function (): void {
    sessionFixture(function (): void {
        Session::start('client'); $clientSid=session_id();
        $_COOKIE['__Host-hostider_admin']=$clientSid;
        raises(HttpError::class,fn()=>Session::start('admin'));
        same([],Session::start('client')->data);
    });
});
runTests();
