<?php
declare(strict_types=1);
require_once __DIR__.'/support-d-fixture.php';
require_once __DIR__.'/support-c-fixture.php';
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Queue\{Queue,Worker};
use Hostider\Support\AutoClose\{Policy as AutoPolicy,Engine as AutoEngine};

function fFixture(bool $sla=false,bool $mail=false):array
{
    $f=$mail?cFixture($sla):supportFixture();supportGrant($f,$f['departments'][0],'*');
    if($sla&&!$mail){bPublish($f,bPolicy());}
    $f['auto']=new AutoEngine($f['c']);$f['auto_policy']=new AutoPolicy($f['c']);return $f;
}
function fPolicy(array $f,bool $enabled=true,?int $seconds=1,?string $dep=null):array
{
    $dep??=$f['departments'][0];$v=(int)($f['db']->execute('SELECT version FROM support_auto_close_heads WHERE department_id=?',[$dep])->fetchColumn()?:0);
    $body=['action'=>'policy-set','payload'=>['department_id'=>$dep,'version'=>$v,'enabled'=>$enabled,'wait_seconds'=>$enabled?$seconds:null]];
    $a=$f['auto_policy']->command($f['admin']['reference'],'prepare',$body,Id::uuid(),Id::uuid());
    $body['confirmation']=['id'=>$a['id'],'accepted'=>true];
    return $f['auto_policy']->command($f['admin']['reference'],'execute',$body,Id::uuid(),Id::uuid());
}
function fResolve(array $f,array $t):array
{
    return $f['service']->command($f['admin']['reference'],'admin','state',['id'=>$t['id'],'version'=>(int)bRow($f,$t)['version'],'status'=>'RESOLVED'],Id::uuid(),Id::uuid());
}
function fTicket(array $f):array {$t=bTicket($f);fResolve($f,$t);return $t;}
function fDue():void {usleep(1100000);}
function fWorker(array $f):bool {$w=new Worker($f['db'],new Queue($f['db']));$f['auto']->register($w);return $w->once();}
function fClosed(array $f):int{return (int)$f['db']->execute('SELECT COUNT(*) FROM support_auto_close_events')->fetchColumn();}
function fSnapshot(array $f):array
{
    $v=[];foreach(['support_tickets','support_messages','support_auto_close_heads','support_auto_close_policies','support_auto_close_events','support_events','support_work_events','support_sla_history','support_sla_cycles','support_sla_clocks','audit_logs','outbox_events','jobs'] as $table){$rows=$f['db']->execute('SELECT * FROM '.$table)->fetchAll();usort($rows,fn($a,$b)=>strcmp(CanonicalJson::encode($a),CanonicalJson::encode($b)));$v[$table]=hash('sha256',CanonicalJson::encode($rows));}return $v;
}
function fRace(array $f,array $payloads):array
{
    $children=[];foreach($payloads as $p){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/support-auto-close-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,bEnvironment($f));if(!is_resource($proc)){throw new RuntimeException('F5F_CHILD');}fwrite($pipes[0],json_encode($p,JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$proc,$pipes];}
    $out=[];foreach($children as [$proc,$pipes]){$s=stream_get_contents($pipes[1]);$e=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($proc));same('',$e);$out[]=json_decode($s,true,16,JSON_THROW_ON_ERROR);}return $out;
}
function fCli(array $f,array $args):array
{
    $pipes=[];$proc=proc_open([PHP_BINARY,dirname(__DIR__).'/bin/console.php',...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,bEnvironment($f));
    if(!is_resource($proc)){throw new RuntimeException('F5F_CLI');}fclose($pipes[0]);$s=stream_get_contents($pipes[1]);$e=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
    return ['exit'=>$exit,'stderr'=>$e,'report'=>json_decode($s,true,32,JSON_THROW_ON_ERROR)];
}
