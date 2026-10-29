<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
require __DIR__.'/migration-discovery-fixture.php';
use Hostider\Migration\Discovery\{StrictJson, Validator};
use Hostider\Migration\Contract;

function discoveryRun(string $json, array $allowed = [], ?string $source = null, ?string $snapshot = null): array
{
    $f = discoveryFixture();
    $r = (new Validator())->validate($json, $source ?? $f['source']['id'], $snapshot ?? $f['snapshot']['id'], $allowed);
    same(false, $r['effects_allowed']); same(false, $r['cutover_allowed']); same('BLOCKED', $r['real_gate_status']);
    same('OPEN', $r['G-O03']); same('UNVERIFIED', $r['source_truth']); same('UNVERIFIED', $r['operator_authority']);
    same('NOT_VERIFIED', $r['cryptographic_attestation']); same('NOT_ASSESSED', $r['mapping_readiness']);
    same(true, in_array($r['status'], ['BLOCKED', 'OFFLINE_DISCOVERY_CONTRACT_VALIDATED'], true));
    return $r;
}
function discoveryBlock(string $json): array { $r = discoveryRun($json); same('BLOCKED', $r['status']); return $r; }
function discoveryChange(callable $change): string { $f = discoveryFixture(); $change($f); return discoveryJson(discoverySeal($f)); }

