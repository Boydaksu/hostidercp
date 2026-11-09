<?php
declare(strict_types=1);
require_once __DIR__.'/support-auto-close-fixture.php';
use Hostider\Support\Merge\Commands as MergeCommands;
use Hostider\Core\Id;
function gFixture(bool $sla=false,bool $mail=false):array
{
    $f=fFixture($sla,$mail);$assignment=$f['db']->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL',[$f['admin']['user']])->fetchColumn();
    $f['db']->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope,department_scope) VALUES (?,?,?,?,?)',[Id::uuid(),$assignment,'support.ticket.merge','*',$f['departments'][0]]);if($mail){$f['attachments']=$f['files'];}$f['merge']=new MergeCommands($f['c']);return $f;
}
function gPayload(array $f,array $a,array $b):array{return ['source_id'=>$a['id'],'target_id'=>$b['id'],'source_version'=>(int)bRow($f,$a)['version'],'target_version'=>(int)bRow($f,$b)['version']];}
function gPrepare(array $f,array $a,array $b):array{$body=['action'=>'merge','payload'=>gPayload($f,$a,$b)];$p=$f['merge']->command($f['admin']['reference'],'prepare',$body,Id::uuid(),Id::uuid());$body['confirmation']=['id'=>$p['id'],'accepted'=>true];return $body;}
function gExecute(array $f,array $body,?string $key=null):array{return $f['merge']->command($f['admin']['reference'],'execute',$body,$key??Id::uuid(),Id::uuid());}
function gMerge(array $f,array $a,array $b):array{return gExecute($f,gPrepare($f,$a,$b));}
function gCount(array $f):int{return (int)$f['db']->execute('SELECT COUNT(*) FROM support_ticket_merges')->fetchColumn();}
function gHash(array $f,array $tables):string{$v=[];foreach($tables as $table){$rows=$f['db']->execute('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();$v[$table]=$rows;}return hash('sha256',\Hostider\Core\CanonicalJson::encode($v));}
function gDetail(array $f,array $ticket,bool $admin=false):array{return (new \Hostider\Support\UI\Views(new \Hostider\Support\UI\Context(new \Hostider\Support\Advanced\Common($f['c']))))->detail($f[$admin?'admin':'client']['reference'],$admin?'admin':'client',['id'=>$ticket['id']]);}
function gRace(array $f,array $requests):array
{
    $children=[];foreach($requests as $p){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/support-merge-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,bEnvironment($f));if(!is_resource($proc)){throw new RuntimeException('MERGE_CHILD');}fwrite($pipes[0],json_encode($p,JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$proc,$pipes];}
    $out=[];foreach($children as [$proc,$pipes]){$raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($proc));same('',$err);$out[]=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}return $out;
}
