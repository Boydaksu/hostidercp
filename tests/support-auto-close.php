<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/support-auto-close-fixture.php';
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Database\Migrator;
use Hostider\Support\AutoClose\Engine as AutoEngine;

test('F5-F 01 default disabled no duration no jobs or ticket mutation',function():void{
    $f=fFixture();$t=fTicket($f);$r=$f['auto_policy']->view($f['admin']['reference'],'settings',['department_id'=>$f['departments'][0]]);
    same(false,$r['enabled']);same(null,$r['wait_seconds']);same(0,$r['version']);$before=fSnapshot($f);
    same(0,$f['auto']->run()['queued']);same($before,fSnapshot($f));same('RESOLVED',bRow($f,$t)['status']);
});
test('F5-F 02 explicit enable disable revisions and immutable policy audit',function():void{
    $f=fFixture();$r=fPolicy($f);same(1,$r['version']);same(1,$r['wait_seconds']);$old=$f['db']->execute('SELECT * FROM support_auto_close_policies WHERE id=?',[$r['id']])->fetch();
    $r=fPolicy($f,false);same(2,$r['version']);same(false,$r['enabled']);same(null,$r['wait_seconds']);
    same($old,$f['db']->execute('SELECT * FROM support_auto_close_policies WHERE revision=1')->fetch());same(2,(int)$f['db']->execute("SELECT COUNT(*) FROM support_work_events WHERE kind='AUTO_CLOSE_POLICY'")->fetchColumn());
    raises(PDOException::class,fn()=>$f['db']->execute('UPDATE support_auto_close_policies SET enabled=FALSE WHERE revision=1'));
});
test('F5-F 03 invalid duration boolean and unexpected policy fields rejected',function():void{
    $f=fFixture();foreach([0,-1,31536001,1.5,'10',null] as $duration){
        $p=['action'=>'policy-set','payload'=>['department_id'=>$f['departments'][0],'version'=>0,'enabled'=>true,'wait_seconds'=>$duration]];
        same(422,raises(HttpError::class,fn()=>$f['auto_policy']->command($f['admin']['reference'],'prepare',$p,Id::uuid(),Id::uuid()))->status);
    }same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_auto_close_policies')->fetchColumn());
});
test('F5-F 04 deadline uses last real RESOLVED transition not ticket updated time',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);same(0,$f['auto']->run()['queued']);fDue();
    bReply($f,$t,'note');$r=$f['auto']->run();same(1,$r['queued']);same(true,fWorker($f));same('CLOSED',bRow($f,$t)['status']);same(1,fClosed($f));
});
test('F5-F 05 no other ticket states are changed',function():void{
    $f=fFixture();fPolicy($f);$ids=[];
    foreach(['NEW','WAITING_STAFF','WAITING_CUSTOMER','ON_HOLD','CLOSED'] as $state){
        $t=bTicket($f);if($state==='ON_HOLD'){$f['db']->execute('UPDATE support_departments SET allow_hold=TRUE WHERE id=?',[$f['departments'][0]]);}
        if($state!=='NEW'){$f['service']->command($f['admin']['reference'],'admin','state',['id'=>$t['id'],'version'=>1,'status'=>$state],Id::uuid(),Id::uuid());}$ids[$t['id']]=$state;
    }fDue();same(0,$f['auto']->run()['queued']);foreach($ids as $id=>$status){same($status,bRow($f,['id'=>$id])['status']);}
});
test('F5-F 06 customer reply invalidates queued close and no reply is lost',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();same(1,$f['auto']->run()['queued']);bReply($f,$t,client:true);
    same(true,fWorker($f));same('WAITING_STAFF',bRow($f,$t)['status']);same(0,fClosed($f));same(2,(int)$f['db']->execute('SELECT COUNT(*) FROM support_messages WHERE ticket_id=?',[$t['id']])->fetchColumn());
});
test('F5-F 07 native allow_reopen false retains atomic rejection after close',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();fWorker($f);$before=fSnapshot($f);
    same(409,raises(HttpError::class,fn()=>bReply($f,$t,client:true))->status);same($before,fSnapshot($f));
});
test('F5-F 08 allow_reopen true creates new native SLA cycle preserves old history',function():void{
    $f=fFixture(true);$f['db']->execute('UPDATE support_departments SET allow_reopen=TRUE WHERE id=?',[$f['departments'][0]]);fPolicy($f);$t=fTicket($f);fDue();
    $old=$f['db']->execute('SELECT * FROM support_sla_history ORDER BY id')->fetchAll();$f['auto']->run();fWorker($f);same($old,$f['db']->execute('SELECT * FROM support_sla_history ORDER BY id')->fetchAll());
    bReply($f,$t,client:true);same('WAITING_STAFF',bRow($f,$t)['status']);same(2,(int)$f['db']->execute('SELECT COUNT(*) FROM support_sla_cycles WHERE ticket_id=?',[$t['id']])->fetchColumn());
});
test('F5-F 09 stale ticket version rejected current candidate may close',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();bReply($f,$t,'note');fWorker($f);same('RESOLVED',bRow($f,$t)['status']);same(0,fClosed($f));
    same(1,$f['auto']->run()['queued']);fWorker($f);same(1,fClosed($f));
});
test('F5-F 10 concurrent two native workers produce exactly one close',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();
    $r=fRace($f,[['op'=>'worker'],['op'=>'worker']]);same(1,array_sum(array_map(fn($x)=>(int)$x['worked'],$r)));same(1,fClosed($f));same('CLOSED',bRow($f,$t)['status']);
});
test('F5-F 11 duplicate sweep execution and canonical service replay are idempotent',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$p=$f['auto']->candidates()[0];same(1,$f['auto']->run()['queued']);same(0,$f['auto']->run()['queued']);
    fWorker($f);same(false,$f['db']->transaction(fn()=>$f['service']->autoClose($p,Id::uuid())));same(1,fClosed($f));
    same(1,(int)$f['db']->execute("SELECT COUNT(*) FROM support_work_events WHERE kind='AUTO_CLOSED'")->fetchColumn());
});
test('F5-F 12 old resolution event cannot close a newer resolved cycle',function():void{
    $f=fFixture();$f['db']->execute('UPDATE support_departments SET allow_reopen=TRUE WHERE id=?',[$f['departments'][0]]);fPolicy($f);$t=fTicket($f);fDue();$old=$f['auto']->candidates()[0];$f['auto']->run();
    bReply($f,$t,client:true);fResolve($f,$t);fWorker($f);same(0,fClosed($f));fDue();same(1,$f['auto']->run()['queued']);fWorker($f);same(1,fClosed($f));
    same(false,$f['db']->transaction(fn()=>$f['service']->autoClose($old,Id::uuid())));
});
test('F5-F 13 policy version change invalidates queued job and disable cancels it',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();fPolicy($f,false);fWorker($f);same('RESOLVED',bRow($f,$t)['status']);same(0,fClosed($f));
    fPolicy($f,true);same(1,$f['auto']->run()['queued']);fWorker($f);same(1,fClosed($f));
});
test('F5-F 14 department isolation moved resolved ticket needs a local resolution anchor',function():void{
    $f=fFixture();$other=$f['departments'][1];supportGrant($f,$other,'*');fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();
    $f['service']->command($f['admin']['reference'],'admin','move',['id'=>$t['id'],'version'=>(int)bRow($f,$t)['version'],'department_id'=>$other],Id::uuid(),Id::uuid());
    fPolicy($f,true,1,$other);fWorker($f);same(0,fClosed($f));same(0,$f['auto']->run()['queued']);
});
test('F5-F 15 customer and unscoped staff cannot manage department-wide policy',function():void{
    $f=fFixture();same(true,in_array(raises(HttpError::class,fn()=>$f['auto_policy']->view($f['client']['reference'],'settings',['department_id'=>$f['departments'][0]]))->status,[401,403],true));
    same(404,raises(HttpError::class,fn()=>$f['auto_policy']->view($f['admin']['reference'],'settings',['department_id'=>$f['departments'][1]]))->status);
    $f['db']->execute('DELETE FROM support_department_members WHERE user_id=?',[$f['admin']['user']]);same(404,raises(HttpError::class,fn()=>fPolicy($f))->status);
});
test('F5-F 16 issuer capability and MFA revocation pause durable policy',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();$f['db']->transaction(fn()=>$f['c']->identity->invalidate($f['admin']['user']));fWorker($f);same(0,fClosed($f));same('RESOLVED',bRow($f,$t)['status']);
    $g=fFixture();fPolicy($g);$t=fTicket($g);fDue();$g['auto']->run();$g['db']->execute('UPDATE identity_mfa SET enabled=FALSE WHERE user_id=?',[$g['admin']['user']]);fWorker($g);same(0,fClosed($g));
});
test('F5-F 17 scoped dry run and preview create no business jobs or audit',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$before=fSnapshot($f);
    same(1,$f['auto']->run(dryRun:true)['would_close']);same($before,fSnapshot($f));
    $r=$f['auto_policy']->view($f['admin']['reference'],'preview',['department_id'=>$f['departments'][0]]);same(1,$r['would_close']);same(false,$r['effects_allowed']);same($before,fSnapshot($f));
});
test('F5-F 18 close event receipt audit outbox and immutable SLA remain atomic',function():void{
    $f=fFixture(true);fPolicy($f);$t=fTicket($f);fDue();$history=$f['db']->execute('SELECT * FROM support_sla_history ORDER BY id')->fetchAll();$f['auto']->run();fWorker($f);
    $e=$f['db']->execute('SELECT * FROM support_auto_close_events')->fetch();same('SYSTEM_AUTO_CLOSE',$e['actor_kind']);same((int)$e['from_version']+1,(int)$e['to_version']);
    $w=$f['db']->execute('SELECT * FROM support_work_events WHERE id=?',[$e['id']])->fetch();same('SYSTEM_AUTO_CLOSE',$w['actor_id']);same($e['resolution_event'],$w['source_event']);
    same(1,(int)$f['db']->execute("SELECT COUNT(*) FROM audit_logs WHERE actor_id='SYSTEM_AUTO_CLOSE' AND subject_id=?",[$t['id']])->fetchColumn());
    same('support.work.held',$f['db']->execute('SELECT event_type FROM outbox_events WHERE event_uuid=?',[$e['id']])->fetchColumn());same($history,$f['db']->execute('SELECT * FROM support_sla_history ORDER BY id')->fetchAll());
    raises(PDOException::class,fn()=>$f['db']->execute('DELETE FROM support_auto_close_events WHERE id=?',[$e['id']]));
});
test('F5-F 19 failure after state event audit and outbox rolls all business effects back',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();$before=fSnapshot($f);
    $f['db']->execute("CREATE TRIGGER synthetic_auto_close_failure BEFORE INSERT ON support_auto_close_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
    try{same(true,fWorker($f));same('RESOLVED',bRow($f,$t)['status']);same(0,fClosed($f));$after=fSnapshot($f);unset($before['jobs'],$after['jobs']);same($before,$after);
        $j=$f['db']->execute("SELECT status,attempts,max_attempts FROM jobs WHERE job_type='support.auto-close'")->fetch();same('PENDING',$j['status']);same(1,(int)$j['attempts']);same(3,(int)$j['max_attempts']);
    }finally{$f['db']->execute('DROP TRIGGER synthetic_auto_close_failure');}
    $f['db']->execute("UPDATE jobs SET run_after=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6)) WHERE job_type='support.auto-close'");fWorker($f);same(1,fClosed($f));
});
test('F5-F 20 native retry exhausts at three attempts no infinite reseed',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();
    $f['db']->execute("CREATE TRIGGER synthetic_auto_close_failure BEFORE INSERT ON support_auto_close_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
    try{for($i=0;$i<3;++$i){$f['db']->execute("UPDATE jobs SET run_after=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6)) WHERE job_type='support.auto-close'");fWorker($f);}
        same('FAILED',$f['db']->execute("SELECT status FROM jobs WHERE job_type='support.auto-close'")->fetchColumn());same(1,(int)$f['db']->execute('SELECT COUNT(*) FROM dead_letters')->fetchColumn());same(0,$f['auto']->run()['queued']);same(0,fClosed($f));
    }finally{$f['db']->execute('DROP TRIGGER synthetic_auto_close_failure');}
});
test('F5-F 21 reply racing worker accepts one customer message and honors reopen',function():void{
    $f=fFixture(true);$f['db']->execute('UPDATE support_departments SET allow_reopen=TRUE WHERE id=?',[$f['departments'][0]]);fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();$v=(int)bRow($f,$t)['version'];
    $r=fRace($f,[['op'=>'worker'],['op'=>'reply','ticket_id'=>$t['id'],'version'=>$v,'ref'=>$f['client']['reference']]]);
    same(true,in_array($r[1]['status'],[200,409],true));
    if($r[1]['status']===409){bReply($f,$t,client:true);}
    same('WAITING_STAFF',bRow($f,$t)['status']);same(2,(int)$f['db']->execute('SELECT COUNT(*) FROM support_messages WHERE ticket_id=?',[$t['id']])->fetchColumn());same(true,fClosed($f)<=1);
    same(1,(int)$f['db']->execute("SELECT COUNT(*) FROM support_events WHERE ticket_id=? AND kind='CUSTOMER_REPLY'",[$t['id']])->fetchColumn());
});
test('F5-F 22 private notes and attachments keep native privacy after automatic close',function():void{
    $f=fFixture(mail:true);fPolicy($f);$t=bTicket($f);$f['service']->command($f['admin']['reference'],'admin','note',['id'=>$t['id'],'version'=>(int)bRow($f,$t)['version'],'body'=>'F5F_PRIVATE_NOTE_CANARY'],Id::uuid(),Id::uuid());
    $initial=$f['db']->execute("SELECT id FROM support_messages WHERE ticket_id=? AND type='customer_reply'",[$t['id']])->fetchColumn();
    $file=$f['files']->upload($f['client']['reference'],'client',['ticket_id'=>$t['id'],'message_id'=>$initial,'version'=>(int)bRow($f,$t)['version'],'name'=>'synthetic.pdf','base64'=>base64_encode(\Hostider\Support\Mail\Attachments::FIXTURE)],Id::uuid(),Id::uuid());
    fResolve($f,$t);fDue();$before=$f['db']->execute('SELECT * FROM support_messages WHERE ticket_id=? ORDER BY id',[$t['id']])->fetchAll();$f['auto']->run();fWorker($f);
    same($before,$f['db']->execute('SELECT * FROM support_messages WHERE ticket_id=? ORDER BY id',[$t['id']])->fetchAll());
    $public=$f['service']->view($f['client']['reference'],'client','detail',['id'=>$t['id']]);same(false,str_contains(json_encode($public),'F5F_PRIVATE_NOTE_CANARY'));
    same(404,raises(HttpError::class,fn()=>$f['files']->token($f['other']['reference'],'client',$file['id']))->status);
    same(true,str_contains(json_encode($f['service']->view($f['admin']['reference'],'admin','detail',['id'=>$t['id']])),'F5F_PRIVATE_NOTE_CANARY'));
});
test('F5-F 23 forged job payload or policy pin cannot authorize close',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$p=$f['auto']->candidates()[0];
    same(409,raises(HttpError::class,fn()=>$f['auto']->consume($p))->status);
    $p['policy_id']=Id::uuid();same(false,$f['db']->transaction(fn()=>$f['service']->autoClose($p,Id::uuid())));same('RESOLVED',bRow($f,$t)['status']);
});
test('F5-F 24 CLI bounded dry-run seed and native queue once complete real flow',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();
    $r=fCli($f,['support:auto-close','--once','--dry-run','--max-tickets=1']);same(0,$r['exit']);same(1,$r['report']['would_close']);same(0,$r['report']['queued']);
    $r=fCli($f,['support:auto-close','--once','--max-tickets=1']);same(0,$r['exit']);same(1,$r['report']['queued']);
    $r=fCli($f,['queue:work','--once']);same(0,$r['exit']);same(true,$r['report']['worked']);same('CLOSED',bRow($f,$t)['status']);
});
test('F5-F 25 native HTTP admin settings preview confirmation CSRF and client IDOR',function():void{
    $f=fFixture();$server=new HttpTestServer(dirname(__DIR__),bEnvironment($f),__DIR__.'/commerce-router.php');$a=['cookies'=>[],'csrf'=>''];$client=$a;
    try{bLogin($server,$f['admin'],$a);dClientLogin($server,$f['client'],$client);
        $url='/api/v1/admin/support-auto-close/settings?department_id='.$f['departments'][0];$r=bHttp($server,$url,$a);same(200,$r[0]);same(false,$r[1]['data']['enabled']);
        $body=['action'=>'policy-set','payload'=>['department_id'=>$f['departments'][0],'version'=>0,'enabled'=>true,'wait_seconds'=>1]];
        same(403,bHttp($server,'/api/v1/admin/support-auto-close/prepare',$a,$body,csrf:false)[0]);
        $p=bHttp($server,'/api/v1/admin/support-auto-close/prepare',$a,$body);same(200,$p[0]);$body['confirmation']=['id'=>$p[1]['data']['id'],'accepted'=>true];same(200,bHttp($server,'/api/v1/admin/support-auto-close/execute',$a,$body)[0]);
        same(200,bHttp($server,'/api/v1/admin/support-auto-close/preview?department_id='.$f['departments'][0],$a)[0]);
        same(true,in_array(bHttp($server,$url,$client)[0],[401,403],true));
        same(404,bHttp($server,'/api/v1/client/support-auto-close/prepare',$client,$body)[0]);
    }finally{$server->stop();}
});
test('F5-F 26 native HTTP session revoke invalidates prepared setting',function():void{
    $f=fFixture();$server=new HttpTestServer(dirname(__DIR__),bEnvironment($f),__DIR__.'/commerce-router.php');$a=['cookies'=>[],'csrf'=>''];
    try{bLogin($server,$f['admin'],$a);$body=['action'=>'policy-set','payload'=>['department_id'=>$f['departments'][0],'version'=>0,'enabled'=>true,'wait_seconds'=>1]];
        $p=bHttp($server,'/api/v1/admin/support-auto-close/prepare',$a,$body);same(200,$p[0]);$body['confirmation']=['id'=>$p[1]['data']['id'],'accepted'=>true];
        $f['db']->transaction(fn()=>$f['c']->identity->invalidate($f['admin']['user']));same(401,bHttp($server,'/api/v1/admin/support-auto-close/execute',$a,$body)[0]);same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_auto_close_policies')->fetchColumn());
    }finally{$server->stop();}
});
test('F5-F 27 production gates and bounds reject actual mutation',function():void{
    $f=fFixture();$c=$f['config'];$prod=new \Hostider\Commerce\Services($f['db'],new \Hostider\Core\Config('production',$c->url,$c->key,$c->dsn,$c->dbUser,$c->dbPassword));
    same(503,raises(HttpError::class,fn()=>(new AutoEngine($prod))->run())->status);
    raises(HttpError::class,fn()=>$f['auto']->run(26));raises(HttpError::class,fn()=>$f['auto']->run(seconds:21));
});
function fMigration055Path():string { $dir=dirname(__DIR__).'/storage/f5f-exact055-'.bin2hex(random_bytes(5));mkdir($dir,0700);foreach(glob(dirname(__DIR__).'/migrations/*.php') as $file){if(basename($file)<'056'){copy($file,$dir.'/'.basename($file));}}return $dir;}