test('P2 01 valid explicitly synthetic metadata contract and safe aggregate report', function(): void {
    $f = discoveryFixture(); $r = discoveryRun(discoveryJson($f));
    same('OFFLINE_DISCOVERY_CONTRACT_VALIDATED', $r['status']); same($f['content_sha256'], $r['metadata_fingerprint']);
    same('KNOWN_METADATA', $r['assessment']['schema']); same('UNKNOWN', $r['assessment']['version']);
    foreach (['F6_A_identity_values', 'F6_B_rows_and_files', 'F6_C_finance_visibility_delta'] as $k) { same('UNVERIFIED', $r['assessment'][$k]); }
    same(false, str_contains(json_encode($r), discoveryId('synthetic-table')));
});
test('P2 02 every required root field fails closed when missing', function(): void {
    foreach (array_keys(discoveryFixture()) as $key) {
        $f = discoveryFixture(); unset($f[$key]); discoveryBlock(discoveryJson($f));
    }
});
test('P2 03 unsupported contract version and encoding rejected', function(): void {
    foreach (['contract' => 'hostider.offline-discovery.v999', 'encoding' => 'UTF-16'] as $key => $v) {
        discoveryBlock(discoveryChange(function(array &$f) use ($key, $v): void { $f[$key] = $v; }));
    }
});
test('P2 04 claimed numeric WHMCS version stays unverified unknown remains unknown', function(): void {
    $r = discoveryRun(discoveryChange(function(array &$f): void { $f['version']['fact'] = ['state' => 'KNOWN', 'value' => '99.99.999']; }));
    same('UNVERIFIED', $r['assessment']['version']); same('OFFLINE_DISCOVERY_CONTRACT_VALIDATED', $r['status']);
    discoveryBlock(discoveryChange(function(array &$f): void { $f['version']['fact'] = ['state' => 'KNOWN', 'value' => 'not-a-version']; }));
});
test('P2 05 missing table or column metadata cannot imply inventory compatibility', function(): void {
    $r = discoveryRun(discoveryChange(function(array &$f): void { $f['schema']['schemas'][0]['tables'] = []; }));
    same('UNKNOWN', $r['assessment']['schema']);
    $r = discoveryRun(discoveryChange(function(array &$f): void { $t =& $f['schema']['schemas'][0]['tables'][0]; $t['columns'] = []; $t['indexes'] = []; }));
    same('UNKNOWN', $r['assessment']['schema']);
    discoveryBlock(discoveryChange(function(array &$f): void { unset($f['schema']['schemas'][0]['tables'][0]['columns']); }));
});
test('P2 06 unexpected custom field blocked unless exact out of band allowlist', function(): void {
    $id = discoveryId('custom');
    $json = discoveryChange(function(array &$f) use ($id): void { $f['custom_fields']['items'][] = ['id' => $id, 'entity' => 'customer', 'kind' => 'STRING']; });
    same('INCOMPATIBLE', discoveryBlock($json)['assessment']['custom_fields']);
    same('KNOWN_METADATA', discoveryRun($json, [$id])['assessment']['custom_fields']);
    discoveryBlock(discoveryChange(function(array &$f): void { $f['custom_fields']['items'][] = ['id' => discoveryId('custom'), 'entity' => 'customer', 'kind' => 'STRING', 'value' => 'SYNTHETIC_FORBIDDEN_VALUE']; }));
});
test('P2 07 charset collation contradiction and unknown label cannot pass', function(): void {
    $r = discoveryBlock(discoveryChange(function(array &$f): void { $f['schema']['schemas'][0]['tables'][0]['collation'] = 'ASCII_BIN'; }));
    same('INCOMPATIBLE', $r['assessment']['schema']);
    discoveryBlock(discoveryChange(function(array &$f): void { $f['schema']['schemas'][0]['tables'][0]['charset'] = 'UNSUPPORTED'; }));
});
test('P2 08 password algorithms are metadata claims never hash compatibility', function(): void {
    foreach (['UNKNOWN', 'MIXED', 'BCRYPT', 'ARGON2ID', 'LEGACY', 'UNVERIFIABLE'] as $algorithm) {
        $r = discoveryRun(discoveryChange(function(array &$f) use ($algorithm): void { $f['password']['algorithm'] = $algorithm; }));
        same(in_array($algorithm, ['UNKNOWN', 'MIXED'], true) ? 'UNKNOWN' : 'UNVERIFIED', $r['assessment']['password']);
    }
    discoveryBlock(discoveryChange(function(array &$f): void { $f['password']['algorithm'] = 'invented-whmcs-format'; }));
});
test('P2 09 duplicate root JSON key rejected even with identical value', function(): void {
    $j = discoveryJson(discoveryFixture());
    same(['P2_DUPLICATE_KEY'], discoveryBlock('{"encoding":"UTF-8",'.substr($j, 1))['reasons']);
});
test('P2 10 nested duplicate keys and escaped key equivalence rejected', function(): void {
    foreach (['"kind":"PRIMARY","kind":"PRIMARY"', '"kind":"PRIMARY","\\u006bind":"PRIMARY"'] as $replacement) {
        $j = str_replace('"kind":"PRIMARY"', $replacement, discoveryJson(discoveryFixture()));
        same(['P2_DUPLICATE_KEY'], discoveryBlock($j)['reasons']);
    }
    $j = str_replace('"encoding":"UTF-8"', '"encoding":"UTF-8","enco\\u0064ing":"UTF-8"', discoveryJson(discoveryFixture()));
    same(['P2_DUPLICATE_KEY'], discoveryBlock($j)['reasons']);
});
test('P2 11 malformed JSON invalid Unicode BOM surrogate and trailing input blocked', function(): void {
    foreach (['{', '[]', '{}[]', '{"a":1,}', "\xFF", "\xEF\xBB\xBF{}", '{"a":"\\uD800"}', "{\"a\":\"\0\"}"] as $j) { discoveryBlock($j); }
});
test('P2 12 byte string depth and node limits enforced', function(): void {
    discoveryBlock(str_repeat(' ', StrictJson::MAX_BYTES+1));
    discoveryBlock('{"a":"'.str_repeat('x',129).'"}');
    discoveryBlock(str_repeat('{"a":',17).'0'.str_repeat('}',17));
    same(['P2_COMPLEXITY'], discoveryBlock('{"a":['.implode(',', array_fill(0,8192,'0')).']}')['reasons']);
});
test('P2 13 out of band expected source binding rejects another source', function(): void {
    $r = discoveryRun(discoveryJson(discoveryFixture()), [], 'source-'.discoveryId('other'));
    same('BLOCKED', $r['status']); same(null, $r['metadata_fingerprint']);
});
test('P2 14 snapshot provenance and artifact binding mismatch blocked', function(): void {
    foreach (['source_id', 'snapshot_id'] as $key) {
        discoveryBlock(discoveryChange(function(array &$f) use ($key): void {
            $f['provenance'][$key] = ($key === 'source_id' ? 'source-' : 'snapshot-').discoveryId('other');
        }));
    }
    discoveryBlock(discoveryChange(function(array &$f): void { $f['provenance']['collected_at'] = '2026-10-28T00:00:00Z'; }));
    discoveryBlock(discoveryChange(function(array &$f): void { $f['provenance']['artifact_sha256'] = 'not-digest'; }));
});
test('P2 15 every category rejects mixed source snapshot metadata', function(): void {
    foreach (['version','schema','temporal','password','custom_fields','files'] as $category) {
        foreach (['source_id','snapshot_id'] as $key) {
            discoveryBlock(discoveryChange(function(array &$f) use ($category, $key): void {
                $f[$category][$key] = ($key === 'source_id' ? 'source-' : 'snapshot-').discoveryId('other');
            }));
        }
    }
});
test('P2 16 content fingerprint and schema fingerprint tampering rejected', function(): void {
    $f = discoveryFixture(); $f['files']['items'][0]['size_bytes'] = 99; discoveryBlock(discoveryJson($f));
    $f = discoveryFixture(); $f['snapshot']['schema_sha256'] = discoveryId('wrong'); unset($f['content_sha256']); $f['content_sha256'] = Contract::hash($f);
    same(['P2_SCHEMA_DIGEST'], discoveryBlock(discoveryJson($f))['reasons']);
});
test('P2 17 forbidden personal data fields never enter report or exception text', function(): void {
    $marker = 'SYNTHETIC_PERSONAL_DATA_CANARY';
    $r = discoveryBlock(discoveryChange(function(array &$f) use ($marker): void { $f['source']['customer_email'] = $marker; }));
    same(false, str_contains(json_encode($r), $marker));
    $r = discoveryBlock(discoveryChange(function(array &$f) use ($marker): void { $f['schema']['schemas'][0]['tables'][0]['id'] = $marker; }));
    same(false, str_contains(json_encode($r), $marker));
});
test('P2 18 credentials session secrets and actual password hash shapes forbidden', function(): void {
    $hash = password_hash('SYNTHETIC_TEST_ONLY_NOT_A_CUSTOMER', PASSWORD_BCRYPT, ['cost' => 4]);
    foreach (['password', 'api_token', 'session_secret', 'password_hash'] as $key) {
        $r = discoveryBlock(discoveryChange(function(array &$f) use ($key, $hash): void { $f['password'][$key] = $hash; }));
        same(false, str_contains(json_encode($r), $hash));
    }
    discoveryBlock(discoveryChange(function(array &$f) use ($hash): void { $f['password']['algorithm'] = $hash; }));
});
test('P2 19 forged G-O03 CLOSED and verified authority fields rejected', function(): void {
    foreach (['G-O03' => 'CLOSED', 'source_truth' => 'REAL_SOURCE_VERIFIED', 'operator_authority' => 'APPROVED'] as $key => $value) {
        discoveryBlock(discoveryChange(function(array &$f) use ($key, $value): void { $f[$key] = $value; }));
    }
});
test('P2 20 missing file inventory is UNKNOWN absent category or contradictory items blocked', function(): void {
    $r = discoveryRun(discoveryChange(function(array &$f): void { $f['files']['state'] = 'MISSING'; $f['files']['items'] = []; }));
    same('UNKNOWN', $r['assessment']['files']);
    discoveryBlock(discoveryChange(function(array &$f): void { $f['files']['state'] = 'MISSING'; }));
    $f=discoveryFixture(); unset($f['files']); discoveryBlock(discoveryJson($f));
});
test('P2 21 canonical fingerprint deterministic across repeat and object key order', function(): void {
    $f = discoveryFixture(); $r = discoveryRun(discoveryJson($f));
    same($r, discoveryRun(discoveryJson($f)));
    same($r, discoveryRun(json_encode(array_reverse($f,true), JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)));
});
test('P2 22 malformed typed evidence never produces offline positive', function(): void {
    foreach ([false, -1, 'true', null, [], (object)[]] as $value) {
        $f=discoveryFixture(); $f['files']['items'][0]['size_bytes']=$value; discoveryBlock(discoveryJson(discoverySeal($f)));
    }
    discoveryBlock(str_replace('"size_bytes":0', '"size_bytes":1.5', discoveryJson(discoveryFixture())));
});
test('P2 23 cutover effects readiness escalation always blocked', function(): void {
    foreach (['effects_allowed'=>true,'cutover_allowed'=>true,'status'=>'CUTOVER_GO','real_gate_status'=>'OPEN'] as $key=>$value) {
        discoveryBlock(discoveryChange(function(array &$f) use($key,$value): void { $f[$key]=$value; }));
    }
});
test('P2 24 no path URL traversal or filesystem locator accepted', function(): void {
    foreach (['../private', '/etc/passwd', 'C:\\private', 'https://example.invalid/a', '%2e%2e/private'] as $path) {
        discoveryBlock(discoveryChange(function(array &$f) use($path): void { $f['files']['items'][0]['path']=$path; }));
        $r=(new Validator())->validateStream($path,'source-'.discoveryId('a'),'snapshot-'.discoveryId('b'));
        same('BLOCKED',$r['status']); same(false,$r['effects_allowed']);
    }
});
test('P2 25 regular file handle bounded read and caller ownership preserved', function(): void {
    $f=discoveryFixture(); $h=tmpfile();
    try {
        fwrite($h,discoveryJson($f)); rewind($h);
        $r=(new Validator())->validateStream($h,$f['source']['id'],$f['snapshot']['id']);
        same('OFFLINE_DISCOVERY_CONTRACT_VALIDATED',$r['status']); same($f['content_sha256'],$r['metadata_fingerprint']);
        same(false,$r['cutover_allowed']); same(true,is_resource($h));
        same('BLOCKED',(new Validator())->validateStream($h,$f['source']['id'],$f['snapshot']['id'])['status']);
    } finally { fclose($h); }
});
test('P2 26 oversized file and non regular stream rejected without path reads', function(): void {
    $f=discoveryFixture(); $h=tmpfile();
    try { fwrite($h,str_repeat(' ',StrictJson::MAX_BYTES+1));rewind($h);same('BLOCKED',(new Validator())->validateStream($h,$f['source']['id'],$f['snapshot']['id'])['status']); }
    finally {fclose($h);}
    $h=fopen('php://memory','r+');
    try {fwrite($h,discoveryJson($f));rewind($h);same('BLOCKED',(new Validator())->validateStream($h,$f['source']['id'],$f['snapshot']['id'])['status']);}
    finally {fclose($h);}
});
test('P2 27 duplicate inventory IDs index references primary and bounds rejected', function(): void {
    discoveryBlock(discoveryChange(function(array &$f):void {$f['schema']['schemas'][]=$f['schema']['schemas'][0];}));
    discoveryBlock(discoveryChange(function(array &$f):void {$t=&$f['schema']['schemas'][0]['tables'][0];$t['columns'][]=$t['columns'][0];}));
    discoveryBlock(discoveryChange(function(array &$f):void {$f['schema']['schemas'][0]['tables'][0]['indexes'][0]['columns']=[discoveryId('absent')];}));
    discoveryBlock(discoveryChange(function(array &$f):void {$f['schema']['schemas'][0]['tables'][0]['columns'][0]['nullable']=true;}));
    discoveryBlock(discoveryChange(function(array &$f):void {$f['files']['items']=array_fill(0,129,$f['files']['items'][0]);}));
});
test('P2 28 timezone date ordering and uncertainty allowlist fail closed', function(): void {
    same('INCOMPATIBLE', discoveryBlock(discoveryChange(function(array &$f):void {$f['temporal']['timezone']['value']='Europe/Istanbul';}))['assessment']['temporal']);
    discoveryBlock(discoveryChange(function(array &$f):void {$f['snapshot']['watermark']='2026-11-01T00:00:00Z';}));
    discoveryBlock(discoveryChange(function(array &$f):void {$f['uncertainties'][]='OPERATOR_APPROVED';}));
});
test('P2 29 sanitized operator supplied classification never establishes truth', function(): void {
    $r=discoveryRun(discoveryChange(function(array &$f):void {$f['classification']='SANITIZED_METADATA';$f['provenance']['kind']='OPERATOR_SUPPLIED_UNVERIFIED';}));
    same('OFFLINE_DISCOVERY_CONTRACT_VALIDATED',$r['status']); same('UNVERIFIED',$r['source_truth']);
    discoveryBlock(discoveryChange(function(array &$f):void {$f['classification']='REAL_SOURCE_VERIFIED';}));
});
test('P2 30 strict object list distinction and unknown enum values rejected', function(): void {
    discoveryBlock(discoveryChange(function(array &$f):void {$f['custom_fields']['items']=(object)[];}));
    discoveryBlock(discoveryChange(function(array &$f):void {$f['files']['items'][0]['storage']='PUBLIC';}));
    discoveryBlock(discoveryChange(function(array &$f):void {$f['schema']['schemas'][0]['tables'][0]['columns'][0]['type']='SECRET_TEXT';}));
});
test('P2 31 escaped arbitrary strings do not confuse JSON duplicate scanning', function(): void {
    $f=discoveryFixture(); $j=str_replace('UTF-8', 'UTF\\u002d8', discoveryJson($f));
    same(discoveryRun(discoveryJson($f)),discoveryRun($j));
    $escaped = json_encode(['a' => 'quote"comma,colon:bracket]'], JSON_THROW_ON_ERROR);
    foreach ([substr($escaped, 0, -1).',"a":1}', '{"a":{"b":[{"x":1,"x":2}]}}'] as $json) {
        same(['P2_DUPLICATE_KEY'],discoveryBlock($json)['reasons']);
    }
});
test('P2 32 conflicting knowledge cannot be downgraded to unknown by date policy', function(): void {
    foreach (['UNKNOWN','LOCAL_UNVERIFIED','UTC'] as $policy) {
        $r=discoveryBlock(discoveryChange(function(array &$f) use($policy):void {$f['temporal']['timezone']=['state'=>'CONFLICTING','value'=>null];$f['temporal']['date_policy']=$policy;}));
        same('INCOMPATIBLE',$r['assessment']['temporal']);
    }
});
test('P2 33 column charset metadata checked independently of table defaults', function(): void {
    $r=discoveryBlock(discoveryChange(function(array &$f):void {
        $c=&$f['schema']['schemas'][0]['tables'][0]['columns'][0];
        $c['type']='STRING';$c['charset']='UTF8MB4';$c['collation']='ASCII_BIN';
    }));same('INCOMPATIBLE',$r['assessment']['schema']);
    $r=discoveryRun(discoveryChange(function(array &$f):void {
        $c=&$f['schema']['schemas'][0]['tables'][0]['columns'][0];
        $c['type']='STRING';$c['charset']='UTF8MB4';$c['collation']='UTF8MB4_BIN';
    }));same('KNOWN_METADATA',$r['assessment']['schema']);
    discoveryBlock(discoveryChange(function(array &$f):void {
        $c=&$f['schema']['schemas'][0]['tables'][0]['columns'][0];$c['charset']='UTF8MB4';$c['collation']='UTF8MB4_BIN';
    }));
});
exit(executeTests());
