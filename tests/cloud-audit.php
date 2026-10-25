<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/cloud-fixture.php';
use Hostider\Core\{Id,CanonicalJson};use Hostider\Core\Database\Migrator;use Hostider\Core\Http\HttpError;use Hostider\Cloud\{Worker,Catalog,Containment,EventSink};use Hostider\Providers\Mock\DurableVoyar;
$db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function safetyUser($db,array $f,bool $grant=true): array
{
    static $admin=null;
    $identity=new \Hostider\Identity\IdentityService($db,$f['config']);
    if($admin===null){$password=bin2hex(random_bytes(20));$email='safety-admin@example.invalid';$user=$identity->bootstrap($email,'Synthetic safety admin',$password);$reference=Id::uuid();$identity->login($email,$password,'admin',$reference,Id::uuid());$factor=$identity->enroll($reference,'admin',$password);$codes=$identity->confirmEnrollment($reference,'admin',\Hostider\Identity\Totp::code($factor['secret'],intdiv(time(),30)))['recovery_codes'];$identity->stepUp($reference,'admin',$password,array_pop($codes));$admin=compact('user','reference','password','codes');}
    if($grant){$assignment=$db->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL',[$admin['user']])->fetchColumn();$db->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope,service_scope) VALUES (?,?,?,?,?)',[Id::uuid(),$assignment,'services.manage',$f['customer'],$f['service']['id']]);}
    return $admin;
}
function refundedCloud($db): array{$f=cloudFixture($db);activateCloud($db,$f);$db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$f['order']['payment_request_id']]);$db->transaction(fn()=>(new \Hostider\Services\CloudServices($db,$f['config']))->state($f['service']['id'],'SUSPENDED'));return $f;}
function financeHash($db): string{$data=[];foreach(['finance_journals','finance_payments','billing_requests','billing_request_states'] as $table){$data[$table]=$db->execute('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();}return hash('sha256',CanonicalJson::encode($data));}
test('P0-1 catalog capability never grants a higher PID or another product offer',function()use($db): void{
    $f=cloudFixture($db,false);raises(HttpError::class,fn()=>$f['engine']->attach($f['service']['id'],$f['customer'],$f['catalog'],array_replace($f['spec'],['pid'=>2]),$f['actor']));
    $other=cloudFixture($db,false);raises(HttpError::class,fn()=>$f['engine']->attach($f['service']['id'],$f['customer'],$other['catalog'],$other['spec'],$f['actor']));
    $f['create']('price-publish',['item_id'=>$f['product'],'cycle_id'=>$f['cycle'],'currency'=>'USD','amount'=>'10']);raises(HttpError::class,fn()=>$f['quotes']->plan($f['customer'],new \Hostider\Finance\Currency('USD'),[['product_id'=>$f['product'],'cycle_id'=>$f['cycle'],'quantity'=>1]],[],$f['now']));
    foreach(['osid'=>2,'locationid'=>2] as $k=>$v){raises(HttpError::class,fn()=>$f['engine']->attach($f['service']['id'],$f['customer'],$f['catalog'],array_replace($f['spec'],[$k=>$v]),$f['actor']));}
});
test('P0-1 exact purchased custom CPU RAM disk and IPv4 cannot be expanded',function()use($db): void{
    $policy=['pids'=>[1,2],'osids'=>[1],'locations'=>[1],'actions'=>['CREATE','STOP','TERMINATE'],'custom'=>true,'custom_limits'=>['group_id'=>1,'cpu_min'=>2,'cpu_max'=>8,'cpu_step'=>2,'ram_min'=>2,'ram_max'=>32,'ram_per_core_max'=>4,'disk_min'=>10,'disk_max'=>400,'disk_per_ram_max'=>20,'ipv4_max'=>2],'free_upgrade_targets'=>[],'termination_wait_seconds'=>1];
    $spec=['custom_package'=>['product_group_id'=>1,'cpu_cores'=>2,'ram_gb'=>4,'disk_gb'=>40,'ipv4_count'=>1],'osid'=>1,'locationid'=>1,'type'=>'ondemand'];$f=cloudFixture($db,false,1,$spec,$policy);
    foreach(['cpu_cores'=>4,'ram_gb'=>8,'disk_gb'=>60,'ipv4_count'=>2] as $key=>$value){$bad=$spec;$bad['custom_package'][$key]=$value;(new Catalog($db,$f['config']))->validate($policy,$bad);raises(HttpError::class,fn()=>$f['engine']->attach($f['service']['id'],$f['customer'],$f['catalog'],$bad,$f['actor']));}
    $f['instance']=$f['engine']->attach($f['service']['id'],$f['customer'],$f['catalog'],$spec,$f['actor']);activateCloud($db,$f);
});
test('P0-1 entitlement binds canonical customer order item unit and exact immutable price',function()use($db): void{
    $f=cloudFixture($db,true,2);$other=cloudFixture($db,false);
    raises(HttpError::class,fn()=>$f['engine']->attach($f['service']['id'],$other['customer'],$f['catalog'],$f['spec'],$other['actor']));
    $row=$db->execute('SELECT * FROM cloud_purchased_entitlements WHERE instance_id=?',[$f['instance']])->fetch();same($f['item']['id'],$row['order_item_id']);same(1,(int)$row['unit_no']);
    raises(PDOException::class,fn()=>$db->execute('UPDATE cloud_purchased_entitlements SET service_id=?,unit_no=2 WHERE instance_id=?',[$other['service']['id'],$f['instance']]));
    $bundle=$db->transaction(fn()=>(new \Hostider\Services\CloudServices($db,$f['config']))->purchase($f['service']['id'],$f['customer']));same($f['line']['cloud_entitlement'],$bundle['offer']);same('1080',$bundle['total_minor']);
    $borrowed=$bundle;$borrowed['service_id']=$other['service']['id'];raises(HttpError::class,fn()=>$db->transaction(fn()=>(new \Hostider\Cloud\Entitlements($db,$f['config']))->bind($f['engine']->store->instance($f['instance']),$borrowed,$f['spec'])));
    $borrowed=$bundle;$borrowed['unit_no']=2;raises(HttpError::class,fn()=>$db->transaction(fn()=>(new \Hostider\Cloud\Entitlements($db,$f['config']))->bind($f['engine']->store->instance($f['instance']),$borrowed,$f['spec'])));
    activateCloud($db,$f);same('9.0000000000',(string)$db->execute('SELECT amount FROM catalog_prices WHERE id=?',[$bundle['offer']['price_id']])->fetchColumn());
});
test('P0-1 refund cancellation and changed service price refuse CREATE before dispatch',function()use($db): void{
    foreach(['refund','cancel','price'] as $bad){$f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);
        if($bad==='refund'){$db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$f['order']['payment_request_id']]);}
        if($bad==='cancel'){$db->execute("UPDATE orders SET status='CANCELLED' WHERE id=?",[$f['order']['id']]);}
        if($bad==='price'){$other=cloudFixture($db,false);$db->execute('UPDATE svc_services SET current_price_id=? WHERE id=?',[$other['service']['current_price_id'],$f['service']['id']]);}
        raises(HttpError::class,fn()=>$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']));same(null,$f['engine']->claim($op));same(0,(int)$db->execute('SELECT COUNT(*) FROM cloud_dispatches WHERE operation_id=?',[$op])->fetchColumn());
    }
});
test('P0-1 fixed offers and historical price snapshots are immutable and cannot be forged',function()use($db): void{
    $f=cloudFixture($db);$offer=$f['line']['cloud_entitlement'];raises(PDOException::class,fn()=>$db->execute("UPDATE catalog_cloud_fixed_offers SET spec_json='{}' WHERE id=?",[$offer['offer_id']]));raises(PDOException::class,fn()=>$db->execute("UPDATE svc_prices SET snapshot='{}' WHERE id=?",[$f['service']['current_price_id']]));
    $quote=$f['quotes']->plan($f['customer'],new \Hostider\Finance\Currency('USD'),[['product_id'=>$f['product'],'cycle_id'=>$f['cycle'],'quantity'=>1]],[],$f['now'])->jsonSerialize();same($offer,$quote['lines'][0]['cloud_entitlement']);
});
test('P0-2 refunded VM rejects normal CREATE UPGRADE and REINSTALL',function()use($db): void{
    $f=refundedCloud($db);foreach(['CREATE','UPGRADE','REINSTALL'] as $kind){$data=match($kind){'UPGRADE'=>['pid'=>1],'REINSTALL'=>['osid'=>1],default=>[]};raises(HttpError::class,fn()=>$f['engine']->enqueue($f['instance'],$kind,$data,$f['actor']));}
});
test('P0-2 real fresh scoped Identity session can terminate refunded VM without finance writes',function()use($db): void{
    $f=refundedCloud($db);$admin=safetyUser($db,$f);$safety=new Containment($db,$f['config']);$before=financeHash($db);$c=$safety->prepare($f['instance'],'TERMINATE','Refunded resource containment',$admin['reference']);raises(PDOException::class,fn()=>$db->execute('UPDATE cloud_safety_confirmations SET instance_version=instance_version+1 WHERE id=?',[$c]));raises(HttpError::class,fn()=>$safety->execute($f['instance'],$c,$admin['reference'],false));raises(HttpError::class,fn()=>$safety->execute($f['instance'],$c,$admin['reference'],true));usleep(1100000);$op=$safety->execute($f['instance'],$c,$admin['reference'],true);
    $w=new Worker($db,$f['config']);$w->once($op);same('ACTIVE',$f['engine']->view($f['instance'],$f['actor'])['status']);$w->once($op);same('TERMINATED',$f['engine']->view($f['instance'],$f['actor'])['status']);same($before,financeHash($db));same(1,(int)$db->execute('SELECT financial_exception FROM cloud_containments WHERE operation_id=?',[$op])->fetchColumn());
    $raw=$db->execute('SELECT * FROM cloud_containments WHERE operation_id=?',[$op])->fetch();same(false,str_contains(json_encode($raw),'Refunded resource containment'));same(false,str_contains(json_encode($raw),$admin['reference']));
});
test('P0-2 customer unscoped staff stale step-up and unsupported kinds are denied',function()use($db): void{
    $f=refundedCloud($db);$admin=safetyUser($db,$f,false);$safety=new Containment($db,$f['config']);raises(HttpError::class,fn()=>$safety->prepare($f['instance'],'STOP','Emergency operator reason',$admin['reference']));raises(HttpError::class,fn()=>$safety->prepare($f['instance'],'CREATE','Emergency operator reason',$admin['reference']));
    require_once __DIR__.'/commerce-fixture.php';$client=commerceUser(new \Hostider\Commerce\Services($db,$f['config']),'safety-client');raises(HttpError::class,fn()=>$safety->prepare($f['instance'],'STOP','Emergency operator reason',$client['reference']));
    safetyUser($db,$f);$db->execute('UPDATE identity_sessions SET verified_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 301 SECOND) WHERE native_reference=?',[$admin['reference']]);raises(HttpError::class,fn()=>$safety->prepare($f['instance'],'STOP','Emergency operator reason',$admin['reference']));$db->execute('UPDATE identity_sessions SET verified_at=UTC_TIMESTAMP(6) WHERE native_reference=?',[$admin['reference']]);
});
test('P0-2 stale confirmation and parallel termination produce one operation and one POST',function()use($db): void{
    $f=refundedCloud($db);$admin=safetyUser($db,$f);$safety=new Containment($db,$f['config']);$stale=$safety->prepare($f['instance'],'TERMINATE','Emergency stop old state',$admin['reference']);$db->execute('UPDATE cloud_instances SET version=version+1 WHERE id=?',[$f['instance']]);usleep(1100000);raises(HttpError::class,fn()=>$safety->execute($f['instance'],$stale,$admin['reference'],true));
    $confirmations=[$safety->prepare($f['instance'],'TERMINATE','Parallel operator one',$admin['reference']),$safety->prepare($f['instance'],'TERMINATE','Parallel operator two',$admin['reference'])];usleep(1100000);$children=[];
    foreach($confirmations as $c){$pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/cloud-safety-child.php',$f['instance'],$c],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,array_replace(cloudEnv($f),['SYNTHETIC_SAFETY_SESSION'=>$admin['reference']]));if(!is_resource($p)){throw new RuntimeException('Child unavailable');}fclose($pipes[0]);$children[]=[$p,$pipes];}
    $statuses=[];$op=null;foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($p));same('',$err);$r=json_decode($out,true,32,JSON_THROW_ON_ERROR);$statuses[]=$r['status'];$op=$r['operation']??$op;}sort($statuses);same([200,409],$statuses);$w=new Worker($db,$f['config']);$w->once($op);$w->once($op);same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());
});
test('P0-2 terminate timeout-after-effect reconciles existing task without a second POST',function()use($db): void{
    $f=refundedCloud($db);$a=safetyUser($db,$f);$safety=new Containment($db,$f['config']);$c=$safety->prepare($f['instance'],'TERMINATE','Timeout containment reason',$a['reference']);usleep(1100000);$op=$safety->execute($f['instance'],$c,$a['reference'],true);(new Worker($db,$f['config'],'TIMEOUT_AFTER_EFFECT'))->once($op);same('UNKNOWN',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());raises(HttpError::class,fn()=>$safety->prepare($f['instance'],'TERMINATE','No blind second terminate',$a['reference']));
    $i=$f['engine']->store->instance($f['instance'],false);$p=new DurableVoyar($db,$f['config'],$i['account_id']);$e=$p->observe($op);$p->advance($e['task']['id']);$identity=new \Hostider\Identity\IdentityService($db,$f['config']);$actor=$identity->withStepUp($a['reference'],'admin',fn($u,$s)=>$identity->principal($u,$s));$obs=$f['engine']->observe($op,$actor);$confirm=$f['engine']->prepare($f['instance'],'RESOLVE',['observation_id'=>$obs],$actor);$f['engine']->resolve($op,$obs,$confirm,$actor);same('TERMINATED',$f['engine']->view($f['instance'],$actor)['status']);same(false,(new Worker($db,$f['config']))->once($op));same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());
});
test('P0-2 other account or changed external resource cannot authorize containment',function()use($db): void{
    $f=refundedCloud($db);$a=safetyUser($db,$f);$safety=new Containment($db,$f['config']);$c=$safety->prepare($f['instance'],'STOP','Wrong resource protection',$a['reference']);$resource=$f['engine']->store->instance($f['instance'],false)['resource_id'];$db->execute('UPDATE mock_voyar_resources SET version=version+1 WHERE id=?',[$resource]);raises(HttpError::class,fn()=>$safety->execute($f['instance'],$c,$a['reference'],true));
    $other=Id::uuid();$db->execute("INSERT INTO cloud_accounts(id,environment) VALUES (?,'mock')",[$other]);$db->execute('UPDATE mock_voyar_resources SET account_id=? WHERE id=?',[$other,$resource]);raises(HttpError::class,fn()=>$safety->prepare($f['instance'],'STOP','Wrong account protection',$a['reference']));
});
test('P0-2 mismatched task resource cannot produce TERMINATED',function()use($db): void{
    $f=refundedCloud($db);$other=refundedCloud($db);$a=safetyUser($db,$f);$safety=new Containment($db,$f['config']);$c=$safety->prepare($f['instance'],'TERMINATE','Mismatched task protection',$a['reference']);usleep(1100000);$op=$safety->execute($f['instance'],$c,$a['reference'],true);$w=new Worker($db,$f['config']);$w->once($op);
    $resource=$f['engine']->store->instance($other['instance'],false)['resource_id'];$db->execute('UPDATE mock_voyar_tasks SET resource_id=? WHERE operation_id=?',[$resource,$op]);$w->once($op);same('UNKNOWN',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same('UNKNOWN',$f['engine']->view($f['instance'],$f['actor'])['status']);same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());
});

