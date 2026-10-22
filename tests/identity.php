<?php
declare(strict_types=1);
require __DIR__.'/harness.php'; require __DIR__.'/database.php';
use Hostider\Core\{Id,CanonicalJson};
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\{Request,HttpError};
use Hostider\Identity\{Passwords,Totp,Repository};
$db=integrationDb(); (new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
$identityConfig=testConfig();
$identity=new \Hostider\Identity\IdentityService($db,$identityConfig);
function rootFixture(): array {
    global $identity;
    cleanIdentity(); $password=bin2hex(random_bytes(20)); $id=$identity->bootstrap('admin@example.invalid','Synthetic admin',$password); $ref=Id::uuid();
    $login=$identity->login('admin@example.invalid',$password,'admin',$ref,'fixture-network');
    $factor=$identity->enroll($ref,'admin',$password);
    $codes=$identity->confirmEnrollment($ref,'admin',Totp::code($factor['secret'],intdiv(time(),30)));
    return [$id,$ref,$password,$codes['recovery_codes'],$factor['secret']];
}
function notificationToken(string $user,string $purpose): string {
    global $db,$identityConfig;
    $row=$db->execute('SELECT * FROM identity_notifications WHERE user_id=? AND template=? ORDER BY created_at DESC LIMIT 1',[$user,$purpose])->fetch();
    $crypto=new \Hostider\Core\Security\Crypto(base64_decode($identityConfig->key,true));
    return json_decode($crypto->decrypt($row['body_encrypted'],'notification:'.$row['id']),true,32,JSON_THROW_ON_ERROR)['token'];
}
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
test('F1-B registration verify and reset tokens are encrypted single-use and mail remains held', function () use($db,$identity): void {
    cleanIdentity(); cleanJobs($db); $password=bin2hex(random_bytes(20));
    $identity->register('client@example.invalid','Synthetic client',$password,'network-register');
    $id=$db->execute("SELECT id FROM identity_users WHERE email='client@example.invalid'")->fetchColumn();
    $token=notificationToken($id,'verify');
    same(false,str_contains($db->execute('SELECT body_encrypted FROM identity_notifications')->fetchColumn(),$token));
    same('HELD',$db->execute('SELECT status FROM identity_notifications')->fetchColumn());
    same(['denied'=>true],$identity->login('client@example.invalid',$password,'client',Id::uuid(),'verify-pending'));
    $identity->completeToken($token,'verify'); raises(HttpError::class,fn()=>$identity->completeToken($token,'verify'));
    $ref=Id::uuid(); same('active',$identity->login('client@example.invalid',$password,'client',$ref,'verified-login')['stage']);
    $identity->requestToken('client@example.invalid','reset','reset-network'); $reset=notificationToken($id,'reset');
    $next=bin2hex(random_bytes(20)); $identity->completeToken($reset,'reset',$next);
    raises(HttpError::class,fn()=>$identity->locked($ref,'client',fn()=>null));
    raises(HttpError::class,fn()=>$identity->completeToken($reset,'reset',$next));
    same(['denied'=>true],$identity->login('missing@example.invalid',$password,'client',Id::uuid(),'missing-network'));
    same('active',$identity->login('client@example.invalid',$next,'client',Id::uuid(),'next-login')['stage']);
});
test('F1-B bootstrap is one-use has no seeded password and login throttles account attempts', function () use($identity,$db): void {
    cleanIdentity(); cleanJobs($db); $pw=bin2hex(random_bytes(20)); $identity->bootstrap('admin@example.invalid','Synthetic admin',$pw);
    raises(HttpError::class,fn()=>$identity->bootstrap('second@example.invalid','second',$pw));
    for($i=0;$i<10;++$i){same(['denied'=>true],$identity->login('nobody@example.invalid','wrong','admin',Id::uuid(),'different'.$i));}
    same(429,raises(HttpError::class,fn()=>$identity->login('nobody@example.invalid','wrong','admin',Id::uuid(),'extra'))->status);
});
test('F1-C staff enrollment MFA recovery replay and trusted-device bypass denial', function () use($identity,$db): void {
    cleanJobs($db); [$id,$ref,$pw,$codes,$secret]=rootFixture();
    raises(HttpError::class,fn()=>$identity->verifyMfa($ref,'admin',Totp::code($secret,intdiv(time(),30))));
    $device=$identity->trustDevice($ref,'admin');
    $next=Id::uuid(); same('mfa',$identity->login('admin@example.invalid',$pw,'admin',$next,'new-network',$device['device_token'])['stage']);
    raises(HttpError::class,fn()=>$identity->locked($next,'admin',fn()=>null));
    $identity->verifyMfa($next,'admin',$codes[0]);
    raises(HttpError::class,fn()=>$identity->verifyMfa($next,'admin',$codes[0]));
    same(1,(int)$db->execute('SELECT COUNT(*) FROM identity_recovery WHERE used_at IS NOT NULL')->fetchColumn());
    raises(HttpError::class,fn()=>$identity->disableMfa($next,'admin'));
});
test('F1-B persistent guard logout-all and password change invalidate sessions', function () use($identity,$db): void {
    cleanJobs($db); [$id,$ref,$pw,$codes]=rootFixture();
    $session=$identity->repository->session($ref,'admin');
    $guard=new \Hostider\Identity\PersistentSessionGuard($identity->repository,'admin');
    $snapshot=new \Hostider\Core\Security\SessionSnapshot([],0,$ref); $guard->assertActive($snapshot);
    $identity->changePassword($ref,'admin',$pw,bin2hex(random_bytes(20)));
    raises(HttpError::class,fn()=>$guard->assertActive($snapshot));
    same(0,(int)$db->execute('SELECT COUNT(*) FROM identity_sessions WHERE revoked_at IS NULL')->fetchColumn());
});
runTests();
