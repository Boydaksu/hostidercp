<?php
declare(strict_types=1);
require_once __DIR__.'/support-fixture.php';require_once __DIR__.'/support/HttpTestServer.php';
function bCalendar(string $zone='UTC',bool $weekdays=false): array
{
    $weekly=[];foreach(range(1,7) as $n){$weekly[$n]=$weekdays?($n<=5?[['09:00','18:00']]:[]):[['00:00','24:00']];}return ['timezone'=>$zone,'weekly'=>$weekly,'exceptions'=>[]];
}
function bPolicy(string $mode='MANUAL',array $pause=[]): array
{
    $targets=[];foreach(\Hostider\Support\Work\Policy::PRIORITIES as $p){$targets[$p]=['FIRST'=>60,'NEXT'=>30,'RESOLUTION'=>240];}return ['calendar'=>bCalendar(),'targets'=>$targets,'pause'=>['FIRST'=>[],'NEXT'=>[],'RESOLUTION'=>$pause],'risk_percent'=>80,'assignment'=>['mode'=>$mode,'weights'=>['LOW'=>1,'NORMAL'=>1,'HIGH'=>2,'CRITICAL'=>4]]];
}
function bCommand(array $f,string $action,array $p,?string $ref=null): array
{
    $cmd=new \Hostider\Support\Work\Commands($f['c']);$ref??=$f['admin']['reference'];$body=['action'=>$action,'payload'=>$p];$a=$cmd->command($ref,'prepare',$body,\Hostider\Core\Id::uuid(),\Hostider\Core\Id::uuid());$body['confirmation']=['id'=>$a['id'],'accepted'=>true];return $cmd->command($ref,'execute',$body,\Hostider\Core\Id::uuid(),\Hostider\Core\Id::uuid());
}
function bPublish(array $f,array $policy,?string $dep=null): array
{
    $dep??=$f['departments'][0];$v=(int)($f['db']->execute('SELECT version FROM support_work_heads WHERE department_id=?',[$dep])->fetchColumn()?:0);return bCommand($f,'policy-publish',['department_id'=>$dep,'version'=>$v,'policy'=>$policy]);
}
function bRoster(array $f,string $user,array $changes=[],?string $dep=null): array
{
    $dep??=$f['departments'][0];$v=(int)($f['db']->execute('SELECT version FROM support_work_roster_heads WHERE department_id=? AND user_id=?',[$dep,$user])->fetchColumn()?:0);$at=\Hostider\Support\Work\Time::now($f['db']);$r=array_replace(['calendar'=>bCalendar(),'available'=>true,'leaves'=>[],'priorities'=>['LOW','NORMAL','HIGH','CRITICAL'],'expires_at'=>\Hostider\Support\Work\Time::format($at+864_000_000_000)],$changes);return bCommand($f,'roster-set',['department_id'=>$dep,'user_id'=>$user,'version'=>$v,'roster'=>$r]);
}
function bFixture(?string $mode='MANUAL',bool $rosters=true): array
{
    $f=supportFixture();$dep=$f['departments'][0];supportGrant($f,$dep);$db=$f['db'];$a=$db->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL',[$f['admin']['user']])->fetchColumn();$db->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope,department_scope) VALUES (?,?,?,?,?)',[\Hostider\Core\Id::uuid(),$a,'support.ticket.assign',$f['client']['customer'],$dep]);
    $staff=commerceUser($f['c'],'sla-staff');$f['c']->identity->staff($f['admin']['reference'],'admin',$staff['user'],true,false);$role=$db->execute("SELECT id FROM identity_roles WHERE name='Destek'")->fetchColumn();$a=$db->transaction(fn()=>$f['c']->identity->assign($staff['user'],$role));supportGrant($f,$dep,user:$staff['user']);$db->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope,department_scope) VALUES (?,?,?,?,?)',[\Hostider\Core\Id::uuid(),$a,'support.ticket.assign',$f['client']['customer'],$dep]);
    $staff['reference']=\Hostider\Core\Id::uuid();$f['c']->identity->login($staff['email'],$staff['password'],'admin',$staff['reference'],\Hostider\Core\Id::uuid());$factor=$f['c']->identity->enroll($staff['reference'],'admin',$staff['password']);$staff['recovery']=$f['c']->identity->confirmEnrollment($staff['reference'],'admin',\Hostider\Identity\Totp::code($factor['secret'],intdiv(time(),30)))['recovery_codes'];$f['c']->identity->stepUp($staff['reference'],'admin',$staff['password'],$staff['recovery'][7]);$f['staff']=$staff;
    $f['engine']=new \Hostider\Support\Work\Engine($f['c']);if($mode!==null){$f['policy']=bPublish($f,bPolicy($mode));}if($rosters){bRoster($f,$f['admin']['user']);bRoster($f,$staff['user']);}return $f;
}
function bTicket(array $f,string $priority='NORMAL',?string $dep=null,?array $who=null): array
{
    $who??=$f['client'];return $f['service']->command($who['reference'],'client','create',['department_id'=>$dep??$f['departments'][0],'subject'=>'Synthetic SLA assignment ticket','body'=>'Synthetic request','priority'=>$priority],\Hostider\Core\Id::uuid(),\Hostider\Core\Id::uuid());
}
function bRow(array $f,array $t): array{return $f['db']->execute('SELECT * FROM support_tickets WHERE id=?',[$t['id']])->fetch();}
function bWork(array $f,array $t): array{return $f['db']->execute('SELECT * FROM support_work_tickets WHERE ticket_id=?',[$t['id']])->fetch();}
function bReply(array $f,array $t,string $type='reply',bool $client=false): array
{
    return $f['service']->command($f[$client?'client':'admin']['reference'],$client?'client':'admin',$type,['id'=>$t['id'],'version'=>(int)bRow($f,$t)['version'],'body'=>'Synthetic '.$type],\Hostider\Core\Id::uuid(),\Hostider\Core\Id::uuid());
}
function bState(array $f,array $t,string $status): array{return $f['service']->command($f['admin']['reference'],'admin','state',['id'=>$t['id'],'version'=>(int)bRow($f,$t)['version'],'status'=>$status],\Hostider\Core\Id::uuid(),\Hostider\Core\Id::uuid());}
function bHold(array $f): void
{
    $d=$f['db']->execute('SELECT * FROM support_departments WHERE id=?',[$f['departments'][0]])->fetch();$f['service']->command($f['admin']['reference'],'admin','department',['id'=>$d['id'],'version'=>(int)$d['version'],'name'=>$d['name'],'enabled'=>true,'allow_hold'=>true,'allow_reopen'=>true],\Hostider\Core\Id::uuid(),\Hostider\Core\Id::uuid());
}
function bClocks(array $f,array $t): array{return $f['db']->execute('SELECT * FROM support_sla_clocks WHERE cycle_id=? ORDER BY metric,id',[bWork($f,$t)['cycle_id']])->fetchAll();}
function bSnapshot(array $f): array
{
    $out=[];foreach(['support_tickets','support_work_tickets','support_work_heads','support_sla_clocks','support_sla_alerts','support_work_events','support_messages','audit_logs','outbox_events'] as $table){$out[$table]=$f['db']->execute('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();}return $out;
}
function bEnvironment(array $f): array
{
    $c=$f['config'];return array_replace(getenv(),['APP_ENV'=>'test','APP_URL'=>$c->url,'APP_KEY'=>$c->key,'DB_DSN'=>$c->dsn,'DB_USER'=>$c->dbUser,'DB_PASSWORD'=>$c->dbPassword,'HTTP_TEST_ENGINE'=>'interpreter','EXTERNAL_MAIL_ALLOWED'=>'false','PAYMENT_LIVE_ALLOWED'=>'false','PROVISIONING_ALLOWED'=>'false']);
}
function bHttp(HttpTestServer $server,string $path,array &$auth,?array $body=null,?string $key=null,bool $csrf=true,?string $method=null): array
{
    $options=[];if($auth['cookies']){$options[CURLOPT_COOKIE]=implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($auth['cookies']),$auth['cookies']));}if($body!==null){$headers=['Content-Type: application/json','Idempotency-Key: '.($key??\Hostider\Core\Id::uuid())];if($csrf){$headers[]='X-CSRF-Token: '.$auth['csrf'];}$options+=[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode((object)$body,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>$headers];}if($method){$options[CURLOPT_CUSTOMREQUEST]=$method;}
    [$status,$raw,$headers]=$server->request($path,$options);foreach($headers as $h){if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$h,$m)){$auth['cookies'][$m[1]]=$m[2];}}$json=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(isset($json['data']['csrf'])){$auth['csrf']=$json['data']['csrf'];}return [$status,$json,$raw,$headers];
}
function bLogin(HttpTestServer $server,array $who,array &$auth): void
{
    bHttp($server,'/api/v1/admin/identity/bootstrap',$auth);same(200,bHttp($server,'/api/v1/admin/identity/login',$auth,['email'=>$who['email'],'password'=>$who['password']])[0]);same(200,bHttp($server,'/api/v1/admin/identity/mfa',$auth,['code'=>$who['recovery'][0]])[0]);same(200,bHttp($server,'/api/v1/admin/identity/step-up',$auth,['password'=>$who['password'],'code'=>$who['recovery'][1]])[0]);
}
function bRace(array $f,array $requests): array
{
    $children=[];foreach($requests as $r){$pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/support-b-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,bEnvironment($f));if(!is_resource($p)){throw new RuntimeException('Support child unavailable');}fwrite($pipes[0],json_encode($r,JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$p,$pipes];}$out=[];$bad=false;foreach($children as [$p,$pipes]){$raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);$bad=$bad||$code!==0||$err!=='';$out[]=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}same(false,$bad);return $out;
}
