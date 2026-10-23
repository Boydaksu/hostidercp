<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/services-fixture.php';require __DIR__.'/support/FixtureDirectory.php';
use Hostider\Core\Database\Migrator;use Hostider\Core\Id;use Hostider\Core\Http\HttpError;use Hostider\Services\Engine;
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function serviceChildren(array $f,string $mode,int $count=4): array
{
    $directory=dirname(__DIR__).'/storage/service-test-'.bin2hex(random_bytes(8));mkdir($directory,0700,true);$file=$directory.'/fixture.json';
    file_put_contents($file,json_encode(['key'=>$f['config']->key,'order'=>$f['order']['id'],'operation'=>$f['operation']??null],JSON_THROW_ON_ERROR));$children=[];$results=[];
    try{
        for($i=0;$i<$count;++$i){$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/services-child.php',$file,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process)){throw new RuntimeException('Child unavailable');}fclose($pipes[0]);$children[]=[$process,$pipes];}
        foreach($children as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($process));same('',$err);$results[]=json_decode($out,true,32,JSON_THROW_ON_ERROR);}
    }finally{foreach($children as [$process,$pipes]){if(is_resource($process)){proc_terminate($process);foreach([1,2] as $fd){if(is_resource($pipes[$fd])){fclose($pipes[$fd]);}}proc_close($process);}}FixtureDirectory::remove($directory);}
    return $results;
}
test('F3-B independent overlapping discovery creates one service and one operation per unit',function()use($db): void{
    $f=serviceFixture($db,3);$results=serviceChildren($f,'discover');foreach($results as $result){same(3,count($result));$sorted=$result;sort($sorted);$first=$results[0];sort($first);same($first,$sorted);}
    same(3,(int)$db->execute('SELECT COUNT(*) FROM svc_operations WHERE order_item_id=?',[$f['item']['id']])->fetchColumn());same(3,(int)$db->execute('SELECT COUNT(*) FROM svc_provider_operations p JOIN svc_operations o ON o.id=p.lifecycle_operation_id WHERE o.order_item_id=?',[$f['item']['id']])->fetchColumn());
});
test('F3-B queue loss does not lose canonical work and four workers obtain a single fence',function()use($db): void{
    $f=serviceFixture($db);$engine=new Engine($db,$f['config']);$ids=$engine->discover($f['order']['id'],$f['actor'],Id::uuid());$f['operation']=$db->execute('SELECT current_operation_id FROM svc_services WHERE id=?',[$ids[0]])->fetchColumn();
    $db->execute("DELETE FROM outbox_events WHERE event_type='services.execute' AND aggregate_id=?",[$f['operation']]);$results=serviceChildren($f,'claim');$claims=array_values(array_filter($results));same(1,count($claims));same('RUNNING',$claims[0]['status']);same(false,$db->pdo->inTransaction());same('PROVISIONING',$engine->repository->find($ids[0],lock:false)['status']);
    $engine->markUnknown($f['operation'],$claims[0]['lease_token'],$f['actor'],Id::uuid());same(null,$engine->claim($f['actor'],Id::uuid(),$f['operation']));same($ids,$engine->discover($f['order']['id'],$f['actor'],Id::uuid()));same(1,(int)$db->execute('SELECT COUNT(*) FROM svc_operations WHERE order_item_id=?',[$f['item']['id']])->fetchColumn());
});
test('F3-B expired lease becomes UNKNOWN and rejects stale completion without retry',function()use($db): void{
    $f=serviceFixture($db);$engine=new Engine($db,$f['config']);$ids=$engine->discover($f['order']['id'],$f['actor'],Id::uuid());$id=$engine->repository->find($ids[0],lock:false)['current_operation_id'];$claim=$engine->claim($f['actor'],Id::uuid(),$id);$db->execute('UPDATE svc_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$id]);same(1,$engine->expire($f['actor'],Id::uuid(),$id));same('UNKNOWN',$engine->repository->find($ids[0],lock:false)['status']);same(409,raises(HttpError::class,fn()=>$engine->markUnknown($id,$claim['lease_token'],$f['actor'],Id::uuid()))->status);same(null,$engine->claim($f['actor'],Id::uuid(),$id));raises(PDOException::class,fn()=>$db->execute("UPDATE svc_operations SET status='PENDING' WHERE id=?",[$id]));raises(PDOException::class,fn()=>$db->execute('DELETE FROM svc_attempt_events WHERE operation_id=?',[$id]));
});
test('F3-B manual payment and source changes fail closed before dispatch',function()use($db): void{
    $f=serviceFixture($db,1,true);$engine=new Engine($db,$f['config']);$ids=$engine->discover($f['order']['id'],$f['actor'],Id::uuid());same('PENDING_APPROVAL',$engine->repository->find($ids[0],lock:false)['status']);same(0,(int)$db->execute('SELECT COUNT(*) FROM svc_operations WHERE order_item_id=?',[$f['item']['id']])->fetchColumn());
    $f=serviceFixture($db);$engine=new Engine($db,$f['config']);$ids=$engine->discover($f['order']['id'],$f['actor'],Id::uuid());$id=$engine->repository->find($ids[0],lock:false)['current_operation_id'];$db->execute("UPDATE orders SET status='CANCELLED' WHERE id=?",[$f['order']['id']]);same(409,raises(HttpError::class,fn()=>$engine->claim($f['actor'],Id::uuid(),$id))->status);same('PENDING',$db->execute('SELECT status FROM svc_operations WHERE id=?',[$id])->fetchColumn());
});
runTests();
