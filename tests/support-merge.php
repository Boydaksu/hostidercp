<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/support-merge-fixture.php';
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Database\Migrator;

function gMigration056Path():string{$dir=dirname(__DIR__).'/storage/f5g-exact056-'.bin2hex(random_bytes(5));mkdir($dir,0700);foreach(glob(dirname(__DIR__).'/migrations/*.php') as $file){if(basename($file)<'057'){copy($file,$dir.'/'.basename($file));}}return $dir;}

test('F5-G 01 scoped merge preserves tickets and messages with immutable directed alias',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$hash=gHash($f,['support_messages','support_events']);$r=gMerge($f,$a,$b);same($b['id'],$r['target_id']);same(1,gCount($f));same(2,(int)$f['db']->execute('SELECT COUNT(*) FROM support_merge_members')->fetchColumn());same($hash,gHash($f,['support_messages','support_events']));same(2,(int)bRow($f,$a)['version']);same(2,(int)bRow($f,$b)['version']);same('NEW',bRow($f,$a)['status']);same(false,$r['live_merge_allowed']);
 foreach(['support_ticket_merges','support_merge_members'] as $t){raises(PDOException::class,fn()=>$f['db']->execute('DELETE FROM '.$t));}
});
test('F5-G 02 same ticket and unknown payload scope assertions are rejected',function():void{
 $f=gFixture();$a=bTicket($f);same(422,raises(HttpError::class,fn()=>gPrepare($f,$a,$a))->status);$b=bTicket($f);$p=gPayload($f,$a,$b);$p['customer_id']=$f['other']['customer'];same(422,raises(HttpError::class,fn()=>$f['merge']->command($f['admin']['reference'],'prepare',['action'=>'merge','payload'=>$p],Id::uuid(),Id::uuid()))->status);same(0,gCount($f));
});
test('F5-G 03 cross customer merge rejected using real locked DB owners',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f,who:$f['other']);same('MERGE_SCOPE_MISMATCH',raises(HttpError::class,fn()=>gPrepare($f,$a,$b))->errorCode);same(0,gCount($f));
});
test('F5-G 04 cross department merge denied even with both explicit grants',function():void{
 $f=gFixture();supportGrant($f,$f['departments'][1],'*');$assignment=$f['db']->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL',[$f['admin']['user']])->fetchColumn();$f['db']->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope,department_scope) VALUES (?,?,?,?,?)',[Id::uuid(),$assignment,'support.ticket.merge','*',$f['departments'][1]]);$a=bTicket($f);$b=bTicket($f,dep:$f['departments'][1]);same('MERGE_SCOPE_MISMATCH',raises(HttpError::class,fn()=>gPrepare($f,$a,$b))->errorCode);
});
test('F5-G 05 customer and administrator without explicit merge grant cannot prepare',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=['action'=>'merge','payload'=>gPayload($f,$a,$b)];raises(HttpError::class,fn()=>$f['merge']->command($f['client']['reference'],'prepare',$p,Id::uuid(),Id::uuid()));$f['db']->execute("DELETE FROM identity_grants WHERE permission='support.ticket.merge'");same(404,raises(HttpError::class,fn()=>gPrepare($f,$a,$b))->status);same(0,gCount($f));
});
test('F5-G 06 session revocation invalidates prepared merge atomically',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$f['db']->transaction(fn()=>$f['c']->identity->invalidate($f['admin']['user']));same(401,raises(HttpError::class,fn()=>gExecute($f,$p))->status);same(0,gCount($f));
});
test('F5-G 07 disabled MFA cannot execute prepared merge',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$f['db']->execute('UPDATE identity_mfa SET enabled=FALSE WHERE user_id=?',[$f['admin']['user']]);raises(HttpError::class,fn()=>gExecute($f,$p));same(0,gCount($f));
});
test('F5-G 08 current merge grant and membership revocation block execute',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$f['db']->execute("DELETE FROM identity_grants WHERE permission='support.ticket.merge'");same(404,raises(HttpError::class,fn()=>gExecute($f,$p))->status);same(0,gCount($f));
});
test('F5-G 09 stale source version rejects with no merge side effects',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);bReply($f,$a,'note');$before=gHash($f,['support_tickets','support_work_events','outbox_events']);same('MERGE_VERSION_CONFLICT',raises(HttpError::class,fn()=>gExecute($f,$p))->errorCode);same($before,gHash($f,['support_tickets','support_work_events','outbox_events']));
});
test('F5-G 10 stale target version rejects prepared merge',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);bReply($f,$b,client:true);same('MERGE_VERSION_CONFLICT',raises(HttpError::class,fn()=>gExecute($f,$p))->errorCode);same(0,gCount($f));
});
test('F5-G 11 direction tampering and explicit decline cannot execute',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$reverse=$p;$reverse['payload']=gPayload($f,$b,$a);same('MERGE_CONFIRMATION_INVALID',raises(HttpError::class,fn()=>gExecute($f,$reverse))->errorCode);$p['confirmation']['accepted']=false;same(403,raises(HttpError::class,fn()=>gExecute($f,$p))->status);same(0,gCount($f));
});
test('F5-G 12 exact duplicate is idempotent but fresh-key replay is blocked',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$key=Id::uuid();$r=gExecute($f,$p,$key);$n=gHash($f,['support_tickets','support_ticket_merges','support_work_events','audit_logs']);same($r,gExecute($f,$p,$key));same($n,gHash($f,['support_tickets','support_ticket_merges','support_work_events','audit_logs']));raises(HttpError::class,fn()=>gExecute($f,$p));same(1,gCount($f));
});
test('F5-G 13 inverse real process race creates at most one edge and no cycle',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$q=gPrepare($f,$b,$a);$r=gRace($f,[['op'=>'merge','ref'=>$f['admin']['reference'],'body'=>$p,'key'=>Id::uuid()],['op'=>'merge','ref'=>$f['admin']['reference'],'body'=>$q,'key'=>Id::uuid()]]);same(1,count(array_filter($r,fn($v)=>$v['status']===200)));same(1,gCount($f));same(2,(int)$f['db']->execute('SELECT COUNT(*) FROM support_merge_members')->fetchColumn());
});
test('F5-G 14 triples chains repeated endpoints and cycles remain blocked',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$c=bTicket($f);gMerge($f,$a,$b);foreach([[$b,$c],[$c,$b],[$c,$a],[$b,$a]] as [$x,$y]){same('MERGE_CHAIN_BLOCKED',raises(HttpError::class,fn()=>gPrepare($f,$x,$y))->errorCode);}same(1,gCount($f));
});
test('F5-G 15 reply to source explicitly identifies target and never silently appends',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);gMerge($f,$a,$b);$n=gHash($f,['support_messages','support_tickets']);$err=raises(HttpError::class,fn()=>bReply($f,$a,client:true));same('SUPPORT_TICKET_MERGED_TARGET_'.$b['id'],$err->errorCode);same($n,gHash($f,['support_messages','support_tickets']));bReply($f,$b,client:true);same(3,(int)$f['db']->execute('SELECT COUNT(*) FROM support_messages')->fetchColumn());
});
test('F5-G 16 customer alias lookup and legacy projection enforce current ACL',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);gMerge($f,$a,$b);$d=gDetail($f,$a);same($b['id'],$d['merge_alias']['target_id']);same(false,$d['capabilities']['reply']);same(2,count($d['messages']));$legacy=$f['service']->view($f['client']['reference'],'client','detail',['id'=>$a['id']]);same($b['id'],$legacy['merge_alias']['target_id']);same(404,raises(HttpError::class,fn()=>$f['service']->view($f['other']['reference'],'client','detail',['id'=>$a['id']]))->status);
});
test('F5-G 17 private notes metadata and attachment ownership stay on original ticket',function():void{
 $f=gFixture(mail:true);$a=bTicket($f);$b=bTicket($f);bReply($f,$a,'note');$file=$f['attachments']->upload($f['client']['reference'],'client',['ticket_id'=>$a['id'],'message_id'=>$a['message_id'],'version'=>(int)bRow($f,$a)['version'],'name'=>'synthetic.pdf','base64'=>base64_encode(\Hostider\Support\Mail\Attachments::FIXTURE)],Id::uuid(),Id::uuid());$before=gHash($f,['support_messages','support_mail_files']);gMerge($f,$a,$b);same($before,gHash($f,['support_messages','support_mail_files']));$d=gDetail($f,$b);same(2,count($d['messages']));foreach($d['messages'] as $m){same(false,array_key_exists('author_id',$m));}same(3,count(gDetail($f,$b,true)['messages']));$token=$f['attachments']->token($f['client']['reference'],'client',$file['id']);same(\Hostider\Support\Mail\Attachments::FIXTURE,$f['attachments']->download($f['client']['reference'],'client',$file['id'],$token['token'],Id::uuid())['bytes']);same($a['id'],$f['db']->execute('SELECT ticket_id FROM support_mail_files WHERE id=?',[$file['id']])->fetchColumn());same(1,count($f['attachments']->listing($f['client']['reference'],'client',$a['id'],$a['message_id'])));raises(HttpError::class,fn()=>$f['attachments']->token($f['other']['reference'],'client',$file['id']));
});
test('F5-G 18 original source and target SLA history remains byte equal after merge and tick',function():void{
 $f=gFixture(true);$a=bTicket($f);$b=bTicket($f);$tables=['support_sla_cycles','support_sla_clocks','support_sla_history'];$hash=gHash($f,$tables);gMerge($f,$a,$b);same($hash,gHash($f,$tables));same(false,(new \Hostider\Support\Work\Engine($f['c']))->tick($a['id']));same($hash,gHash($f,$tables));
});
test('F5-G 19 immutable work events audit provenance and HELD outbox are atomic no fake reply',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$before=gHash($f,['support_events','support_messages']);$r=gMerge($f,$a,$b);same($before,gHash($f,['support_events','support_messages']));$rows=$f['db']->execute("SELECT * FROM support_work_events WHERE kind IN ('MERGED_SOURCE','MERGED_TARGET') ORDER BY kind")->fetchAll();same(2,count($rows));same($r['merge_id'],$rows[0]['id']);same($r['merge_id'],$rows[1]['source_event']);foreach($rows as $row){same($f['admin']['user'],$row['actor_id']);same($b['id'],json_decode($row['details'],true)['target_id']);same(1,(int)$f['db']->execute("SELECT COUNT(*) FROM outbox_events WHERE event_uuid=? AND event_type='support.work.held'",[$row['id']])->fetchColumn());}same(0,(int)$f['db']->execute("SELECT COUNT(*) FROM support_automation_events WHERE source_id IN (?,?)",array_column($rows,'id'))->fetchColumn());
});
test('F5-G 20 queued auto close source skips and target stale version skips then target can close',function():void{
 $f=gFixture();fPolicy($f);$a=fTicket($f);$b=fTicket($f);fDue();same(2,$f['auto']->run()['queued']);gMerge($f,$a,$b);while(fWorker($f)){}same(0,fClosed($f));same(1,count($f['auto']->candidates()));same(1,$f['auto']->run()['queued']);while(fWorker($f)){}same(1,fClosed($f));same('RESOLVED',bRow($f,$a)['status']);same('CLOSED',bRow($f,$b)['status']);
});
test('F5-G 21 actual merge and auto close race commits a valid version-fenced outcome',function():void{
 $f=gFixture();fPolicy($f);$a=fTicket($f);$b=bTicket($f);fDue();$p=gPrepare($f,$a,$b);$f['auto']->run();$r=gRace($f,[['op'=>'merge','ref'=>$f['admin']['reference'],'body'=>$p,'key'=>Id::uuid()],['op'=>'worker']]);same(true,in_array($r[0]['status'],[200,409],true));same(true,fClosed($f)<=1);if(gCount($f)===1){same('RESOLVED',bRow($f,$a)['status']);same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_auto_close_events WHERE ticket_id=?',[$a['id']])->fetchColumn());}else{same(0,gCount($f));same(true,fClosed($f)===1||$r[0]['status']===409);}
});
test('F5-G 22 transaction member insertion fault rolls back versions events audit edge and outbox',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$tables=['support_tickets','support_ticket_merges','support_merge_members','support_work_events','audit_logs','outbox_events'];$h=gHash($f,$tables);$f['db']->execute("CREATE TRIGGER synthetic_merge_fault BEFORE INSERT ON support_merge_members FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");raises(PDOException::class,fn()=>gExecute($f,$p));same($h,gHash($f,$tables));$f['db']->execute('DROP TRIGGER synthetic_merge_fault');gExecute($f,$p);same(1,gCount($f));
});
test('F5-G 23 native HTTP prepare CSRF execute and source conflict expose authorized target',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$server=new HttpTestServer(dirname(__DIR__),bEnvironment($f),__DIR__.'/commerce-router.php');$auth=['cookies'=>[],'csrf'=>''];
 try{bLogin($server,$f['admin'],$auth);$body=['action'=>'merge','payload'=>gPayload($f,$a,$b)];same(403,bHttp($server,'/api/v1/admin/support-merge/prepare',$auth,$body,csrf:false)[0]);$p=bHttp($server,'/api/v1/admin/support-merge/prepare',$auth,$body);same(200,$p[0]);$body['confirmation']=['id'=>$p[1]['data']['id'],'accepted'=>true];same(200,bHttp($server,'/api/v1/admin/support-merge/execute',$auth,$body)[0]);$client=['cookies'=>[],'csrf'=>''];dClientLogin($server,$f['client'],$client);same(404,bHttp($server,'/api/v1/client/support-merge/prepare',$client,$body)[0]);same(401,bHttp($server,'/api/v1/admin/support-merge/prepare',$client,$body)[0]);$d=bHttp($server,'/api/v1/client/support-ui/detail?id='.$a['id'],$client);same(200,$d[0]);same($b['id'],$d[1]['data']['merge_alias']['target_id']);$reply=bHttp($server,'/api/v1/client/support/reply',$client,['id'=>$a['id'],'version'=>2,'body'=>'Synthetic post merge reply']);same(409,$reply[0]);same(true,str_contains($reply[2],$b['id']));}finally{$server->stop();}
});
test('F5-G 24 client cannot access staff merge route and current membership revoke denies execute',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$f['db']->execute('DELETE FROM support_department_members WHERE user_id=?',[$f['admin']['user']]);same(404,raises(HttpError::class,fn()=>gExecute($f,$p))->status);same(0,gCount($f));
});
test('F5-G 25 changed policy invalidates confirmation and expired confirmation cannot execute',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);fPolicy($f);same('MERGE_CONFIRMATION_INVALID',raises(HttpError::class,fn()=>gExecute($f,$p))->errorCode);$p=gPrepare($f,$a,$b);$f['db']->execute('UPDATE support_work_confirmations SET expires_at=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6)) WHERE id=?',[$p['confirmation']['id']]);same('MERGE_CONFIRMATION_INVALID',raises(HttpError::class,fn()=>gExecute($f,$p))->errorCode);
});
test('F5-G 26 uncertain RUNNING queue job or active sandbox migration blocks merge',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$queue=new \Hostider\Core\Queue\Queue($f['db']);$f['db']->transaction(fn()=>$queue->enqueue('synthetic.pending',['ticket_id'=>$a['id']],'synthetic-merge-job',Id::uuid(),true));$f['db']->execute("UPDATE jobs SET status='RUNNING' WHERE dedupe_key='synthetic-merge-job'");same('MERGE_OPERATION_UNCERTAIN',raises(HttpError::class,fn()=>gPrepare($f,$a,$b))->errorCode);$f['db']->execute("UPDATE jobs SET status='SUCCEEDED' WHERE dedupe_key='synthetic-merge-job'");$f['db']->execute('CREATE TABLE f6b_runs(synthetic_state VARCHAR(16)) ENGINE=InnoDB');same('MERGE_MIGRATION_BLOCKED',raises(HttpError::class,fn()=>gPrepare($f,$a,$b))->errorCode);
});
test('F5-G 27 fresh056 prefix055 upgrade and replay preserve all previous migration rows',function():void{
 $db=isolatedDomainDb();$root=dirname(__DIR__);$dir=$root.'/storage/f5g-prefix-'.bin2hex(random_bytes(5));mkdir($dir,0700);foreach(glob($root.'/migrations/*.php') as $file){if(basename($file)<'056'){copy($file,$dir.'/'.basename($file));}}
 same(55,count((new Migrator($db,$dir))->migrate()));$old=$db->execute('SELECT * FROM schema_migrations ORDER BY version')->fetchAll();$m=new Migrator($db,gMigration056Path());same(['056_support_ticket_merge.php'],$m->migrate());same([],$m->migrate());$m->assertReady();same($old,$db->execute("SELECT * FROM schema_migrations WHERE version<'056' ORDER BY version")->fetchAll());same(56,(int)$db->execute('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
});
test('F5-G 28 partial056 failure blocks runtime and requires reviewed forward completion',function():void{
 $db=isolatedDomainDb();$root=dirname(__DIR__);$dir=$root.'/storage/f5g-fail-'.bin2hex(random_bytes(5));mkdir($dir,0700);foreach(glob($root.'/migrations/*.php') as $file){if(basename($file)<'056'){copy($file,$dir.'/'.basename($file));}}(new Migrator($db,$dir))->migrate();$db->execute('CREATE TABLE support_merge_members(synthetic_collision INT) ENGINE=InnoDB');$m=new Migrator($db,gMigration056Path());raises(PDOException::class,fn()=>$m->migrate());raises(RuntimeException::class,fn()=>$m->assertReady());raises(RuntimeException::class,fn()=>$m->migrate());same('APPLYING',$db->execute("SELECT status FROM schema_migrations WHERE version='056_support_ticket_merge.php'")->fetchColumn());$db->execute('RENAME TABLE support_merge_members TO synthetic_quarantine_merge');$sql=require $root.'/migrations/056_support_ticket_merge.php';foreach(array_slice($sql,3) as $statement){$db->execute($statement);}$db->execute("UPDATE schema_migrations SET status='APPLIED',applied_at=UTC_TIMESTAMP(6) WHERE version='056_support_ticket_merge.php'");$m->assertReady();same([],$m->migrate());
});
test('F5-G 29 bounded mixed projection retains original IDs timestamps and stable provenance',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);for($i=0;$i<50;++$i){bReply($f,$i%2?$a:$b,'note');}gMerge($f,$a,$b);$d=gDetail($f,$b,true);same(50,count($d['messages']));same(true,$d['older_messages']);foreach($d['messages'] as $m){$row=$f['db']->execute('SELECT ticket_id,author_id,created_at FROM support_messages WHERE id=?',[$m['id']])->fetch();same($row['ticket_id'],$m['origin_ticket_id']);same($row['created_at'],$m['created_at']);same($row['author_id'],$m['author_id']);}same(2,count(gDetail($f,$b)['messages']));
});
test('F5-G 30 merged target cannot move departments and production gate denies merge',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);gMerge($f,$a,$b);supportGrant($f,$f['departments'][1],'*');same('MERGE_DEPARTMENT_PINNED',raises(HttpError::class,fn()=>$f['service']->command($f['admin']['reference'],'admin','move',['id'=>$b['id'],'version'=>2,'department_id'=>$f['departments'][1]],Id::uuid(),Id::uuid()))->errorCode);$c=$f['config'];$prod=new \Hostider\Commerce\Services($f['db'],new \Hostider\Core\Config('production',$c->url,$c->key,$c->dsn,$c->dbUser,$c->dbPassword));same(503,raises(HttpError::class,fn()=>(new \Hostider\Support\Merge\Commands($prod))->command($f['admin']['reference'],'prepare',['action'=>'merge','payload'=>gPayload($f,$a,$b)],Id::uuid(),Id::uuid()))->status);
});

