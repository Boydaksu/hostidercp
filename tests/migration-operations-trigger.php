<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';require_once __DIR__.'/migration-operations-fixture.php';

test('F6-D unapproved target definer trigger blocks restore before any insert',function(): void{
    $f=operationsFixture();$backup=$f['vault']->backup($f['source'],$f['crypto'],'synthetic-v1');$t=operationsTarget($f);
    $t['setup']->execute('CREATE TRIGGER f6d_unapproved AFTER INSERT ON f6b_entities FOR EACH ROW SET @f6d_unapproved=1');
    raises(InvalidArgumentException::class,fn()=>$f['vault']->restore($backup['backup_id'],$f['crypto'],'synthetic-v1',$t['target']));
    same(0,(int)$t['setup']->execute('SELECT COUNT(*) FROM f6b_entities')->fetchColumn());
});
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){try{$exit=executeTests();}finally{operationsCleanup();}exit($exit);}
