<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';
require __DIR__ . '/database.php';
use Hostider\Core\Database\Migrator;
use Hostider\Core\Queue\{Queue, Outbox, Worker};
use Hostider\Core\{Id, Idempotency};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Audit, Principal, RateLimiter};
$db = integrationDb();
$migrationDir = dirname(__DIR__) . '/migrations';

test('MariaDB migrations apply/replay and expected table/JSON/status constraints', function () use ($db,$migrationDir): void {
    $result = (new Migrator($db,$migrationDir))->migrate();
    same([], (new Migrator($db,$migrationDir))->migrate());
    same('APPLIED',$db->execute('SELECT status FROM schema_migrations')->fetchColumn());
    raises(PDOException::class, fn() => $db->execute("INSERT INTO jobs(job_type,payload,status,dedupe_key,correlation_id) VALUES ('bad','{}','ACTIVE','bad',?)", [Id::uuid()]));
    raises(PDOException::class, fn() => $db->execute("INSERT INTO jobs(job_type,payload,dedupe_key,correlation_id) VALUES ('bad','not json','bad',?)", [Id::uuid()]));
});
test('migration checksum/missing file/incomplete DDL stop execution', function () use ($db,$migrationDir): void {
    $db->execute("UPDATE schema_migrations SET checksum=REPEAT('0',64)");
    raises(RuntimeException::class, fn() => (new Migrator($db,$migrationDir))->migrate());
    $db->execute('UPDATE schema_migrations SET checksum=?',[hash_file('sha256',$migrationDir.'/001_f0_infrastructure.php')]);
    $db->execute("UPDATE schema_migrations SET status='APPLYING'");
    raises(RuntimeException::class, fn() => (new Migrator($db,$migrationDir))->migrate());
    $db->execute("UPDATE schema_migrations SET status='APPLIED'");
    $db->execute("INSERT INTO schema_migrations VALUES ('000_missing.php',REPEAT('a',64),'APPLIED',UTC_TIMESTAMP(6))");
    raises(RuntimeException::class, fn() => (new Migrator($db,$migrationDir))->migrate());
    $db->execute("DELETE FROM schema_migrations WHERE version='000_missing.php'");
});
test('migration advisory lock excludes concurrent deployer', function () use ($db,$migrationDir): void {
    $other = integrationDb(); $other->execute("SELECT GET_LOCK('hostider_schema',0)");
    try { raises(RuntimeException::class, fn() => (new Migrator($db,$migrationDir))->migrate()); }
    finally { $other->execute("SELECT RELEASE_LOCK('hostider_schema')"); }
});
test('actual partial DDL remains APPLYING and blocks runner/readiness', function () use ($db,$migrationDir): void {
    $dir=dirname(__DIR__).'/storage/migration-failure';
    if (!is_dir($dir)) { mkdir($dir,0700,true); }
    copy($migrationDir.'/001_f0_infrastructure.php',$dir.'/001_f0_infrastructure.php');
    file_put_contents($dir.'/002_failure.php',"<?php return ['CREATE TABLE f0_partial_probe (id INT PRIMARY KEY) ENGINE=InnoDB', 'INVALID SQL'];");
    try {
        raises(PDOException::class,fn()=>(new Migrator($db,$dir))->migrate());
        same('APPLYING',$db->execute("SELECT status FROM schema_migrations WHERE version='002_failure.php'")->fetchColumn());
        same(0,(int)$db->execute('SELECT COUNT(*) FROM f0_partial_probe')->fetchColumn());
        raises(RuntimeException::class,fn()=>(new Migrator($db,$dir))->migrate());
        raises(RuntimeException::class,fn()=>(new Migrator($db,$dir))->assertReady());
    } finally {
        // Explicit manual recovery of a disposable, prefix-guarded test database.
        $db->execute('DROP TABLE IF EXISTS f0_partial_probe');
        $db->execute("DELETE FROM schema_migrations WHERE version='002_failure.php'");
        unlink($dir.'/002_failure.php'); unlink($dir.'/001_f0_infrastructure.php'); rmdir($dir);
    }
    (new Migrator($db,$migrationDir))->assertReady();
});
test('migration out-of-order insertion and pending readiness fail closed', function () use ($db,$migrationDir): void {
    $dir=dirname(__DIR__).'/storage/migration-order';
    if (!is_dir($dir)) { mkdir($dir,0700,true); }
    copy($migrationDir.'/001_f0_infrastructure.php',$dir.'/001_f0_infrastructure.php');
    file_put_contents($dir.'/000_earlier.php',"<?php return ['SELECT 1'];");
    try {
        raises(RuntimeException::class,fn()=>(new Migrator($db,$dir))->migrate());
        raises(RuntimeException::class,fn()=>(new Migrator($db,$dir))->assertReady());
    } finally { unlink($dir.'/000_earlier.php'); unlink($dir.'/001_f0_infrastructure.php'); rmdir($dir); }
});
test('business write and outbox rollback/commit together', function () use ($db): void {
    cleanJobs($db); $q = new Queue($db); $out = new Outbox($db,$q);
    raises(LogicException::class, fn() => $out->append(Id::uuid(),'core.probe','probe',[],Id::uuid()));
    raises(RuntimeException::class, fn() => $db->transaction(function () use ($db,$out): void {
        $db->execute("INSERT INTO inbox_events (consumer,event_uuid) VALUES ('business',?)", [Id::uuid()]);
        $out->append(Id::uuid(),'core.probe','probe',[],Id::uuid());
        throw new RuntimeException('rollback');
    }));
    same(0,(int)$db->execute('SELECT COUNT(*) FROM inbox_events')->fetchColumn());
    same(0,(int)$db->execute('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    $db->transaction(fn() => $out->append(Id::uuid(),'core.probe','probe',[],Id::uuid()));
    same(true,$out->dispatchOne()); same(false,$out->dispatchOne());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM jobs')->fetchColumn());
});
test('idempotency replay, actor/scope isolation, conflict and rollback', function () use ($db): void {
    cleanJobs($db); $i = new Idempotency($db); $count = 0;
    $work = function () use (&$count): array { ++$count; return ['status'=>'ok']; };
    same(['status'=>'ok'],$i->run('a','create','request_key_123456',['b'=>1,'a'=>2],$work));
    same(['status'=>'ok'],$i->run('a','create','request_key_123456',['a'=>2,'b'=>1],$work)); same(1,$count);
    same(409,raises(HttpError::class,fn()=>$i->run('a','create','request_key_123456',['a'=>3],$work))->status);
    $i->run('b','create','request_key_123456',['a'=>3],$work); same(2,$count);
    raises(RuntimeException::class,fn()=>$i->run('a','failure','request_key_123456',[],fn()=>throw new RuntimeException('failure')));
    same(['status'=>'ok'],$i->run('a','failure','request_key_123456',[],$work));
});
test('two independent PHP processes cannot execute same idempotency work twice', function () use ($db): void {
    cleanJobs($db); $results = parallelChildren('idempotency');
    foreach ($results as $r) { same(['ok'=>true],$r); }
    same(1,(int)$db->execute("SELECT COUNT(*) FROM inbox_events WHERE consumer='parallel-work'")->fetchColumn());
});
test('two independent worker processes claim exactly one lease', function () use ($db): void {
    cleanJobs($db); $q = new Queue($db);
    $db->transaction(fn() => $q->enqueue('core.probe',[],'single',Id::uuid()));
    $results = parallelChildren('claim');
    same(1,count(array_filter($results))); same(1,(int)$db->execute('SELECT COUNT(*) FROM job_attempts')->fetchColumn());
});
test('expired unsafe lease becomes UNKNOWN and stale worker is fenced', function () use ($db): void {
    cleanJobs($db); $q = new Queue($db);
    $db->transaction(fn() => $q->enqueue('provider.create',[],'unsafe',Id::uuid()));
    $job = $q->claim(); $db->execute('UPDATE jobs SET leased_until=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6))');
    same(null,$q->claim()); same('UNKNOWN',$db->execute('SELECT status FROM jobs')->fetchColumn());
    raises(RuntimeException::class,fn()=>$q->finish($job,'SUCCEEDED'));
    same(null,$q->claim());
});
test('expired safe lease retries with different fencing token', function () use ($db): void {
    cleanJobs($db); $q = new Queue($db);
    $db->transaction(fn()=>$q->enqueue('local.safe',[],'safe',Id::uuid(),true));
    $old = $q->claim(); $db->execute('UPDATE jobs SET leased_until=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6))');
    $new = $q->claim(); same(false,$old['lease_token']===$new['lease_token']);
    raises(RuntimeException::class,fn()=>$q->finish($old,'SUCCEEDED')); $q->finish($new,'SUCCEEDED');
    same('SUCCEEDED',$db->execute('SELECT status FROM jobs')->fetchColumn());
});
test('safe bounded retry/backoff ends in dead letter', function () use ($db): void {
    cleanJobs($db); $q = new Queue($db);
    $db->transaction(fn()=>$q->enqueue('local.safe',[],'safe',Id::uuid(),true));
    for ($n=1;$n<=3;++$n) {
        $job=$q->claim(); $q->finish($job,'FAILED');
        same(null,$q->claim());
        $db->execute('UPDATE jobs SET run_after=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6))');
    }
    same('FAILED',$db->execute('SELECT status FROM jobs')->fetchColumn());
    same(1,(int)$db->execute('SELECT COUNT(*) FROM dead_letters')->fetchColumn());
});
test('unsafe handler exception is UNKNOWN with no second execution', function () use ($db): void {
    cleanJobs($db); $q = new Queue($db); $w = new Worker($db,$q); $calls = 0;
    $w->register('provider.create',function () use (&$calls): never { ++$calls; throw new RuntimeException('timeout secret'); });
    $db->transaction(fn()=>$q->enqueue('provider.create',[],'unsafe',Id::uuid()));
    same(true,$w->once()); same(false,$w->once()); same(1,$calls);
    same('UNKNOWN',$db->execute('SELECT status FROM jobs')->fetchColumn());
    same('UNKNOWN',$db->execute('SELECT last_error_safe FROM jobs')->fetchColumn());
});
test('unregistered handler fails closed and worker refuses open transaction', function () use ($db): void {
    cleanJobs($db); $q = new Queue($db); $w = new Worker($db,$q);
    $db->transaction(fn()=>$q->enqueue('unknown',[],'missing',Id::uuid())); same(true,$w->once());
    same('FAILED',$db->execute('SELECT status FROM jobs')->fetchColumn());
    raises(LogicException::class,fn()=>$db->transaction(fn()=>$w->once()));
    raises(LogicException::class,fn()=>$db->transaction(fn()=>$db->transaction(fn()=>null)));
});
test('inbox dedupe and consumer failure rolls back receipt', function () use ($db): void {
    cleanJobs($db); $out = new Outbox($db,new Queue($db)); $id = Id::uuid(); $calls=0;
    $work = function () use (&$calls): void { ++$calls; };
    same(true,$out->consume('consumer',$id,$work)); same(false,$out->consume('consumer',$id,$work)); same(1,$calls);
    $id2=Id::uuid(); raises(RuntimeException::class,fn()=>$out->consume('consumer',$id2,fn()=>throw new RuntimeException('failed')));
    same(true,$out->consume('consumer',$id2,$work));
});
test('queue dedupe rejects different payload without modifying original', function () use ($db): void {
    cleanJobs($db); $q=new Queue($db);
    $db->transaction(fn()=>$q->enqueue('probe',['value'=>1],'dedupe',Id::uuid()));
    raises(LogicException::class,fn()=>$db->transaction(fn()=>$q->enqueue('probe',['value'=>2],'dedupe',Id::uuid())));
    same(1,(int)$db->execute('SELECT COUNT(*) FROM jobs')->fetchColumn());
});
test('rate limiter stores hashed bucket and enforces bound', function () use ($db): void {
    cleanJobs($db); $limiter=new RateLimiter($db,random_bytes(32));
    same(true,$limiter->allow('private@example.invalid',2)); same(true,$limiter->allow('private@example.invalid',2)); same(false,$limiter->allow('private@example.invalid',2));
    same(64,strlen($db->execute('SELECT bucket_hash FROM rate_limits')->fetchColumn()));
});
test('audit keeps original actor/effective customer and refuses free text', function () use ($db): void {
    cleanJobs($db); $audit=new Audit($db); $p=new Principal('staff_1',[],customerId:'customer_2',impersonating:true);
    $db->transaction(fn()=>$audit->record($p,'impersonation.started','session_1',Id::uuid()));
    $row=$db->execute('SELECT * FROM audit_logs')->fetch(); same('staff_1',$row['actor_id']); same('customer_2',$row['effective_customer_id']);
    raises(InvalidArgumentException::class,fn()=>$db->transaction(fn()=>$audit->record($p,'private@example.invalid','session_1',Id::uuid())));
});
function parallelChildren(string $mode): array
{
    $running=[];
    for ($i=0;$i<2;++$i) {
        $pipes=[];
        $process=proc_open([PHP_BINARY,__DIR__.'/child.php',$mode], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot spawn concurrency test'); }
        fclose($pipes[0]); $running[]=[$process,$pipes];
    }
    $results=[];
    foreach ($running as [$process,$pipes]) {
        $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        same(0,proc_close($process)); same('',$err); $results[]=json_decode($out,true,32,JSON_THROW_ON_ERROR);
    }
    return $results;
}
test('F01-QUEUE reordered nested payload reuses one job including legacy JSON', function () use ($db): void {
    cleanJobs($db); $q=new Queue($db);
    $db->transaction(fn()=>$q->enqueue('probe',['b'=>['y'=>1,'x'=>2],'a'=>3],'order',Id::uuid()));
    // Existing F0 rows need no rewrite or migration to compare canonically.
    $db->execute('UPDATE jobs SET payload=?',['{"b":{"y":1,"x":2},"a":3}']);
    $db->transaction(fn()=>$q->enqueue('probe',['a'=>3,'b'=>['x'=>2,'y'=>1]],'order',Id::uuid()));
    same(1,(int)$db->execute('SELECT COUNT(*) FROM jobs')->fetchColumn());
});
test('F01-QUEUE scalar list object and operation differences remain conflicts', function () use ($db): void {
    foreach ([[['x'=>1],['x'=>'1']], [['x'=>[1,2]],['x'=>[2,1]]], [['x'=>new stdClass()],['x'=>[]]], [['x'=>true],['x'=>1]]] as [$first,$second]) {
        cleanJobs($db); $q=new Queue($db);
        $db->transaction(fn()=>$q->enqueue('probe',$first,'types',Id::uuid()));
        raises(LogicException::class,fn()=>$db->transaction(fn()=>$q->enqueue('probe',$second,'types',Id::uuid())));
        raises(LogicException::class,fn()=>$db->transaction(fn()=>$q->enqueue('different',$first,'types',Id::uuid())));
        raises(LogicException::class,fn()=>$db->transaction(fn()=>$q->enqueue('probe',$first,'types',Id::uuid(),true)));
    }
});
runTests();