test('F5-G 31 attempted pending mail blocks merge without creating new delivery',function():void{
 $f=gFixture(mail:true);$a=bTicket($f);$b=bTicket($f);bReply($f,$a);$f['db']->execute("UPDATE support_mail_outbound SET status='PENDING',attempt_count=1 WHERE ticket_id=?",[$a['id']]);same(true,(int)$f['db']->execute('SELECT COUNT(*) FROM support_mail_outbound')->fetchColumn()>0);same('MERGE_OPERATION_UNCERTAIN',raises(HttpError::class,fn()=>gPrepare($f,$a,$b))->errorCode);same(0,gCount($f));
});
test('F5-G 32 fresh install056 has empty aliases and second migration is no op',function():void{
 $db=isolatedDomainDb();$m=new Migrator($db,gMigration056Path());same(56,count($m->migrate()));same([],$m->migrate());$m->assertReady();same(0,(int)$db->execute('SELECT COUNT(*) FROM support_ticket_merges')->fetchColumn());
});
test('F5-G 33 current authorization epoch changes require new review even if merge grant survives',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);$p=gPrepare($f,$a,$b);$f['db']->execute("DELETE FROM identity_grants WHERE permission='support.ticket.internal_note'");same('MERGE_CONFIRMATION_INVALID',raises(HttpError::class,fn()=>gExecute($f,$p))->errorCode);same(0,gCount($f));
});

