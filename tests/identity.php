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
test('F1-D explicit staff customer grant and impersonation actor constraints', function () use($identity,$db): void {
    cleanJobs($db); [$root,$ref,$pw,$codes]=rootFixture();
    $clientPw=bin2hex(random_bytes(20)); $identity->register('customer@example.invalid','Customer',$clientPw,'new-customer');
    $client=$db->execute("SELECT id FROM identity_users WHERE email='customer@example.invalid'")->fetchColumn();
    $identity->completeToken(notificationToken($client,'verify'),'verify');
    $customer=$db->execute('SELECT customer_id FROM identity_memberships WHERE user_id=?',[$client])->fetchColumn();
    raises(HttpError::class,fn()=>$identity->impersonate($ref,'admin',$customer,$client,'Synthetic support'));
    $assignment=$db->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL',[$root])->fetchColumn();
    $db->execute('INSERT INTO identity_grants VALUES (?,?,?,?,?,?)',[Id::uuid(),$assignment,'identity.impersonate',$customer,'','']);
    $identity->impersonate($ref,'admin',$customer,$client,'Synthetic support');
    same($customer,$identity->view($ref,'admin','account',$customer)['id']);
    raises(HttpError::class,fn()=>$identity->createKey($ref,'admin',['identity.users.view'],3600));
    raises(HttpError::class,fn()=>$identity->view($ref,'admin','users'));
    $db->execute('UPDATE identity_impersonations SET expires_at=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6))');
    raises(HttpError::class,fn()=>$identity->view($ref,'admin','users'));
    $identity->endImpersonation($ref,'admin',$pw,$codes[0]);
    same(false,$identity->view($ref,'admin','me')['impersonating']);
    same($root,$db->execute("SELECT actor_id FROM audit_logs WHERE action='impersonation.started' ORDER BY id DESC LIMIT 1")->fetchColumn());
});
test('F1-D invitation creates membership without staff permission escalation', function () use($identity,$db): void {
    cleanIdentity(); cleanJobs($db); $pw=bin2hex(random_bytes(20)); $identity->register('owner@example.invalid','Owner',$pw,'owner-reg');
    $owner=$db->execute("SELECT id FROM identity_users WHERE email='owner@example.invalid'")->fetchColumn();
    $identity->completeToken(notificationToken($owner,'verify'),'verify'); $ref=Id::uuid();
    $login=$identity->login('owner@example.invalid',$pw,'client',$ref,'owner-login'); $customer=$login['customer_id'];
    $identity->stepUp($ref,'client',$pw,'');
    $role=$db->execute("SELECT id FROM identity_roles WHERE name='Alt Kullanıcı'")->fetchColumn();
    $identity->invite($ref,'client',$customer,'invite@example.invalid',$role);
    $invitee=$db->execute("SELECT id FROM identity_users WHERE email='invite@example.invalid'")->fetchColumn();
    $inviteToken=notificationToken($invitee,'invite'); $next=bin2hex(random_bytes(20)); $identity->completeToken($inviteToken,'invite',$next);
    $subref=Id::uuid(); same('active',$identity->login('invite@example.invalid',$next,'client',$subref,'sub-login')['stage']);
    same($customer,$identity->view($subref,'client','account')['id']);
    $db->execute('INSERT INTO identity_resource_scopes VALUES (?,?,?)',['scoped-service',$customer,'scoped-department']);
    raises(HttpError::class,fn()=>$identity->locked($subref,'client',fn($u,$s)=>$identity->authorize($u,$s,'services.view',$customer,'scoped-service')));
    $identity->memberScope($ref,'client',$customer,$invitee,'services.view','scoped-service','scoped-department');
    raises(HttpError::class,fn()=>$identity->view($subref,'client','me'));
    $subref=Id::uuid(); $identity->login('invite@example.invalid',$next,'client',$subref,'sub-login-scope');
    $identity->locked($subref,'client',fn($u,$s)=>$identity->authorize($u,$s,'services.view',$customer,'scoped-service'));
    $super=$db->execute("SELECT id FROM identity_roles WHERE name='Super Admin'")->fetchColumn();
    raises(HttpError::class,fn()=>$identity->invite($ref,'client',$customer,'escalate@example.invalid',$super));
    $identity->removeMembership($ref,'client',$customer,$invitee);
    raises(HttpError::class,fn()=>$identity->view($subref,'client','me'));
});
test('F1-E API keys are shown once scoped hashed revocable and use current owner permissions', function () use($identity,$db): void {
    cleanJobs($db); [$root,$ref]=rootFixture();
    $key=$identity->createKey($ref,'admin',['identity.users.view'],3600);
    same(false,str_contains(json_encode($identity->view($ref,'admin','keys')),$key['key']));
    same($root,$identity->withKey($key['key'],'identity.users.view',null,fn($p)=>$p->actorId));
    raises(HttpError::class,fn()=>$identity->withKey($key['key'],'identity.staff.manage',null,fn()=>null));
    raises(HttpError::class,fn()=>$identity->changeKey($ref,'admin',$key['id'],['identity.users.view','identity.staff.manage']));
    $identity->changeKey($ref,'admin',$key['id']); raises(HttpError::class,fn()=>$identity->withKey($key['key'],'identity.users.view',null,fn()=>null));
    $second=$identity->createKey($ref,'admin',['identity.users.view'],3600);
    $expired=$identity->createKey($ref,'admin',['identity.users.view'],60);
    $db->execute('UPDATE identity_api_credentials SET expires_at=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6)) WHERE id=?',[$expired['id']]);
    same(401,raises(HttpError::class,fn()=>$identity->withKey($expired['key'],'identity.users.view',null,fn()=>null))->status);
    $db->execute('UPDATE identity_users SET identity_version=identity_version+1 WHERE id=?',[$root]);
    raises(HttpError::class,fn()=>$identity->withKey($second['key'],'identity.users.view',null,fn()=>null));
});

