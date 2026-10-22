<?php
declare(strict_types=1);
require __DIR__.'/harness.php'; require __DIR__.'/database.php';
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\{Request,HttpError};
use Hostider\Identity\{Passwords,Totp,Repository};
$db=integrationDb(); (new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function cleanIdentity(): void {
    global $db;
    foreach (['identity_impersonations','identity_api_credentials','identity_devices','identity_sessions','identity_notifications','identity_security_events','identity_recovery','identity_mfa','identity_tokens','identity_resource_scopes','identity_grants','identity_assignments','identity_staff','identity_memberships','identity_customers','identity_credentials','identity_users'] as $t) { $db->execute('DELETE FROM '.$t); }
    $db->execute('UPDATE identity_settings SET bootstrapped=FALSE');
}
test('F1-A request preserves nested object/list types through canonical fingerprint', function (): void {
    $r=new Request('POST','/', ['content-type'=>'application/json'],'{"object":{},"list":[],"deep":{"a":[],"b":{}}}');
    $v=$r->json(); same(true,$v['object'] instanceof stdClass); same([],$v['list']);
    same('{"deep":{"a":[],"b":{}},"list":[],"object":{}}',CanonicalJson::encode($v));
});
test('F1-A identity schema relationships and initial roles exist without default users', function () use ($db): void {
    cleanIdentity(); same(6,(int)$db->execute('SELECT COUNT(*) FROM identity_roles')->fetchColumn());
    same(0,(int)$db->execute('SELECT COUNT(*) FROM identity_users')->fetchColumn());
    raises(PDOException::class,fn()=>$db->execute('INSERT INTO identity_memberships VALUES (?,?,FALSE)',[Id::uuid(),Id::uuid()]));
});
test('F1-A ownership is read from authoritative database relation', function () use ($db): void {
    cleanIdentity(); $c=Id::uuid();
    $db->execute('INSERT INTO identity_customers(id,name,currency) VALUES (?,?,?)',[$c,'Synthetic','TRY']);
    $db->execute('INSERT INTO identity_resource_scopes VALUES (?,?,?)',['service-test',$c,'department-test']);
    $repo=new Repository($db);
    $db->transaction(fn()=>$repo->owns('service-test',$c,'department-test'));
    raises(HttpError::class,fn()=>$db->transaction(fn()=>$repo->owns('service-test',Id::uuid())));
    raises(HttpError::class,fn()=>$db->transaction(fn()=>$repo->owns('service-test',$c,'other')));
});
test('F1-B Argon2id policy and verification use native safe hashes', function (): void {
    $hash=Passwords::hash('synthetic-long-password'); same(true,Passwords::verify('synthetic-long-password',$hash));
    same(false,Passwords::verify('wrong',$hash)); same(false,Passwords::needsRehash($hash));
    raises(HttpError::class,fn()=>Passwords::hash('short'));
});
test('F1-C TOTP known RFC counter vector and replay fence', function (): void {
    $secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; same('287082',Totp::code($secret,1));
    same(1,Totp::counter($secret,'287082',-1,59)); same(null,Totp::counter($secret,'287082',1,59));
});
runTests();