test('P0-2 STOP preserves suspended financial service and makes no cost-stopped claim',function()use($db): void{
    $f=refundedCloud($db);$a=safetyUser($db,$f);$safety=new Containment($db,$f['config']);$before=financeHash($db);$c=$safety->prepare($f['instance'],'STOP','Stop does not prove cost end',$a['reference']);$op=$safety->execute($f['instance'],$c,$a['reference'],true);$w=new Worker($db,$f['config']);$w->once($op);$w->once($op);$view=$f['engine']->view($f['instance'],$f['actor']);same('STOPPED',$view['status']);same(false,array_key_exists('billing_stopped',$view));same('SUSPENDED',$db->execute('SELECT status FROM svc_services WHERE id=?',[$f['service']['id']])->fetchColumn());same($before,financeHash($db));
});
test('P1 pending task has successful queue delivery and remains WAITING_TASK',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);$q=new \Hostider\Core\Queue\Queue($db);$db->transaction(fn()=>$q->enqueue('cloud.execute',['operation_id'=>$op],'pending-task:'.$op,Id::uuid()));$worker=new \Hostider\Core\Queue\Worker($db,$q);(new EventSink($db,$f['config'],false))->register($worker);same(true,$worker->once());same('SUCCEEDED',$db->execute('SELECT status FROM jobs WHERE dedupe_key=?',['pending-task:'.$op])->fetchColumn());same('WAITING_TASK',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same('SUCCEEDED',(new EventSink($db,$f['config'],false))->consume(['operation_id'=>$op]));same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());same(1,(int)$db->execute("SELECT COUNT(*) FROM inbox_events WHERE consumer='cloud.delivery.v1' AND event_uuid IN (SELECT event_uuid FROM outbox_events WHERE aggregate_id=?)",[$op])->fetchColumn());
});
test('P0-2 revoked staff session after enqueue cannot dispatch an emergency mutation',function()use($db): void{
    $f=refundedCloud($db);$a=safetyUser($db,$f);$safety=new Containment($db,$f['config']);$c=$safety->prepare($f['instance'],'STOP','Revocation before dispatch',$a['reference']);$op=$safety->execute($f['instance'],$c,$a['reference'],true);(new \Hostider\Identity\IdentityService($db,$f['config']))->revokeSession($a['reference'],'admin');same(null,$f['engine']->claim($op));same(0,(int)$db->execute('SELECT COUNT(*) FROM cloud_dispatches WHERE operation_id=?',[$op])->fetchColumn());
});
runTests();
