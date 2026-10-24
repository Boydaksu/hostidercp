<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/cloud-fixture.php';
use Hostider\Core\{Id,Config};use Hostider\Core\Database\{Database,Migrator};use Hostider\Core\Http\HttpError;use Hostider\Core\Security\Principal;
use Hostider\Cloud\{Catalog,Engine,Worker};use Hostider\Providers\Mock\DurableVoyar;
$db=isolatedDomainDb();$upgrade=dirname(__DIR__).'/storage/f4-d-upgrade-'.bin2hex(random_bytes(6));mkdir($upgrade,0700);$copies=[];
try{foreach(glob(dirname(__DIR__).'/migrations/*.php') as $source){if(basename($source)<'045'){$target=$upgrade.'/'.basename($source);copy($source,$target);$copies[]=$target;}}same(44,count((new Migrator($db,$upgrade))->migrate()));$m=new Migrator($db,dirname(__DIR__).'/migrations');same(['045_cloud_purchase_containment.php'],$m->migrate());same([],$m->migrate());$m->assertReady();echo 'MIGRATION 001-044 preserved; 044 to 045 additive upgrade; replay no-op PASS'.PHP_EOL;}finally{foreach($copies as $target){unlink($target);}rmdir($upgrade);}
test('F4-D canonical paid CLOUD source scope unit and immutable specification',function()use($db): void{
    $f=cloudFixture($db,false);$engine=$f['engine'];
    $bad=new Principal('wrong',['services.manage'],customerId:Id::uuid(),expiresAt:time()+3600,verifiedAt:time());
    raises(HttpError::class,fn()=>$engine->attach($f['service']['id'],$f['customer'],$f['catalog'],$f['spec'],$bad));
    $db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$f['order']['payment_request_id']]);
    raises(HttpError::class,fn()=>$engine->attach($f['service']['id'],$f['customer'],$f['catalog'],$f['spec'],$f['actor']));
    $db->execute("UPDATE billing_request_states SET refund_status='NONE' WHERE request_id=?",[$f['order']['payment_request_id']]);
    $id=$engine->attach($f['service']['id'],$f['customer'],$f['catalog'],$f['spec'],$f['actor']);same($id,$engine->attach($f['service']['id'],$f['customer'],$f['catalog'],$f['spec'],$f['actor']));
    raises(HttpError::class,fn()=>$engine->attach($f['service']['id'],$f['customer'],$f['catalog'],array_replace($f['spec'],['pid'=>2]),$f['actor']));
    raises(PDOException::class,fn()=>$db->execute('UPDATE cloud_instances SET service_id=? WHERE id=?',[Id::uuid(),$id]));
});
test('F4-D catalog OS location custom bounds and secret fields fail closed',function()use($db): void{
    $f=cloudFixture($db,false);$catalog=new Catalog($db,$f['config']);foreach([['osid'=>99],['locationid'=>99],['root_password'=>'must-never-persist']] as $bad){raises(HttpError::class,fn()=>$catalog->validate($f['policy'],array_replace($f['spec'],$bad)));}
    raises(HttpError::class,fn()=>$catalog->action($f['policy'],'DOWNGRADE',[]));
    $policy=array_replace($f['policy'],['custom'=>true,'custom_limits'=>['group_id'=>1,'cpu_min'=>2,'cpu_max'=>8,'cpu_step'=>2,'ram_min'=>2,'ram_max'=>32,'ram_per_core_max'=>4,'disk_min'=>10,'disk_max'=>400,'disk_per_ram_max'=>20,'ipv4_max'=>2]]);
    $custom=['custom_package'=>['product_group_id'=>1,'cpu_cores'=>2,'ram_gb'=>4,'disk_gb'=>40,'ipv4_count'=>1],'osid'=>1,'locationid'=>1,'type'=>'ondemand'];$catalog->validate($policy,$custom);
    foreach(['cpu_cores'=>3,'ram_gb'=>32,'disk_gb'=>400,'ipv4_count'=>3] as $key=>$value){$bad=$custom;$bad['custom_package'][$key]=$value;raises(HttpError::class,fn()=>$catalog->validate($policy,$bad));}
    $id=$catalog->configure($f['product'],$policy);raises(HttpError::class,fn()=>$f['engine']->attach($f['service']['id'],$f['customer'],$id,$custom,$f['actor']));$customF=cloudFixture($db,true,1,$custom,$policy);$instance=$customF['instance'];activateCloud($db,$customF);
    $c=$customF['engine']->prepare($instance,'UPGRADE',['pid'=>2],$customF['actor']);same('CLOUD_CUSTOM_UPGRADE_UNSUPPORTED',raises(HttpError::class,fn()=>$customF['engine']->enqueue($instance,'UPGRADE',['pid'=>2],$customF['actor'],$c))->errorCode);
});
test('F4-D create acceptance is not ACTIVE until independent task and resource evidence',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);same($op,$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']));
    $worker=new Worker($db,$f['config'],completeTasks:false);same(true,$worker->once($op));same('WAITING_TASK',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same('PROVISIONING',$db->execute('SELECT status FROM svc_services WHERE id=?',[$f['service']['id']])->fetchColumn());
    same(true,$worker->once($op));same('WAITING_TASK',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());
    same(true,(new Worker($db,$f['config']))->once($op));same('ACTIVE',$f['engine']->view($f['instance'],$f['client'])['status']);
    same(false,$worker->once($op));same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_effects WHERE operation_id=?',[$op])->fetchColumn());
});
test('F4-D power reinstall upgrade and terminate use confirmation and verified tasks',function()use($db): void{
    $f=cloudFixture($db);activateCloud($db,$f);$worker=new Worker($db,$f['config']);$journals=$db->execute('SELECT COUNT(*) FROM finance_journals')->fetchColumn();
    foreach(['STOP','START','RESTART','REINSTALL','UPGRADE'] as $kind){$data=match($kind){'REINSTALL'=>['osid'=>1],'UPGRADE'=>['pid'=>1],default=>[]};raises(HttpError::class,fn()=>$f['engine']->enqueue($f['instance'],$kind,$data,$f['actor']));$op=cloudAction($f,$kind,$data);same(true,$worker->once($op));same(true,$worker->once($op));same('SUCCEEDED',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());}
    raises(HttpError::class,fn()=>$f['engine']->prepare($f['instance'],'UPGRADE',['pid'=>3],$f['actor']));
    $c=$f['engine']->prepare($f['instance'],'TERMINATE',[],$f['actor']);raises(HttpError::class,fn()=>$f['engine']->enqueue($f['instance'],'TERMINATE',[],$f['actor'],$c));
    usleep(1100000);$op=$f['engine']->enqueue($f['instance'],'TERMINATE',[],$f['actor'],$c);$worker->once($op);same('ACTIVE',$f['engine']->view($f['instance'],$f['actor'])['status']);$worker->once($op);same('TERMINATED',$f['engine']->view($f['instance'],$f['actor'])['status']);
    same($journals,$db->execute('SELECT COUNT(*) FROM finance_journals')->fetchColumn());
});
test('F4-D before after body-loss and auth-loss never trigger a second create',function()use($db): void{
    foreach(['TIMEOUT_BEFORE_EFFECT','TIMEOUT_AFTER_EFFECT','BODY_LOST','AUTH_AFTER_EFFECT'] as $scenario){$f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);$w=new Worker($db,$f['config'],$scenario);$w->once($op);same('UNKNOWN',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same(false,$w->once($op));same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_dispatches WHERE operation_id=?',[$op])->fetchColumn());same($scenario==='TIMEOUT_BEFORE_EFFECT'?0:1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_resources WHERE create_operation_id=?',[$op])->fetchColumn());}
});
test('F4-D UNKNOWN absent call or pending task cannot become successful',function()use($db): void{
    foreach(['TIMEOUT_BEFORE_EFFECT','TIMEOUT_AFTER_EFFECT'] as $scenario){$f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);(new Worker($db,$f['config'],$scenario))->once($op);$obs=$f['engine']->observe($op,$f['actor']);$c=$f['engine']->prepare($f['instance'],'RESOLVE',['observation_id'=>$obs],$f['actor']);raises(HttpError::class,fn()=>$f['engine']->resolve($op,$obs,$c,$f['actor']));same('UNKNOWN',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());}
});
test('F4-D timeout after effect reconciles one existing task with explicit scoped operator decision',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);(new Worker($db,$f['config'],'TIMEOUT_AFTER_EFFECT'))->once($op);
    $i=$f['engine']->store->instance($f['instance'],false);$p=new DurableVoyar($db,$f['config'],$i['account_id']);$e=$p->observe($op);$p->advance($e['task']['id']);$obs=$f['engine']->observe($op,$f['actor']);$c=$f['engine']->prepare($f['instance'],'RESOLVE',['observation_id'=>$obs],$f['actor']);$f['engine']->resolve($op,$obs,$c,$f['actor']);same('ACTIVE',$f['engine']->view($f['instance'],$f['actor'])['status']);
    raises(HttpError::class,fn()=>$f['engine']->resolve($op,$obs,$c,$f['actor']));same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_resources WHERE create_operation_id=?',[$op])->fetchColumn());
});
test('F4-D crash after effect expires lease; stale response and durable send replay are fenced',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);$c=$f['engine']->claim($op);$p=new DurableVoyar($db,$f['config'],$c['account_id']);$altered=$c['request'];$altered['data']['pid']=99;raises(HttpError::class,fn()=>$p->mutate($altered,$c['fingerprint']));same(0,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());$result=$f['engine']->fence($c)->send(fn()=>$p->mutate($c['request'],$c['fingerprint']));
    raises(HttpError::class,fn()=>$f['engine']->fence($c)->send(fn()=>null));$db->execute('UPDATE cloud_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$op]);same(1,$f['engine']->expire());raises(HttpError::class,fn()=>$f['engine']->accepted($c,$result));same(false,(new Worker($db,$f['config']))->once($op));same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());
});
test('F4-D alien task status resource account and fingerprint never mean success',function()use($db): void{
    foreach(['ALIEN_SUCCESS','fingerprint','account','resource','configuration'] as $bad){$f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);(new Worker($db,$f['config']))->once($op);$task=$db->execute('SELECT * FROM mock_voyar_tasks WHERE operation_id=?',[$op])->fetch();$p=new DurableVoyar($db,$f['config'],$task['account_id']);$p->advance($task['id']);
        if($bad==='ALIEN_SUCCESS'){$db->execute("UPDATE mock_voyar_tasks SET state='SUCCESS' WHERE id=?",[$task['id']]);}
        if($bad==='fingerprint'){$db->execute('UPDATE mock_voyar_tasks SET fingerprint=? WHERE id=?',[str_repeat('0',64),$task['id']]);}
        if($bad==='account'){$other=Id::uuid();$db->execute("INSERT INTO cloud_accounts(id,environment) VALUES (?,'mock')",[$other]);$db->execute('UPDATE mock_voyar_tasks SET account_id=? WHERE id=?',[$other,$task['id']]);}
        if($bad==='resource'){$db->execute('UPDATE mock_voyar_resources SET last_operation_id=? WHERE id=?',[Id::uuid(),$task['resource_id']]);}
        if($bad==='configuration'){$db->execute("UPDATE mock_voyar_resources SET spec_json=JSON_SET(spec_json,'$.pid',99) WHERE id=?",[$task['resource_id']]);}
        (new Worker($db,$f['config']))->once($op);same('UNKNOWN',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());same(0,(int)$db->execute('SELECT COUNT(*) FROM cloud_effects WHERE operation_id=?',[$op])->fetchColumn());
    }
});
test('F4-D refund after dispatch preserves resource evidence and suspends funded service',function()use($db): void{
    $f=cloudFixture($db);$op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);$w=new Worker($db,$f['config']);$w->once($op);$db->execute("UPDATE billing_request_states SET refund_status='FULL' WHERE request_id=?",[$f['order']['payment_request_id']]);$w->once($op);same('ACTIVE',$f['engine']->view($f['instance'],$f['actor'])['status']);same('SUSPENDED',$db->execute('SELECT status FROM svc_services WHERE id=?',[$f['service']['id']])->fetchColumn());
});
test('F4-D imported VM binds verified external resource and cannot CREATE',function()use($db): void{
    $f=cloudFixture($db,false);$mapping=(new Catalog($db,$f['config']))->find($f['catalog']);$resource=Id::uuid();$external=Id::uuid();
    $imported=Id::uuid();$db->execute("INSERT INTO svc_services(id,customer_id,unit_no,product_id,name,origin_environment,imported,status) VALUES (?,?,1,?,'Synthetic imported VM','test',TRUE,'ACTIVE')",[$imported,$f['customer'],$f['product']]);
    $actor=new Principal('import-staff',['services.manage'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time(),grants:[new \Hostider\Core\Security\AccessGrant('services.manage',[$f['customer']],[$imported])]);
    $db->execute("INSERT INTO mock_voyar_resources(id,account_id,provider_ref,create_operation_id,last_operation_id,spec_json,state) VALUES (?,?,?,?,?,?,'RUNNING')",[$resource,$mapping['account_id'],'MOCK-IMPORTED-'.$resource,$external,$external,\Hostider\Core\CanonicalJson::encode($f['spec'])]);
    $id=$f['engine']->bindImported($imported,$f['customer'],$f['catalog'],$resource,$actor);raises(HttpError::class,fn()=>$f['engine']->enqueue($id,'CREATE',[],$actor));same(0,(int)$db->execute('SELECT COUNT(*) FROM cloud_operations WHERE instance_id=?',[$id])->fetchColumn());
});
test('F4-D projections immutable usage provenance and non-test environments exclude secrets',function()use($db): void{
    $f=cloudFixture($db);$op=activateCloud($db,$f);$view=$f['engine']->view($f['instance'],$f['client']);same(['id','service_id','status','imported','version'],array_keys($view));
    foreach(['password','credential','sessionToken','resource_id','account_id','request_encrypted'] as $key){same(false,array_key_exists($key,$view));}
    $unscoped=new Principal('unscoped',['services.manage'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time());raises(HttpError::class,fn()=>$f['engine']->view($f['instance'],$unscoped));
    $cross=new Principal('cross',['services.manage'],customerId:Id::uuid(),expiresAt:time()+3600,verifiedAt:time());raises(HttpError::class,fn()=>$f['engine']->view($f['instance'],$cross));
    $effect=$db->execute('SELECT provider_task_ref,provider_resource_ref FROM cloud_effects WHERE operation_id=?',[$op])->fetch();same(true,str_starts_with($effect['provider_task_ref'],'MOCK-TASK-'));same(true,str_starts_with($effect['provider_resource_ref'],'MOCK-VM-'));
    same('MOCK',$db->execute('SELECT provenance FROM cloud_effects WHERE operation_id=?',[$op])->fetchColumn());raises(PDOException::class,fn()=>$db->execute('DELETE FROM cloud_effects WHERE operation_id=?',[$op]));
    foreach(['staging','production'] as $env){$c=$f['config'];$config=new Config($env,$c->url,$c->key,$c->dsn,$c->dbUser,$c->dbPassword);raises(HttpError::class,fn()=>new Engine($db,$config));raises(HttpError::class,fn()=>new DurableVoyar($db,$config,Id::uuid()));}
});
runTests();
