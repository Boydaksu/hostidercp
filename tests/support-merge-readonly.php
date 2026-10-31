<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/support-merge-fixture.php';
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Http\HttpError;
use Hostider\Support\Advanced\{Common,Csat,Incidents};
function rFixture(bool $mail=false):array{$f=gFixture(mail:$mail);dGrant($f);$f['advanced']=new Common($f['c']);$f['csat']=new Csat($f['advanced']);dCommand($f,'csat-policy',['department_id'=>$f['departments'][0],'version'=>0,'window_seconds'=>3600]);return $f;}
function rPayload(array $f,array $t,int $v=0,int $score=4):array{return ['ticket_id'=>$t['id'],'ticket_version'=>(int)bRow($f,$t)['version'],'version'=>$v,'score'=>$score,'comment'=>'Synthetic R1 own rating'];}
function rRate(array $f,array $t,int $v=0,?string $key=null):array{return $f['csat']->command($f['client']['reference'],rPayload($f,$t,$v),$key??Id::uuid(),Id::uuid());}
function rSnapshot(array $f):array{$out=[];$tables=$f['db']->execute("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND (table_name LIKE 'support_%' OR table_name IN ('audit_logs','outbox_events','inbox_events','jobs','idempotency_keys')) ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);foreach($tables as $table){$rows=$f['db']->execute('SELECT * FROM `'.$table.'`')->fetchAll();usort($rows,fn($a,$b)=>strcmp(CanonicalJson::encode($a),CanonicalJson::encode($b)));$out[$table]=hash('sha256',CanonicalJson::encode($rows));}return $out;}
function rPair(array $f,bool $targetResolved=false):array{$a=bTicket($f);$b=bTicket($f);fResolve($f,$a);if($targetResolved){fResolve($f,$b);}return [$a,$b];}
function rRace(array $f,array $requests):array{$children=[];foreach($requests as $p){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/support-merge-readonly-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,bEnvironment($f));if(!is_resource($proc)){throw new RuntimeException('R1_CHILD');}fwrite($pipes[0],json_encode($p,JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$proc,$pipes];}$out=[];foreach($children as [$proc,$pipes]){$raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($proc));same('',$err);$out[]=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}return $out;}

test('F5-G-R1 01 native HTTP source CSAT CREATE rejects with all business snapshots unchanged',function():void{
 $f=rFixture();[$a,$b]=rPair($f);gMerge($f,$a,$b);$server=new HttpTestServer(dirname(__DIR__),bEnvironment($f),__DIR__.'/commerce-router.php');$auth=['cookies'=>[],'csrf'=>''];try{dClientLogin($server,$f['client'],$auth);$before=rSnapshot($f);$res=bHttp($server,'/api/v1/client/support-advanced/csat',$auth,rPayload($f,$a));$after=rSnapshot($f);
 if($res[0]!==409){$path=dirname(__DIR__).'/docs/evidence/f5-g-r1/initial-csat-observation.json';if(!is_file($path)){file_put_contents($path,json_encode(['native_http_status'=>$res[0],'expected_status'=>409,'source_version'=>(int)bRow($f,$a)['version'],'ratings'=>(int)$f['db']->execute('SELECT COUNT(*) FROM support_ratings')->fetchColumn(),'rating_revisions'=>(int)$f['db']->execute('SELECT COUNT(*) FROM support_rating_revisions')->fetchColumn(),'before_sha256'=>$before,'after_sha256'=>$after,'business_unchanged'=>$before===$after],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));}}
 same(409,$res[0]);same($before,$after);same(true,str_contains($res[2],$b['id']));same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_ratings')->fetchColumn());}finally{$server->stop();}
});
test('F5-G-R1 02 source CSAT UPDATE and completed-key replay deny without new revisions',function():void{
 $f=rFixture();[$a,$b]=rPair($f);$payload=rPayload($f,$a);$key=Id::uuid();rRate($f,$a,key:$key);gMerge($f,$a,$b);$before=rSnapshot($f);same(409,raises(HttpError::class,fn()=>rRate($f,$a,1))->status);same($before,rSnapshot($f));same(409,raises(HttpError::class,fn()=>$f['csat']->command($f['client']['reference'],$payload,$key,Id::uuid()))->status);same($before,rSnapshot($f));same(1,(int)$f['db']->execute('SELECT COUNT(*) FROM support_rating_revisions')->fetchColumn());
});
test('F5-G-R1 03 normal unmerged CSAT CREATE UPDATE and exact replay keep native semantics',function():void{
 $f=rFixture();[$a,$b]=rPair($f);$p=rPayload($f,$a);$key=Id::uuid();$x=$f['csat']->command($f['client']['reference'],$p,$key,Id::uuid());$before=rSnapshot($f);same($x,$f['csat']->command($f['client']['reference'],$p,$key,Id::uuid()));same($before,rSnapshot($f));same(2,rRate($f,$a,1)['version']);same(2,(int)$f['db']->execute('SELECT COUNT(*) FROM support_rating_revisions')->fetchColumn());
});
test('F5-G-R1 04 merged TARGET CSAT works under native policy status and version',function():void{
 $f=rFixture();[$a,$b]=rPair($f,true);gMerge($f,$a,$b);same(1,rRate($f,$b)['version']);same(2,rRate($f,$b,1)['version']);same($b['id'],$f['db']->execute('SELECT ticket_id FROM support_ratings')->fetchColumn());same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_ratings WHERE ticket_id=?',[$a['id']])->fetchColumn());
});
test('F5-G-R1 05 source historical CSAT view remains readable and read only',function():void{
 $f=rFixture();[$a,$b]=rPair($f);rRate($f,$a);gMerge($f,$a,$b);$before=rSnapshot($f);$v=$f['csat']->view($f['client']['reference'],'client',['ticket_id'=>$a['id']]);same(4,$v['score']);same('Synthetic R1 own rating',$v['comment']);same($before,rSnapshot($f));same(404,raises(HttpError::class,fn()=>$f['csat']->view($f['other']['reference'],'client',['ticket_id'=>$a['id']]))->status);
});
test('F5-G-R1 06 native HTTP customer IDOR rejects without leaking source or target',function():void{
 $f=rFixture();[$a,$b]=rPair($f);gMerge($f,$a,$b);$server=new HttpTestServer(dirname(__DIR__),bEnvironment($f),__DIR__.'/commerce-router.php');$auth=['cookies'=>[],'csrf'=>''];try{dClientLogin($server,$f['other'],$auth);$before=rSnapshot($f);$res=bHttp($server,'/api/v1/client/support-advanced/csat',$auth,rPayload($f,$a));same(404,$res[0]);same(false,str_contains($res[2],$b['id']));same(false,str_contains($res[2],$a['id']));same($before,rSnapshot($f));}finally{$server->stop();}
});
test('F5-G-R1 07 stale normal and merged source ticket versions never create a revision',function():void{
 $f=rFixture();[$a,$b]=rPair($f);$p=rPayload($f,$a);$p['ticket_version']=1;$before=rSnapshot($f);same('SUPPORT_VERSION_CONFLICT',raises(HttpError::class,fn()=>$f['csat']->command($f['client']['reference'],$p,Id::uuid(),Id::uuid()))->errorCode);same($before,rSnapshot($f));gMerge($f,$a,$b);$before=rSnapshot($f);same(409,raises(HttpError::class,fn()=>$f['csat']->command($f['client']['reference'],$p,Id::uuid(),Id::uuid()))->status);same($before,rSnapshot($f));
});
test('F5-G-R1 08 native CSRF rejection creates no rating audit outbox or revision',function():void{
 $f=rFixture();[$a,$b]=rPair($f);gMerge($f,$a,$b);$server=new HttpTestServer(dirname(__DIR__),bEnvironment($f),__DIR__.'/commerce-router.php');$auth=['cookies'=>[],'csrf'=>''];try{dClientLogin($server,$f['client'],$auth);$before=rSnapshot($f);same(403,bHttp($server,'/api/v1/client/support-advanced/csat',$auth,rPayload($f,$a),csrf:false)[0]);same($before,rSnapshot($f));}finally{$server->stop();}
});
test('F5-G-R1 09 revoked session denies CSAT and cached replay',function():void{
 $f=rFixture();[$a,$b]=rPair($f);$p=rPayload($f,$a);$key=Id::uuid();rRate($f,$a,key:$key);$f['db']->transaction(fn()=>$f['c']->identity->invalidate($f['client']['user']));$before=rSnapshot($f);same(401,raises(HttpError::class,fn()=>$f['csat']->command($f['client']['reference'],$p,$key,Id::uuid()))->status);same($before,rSnapshot($f));
});
test('F5-G-R1 10 real CSAT and merge process race serializes without post merge rating mutation',function():void{
 $f=rFixture();[$a,$b]=rPair($f);$body=gPrepare($f,$a,$b);$p=rPayload($f,$a);$res=rRace($f,[['op'=>'merge','ref'=>$f['admin']['reference'],'body'=>$body,'key'=>Id::uuid()],['op'=>'csat','ref'=>$f['client']['reference'],'body'=>$p,'key'=>Id::uuid()]]);foreach($res as $x){same(true,in_array($x['status'],[200,409],true));}if(gCount($f)===0){gMerge($f,$a,$b);}same(1,gCount($f));$count=(int)$f['db']->execute('SELECT COUNT(*) FROM support_rating_revisions')->fetchColumn();same($res[1]['status']===200?1:0,$count);$before=rSnapshot($f);same(409,raises(HttpError::class,fn()=>rRate($f,$a,$count))->status);same($before,rSnapshot($f));
});
test('F5-G-R1 11 service operations work and UI drafts reject SOURCE business writes',function():void{
 $f=rFixture();[$a,$b]=rPair($f);gMerge($f,$a,$b);$before=rSnapshot($f);foreach(['reply','note'] as $action){same(409,raises(HttpError::class,fn()=>bReply($f,$a,$action))->status);}same(409,raises(HttpError::class,fn()=>bState($f,$a,'CLOSED'))->status);same(409,raises(HttpError::class,fn()=>dCommand($f,'priority',['ticket_id'=>$a['id'],'version'=>(int)bRow($f,$a)['version'],'value'=>'HIGH','reason'=>'Synthetic']))->status);same(409,raises(HttpError::class,fn()=>bCommand($f,'assign',['id'=>$a['id'],'version'=>(int)bRow($f,$a)['version'],'assignee_id'=>null]))->status);$store=new \Hostider\Support\UI\StateStore(new \Hostider\Support\UI\Context($f['advanced']));same(409,raises(HttpError::class,fn()=>$store->command($f['client']['reference'],'client','draft-save',['ticket_id'=>$a['id'],'kind'=>'reply','body'=>'Synthetic','base_version'=>(int)bRow($f,$a)['version'],'version'=>0],Id::uuid(),Id::uuid()))->status);same($before,rSnapshot($f));
});
test('F5-G-R1 12 native incident link seam rejects SOURCE loaded with view permission',function():void{
 $f=rFixture();[$a,$b]=rPair($f);$incident=dCommand($f,'incident-new',['department_id'=>$f['departments'][0],'version'=>0,'title'=>'Synthetic incident','description'=>'Synthetic public','private_root'=>'Synthetic private','affected'=>'Synthetic']);gMerge($f,$a,$b);$before=rSnapshot($f);same(409,raises(HttpError::class,fn()=>$f['c']->identity->withStepUp($f['admin']['reference'],'admin',function($u,$s)use($f,$a,$incident){$t=$f['service']->access->ticket($u,$s,$a['id']);return (new Incidents($f['advanced']))->link($u,$s,$t,$incident['id'],Id::uuid());}))->status);same($before,rSnapshot($f));
});
test('F5-G-R1 13 queued source mail ready does not mutate frozen outbound state',function():void{
 $f=rFixture(true);[$a,$b]=rPair($f);bReply($f,$a);$mail=cDelivery($f,$a);$p=['event_uuid'=>$mail['event_id'],'support_event_uuid'=>$mail['support_event_id'],'ticket_id'=>$a['id'],'customer_id'=>$f['client']['customer'],'message_id'=>$mail['message_id']];gMerge($f,$a,$b);dPublish($f);$before=rSnapshot($f);same('FAILED',$f['outbound']->ready($p));same($before,rSnapshot($f));same('FAILED',$f['outbound']->ready($p));same($before,rSnapshot($f));
});
test('F5-G-R1 14 SOURCE original attachment read works upload rejects and TARGET mail stays native',function():void{
 $f=rFixture(true);[$a,$b]=rPair($f);$upload=['ticket_id'=>$a['id'],'message_id'=>$a['message_id'],'version'=>(int)bRow($f,$a)['version'],'name'=>'synthetic.pdf','base64'=>base64_encode(\Hostider\Support\Mail\Attachments::FIXTURE)];$file=$f['attachments']->upload($f['client']['reference'],'client',$upload,Id::uuid(),Id::uuid());gMerge($f,$a,$b);$token=$f['attachments']->token($f['client']['reference'],'client',$file['id']);same(\Hostider\Support\Mail\Attachments::FIXTURE,$f['attachments']->download($f['client']['reference'],'client',$file['id'],$token['token'],Id::uuid())['bytes']);$upload['version']=(int)bRow($f,$a)['version'];$before=rSnapshot($f);same(409,raises(HttpError::class,fn()=>$f['attachments']->upload($f['client']['reference'],'client',$upload,Id::uuid(),Id::uuid()))->status);same($before,rSnapshot($f));bReply($f,$b);$mail=cDelivery($f,$b);dPublish($f);$p=['event_uuid'=>$mail['event_id'],'support_event_uuid'=>$mail['support_event_id'],'ticket_id'=>$b['id'],'customer_id'=>$f['client']['customer'],'message_id'=>$mail['message_id']];same('SUCCEEDED',$f['outbound']->ready($p));same('HELD',cDelivery($f,$b)['status']);
});
test('F5-G-R1 15 alias disclosure fails closed when target current scope is inconsistent',function():void{
 $f=rFixture();[$a,$b]=rPair($f);gMerge($f,$a,$b);$f['db']->execute('UPDATE support_tickets SET department_id=? WHERE id=?',[$f['departments'][1],$b['id']]);$before=rSnapshot($f);$x=raises(HttpError::class,fn()=>rRate($f,$a));same(404,$x->status);same(false,str_contains($x->errorCode,$b['id']));same($before,rSnapshot($f));
});
test('F5-G-R1 16 TARGET native unresolved window and rating versions remain enforced',function():void{
 $f=rFixture();[$a,$b]=rPair($f);gMerge($f,$a,$b);same('CSAT_RESOLVED_REQUIRED',raises(HttpError::class,fn()=>rRate($f,$b))->errorCode);fResolve($f,$b);rRate($f,$b);$before=rSnapshot($f);same('CSAT_VERSION_CONFLICT',raises(HttpError::class,fn()=>rRate($f,$b))->errorCode);same($before,rSnapshot($f));$f['db']->execute('UPDATE support_csat_policies SET window_seconds=1 WHERE department_id=?',[$f['departments'][0]]);usleep(1100000);$before=rSnapshot($f);same('CSAT_WINDOW_EXPIRED',raises(HttpError::class,fn()=>rRate($f,$b,1))->errorCode);same($before,rSnapshot($f));
});

test('F5-G-R1 17 current writable proof detects merge after repeatable read snapshot starts',function():void{
 $f=rFixture();[$a,$b]=rPair($f);$body=gPrepare($f,$a,$b);$f['db']->execute('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
 $f['db']->transaction(function()use($f,$a,$b,$body):void{same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_merge_members')->fetchColumn());$res=rRace($f,[['op'=>'merge','ref'=>$f['admin']['reference'],'body'=>$body,'key'=>Id::uuid()]]);same(200,$res[0]['status']);same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_merge_members')->fetchColumn());same(409,raises(HttpError::class,fn()=>$f['c']->identity->locked($f['client']['reference'],'client',function($u,$s)use($f,$a){$t=$f['service']->access->ticket($u,$s,$a['id']);same(3,(int)$t['version']);(new \Hostider\Support\Merge\Projection($f['c']))->writable($u,$s,$t);}))->status);});same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_ratings')->fetchColumn());
});
test('F5-G-R1 18 canonical inbound reply rejects SOURCE after an earlier read snapshot',function():void{
 $f=rFixture();[$a,$b]=rPair($f);$body=gPrepare($f,$a,$b);$f['db']->execute('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
 $f['db']->transaction(function()use($f,$a,$body):void{same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_merge_members')->fetchColumn());$res=rRace($f,[['op'=>'merge','ref'=>$f['admin']['reference'],'body'=>$body,'key'=>Id::uuid()]]);same(200,$res[0]['status']);same(409,raises(HttpError::class,fn()=>$f['service']->inbound($f['client']['reference'],'reply',['id'=>$a['id'],'version'=>3,'body'=>'Synthetic stale snapshot reply'],Id::uuid()))->status);});same(2,(int)$f['db']->execute('SELECT COUNT(*) FROM support_messages')->fetchColumn());same(3,(int)bRow($f,$a)['version']);
});

test('F5-G-R1 19 target department pin uses current merge relationship after old snapshot',function():void{
 $f=rFixture();supportGrant($f,$f['departments'][1],'*');[$a,$b]=rPair($f);$body=gPrepare($f,$a,$b);$f['db']->execute('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');$f['db']->transaction(function()use($f,$a,$b,$body):void{same(0,(int)$f['db']->execute('SELECT COUNT(*) FROM support_merge_members')->fetchColumn());$res=rRace($f,[['op'=>'merge','ref'=>$f['admin']['reference'],'body'=>$body,'key'=>Id::uuid()]]);same(200,$res[0]['status']);same('MERGE_DEPARTMENT_PINNED',raises(HttpError::class,fn()=>$f['service']->compose($f['admin']['reference'],'admin','move',['id'=>$b['id'],'version'=>2,'department_id'=>$f['departments'][1]],Id::uuid()))->errorCode);});same($f['departments'][0],bRow($f,$b)['department_id']);
});
exit(executeTests());


