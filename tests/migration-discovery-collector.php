<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
require __DIR__.'/migration-discovery-collector-fixture.php';
use Hostider\Core\CanonicalJson;
use Hostider\Migration\Discovery\{Collector,MetadataSession,PrivateExport,InventoryAdapter,Validator,StrictJson};
function goFlags(array $r):void {
    same('OPEN',$r['G-O03']);same('UNVERIFIED',$r['source_truth']);same('UNVERIFIED',$r['operator_authority']);
    same(false,$r['cutover_allowed']);same(false,$r['effects_allowed']);same('BLOCKED',$r['real_gate_status']);
}
test('GO03 01 actual read only schema collection and exact synthetic counts',function():void{
    goCase(function(array $f):void{$r=goRun($f);goFlags($r);same('METADATA_CAPTURE_COMPLETE_WITHIN_LIMITS',$r['status']);
        same(['schemas'=>1,'tables'=>1,'columns'=>3,'indexes'=>3],$r['inventory']);same('MATCHED_TWO_READS',$r['observed_consistency']);same('NOT_VERIFIED',$r['global_snapshot_atomicity']);same(['.','..'],scandir($f['directory']));});
});
test('GO03 02 actual column and composite index metadata preserved through P3',function():void{
    goCase(function(array $f):void{$s=goSession($f);$raw=goRaw($f,$s);$e=goExport($f,$raw);$indexes=$e['schemas'][0]['tables'][0]['indexes'];
        $composite=array_values(array_filter($indexes,fn($x)=>$x['name']==='synthetic_composite'))[0];same(['value','code'],$composite['columns']);
        $j=CanonicalJson::encode($e);$r=PrivateExport::adapt($f['directory'],$j,$f['source'],$f['snapshot'],hash('sha256',$j),$f['key']);
        same('OFFLINE_INVENTORY_ADAPTED',$r['status']);$m=$r['manifest'];same($r['p2_report'],(new Validator())->validate(CanonicalJson::encode($m),$f['source'],$f['snapshot']));
        same('KNOWN_METADATA',$r['p2_report']['assessment']['schema']);});
});
test('GO03 03 HMAC hides schema account table column credentials and row canary',function():void{
    goCase(function(array $f):void{$r=goRun($f);goFlags($r);$j=json_encode($r);
        foreach([$f['schema'],$f['user'],$f['password'],bin2hex($f['key']),base64_encode($f['key']),'synthetic_inventory','synthetic_unique','synthetic_composite','SYNTHETIC_ROW_CANARY_NOT_REAL_PII'] as $secret){same(false,str_contains($j,$secret));}
        same(false,array_key_exists('manifest',$r));});
});
test('GO03 04 source snapshot and key fingerprints are separated',function():void{
    goCase(function(array $f):void{$a=goRun($f);$g=$f;$g['snapshot']='snapshot-'.inventoryId('other');$b=goRun($g);
        same($a['source_fingerprint'],$b['source_fingerprint']);same(false,$a['snapshot_fingerprint']===$b['snapshot_fingerprint']);
        $g=$f;$g['source']='source-'.inventoryId('other');$b=goRun($g);same(false,$a['source_fingerprint']===$b['source_fingerprint']);
        $g=$f;$g['key']=random_bytes(32);$b=goRun($g);same(false,$a['source_fingerprint']===$b['source_fingerprint']);});
});
test('GO03 05 duplicate column and inconsistent index observations blocked',function():void{
    goCase(function(array $f):void{$raw=goRaw($f,goSession($f));$x=$raw;$x['tables'][0]['columns'][1]['ORDINAL_POSITION']=1;
        same('GO_COLUMN_ORDER',raises(InvalidArgumentException::class,fn()=>goExport($f,$x))->getMessage());
        $x=$raw;$x['tables'][0]['indexes'][1]['SEQ_IN_INDEX']=99;same('GO_INDEX_ORDER',raises(InvalidArgumentException::class,fn()=>goExport($f,$x))->getMessage());
        $x=$raw;$x['tables'][0]['columns'][1]['COLUMN_NAME']=$x['tables'][0]['columns'][0]['COLUMN_NAME'];
        $j=CanonicalJson::encode(goExport($f,$x));$r=PrivateExport::adapt($f['directory'],$j,$f['source'],$f['snapshot'],hash('sha256',$j),$f['key']);same('BLOCKED',$r['status']);});
});
test('GO03 06 unknown collation becomes partial without fake compatibility',function():void{
    goCase(function(array $f):void{$f['root']->execute('ALTER TABLE `'.$f['schema'].'`.synthetic_inventory CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $r=goRun($f);goFlags($r);same('METADATA_CAPTURE_PARTIAL',$r['status']);same('UNKNOWN',$r['metadata_compatibility']['schema']);});
});
test('GO03 07 insufficient read scope is blocked',function():void{goCase(function(array $f):void{$r=goRun($f);goFlags($r);same('BLOCKED',$r['status']);},'none');});
test('GO03 08 schema writer and grant option accounts are rejected',function():void{
    foreach(['writer','grant_option'] as $mode){goCase(function(array $f):void{$r=goRun($f);same('BLOCKED',$r['status']);same(['GO_READ_ONLY_SCOPE'],$r['reasons']);},$mode);}
});
test('GO03 09 global and table write authority are rejected',function():void{
    foreach(['global','table_writer'] as $mode){goCase(function(array $f):void{$r=goRun($f);same('BLOCKED',$r['status']);goFlags($r);},$mode);}
});
test('GO03 10 available role and executable routine authority are rejected',function():void{
    foreach(['role','routine'] as $mode){goCase(function(array $f):void{$r=goRun($f);same('BLOCKED',$r['status']);same(['GO_EXTRA_AUTHORITY'],$r['reasons']);},$mode);}
});
test('GO03 11 arbitrary SQL names cannot reach PDO',function():void{
    goCase(function(array $f):void{$s=goSession($f);$count=$s->count();
        foreach(['DELETE FROM synthetic_inventory','CALL unsafe','SELECT * FROM synthetic_inventory','LOAD DATA','SET ROLE anything','INTO OUTFILE'] as $sql){same('GO_QUERY_DENIED',raises(InvalidArgumentException::class,fn()=>$s->read($sql))->getMessage());}
        same($count,$s->count());foreach(MetadataSession::SQL as $sql){same(true,str_starts_with($sql,'SELECT '));same(true,str_contains($sql,' FROM information_schema.'));same(true,preg_match('/ LIMIT [0-9]+$/D',$sql)===1);}});
});
test('GO03 12 read only collection does not change actual synthetic source rows',function():void{
    goCase(function(array $f):void{$before=$f['root']->execute('SELECT * FROM `'.$f['schema'].'`.synthetic_inventory ORDER BY id')->fetchAll();
        same('METADATA_CAPTURE_COMPLETE_WITHIN_LIMITS',goRun($f)['status']);
        same($before,$f['root']->execute('SELECT * FROM `'.$f['schema'].'`.synthetic_inventory ORDER BY id')->fetchAll());});
});
test('GO03 13 missing and changed observations cannot be called complete',function():void{
    goCase(function(array $f):void{$raw=goRaw($f,goSession($f));$x=$raw;$x['tables'][0]['columns']=[];
        same('GO_PARTIAL_METADATA',raises(InvalidArgumentException::class,fn()=>goExport($f,$x))->getMessage());
        $m=new ReflectionMethod(Collector::class,'consistent');$x=$raw;$x['tables'][0]['TABLE_COLLATION']='ascii_bin';
        same('GO_METADATA_CHANGED',raises(InvalidArgumentException::class,fn()=>$m->invoke(null,[],[],$raw,$x))->getMessage());
        same('GO_METADATA_CHANGED',raises(InvalidArgumentException::class,fn()=>$m->invoke(null,['a'],['b'],$raw,$raw))->getMessage());});
});
test('GO03 14 excessive table inventory never silently truncates',function():void{
    goCase(function(array $f):void{for($i=0;$i<64;++$i){$f['root']->execute('CREATE TABLE `'.$f['schema'].'`.synthetic_extra_'.$i.' (id INT NOT NULL PRIMARY KEY)');}
        $r=goRun($f);same('BLOCKED',$r['status']);same(['GO_METADATA_LIMIT'],$r['reasons']);});
});
test('GO03 15 prefix index and views fail closed rather than lose metadata',function():void{
    goCase(function(array $f):void{$f['root']->execute('CREATE INDEX synthetic_prefix ON `'.$f['schema'].'`.synthetic_inventory(value(4))');$r=goRun($f);same('BLOCKED',$r['status']);same(['GO_UNSUPPORTED_INDEX'],$r['reasons']);});
    goCase(function(array $f):void{$f['root']->execute('CREATE VIEW `'.$f['schema'].'`.synthetic_view AS SELECT 1 AS id');$r=goRun($f);same('BLOCKED',$r['status']);same(['GO_UNSUPPORTED_TABLE_KIND'],$r['reasons']);});
});
test('GO03 16 private directory symlink permissions and file types rejected',function():void{
    goCase(function(array $f):void{$link=$f['directory'].'-link';symlink($f['directory'],$link);
        try{raises(InvalidArgumentException::class,fn()=>PrivateExport::assertRoot($link));}finally{unlink($link);}
        chmod($f['directory'],0755);try{raises(InvalidArgumentException::class,fn()=>PrivateExport::assertRoot($f['directory']));}finally{chmod($f['directory'],0700);}
        $path=$f['directory'].'/file';file_put_contents($path,'SYNTHETIC_ONLY');try{raises(InvalidArgumentException::class,fn()=>PrivateExport::assertRoot($path));}finally{unlink($path);}
        same(['.','..'],scandir($f['directory']));});
});
test('GO03 17 private transfer mismatch malformed and oversized export clean up',function():void{
    goCase(function(array $f):void{$j=CanonicalJson::encode(inventoryFixture());
        $r=PrivateExport::adapt($f['directory'],$j,inventoryFixture()['source_id'],inventoryFixture()['snapshot_id'],str_repeat('a',64),$f['key']);
        same('BLOCKED',$r['status']);same(['.','..'],scandir($f['directory']));
        $r=PrivateExport::adapt($f['directory'],'{',$f['source'],$f['snapshot'],hash('sha256','{'),$f['key']);same('BLOCKED',$r['status']);same(['.','..'],scandir($f['directory']));
        raises(InvalidArgumentException::class,fn()=>PrivateExport::adapt($f['directory'],str_repeat(' ',StrictJson::MAX_BYTES+1),$f['source'],$f['snapshot'],str_repeat('a',64),$f['key']));
        same(['.','..'],scandir($f['directory']));});
});
test('GO03 18 exact underscore schema grant remains bounded',function():void{
    goCase(function(array $f):void{same('METADATA_CAPTURE_COMPLETE_WITHIN_LIMITS',goRun($f)['status']);},'underscore');
});
test('GO03 19 actual standalone CLI uses private descriptors and emits sanitized JSON',function():void{
    goCase(function(array $f):void{$c=goChild($f);same(0,$c['exit']);same('',$c['stderr']);goFlags($c['report']);same('METADATA_CAPTURE_COMPLETE_WITHIN_LIMITS',$c['report']['status']);
        foreach([$f['schema'],$f['user'],$f['password'],bin2hex($f['key']),'synthetic_inventory','SYNTHETIC_ROW_CANARY_NOT_REAL_PII'] as $secret){same(false,str_contains($c['out'],$secret));}
        same(['.','..'],scandir($f['directory']));});
});
test('GO03 20 CLI missing ack unknown authority argument and insecure descriptor blocked',function():void{
    goCase(function(array $f):void{foreach([goChild($f,[],false),goChild($f,['--G-O03=CLOSED']),goChild($f,[],true,true)] as $c){
        same(1,$c['exit']);same('',$c['stderr']);same('BLOCKED',$c['report']['status']);goFlags($c['report']);}same(['.','..'],scandir($f['directory']));});
});
test('GO03 21 no fallback fake source on missing credentials and intentional gate enforced',function():void{
    same('GO_MANUAL_GATE',raises(InvalidArgumentException::class,fn()=>MetadataSession::connect(1,'unused','SYNTHETIC_ONLY','NOT_APPROVED'))->getMessage());
    goCase(function(array $f):void{$f['password']='SYNTHETIC_WRONG_PASSWORD';$c=goChild($f);same(1,$c['exit']);same('BLOCKED',$c['report']['status']);same('',$c['stderr']);same(false,str_contains($c['out'],$f['password']));goFlags($c['report']);});
});
test('GO03 22 unknown non-schema categories and real gate cannot be inferred',function():void{
    goCase(function(array $f):void{$r=goRun($f);foreach(['version','temporal','password','custom_fields','files'] as $k){same('UNKNOWN',$r['metadata_compatibility'][$k]);}
        same(true,in_array('WHMCS_APPLICATION_VERSION',$r['missing_information'],true));goFlags($r);});
});
exit(executeTests());
