<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';
require_once __DIR__.'/migration-import-fixture.php';
use Hostider\Migration\Synthetic\{Fixture,Loader};

test('F6-B B-01 migration readiness lost inside batch rolls back before commit',function(): void{
    $f=importFixture();$s=$f['sandbox'];$loader=new Loader();
    $prefix=$loader->load(Fixture::named('baseline'),$s,1);
    same('APPLYING',$prefix['status']);same(1,$prefix['checkpoint']['cursor']);
    $counts=importCounts($s);
    $run=$s->db->execute('SELECT * FROM f6b_runs')->fetch();
    $history=$s->db->execute('SELECT * FROM f6b_history ORDER BY id')->fetchAll();
    $migration=$s->db->execute('SELECT * FROM schema_migrations ORDER BY version')->fetchAll();
    // Test-only definer has UPDATE; loader still has no migration UPDATE privilege.
    $f['setup']->execute("CREATE TRIGGER f6b_b01_readiness AFTER INSERT ON f6b_entities FOR EACH ROW UPDATE schema_migrations SET status='APPLYING'");
    try{
        $result=$loader->load(Fixture::named('baseline'),$s,2,$prefix['checkpoint']);
        same('FAILED',$result['status']);
        same(['F6B_STORAGE_UNAVAILABLE'],$result['reasons']);
        same($counts,importCounts($s));
        same($run,$s->db->execute('SELECT * FROM f6b_runs')->fetch());
        same($history,$s->db->execute('SELECT * FROM f6b_history ORDER BY id')->fetchAll());
        same($migration,$s->db->execute('SELECT * FROM schema_migrations ORDER BY version')->fetchAll());
    }finally{$f['setup']->execute('DROP TRIGGER f6b_b01_readiness');}
    $s->assertReady();
    same('APPLIED',$loader->load(Fixture::named('baseline'),$s,64,$prefix['checkpoint'])['status']);
    same(26,importCounts($s)['f6b_entities']);
});

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{$exit=executeTests();}finally{importCleanup();}exit($exit);
}
