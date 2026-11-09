<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';require_once __DIR__.'/database.php';require_once __DIR__.'/support-fixture.php';require_once __DIR__.'/support/HttpTestServer.php';
use Hostider\Core\{CanonicalJson,Id};
use Hostider\Core\Queue\{Outbox,Queue,Worker};
use Hostider\Support\EventSink;

function auditSupportFixture(): array
{
    $f=supportFixture();supportGrant($f,$f['departments'][0]);
    $q=new Queue($f['db']);$out=new Outbox($f['db'],$q);while($out->dispatchOne()){}
    $w=new Worker($f['db'],$q);(new \Hostider\Commerce\EventSink($f['db']))->register($w);while($w->once()){}
    return $f;
}
function auditTicket(array $f,string $subject='Synthetic audit ticket',?array $who=null,?string $department=null): array
{
    $who??=$f['client'];return $f['service']->command($who['reference'],'client','create',['department_id'=>$department??$f['departments'][0],'subject'=>$subject,'body'=>'Synthetic public message','priority'=>'NORMAL'],Id::uuid(),Id::uuid());
}
function auditSupportState(array $f,array $t,string $state,int $version): void
{
    $f['service']->command($f['admin']['reference'],'admin','state',['id'=>$t['id'],'version'=>$version,'status'=>$state],Id::uuid(),Id::uuid());
}
function auditEvents(array $f,string $ticket): array{return $f['db']->execute("SELECT event_uuid,payload,published_at FROM outbox_events WHERE event_type='support.changed' AND aggregate_id=? ORDER BY id",[$ticket])->fetchAll();}
function auditWorker(array $f): Worker{$q=new Queue($f['db']);$w=new Worker($f['db'],$q);(new EventSink($f['db'],$f['config']))->register($w);return $w;}
function auditReceipt(array $f,string $id): int{return (int)$f['db']->execute("SELECT COUNT(*) FROM inbox_events WHERE consumer='support.held.v1' AND event_uuid=?",[$id])->fetchColumn();}
function auditHistory(array $f): string
{
    $rows=[];foreach(['finance_journals','finance_lines','support_messages','identity_notifications'] as $table){$rows[$table]=$f['db']->execute('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();}return hash('sha256',CanonicalJson::encode($rows));
}

test('P1-1 distinct STATE events consume only their own dispatched receipt',function(): void{
    $f=auditSupportFixture();$t=auditTicket($f);$out=new Outbox($f['db'],new Queue($f['db']));$w=auditWorker($f);
    same(true,$out->dispatchOne());same(true,$w->once());$create=auditEvents($f,$t['id'])[0];same(1,auditReceipt($f,$create['event_uuid']));
    $history=auditHistory($f);auditSupportState($f,$t,'RESOLVED',1);auditSupportState($f,$t,'CLOSED',2);$events=auditEvents($f,$t['id']);same(3,count($events));
    $one=$events[1];$two=$events[2];same(false,$one['event_uuid']===$two['event_uuid']);$p1=json_decode($one['payload'],true,32,JSON_THROW_ON_ERROR);$p2=json_decode($two['payload'],true,32,JSON_THROW_ON_ERROR);
    unset($p1['event_uuid'],$p2['event_uuid']);same($p1,$p2);same(['ticket_id'=>$t['id'],'message_id'=>null],$p1);
    same(true,$out->dispatchOne());same(true,$w->once());same(1,auditReceipt($f,$one['event_uuid']));same(0,auditReceipt($f,$two['event_uuid']));same(null,auditEvents($f,$t['id'])[2]['published_at']);
    same(true,$out->dispatchOne());same(true,$w->once());same(1,auditReceipt($f,$two['event_uuid']));$sink=new EventSink($f['db'],$f['config']);same('SUCCEEDED',$sink->consume(json_decode($two['payload'],true,32,JSON_THROW_ON_ERROR)));same(1,auditReceipt($f,$two['event_uuid']));same($history,auditHistory($f));
});
test('P1-1 event ticket message private note and extra fields cannot forge receipt',function(): void{
    $f=auditSupportFixture();$t=auditTicket($f);$other=auditTicket($f);$note=$f['service']->command($f['admin']['reference'],'admin','note',['id'=>$t['id'],'version'=>1,'body'=>'Private audit note'],Id::uuid(),Id::uuid());
    $out=new Outbox($f['db'],new Queue($f['db']));while($out->dispatchOne()){}$event=auditEvents($f,$t['id'])[0];$p=json_decode($event['payload'],true,32,JSON_THROW_ON_ERROR);same($event['event_uuid'],$p['event_uuid']??null);$sink=new EventSink($f['db'],$f['config']);
    $private=$f['db']->execute("SELECT id FROM support_events WHERE ticket_id=? AND kind='INTERNAL_NOTE'",[$t['id']])->fetchColumn();$history=auditHistory($f);
    foreach([array_replace($p,['event_uuid'=>Id::uuid()]),array_replace($p,['ticket_id'=>$other['id']]),array_replace($p,['message_id'=>$other['message_id']]),array_replace($p,['message_id'=>$note['message_id']]),array_replace($p,['event_uuid'=>$private,'message_id'=>$note['message_id']]),$p+['visibility'=>true]] as $forged){same('FAILED',$sink->consume($forged));}
    same(0,auditReceipt($f,$event['event_uuid']));same(0,auditReceipt($f,$private));same('SUCCEEDED',$sink->consume($p));same(1,auditReceipt($f,$event['event_uuid']));same($history,auditHistory($f));
});
test('P1-1 unpublished legacy or mismatched native queue payload stays unreceived',function(): void{
    $f=auditSupportFixture();$t=auditTicket($f);$e=auditEvents($f,$t['id'])[0];$p=json_decode($e['payload'],true,32,JSON_THROW_ON_ERROR);$sink=new EventSink($f['db'],$f['config']);same('FAILED',$sink->consume($p));same(0,auditReceipt($f,$e['event_uuid']));
    $out=new Outbox($f['db'],new Queue($f['db']));same(true,$out->dispatchOne());$legacy=$p;unset($legacy['event_uuid']);same('FAILED',$sink->consume($legacy));$job=$f['db']->execute('SELECT id,payload FROM jobs WHERE dedupe_key=?',['outbox:'.$e['event_uuid']])->fetch();
    $f['db']->execute('UPDATE jobs SET payload=? WHERE id=?',[CanonicalJson::encode(array_replace($p,['ticket_id'=>Id::uuid()])),$job['id']]);same('FAILED',$sink->consume($p));same(0,auditReceipt($f,$e['event_uuid']));$f['db']->execute('UPDATE jobs SET payload=? WHERE id=?',[$job['payload'],$job['id']]);same(true,auditWorker($f)->once());same(1,auditReceipt($f,$e['event_uuid']));
});

/** Real native cookie/session HTTP, separate from application service calls used to seed rows. */
function auditHttp(HttpTestServer $server,string $ctx,string $route,array &$auth,?array $body=null): array
{
    $options=[];if($auth['cookies']){$options[CURLOPT_COOKIE]=implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($auth['cookies']),$auth['cookies']));}
    if($body!==null){$options+=[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode((object)$body,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.$auth['csrf'],'Idempotency-Key: '.Id::uuid()]];}
    [$status,$raw,$headers]=$server->request('/api/v1/'.$ctx.'/'.$route,$options);foreach($headers as $h){if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$h,$m)){$auth['cookies'][$m[1]]=$m[2];}}$json=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(isset($json['data']['csrf'])){$auth['csrf']=$json['data']['csrf'];}return [$status,$json,$raw];
}
function auditHttpServer(array $f): array
{
    $c=$f['config'];$env=array_replace(getenv(),['APP_ENV'=>'test','APP_URL'=>$c->url,'APP_KEY'=>$c->key,'DB_DSN'=>$c->dsn,'DB_USER'=>$c->dbUser,'DB_PASSWORD'=>$c->dbPassword,'HTTP_TEST_ENGINE'=>'interpreter','EXTERNAL_MAIL_ALLOWED'=>'false','PAYMENT_LIVE_ALLOWED'=>'false','PROVISIONING_ALLOWED'=>'false']);$server=new HttpTestServer(dirname(__DIR__),$env,__DIR__.'/commerce-router.php');$auth=['client'=>['cookies'=>[],'csrf'=>''],'admin'=>['cookies'=>[],'csrf'=>'']];
    foreach(['client','admin'] as $ctx){$who=$f[$ctx];auditHttp($server,$ctx,'identity/bootstrap',$auth[$ctx]);same(200,auditHttp($server,$ctx,'identity/login',$auth[$ctx],['email'=>$who['email'],'password'=>$who['password']])[0]);if($ctx==='admin'){same(200,auditHttp($server,$ctx,'identity/mfa',$auth[$ctx],['code'=>$who['recovery'][0]])[0]);}}
    return [$server,$auth];
}
function auditPageFixture(): array
{
    $f=auditSupportFixture();$ids=[];for($n=0;$n<51;++$n){$ids[]=auditTicket($f,'Nonmatching synthetic '.$n)['id'];}sort($ids,SORT_STRING);$match=$ids[50];
    // Fixture-only encrypted subject selection makes the match deterministically the 51st ID.
    $encrypted=(new \Hostider\Core\Security\Crypto(base64_decode($f['config']->key,true)))->encrypt('UNIQUE_SUBJECT_MATCH','support-subject:'.$match);$f['db']->execute('UPDATE support_tickets SET subject_encrypted=? WHERE id=?',[$encrypted,$match]);
    auditTicket($f,'FOREIGN_CUSTOMER_MATCH',$f['other']);auditTicket($f,'FOREIGN_DEPARTMENT_MATCH',$f['other'],$f['departments'][1]);$f['service']->command($f['admin']['reference'],'admin','note',['id'=>$match,'version'=>1,'body'=>'PRIVATE_SEARCH_SENTINEL'],Id::uuid(),Id::uuid());return [$f,$ids];
}
test('P1-2 search beyond first 50 is explicitly unsupported for client and department staff',function(): void{
    [$f,$ids]=auditPageFixture();[$server,$auth]=auditHttpServer($f);try{foreach(['client','admin'] as $ctx){foreach(['UNIQUE_SUBJECT_MATCH','FOREIGN_CUSTOMER_MATCH','FOREIGN_DEPARTMENT_MATCH','PRIVATE_SEARCH_SENTINEL',''] as $q){$r=auditHttp($server,$ctx,'support/list?offset=0&q='.rawurlencode($q),$auth[$ctx]);same(422,$r[0]);same('SUPPORT_SEARCH_UNSUPPORTED',$r[1]['errors'][0]['code']);same(null,$r[1]['data']);foreach(['UNIQUE_SUBJECT_MATCH','FOREIGN_','PRIVATE_SEARCH_SENTINEL'] as $secret){same(false,str_contains($r[2],$secret));}}}}finally{$server->stop();}
});
test('P1-2 unfiltered pagination covers authorized 51 once with matching client staff pages',function(): void{
    [$f,$expected]=auditPageFixture();[$server,$auth]=auditHttpServer($f);$all=[];try{foreach(['client','admin'] as $ctx){$seen=[];foreach([0,50,100] as $offset){$r=auditHttp($server,$ctx,'support/list?offset='.$offset,$auth[$ctx]);same(200,$r[0]);same(array_slice($expected,$offset,50),array_column($r[1]['data'],'id'));same(false,str_contains($r[2],'FOREIGN_'));same(false,str_contains($r[2],'PRIVATE_SEARCH_SENTINEL'));array_push($seen,...array_column($r[1]['data'],'id'));}same($expected,$seen);same(51,count(array_unique($seen)));$all[$ctx]=$seen;}same($all['client'],$all['admin']);}finally{$server->stop();}
});
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===realpath(__FILE__)){runTests();}