test('F5-G 34 archived source keeps assignment snapshot but cannot inflate active least load',function():void{
 $f=bFixture('LEAST_LOAD');$assignment=$f['db']->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL',[$f['admin']['user']])->fetchColumn();$f['db']->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope,department_scope) VALUES (?,?,?,?,?)',[Id::uuid(),$assignment,'support.ticket.merge',$f['client']['customer'],$f['departments'][0]]);$f['merge']=new \Hostider\Support\Merge\Commands($f['c']);$a=bTicket($f,'CRITICAL');$b=bTicket($f);bCommand($f,'assign',['id'=>$a['id'],'version'=>(int)bRow($f,$a)['version'],'assignee_id'=>$f['admin']['user']]);bCommand($f,'assign',['id'=>$b['id'],'version'=>(int)bRow($f,$b)['version'],'assignee_id'=>$f['staff']['user']]);$work=bWork($f,$a);gMerge($f,$a,$b);same($work,bWork($f,$a));$c=bTicket($f);$f['engine']->tick($c['id']);same($f['admin']['user'],bWork($f,$c)['assignee_id']);
});

test('F5-G 35 source alias poll observes new target reply without rewriting source version',function():void{
 $f=gFixture();$a=bTicket($f);$b=bTicket($f);gMerge($f,$a,$b);$old=gDetail($f,$a);bReply($f,$b,client:true);$views=new \Hostider\Support\UI\Views(new \Hostider\Support\UI\Context(new \Hostider\Support\Advanced\Common($f['c'])));$new=$views->detail($f['client']['reference'],'client',['id'=>$a['id'],'snapshot'=>$old['snapshot']],true);same(true,$new['changed']);same(2,$new['ticket']['version']);same(3,$new['merge_alias']['target_version']);same(3,count($new['messages']));
});
test('F5-G 36 pending source and target automation jobs skip stale versions and replay no effect',function():void{
 $f=gFixture();dGrant($f);$f['advanced']=new \Hostider\Support\Advanced\Common($f['c']);$f['automation']=new \Hostider\Support\Automation\Engine($f['advanced']);$a=bTicket($f);$b=bTicket($f);dRule($f,$a,dSpec());$p=dStage($f,$a);$q=dStage($f,$b);same(true,is_string($p['event_uuid']));same(true,is_string($q['event_uuid']));gMerge($f,$a,$b);dPublish($f);same('SUCCEEDED',$f['automation']->consume($p));same('SUCCEEDED',$f['automation']->consume($q));same(2,(int)$f['db']->execute("SELECT COUNT(*) FROM support_rule_executions WHERE result='SKIPPED' AND reason='STALE_TICKET'")->fetchColumn());same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_ticket_tags')->fetchColumn());$h=gHash($f,['support_rule_executions','support_rule_action_results','support_tickets']);same('SUCCEEDED',$f['automation']->consume($p));same($h,gHash($f,['support_rule_executions','support_rule_action_results','support_tickets']));
});
exit(executeTests(true));