test('F5-F 28 migration fresh 055 replay and immutable original checksums',function():void{
    $db=isolatedDomainDb();$root=dirname(__DIR__);$m=new Migrator($db,fMigration055Path());same(55,count($m->migrate()));same([],$m->migrate());$m->assertReady();
    foreach($db->execute('SELECT * FROM schema_migrations ORDER BY version')->fetchAll() as $r){same(hash_file('sha256',$root.'/migrations/'.$r['version']),$r['checksum']);same('APPLIED',$r['status']);}
    same(0,(int)$db->execute('SELECT COUNT(*) FROM support_auto_close_heads')->fetchColumn());
});
test('F5-F 29 installed 054 upgrade applies only 055 preserves previous rows',function():void{
    $db=isolatedDomainDb();$root=dirname(__DIR__);$dir=$root.'/storage/f5f-prefix-'.bin2hex(random_bytes(5));mkdir($dir,0700);$files=[];
    try{foreach(glob($root.'/migrations/*.php') as $file){if(basename($file)<'055'){$copy=$dir.'/'.basename($file);copy($file,$copy);$files[]=$copy;}}
        same(54,count((new Migrator($db,$dir))->migrate()));$old=$db->execute('SELECT * FROM schema_migrations ORDER BY version')->fetchAll();$m=new Migrator($db,fMigration055Path());same(['055_support_auto_close.php'],$m->migrate());same([],$m->migrate());$m->assertReady();
        same($old,$db->execute("SELECT * FROM schema_migrations WHERE version<'055' ORDER BY version")->fetchAll());
    }finally{foreach($files as $file){unlink($file);}rmdir($dir);}
});
test('F5-F 30 partial 055 blocks runtime until reviewed isolated forward completion',function():void{
    $db=isolatedDomainDb();$root=dirname(__DIR__);$dir=$root.'/storage/f5f-failure-'.bin2hex(random_bytes(5));mkdir($dir,0700);$files=[];
    try{foreach(glob($root.'/migrations/*.php') as $file){if(basename($file)<'055'){$copy=$dir.'/'.basename($file);copy($file,$copy);$files[]=$copy;}}
        (new Migrator($db,$dir))->migrate();$old=$db->execute('SELECT * FROM schema_migrations ORDER BY version')->fetchAll();$db->execute('CREATE TABLE support_auto_close_heads (synthetic_collision INT) ENGINE=InnoDB');
        $m=new Migrator($db,fMigration055Path());raises(PDOException::class,fn()=>$m->migrate());same('APPLYING',$db->execute("SELECT status FROM schema_migrations WHERE version='055_support_auto_close.php'")->fetchColumn());raises(RuntimeException::class,fn()=>$m->assertReady());raises(RuntimeException::class,fn()=>$m->migrate());
        // Explicit isolated operator forward-completion exercise; never an automatic runtime repair.
        $db->execute('RENAME TABLE support_auto_close_heads TO synthetic_quarantined_collision');
        $sql=require $root.'/migrations/055_support_auto_close.php';foreach(array_slice($sql,1) as $statement){$db->execute($statement);}
        same(3,(int)$db->execute("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('support_auto_close_policies','support_auto_close_heads','support_auto_close_events')")->fetchColumn());
        $db->execute("UPDATE schema_migrations SET status='APPLIED',applied_at=UTC_TIMESTAMP(6) WHERE version='055_support_auto_close.php'");$m->assertReady();same([],$m->migrate());
        same($old,$db->execute("SELECT * FROM schema_migrations WHERE version<'055' ORDER BY version")->fetchAll());
    }finally{foreach($files as $file){unlink($file);}rmdir($dir);}
});
test('F5-F 31 contended department lock yields bounded native retry then safe completion',function():void{
    $f=fFixture();fPolicy($f);$t=fTicket($f);fDue();$f['auto']->run();$start=microtime(true);
    $f['db']->transaction(function()use($f):void{
        $f['db']->execute('SELECT id FROM support_departments WHERE id=? FOR UPDATE',[$f['departments'][0]])->fetch();
        $r=fRace($f,[['op'=>'worker']]);same(true,$r[0]['worked']);
    });
    same(true,microtime(true)-$start<10);same('RESOLVED',bRow($f,$t)['status']);same(0,fClosed($f));
    same('PENDING',$f['db']->execute("SELECT status FROM jobs WHERE job_type='support.auto-close'")->fetchColumn());
    $f['db']->execute("UPDATE jobs SET run_after=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6)) WHERE job_type='support.auto-close'");fWorker($f);same(1,fClosed($f));
});
exit(executeTests(true));
