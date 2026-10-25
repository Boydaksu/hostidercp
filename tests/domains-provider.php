<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/domains-fixture.php';
use Hostider\Core\Id;use Hostider\Core\Database\Migrator;use Hostider\Core\Http\HttpError;use Hostider\Providers\Mock\DurableRegistrar;use Hostider\Providers\Contract\Operation;
$db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function registrarFixture(\Hostider\Core\Database\Database $db,string $provider='resellerclub',string $scenario='SUCCESS'): array{
    $f=domainFixture($db);$rule=$provider==='resellerclub'?$f['rule']:$f['registry']->configure('example',$provider,$f['policy']);$mapping=$f['registry']->policy($rule);
    $adapter=new DurableRegistrar($db,$f['config'],$mapping['account_id'],$scenario);
    $request=['domain'=>$f['name'],'rule_id'=>$rule,'contact'=>$f['contact'],'nameservers'=>$f['ns'],'years'=>1];
    return array_replace($f,compact('adapter','mapping','request'));
}
test('F4-B both durable registrar contracts replay UUID and reject altered payload or transaction nesting',function()use($db): void{
    foreach(['resellerclub','domainnameapi'] as $provider){$f=registrarFixture($db,$provider);$p=$f['adapter'];
        $availability=$p->availability(new Operation(Id::uuid(),'availability',configuration:$f['request']));same('MOCK_AVAILABLE_NOT_RESERVED',$availability->safeErrorCode);
        $op=new Operation(Id::uuid(),'register',configuration:$f['request']);$result=$p->register($op);same('SUCCESS',$result->status);same($provider,$result->provider);same($result->externalResourceId,$p->register($op)->externalResourceId);
        same(409,raises(HttpError::class,fn()=>$p->register(new Operation($op->id,'register',configuration:array_replace($f['request'],['years'=>2]))))->status);
        $renew=new Operation(Id::uuid(),'renew',$result->externalResourceId,$f['request']);$p->renew($renew);$p->renew($renew);same(2,(int)$p->evidence($f['name'])['term_years']);
        same('MOCK_UNAVAILABLE',$p->availability(new Operation(Id::uuid(),'availability',configuration:$f['request']))->safeErrorCode);
        same('UNSUPPORTED',$p->epp(new Operation(Id::uuid(),'epp',$result->externalResourceId,$f['request']))->status);
        raises(LogicException::class,fn()=>$db->transaction(fn()=>$p->status(new Operation(Id::uuid(),'status',configuration:$f['request']))));
    }
});
test('F4-B ambiguous mock persists effect independently without disclosing resource; explicit inspect finds one resource',function()use($db): void{
    $f=registrarFixture($db,scenario:'TIMEOUT_AFTER_EFFECT');$op=new Operation(Id::uuid(),'register',configuration:$f['request']);same('UNKNOWN',$f['adapter']->register($op)->status);same(null,$f['adapter']->register($op)->externalResourceId);
    $evidence=$f['adapter']->evidence($f['name']);same('REGISTERED',$evidence['status']);same($op->id,$evidence['create_operation_id']);
    $newProcessAdapter=new DurableRegistrar($db,$f['config'],$f['mapping']['account_id']);same('UNKNOWN',$newProcessAdapter->register($op)->status);
    same('SUCCESS',$newProcessAdapter->status(new Operation(Id::uuid(),'status',configuration:$f['request']))->status);
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_domains WHERE name_ascii=?',[$f['name']])->fetchColumn());
    same(1,(int)$db->execute("SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=? AND action='register'",[$op->id])->fetchColumn());
});
test('F4-B definitive no effect proof and unsupported capability do not create external domain',function()use($db): void{
    $f=registrarFixture($db,scenario:'DEFINITIVE_FAILURE');$op=new Operation(Id::uuid(),'register',configuration:$f['request']);same('DEFINITIVE_FAILURE',$f['adapter']->register($op)->status);same(null,$f['adapter']->evidence($f['name']));
    same(1,(int)$db->execute('SELECT no_effect FROM mock_registrar_calls WHERE operation_id=?',[$op->id])->fetchColumn());
    $f=registrarFixture($db,scenario:'TIMEOUT_BEFORE_EFFECT');$op=new Operation(Id::uuid(),'register',configuration:$f['request']);same('UNKNOWN',$f['adapter']->register($op)->status);same(null,$f['adapter']->evidence($f['name']));
    same(0,(int)$db->execute('SELECT no_effect FROM mock_registrar_calls WHERE operation_id=?',[$op->id])->fetchColumn());
    same('UNSUPPORTED',$f['adapter']->privacy(new Operation(Id::uuid(),'privacy',configuration:$f['request']))->status);
});
test('OPS-005 registrar mock independent PHP workers replay create and renew with one physical effect',function()use($db): void{
    $f=registrarFixture($db);$resource=null;
    foreach(['register','renew'] as $action){
        $op=Id::uuid();$children=[];$env=getenv();$env['APP_KEY']=$f['config']->key;
        for($i=0;$i<4;++$i){$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/domains-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);
            if(!is_resource($process)){throw new RuntimeException('Worker unavailable');}
            fwrite($pipes[0],json_encode(['account'=>$f['mapping']['account_id'],'operation'=>$op,'action'=>$action,'resource'=>$resource,'request'=>$f['request']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$children[]=[$process,$pipes];
        }
        foreach($children as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($process));same('',$err);$response=json_decode($out,true,32,JSON_THROW_ON_ERROR);same('SUCCESS',$response['status']);if($resource!==null){same($resource,$response['resource']);}$resource=$response['resource'];}
        same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_calls WHERE operation_id=?',[$op])->fetchColumn());
    }
    same(2,(int)$f['adapter']->evidence($f['name'])['term_years']);
    same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_registrar_domains WHERE name_ascii=?',[$f['name']])->fetchColumn());
});
runTests();
