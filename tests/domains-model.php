<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/domains-fixture.php';
use Hostider\Core\{Id,Config};use Hostider\Core\Database\Migrator;use Hostider\Core\Http\HttpError;use Hostider\Domains\Registry;
$db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F4-A paid canonical domain binds once with encrypted immutable contact history and outbox',function()use($db): void{
    $f=domainFixture($db);$id=$f['attach']();same($id,$f['attach']());
    same(\Hostider\Core\CanonicalJson::encode($f['contact']),\Hostider\Core\CanonicalJson::encode($f['registry']->contact($id,$f['customer'],$f['actor'])));
    same('PENDING',$f['registry']->view($id,$f['customer'],$f['actor'])['status']);
    same(1,(int)$db->execute('SELECT COUNT(*) FROM domain_history WHERE domain_id=?',[$id])->fetchColumn());
    same(1,(int)$db->execute("SELECT COUNT(*) FROM audit_logs WHERE subject_id=? AND action='domain.changed'",[$id])->fetchColumn());
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['registry']->attach($f['service']['id'],$f['customer'],$f['name'],$f['rule'],array_replace($f['contact'],['name'=>'Altered owner']),$f['ns'],$f['actor'],Id::uuid())))->status);
    $row=$db->execute('SELECT * FROM domain_contacts WHERE domain_id=?',[$id])->fetch();same(false,str_contains(json_encode($row),'synthetic@example.invalid'));
    same(1,(int)$db->execute("SELECT COUNT(*) FROM outbox_events WHERE aggregate_id=? AND event_type='domains.changed'",[$id])->fetchColumn());
    foreach(['domain_contacts','domain_nameserver_snapshots','domain_history'] as $table){raises(PDOException::class,fn()=>$db->execute('DELETE FROM '.$table.' WHERE domain_id=?',[$id]));}
    raises(PDOException::class,fn()=>$db->execute("UPDATE domains SET name_ascii='forged.example' WHERE id=?",[$id]));
});
test('SEC-004 domain contact and client projection deny cross customer expired and unscoped access',function()use($db): void{
    $f=domainFixture($db);$id=$f['attach']();$view=$f['registry']->view($id,$f['customer'],$f['actor']);
    foreach(['customer_id','provider_external_id','rule_id','contact_encrypted'] as $key){same(false,array_key_exists($key,$view));}
    same(404,raises(HttpError::class,fn()=>$f['registry']->contact($id,Id::uuid(),$f['actor']))->status);
    $unscoped=new \Hostider\Core\Security\Principal('other',['services.view','services.manage'],$f['customer'],expiresAt:time()+600,verifiedAt:time());
    same(403,raises(HttpError::class,fn()=>$f['registry']->contact($id,$f['customer'],$unscoped))->status);
    $expired=new \Hostider\Core\Security\Principal('expired',['services.view'],$f['customer']);
    same(401,raises(HttpError::class,fn()=>$f['registry']->view($id,$f['customer'],$expired))->status);
});
test('F4-A rollback removes model history contact and event atomically; non-test model unavailable',function()use($db): void{
    $f=domainFixture($db);raises(RuntimeException::class,fn()=>$db->transaction(function()use($f): void{
        $f['registry']->attach($f['service']['id'],$f['customer'],$f['name'],$f['rule'],$f['contact'],$f['ns'],$f['actor'],Id::uuid());throw new RuntimeException('Synthetic rollback');
    }));same(0,(int)$db->execute('SELECT COUNT(*) FROM domains WHERE service_id=?',[$f['service']['id']])->fetchColumn());
    foreach(['staging','production'] as $env){$c=$f['config'];same(403,raises(HttpError::class,fn()=>new Registry($db,new Config($env,$c->url,$c->key,$c->dsn,$c->dbUser,$c->dbPassword)))->status);}
});
runTests();
