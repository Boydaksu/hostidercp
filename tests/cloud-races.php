<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/cloud-fixture.php';
use Hostider\Core\{Id,CanonicalJson};use Hostider\Core\Database\{Migrator,Database};use Hostider\Core\Http\HttpError;use Hostider\Cloud\{Worker,EventSink};use Hostider\Providers\Mock\DurableVoyar;
$db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function cloudChildren(array $f,array $args,int $count,array $extra=[]): array
{
    $start=hrtime(true);$children=[];
    for($n=0;$n<$count;++$n){$pipes=[];$p=proc_open(array_merge([PHP_BINARY,__DIR__.'/cloud-child.php'],$args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,array_replace(cloudEnv($f),$extra));if(!is_resource($p)){throw new RuntimeException('Child unavailable');}fclose($pipes[0]);$children[]=[$p,$pipes];}
    $results=[];foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($p));same('',$err);$results[]=json_decode($out,true,32,JSON_THROW_ON_ERROR);}
    return ['results'=>$results,'elapsed_us'=>intdiv(hrtime(true)-$start,1000)];
}
test('F4-D 1 4 8 PHP workers create one VM and apply one task completion',function()use($db): void{
    $measurements=[];foreach([1,4,8] as $count){$f=cloudFixture($db);$enqueued=cloudChildren($f,['enqueue',$f['instance'],$f['service']['id'],$f['customer']],$count);$ids=array_column($enqueued['results'],'operation');same(1,count(array_unique($ids)));$op=$ids[0];same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_operations WHERE instance_id=?',[$f['instance']])->fetchColumn());$result=cloudChildren($f,[$op],$count);$w=new Worker($db,$f['config']);for($n=0;$n<2;++$n){$w->once($op);}
        same('SUCCEEDED',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_resources WHERE create_operation_id=?',[$op])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_effects WHERE operation_id=?',[$op])->fetchColumn());
        $task=$db->execute('SELECT task_id FROM cloud_operations WHERE id=?',[$op])->fetchColumn();$account=$f['engine']->store->instance($f['instance'],false)['account_id'];$provider=new DurableVoyar($db,$f['config'],$account);$v=$provider->observe($op)['resource']['version'];$provider->advance($task);$provider->advance($task);same($v,$provider->observe($op)['resource']['version']);
        $measurements[]=['workers'=>$count,'instances'=>1,'wall_us'=>$result['elapsed_us'],'physical_create'=>1,'applied_effect'=>1];}
    echo 'MEASURE '.json_encode($measurements,JSON_THROW_ON_ERROR).PHP_EOL;
});
test('F4-D durable send permission remains consumed in eight new PHP processes',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);$c=$f['engine']->claim($op);$f['engine']->fence($c)->send(fn()=>null);
    $r=cloudChildren($f,['fence'],8,['SYNTHETIC_CLOUD_CLAIM'=>CanonicalJson::encode($c)]);foreach($r['results'] as $result){same(false,$result['admitted']);}
    same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_dispatches WHERE operation_id=?',[$op])->fetchColumn());same(0,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());$f['engine']->lost($c);
});
test('F4-D SKIP LOCKED and rollback keep operation and hold records consistent',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);$other=Database::connect($f['config']);$other->pdo->beginTransaction();
    try{$other->execute('SELECT id FROM cloud_operations WHERE id=? FOR UPDATE',[$op]);same(null,$f['engine']->claim($op));}finally{$other->pdo->rollBack();}
    $db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$f['order']['payment_request_id']]);
    $db->execute("CREATE TRIGGER synthetic_cloud_rollback BEFORE UPDATE ON cloud_operations FOR EACH ROW BEGIN IF NEW.id='".$op."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic rollback'; END IF; END");
    try{raises(PDOException::class,fn()=>$f['engine']->claim($op));}finally{$db->execute('DROP TRIGGER synthetic_cloud_rollback');}
    same(null,$db->execute('SELECT runner_checked_at FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same(null,$f['engine']->claim($op));same('CLOUD_SOURCE_CHANGED',$db->execute('SELECT safe_code FROM cloud_operations WHERE id=?',[$op])->fetchColumn());
});
test('F4-D polling crash expired lease fences late task completion then reconciles once',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);(new Worker($db,$f['config']))->once($op);$c=$f['engine']->claim($op);$p=new DurableVoyar($db,$f['config'],$c['account_id']);$p->advance($c['task_id']);$obs=$f['engine']->observe($op);
    $db->execute('UPDATE cloud_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$op]);$f['engine']->expire();raises(HttpError::class,fn()=>$f['engine']->finish($c,$obs));
    $fresh=$f['engine']->observe($op,$f['actor']);$confirm=$f['engine']->prepare($f['instance'],'RESOLVE',['observation_id'=>$fresh],$f['actor']);$f['engine']->resolve($op,$fresh,$confirm,$f['actor']);same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_effects WHERE operation_id=?',[$op])->fetchColumn());
});
test('F4-D confirmation payload version actor freshness and observation fences reject reuse',function()use($db): void{
    $f=cloudFixture($db);activateCloud($db,$f);$c=$f['engine']->prepare($f['instance'],'REINSTALL',['osid'=>1],$f['actor']);raises(HttpError::class,fn()=>$f['engine']->enqueue($f['instance'],'REINSTALL',['osid'=>2],$f['actor'],$c));
    $db->execute('UPDATE cloud_instances SET version=version+1 WHERE id=?',[$f['instance']]);raises(HttpError::class,fn()=>$f['engine']->enqueue($f['instance'],'REINSTALL',['osid'=>1],$f['actor'],$c));
    $expired=new \Hostider\Core\Security\Principal('cloud-staff',['services.manage'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time()-301,grants:$f['actor']->grants);raises(HttpError::class,fn()=>$f['engine']->prepare($f['instance'],'STOP',[],$expired));
});
test('F4-D bounded cron progresses past 101 blocked canonical units without replay',function()use($db): void{
    $f=cloudFixture($db,false,101);
    for($unit=1;$unit<=101;++$unit){$service=$unit===1?$f['service']:$db->transaction(fn()=>$f['repository']->materialize($f['order'],$f['item'],$f['line'],$unit,$f['category'],$f['actor'],Id::uuid()));
        $actor=new \Hostider\Core\Security\Principal('cloud-unit',['services.manage'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time(),grants:[new \Hostider\Core\Security\AccessGrant('services.manage',[$f['customer']],[$service['id']])]);
        $id=$f['engine']->attach($service['id'],$f['customer'],$f['catalog'],$f['spec'],$actor);$f['engine']->enqueue($id,'CREATE',[],$actor);
    }
    $db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$f['order']['payment_request_id']]);
    $healthy=cloudFixture($db);$op=$healthy['engine']->enqueue($healthy['instance'],'CREATE',[],$healthy['actor']);$w=new Worker($db,$f['config']);same(false,$w->once());same(true,$w->once());same(true,$w->once($op));
    same('SUCCEEDED',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());
    same(0,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls c JOIN cloud_operations o ON o.id=c.operation_id JOIN cloud_instances i ON i.id=o.instance_id WHERE i.catalog_id=?',[$f['catalog']])->fetchColumn());
});
test('F4-D real CLI and bounded outbox queue execute once without leaking payloads',function()use($db): void{
    $db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);same(true,domainCli($f,['outbox:dispatch'])['dispatched']>0);
    for($n=0;$n<100;++$n){$r=domainCli($f,['queue:work','--once']);if(!$r['worked']){break;}}
    same('SUCCEEDED',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same('SUCCEEDED',(new EventSink($db,$f['config']))->consume(['operation_id'=>$op]));same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());
    same(false,domainCli($f,['cloud:work','--once'])['worked']);
});
runTests();
