<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
require __DIR__.'/migration-inventory-fixture.php';
use Hostider\Core\CanonicalJson;
use Hostider\Migration\Discovery\{InventoryAdapter, Validator, StrictJson};

function inventoryFlags(array $r):void
{
    same('OPEN',$r['G-O03']);same('UNVERIFIED',$r['source_truth']);same('UNVERIFIED',$r['operator_authority']);
    same(false,$r['cutover_allowed']);same(false,$r['effects_allowed']);same('BLOCKED',$r['real_gate_status']);
    same(true,in_array($r['status'],['BLOCKED','OFFLINE_INVENTORY_ADAPTED'],true));
}
function inventoryRun(string $bytes,?string $key=null,?string $source=null,?string $snapshot=null,?string $pin=null):array
{
    $f=inventoryFixture();$h=tmpfile();
    try{
        fwrite($h,$bytes);rewind($h);
        $r=(new InventoryAdapter())->convert($h,$source??$f['source_id'],$snapshot??$f['snapshot_id'],$pin??hash('sha256',$bytes),$key??random_bytes(32));
        inventoryFlags($r);same(true,is_resource($h));return $r;
    }finally{fclose($h);}
}
function inventoryChanged(callable $change):string {$f=inventoryFixture();$change($f);return inventoryJson($f);}
function inventoryBlocked(string $bytes):array {$r=inventoryRun($bytes);same('BLOCKED',$r['status']);same(null,$r['manifest']);return $r;}