test('F1-D role revocation fences sessions and identity mutation audit rolls back atomically',function()use($identity,$db):void{
    cleanJobs($db);[$user,$ref]=rootFixture();
    $before=(int)$db->execute('SELECT COUNT(*) FROM identity_customers')->fetchColumn();
    $events=(int)$db->execute('SELECT COUNT(*) FROM identity_security_events')->fetchColumn();
    raises(RuntimeException::class,fn()=>$db->transaction(function()use($identity,$ref):void{
        $identity->createCustomer($ref,'admin','Rollback synthetic','TRY');throw new RuntimeException('synthetic rollback');
    }));
    same($before,(int)$db->execute('SELECT COUNT(*) FROM identity_customers')->fetchColumn());
    same($events,(int)$db->execute('SELECT COUNT(*) FROM identity_security_events')->fetchColumn());
    $password=bin2hex(random_bytes(20));$identity->register('revoke-role@example.invalid','Synthetic role target',$password,'role-fixture');
    $target=$db->execute("SELECT id FROM identity_users WHERE email='revoke-role@example.invalid'")->fetchColumn();
    $identity->completeToken(notificationToken($target,'verify'),'verify');$targetRef=Id::uuid();
    $identity->login('revoke-role@example.invalid',$password,'client',$targetRef,'role-login');
    $assignment=$db->execute('SELECT id FROM identity_assignments WHERE user_id=?',[$target])->fetchColumn();
    $identity->removeRole($ref,'admin',$assignment);
    same(401,raises(HttpError::class,fn()=>$identity->view($targetRef,'client','me'))->status);
    same(1,(int)$db->execute("SELECT COUNT(*) FROM identity_security_events WHERE code='permission.changed'")->fetchColumn());
});
test('F1-D revocation race serializes at transaction boundary and blocks later mutation', function() use($identity,$identityConfig,$db): void {
    cleanJobs($db); [$user,$reference]=rootFixture();
    $directory=dirname(__DIR__).'/storage/identity-race-'.bin2hex(random_bytes(8)); mkdir($directory,0700,true);
    $file=$directory.'/fixture.json'; file_put_contents($file,json_encode(['key'=>$identityConfig->key,'user'=>$user,'reference'=>$reference,'directory'=>$directory],JSON_THROW_ON_ERROR));
    $launch=function(string $mode) use($file): array {
        $pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/identity-child.php',$file,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes); fclose($pipes[0]); return [$p,$pipes];
    };
    $finish=function(array $child): string { [$p,$pipes]=$child; $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); same(0,proc_close($p)); same('',$err); return $out; };
    $wait=function(string $path): void { $deadline=hrtime(true)+4_000_000_000; while(!file_exists($path)){if(hrtime(true)>$deadline){throw new RuntimeException('Barrier timeout');}usleep(10000);} };
    try {
        $writer=$launch('mutation'); $wait($directory.'/locked'); $revoker=$launch('revoke'); $wait($directory.'/revoker-started');
        file_put_contents($directory.'/release','1'); same('OK',$finish($writer)); same('OK',$finish($revoker));
        same('mutation-committed',$db->execute('SELECT display_name FROM identity_users WHERE id=?',[$user])->fetchColumn());
        same('401',$finish($launch('mutation')));
        raises(HttpError::class,fn()=>$identity->locked($reference,'admin',fn()=>null));
    } finally { foreach(glob($directory.'/*') as $path){unlink($path);}rmdir($directory); }
});
test('F1-D a narrow resource grant cannot authorize a broad customer operation',function():void{
    $grant=new \Hostider\Core\Security\AccessGrant('view',['customer'],['service'],['department']);
    same(false,$grant->covers('view','customer',null,null));same(true,$grant->covers('view','customer','service','department'));
});
runTests();