test('P3 01 valid synthetic inventory transforms into actual P2 contract',function():void{
    $r=inventoryRun(inventoryJson(inventoryFixture()));same('OFFLINE_INVENTORY_ADAPTED',$r['status']);
    $m=$r['manifest'];same($r['p2_report'],(new Validator())->validate(CanonicalJson::encode($m),$m['source']['id'],$m['snapshot']['id']));
    same('OFFLINE_DISCOVERY_CONTRACT_VALIDATED',$r['p2_report']['status']);
    same(['tables'=>'KNOWN_METADATA','columns'=>'KNOWN_METADATA','indexes'=>'KNOWN_METADATA'],$r['inventory_coverage']);
    foreach(['version','temporal','password','custom_fields','files'] as $k){same('UNKNOWN',$r['category_coverage'][$k]);}
    same([],$m['custom_fields']['items']);same('MISSING',$m['files']['state']);
});
test('P3 02 repeated conversion same bytes key and context is deterministic',function():void{
    $j=inventoryJson(inventoryFixture());$key=random_bytes(32);same(inventoryRun($j,$key),inventoryRun($j,$key));
});
test('P3 03 wrong out of band source or snapshot always rejected',function():void{
    $j=inventoryJson(inventoryFixture());
    foreach([['source-'.inventoryId('wrong'),null],[null,'snapshot-'.inventoryId('wrong')]] as [$s,$n]){
        $r=inventoryRun($j,null,$s,$n);same('BLOCKED',$r['status']);same(['P3_CONTEXT_MISMATCH'],$r['reasons']);
    }
});
test('P3 04 missing schema or tables inventory explicit UNKNOWN no invented entries',function():void{
    $r=inventoryRun(inventoryChanged(function(array &$f):void{$f['schema_state']='MISSING';$f['schemas']=[];}));
    same('OFFLINE_INVENTORY_ADAPTED',$r['status']);same('UNKNOWN',$r['category_coverage']['schema']);
    same([],$r['manifest']['schema']['schemas']);
    $r=inventoryRun(inventoryChanged(function(array &$f):void{$f['schemas'][0]['tables_state']='UNKNOWN';$f['schemas'][0]['tables']=[];}));
    same('UNKNOWN',$r['inventory_coverage']['tables']);
    inventoryBlocked(inventoryChanged(function(array &$f):void{unset($f['schemas'][0]['tables']);}));
});
test('P3 05 missing columns inventory represented unknown and absent metadata blocked',function():void{
    $r=inventoryRun(inventoryChanged(function(array &$f):void{$t=&$f['schemas'][0]['tables'][0];$t['columns_state']='MISSING';$t['columns']=[];$t['indexes_state']='UNKNOWN';$t['indexes']=[];}));
    same('UNKNOWN',$r['inventory_coverage']['columns']);same('UNKNOWN',$r['p2_report']['assessment']['schema']);
    inventoryBlocked(inventoryChanged(function(array &$f):void{unset($f['schemas'][0]['tables'][0]['columns']);}));
});
test('P3 06 missing indexes explicit unverified without inventing primary key',function():void{
    $r=inventoryRun(inventoryChanged(function(array &$f):void{$t=&$f['schemas'][0]['tables'][0];$t['indexes_state']='MISSING';$t['indexes']=[];}));
    same('UNKNOWN',$r['inventory_coverage']['indexes']);same('UNVERIFIED',$r['category_coverage']['schema']);
    same([],$r['manifest']['schema']['schemas'][0]['tables'][0]['indexes']);
    inventoryBlocked(inventoryChanged(function(array &$f):void{unset($f['schemas'][0]['tables'][0]['indexes_state']);}));
});
test('P3 07 duplicate and case ambiguous schema table column index identifiers rejected',function():void{
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['schemas'][]=$f['schemas'][0];}));
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['schemas'][0]['tables'][]=$f['schemas'][0]['tables'][0];}));
    inventoryBlocked(inventoryChanged(function(array &$f):void{$t=&$f['schemas'][0]['tables'][0];$t['columns'][]=$t['columns'][0];}));
    inventoryBlocked(inventoryChanged(function(array &$f):void{$t=&$f['schemas'][0]['tables'][0];$t['indexes'][]=$t['indexes'][0];}));
    inventoryBlocked(inventoryChanged(function(array &$f):void{$s=$f['schemas'][0];$s['name']=strtolower($s['name']);$f['schemas'][]=$s;}));
});
test('P3 08 charset collation and column conflicts delegated to P2',function():void{
    foreach(['table','column'] as $kind){
        $r=inventoryBlocked(inventoryChanged(function(array &$f)use($kind):void{
            if($kind==='table'){$f['schemas'][0]['tables'][0]['collation']='ASCII_BIN';}
            else{$f['schemas'][0]['tables'][0]['columns'][1]['collation']='ASCII_BIN';}
        }));
        same(['P3_P2_BLOCKED'],$r['reasons']);same('INCOMPATIBLE',$r['p2_report']['assessment']['schema']);
    }
});
test('P3 09 HMAC key separation changes opaque IDs never exposes either key',function():void{
    $j=inventoryJson(inventoryFixture());$a=random_bytes(32);$b=random_bytes(32);
    $ra=inventoryRun($j,$a);$rb=inventoryRun($j,$b);
    same(false,$ra['manifest']['schema']===$rb['manifest']['schema']);
    foreach([$ra,$rb] as $r){$s=json_encode($r);foreach([$a,$b,bin2hex($a),bin2hex($b),base64_encode($a),base64_encode($b)] as $secret){same(false,str_contains($s,$secret));}}
});
test('P3 10 HMAC source and snapshot domain separation with correctly rebound exports',function():void{
    $f=inventoryFixture();$key=random_bytes(32);$base=inventoryRun(inventoryJson($f),$key);
    foreach([['source-'.inventoryId('b'),$f['snapshot_id']],[$f['source_id'],'snapshot-'.inventoryId('b')]] as [$source,$snapshot]){
        $next=inventoryRebind($f,$source,$snapshot);$r=inventoryRun(inventoryJson($next),$key,$source,$snapshot);
        same('OFFLINE_INVENTORY_ADAPTED',$r['status']);same(false,$base['manifest']['schema']['schemas'][0]['id']===$r['manifest']['schema']['schemas'][0]['id']);
    }
});
test('P3 11 HMAC exact canonical algorithm kind and parent namespace independently checked',function():void{
    $f=inventoryFixture();$key=random_bytes(32);$r=inventoryRun(inventoryJson($f),$key);
    $schema=$r['manifest']['schema']['schemas'][0];$sn=$f['schemas'][0]['name'];
    $expected=hash_hmac('sha256',CanonicalJson::encode(['hostider.inventory-id.v1',$f['source_id'],$f['snapshot_id'],'schema',[$sn]]),$key);
    same(true,hash_equals($expected,$schema['id']));
    $other=hash_hmac('sha256',CanonicalJson::encode(['hostider.inventory-id.v1',$f['source_id'],$f['snapshot_id'],'table',[$sn]]),$key);
    same(false,hash_equals($other,$schema['id']));
    $j=inventoryChanged(function(array &$f):void{$t=$f['schemas'][0]['tables'][0];$t['name']=inventoryName('other-parent');$f['schemas'][0]['tables'][]=$t;});
    $r=inventoryRun($j,$key);$t=$r['manifest']['schema']['schemas'][0]['tables'];
    same(false,$t[0]['columns'][0]['id']===$t[1]['columns'][0]['id']);
});
test('P3 12 modified offline export cannot match independently pinned byte hash',function():void{
    $f=inventoryFixture();$pin=hash('sha256',inventoryJson($f));$f['schemas'][0]['tables'][0]['name']=inventoryName('changed');
    $r=inventoryRun(inventoryJson($f),null,null,null,$pin);same('BLOCKED',$r['status']);same(['P3_EXPORT_PIN'],$r['reasons']);
});
test('P3 13 malformed duplicate nested escaped keys and encoding rejected',function():void{
    foreach(['{','[]',"\xFF", "\xEF\xBB\xBF{}",'{"a":"\\uD800"}'] as $j){inventoryBlocked($j);}
    $j=inventoryJson(inventoryFixture());
    foreach(['"kind":"PRIMARY","kind":"PRIMARY"','"kind":"PRIMARY","\\u006bind":"PRIMARY"'] as $text){inventoryBlocked(str_replace('"kind":"PRIMARY"',$text,$j));}
});
test('P3 14 oversized empty deeply nested and excessive strings blocked',function():void{
    foreach(['',str_repeat(' ',StrictJson::MAX_BYTES+1),str_repeat('{"a":',17).'0'.str_repeat('}',17),'{"a":"'.str_repeat('x',129).'"}'] as $j){inventoryBlocked($j);}
});
test('P3 15 wrong file types path URL and closed handle rejected',function():void{
    $f=inventoryFixture();$h=tmpfile();fclose($h);
    foreach(['../x','C:\\x','https://example.invalid/export',null,$h,[]] as $handle){
        $r=(new InventoryAdapter())->convert($handle,$f['source_id'],$f['snapshot_id'],inventoryId('pin'),random_bytes(32));inventoryFlags($r);same('BLOCKED',$r['status']);
    }
    $h=fopen('php://memory','r+');
    try{fwrite($h,inventoryJson($f));rewind($h);$r=(new InventoryAdapter())->convert($h,$f['source_id'],$f['snapshot_id'],hash('sha256',inventoryJson($f)),random_bytes(32));same('BLOCKED',$r['status']);}finally{fclose($h);}
});
test('P3 16 consumed regular handle cannot be silently replayed and remains caller owned',function():void{
    $f=inventoryFixture();$j=inventoryJson($f);$h=tmpfile();$key=random_bytes(32);
    try{fwrite($h,$j);rewind($h);$adapter=new InventoryAdapter();
        $a=$adapter->convert($h,$f['source_id'],$f['snapshot_id'],hash('sha256',$j),$key);same('OFFLINE_INVENTORY_ADAPTED',$a['status']);
        $r=$adapter->convert($h,$f['source_id'],$f['snapshot_id'],hash('sha256',$j),$key);same('BLOCKED',$r['status']);same(true,is_resource($h));
        rewind($h);same($a,$adapter->convert($h,$f['source_id'],$f['snapshot_id'],hash('sha256',$j),$key));
    }finally{fclose($h);}
});
test('P3 17 raw names and private mapping never leave successful adapter result',function():void{
    $f=inventoryFixture();$r=inventoryRun(inventoryJson($f));$text=json_encode($r);
    foreach(['schema-a','table-a','column-a','column-b','index-a','index-b'] as $label){same(false,str_contains($text,inventoryName($label)));}
    foreach(['raw_names','name_mapping','operator_key','key_sha256'] as $field){same(false,array_key_exists($field,$r));}
});
test('P3 18 row data credentials password hashes and PII fields blocked without leak',function():void{
    $canary='SYNTHETIC_FORBIDDEN_CANARY';
    foreach(['rows','customer_email','api_token','password_hash','session_secret','operator_key'] as $field){
        $r=inventoryBlocked(inventoryChanged(function(array &$f)use($field,$canary):void{$f['schemas'][0]['tables'][0][$field]=$canary;}));
        same(false,str_contains(json_encode($r),$canary));
    }
});
test('P3 19 each nested level rejects mixed source or snapshot identifiers',function():void{
    foreach(['schema','table','column','index'] as $level){
        foreach(['source_id','snapshot_id'] as $field){
            inventoryBlocked(inventoryChanged(function(array &$f)use($level,$field):void{
                $new=($field==='source_id'?'source-':'snapshot-').inventoryId('other');
                if($level==='schema'){$f['schemas'][0][$field]=$new;}
                elseif($level==='table'){$f['schemas'][0]['tables'][0][$field]=$new;}
                else{$f['schemas'][0]['tables'][0][$level==='column'?'columns':'indexes'][0][$field]=$new;}
            }));
        }
    }
});
test('P3 20 forged G-O03 source truth and cutover authority cannot enter export',function():void{
    foreach(['G-O03'=>'CLOSED','source_truth'=>'VERIFIED','operator_authority'=>'APPROVED','cutover_allowed'=>true,'effects_allowed'=>true] as $field=>$v){
        inventoryBlocked(inventoryChanged(function(array &$f)use($field,$v):void{$f[$field]=$v;}));
    }
});
test('P3 21 invalid or oversized identifiers traversal Unicode and SQL text rejected',function():void{
    foreach(['../private','public.name','name;SELECT','name with space',str_repeat('x',65),'Náme',''] as $name){
        $r=inventoryBlocked(inventoryChanged(function(array &$f)use($name):void{$f['schemas'][0]['name']=$name;}));if($name!==''){same(false,str_contains(json_encode($r),$name));}
    }
});
test('P3 22 missing required fields inventory conflicts and extra types fail closed',function():void{
    foreach(array_keys(inventoryFixture()) as $key){$f=inventoryFixture();unset($f[$key]);inventoryBlocked(inventoryJson($f));}
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['schema_state']='MISSING';}));
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['schemas'][0]['tables'][0]['indexes_state']='UNKNOWN';}));
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['schemas']=(object)[];}));
});
test('P3 23 composite index order preserved and unknown duplicate references blocked by P2',function():void{
    $f=inventoryFixture();$key=random_bytes(32);$a=inventoryRun(inventoryJson($f),$key);
    $f['schemas'][0]['tables'][0]['indexes'][1]['columns']=array_reverse($f['schemas'][0]['tables'][0]['indexes'][1]['columns']);
    $b=inventoryRun(inventoryJson($f),$key);$ai=$a['manifest']['schema']['schemas'][0]['tables'][0]['indexes'];$bi=$b['manifest']['schema']['schemas'][0]['tables'][0]['indexes'];
    same(false,$ai===$bi);
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['schemas'][0]['tables'][0]['indexes'][0]['columns']=[inventoryName('absent')];}));
    $r=inventoryBlocked(inventoryChanged(function(array &$f):void{$x=&$f['schemas'][0]['tables'][0]['indexes'][0]['columns'];$x[]=$x[0];}));same(['P3_P2_BLOCKED'],$r['reasons']);
});
test('P3 24 shuffled export retains IDs but binds distinct byte provenance',function():void{
    $f=inventoryFixture();$key=random_bytes(32);$a=inventoryRun(inventoryJson($f),$key);
    $f['schemas'][0]['tables'][0]['columns']=array_reverse($f['schemas'][0]['tables'][0]['columns']);$b=inventoryRun(inventoryJson($f),$key);
    same($a['manifest']['schema'],$b['manifest']['schema']);same(false,$a['p2_report']['metadata_fingerprint']===$b['p2_report']['metadata_fingerprint']);
});
test('P3 25 key length version classification and unclaimed source system rejected',function():void{
    $j=inventoryJson(inventoryFixture());foreach(['',str_repeat('x',31),str_repeat('x',33)] as $key){same('BLOCKED',inventoryRun($j,$key)['status']);}
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['contract']='v999';}));
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['classification']='REAL_VERIFIED';}));
    $r=inventoryBlocked(inventoryChanged(function(array &$f):void{$f['system']='OTHER';}));same(['P3_P2_BLOCKED'],$r['reasons']);
});
test('P3 26 operator prepared export still cannot establish operator or source truth',function():void{
    $r=inventoryRun(inventoryChanged(function(array &$f):void{$f['classification']='OPERATOR_PREPARED_EXPORT';}));
    same('OFFLINE_INVENTORY_ADAPTED',$r['status']);same('SANITIZED_METADATA',$r['manifest']['classification']);
    same('OPERATOR_SUPPLIED_UNVERIFIED',$r['manifest']['provenance']['kind']);inventoryFlags($r);
});
test('P3 27 metadata semantic type errors delegated to immutable P2 validator',function():void{
    foreach([false,1,'yes',null,[]] as $value){
        $r=inventoryBlocked(inventoryChanged(function(array &$f)use($value):void{$f['schemas'][0]['tables'][0]['columns'][0]['type']=$value;}));
        same(['P3_P2_BLOCKED'],$r['reasons']);
    }
    inventoryBlocked(inventoryChanged(function(array &$f):void{$f['schemas'][0]['tables'][0]['columns']=array_fill(0,65,$f['schemas'][0]['tables'][0]['columns'][0]);}));
});
exit(executeTests());
