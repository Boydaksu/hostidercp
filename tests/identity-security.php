<?php
declare(strict_types=1);
define('IDENTITY_FIXTURES_ONLY',true);require __DIR__.'/identity.php';$GLOBALS['tests']=[];
use Hostider\Core\Id;
use Hostider\Core\Http\HttpError;
use Hostider\Identity\Totp;
test('F11 MFA active challenge cannot refresh critical verification and explicit password factor is required',function()use($identity,$db):void{
    cleanJobs($db);[$user,$ref,$pw,$codes,$secret]=rootFixture();
    $db->execute('UPDATE identity_sessions SET verified_at=NULL WHERE native_reference=?',[$ref]);
    $future=Totp::code($secret,intdiv(time(),30)+1);
    same(409,raises(HttpError::class,fn()=>$identity->verifyMfa($ref,'admin',$future))->status);
    same(null,$identity->repository->session($ref,'admin')['verified_at']);
    raises(HttpError::class,fn()=>$identity->manageRole($ref,'admin',null,'Denied synthetic',['account.view']));
    raises(HttpError::class,fn()=>$identity->stepUp($ref,'admin','wrong',$future));
    $identity->stepUp($ref,'admin',$pw,$future);
    $role=$identity->manageRole($ref,'admin',null,'Allowed synthetic',['account.view']);same(true,isset($role['id']));
    raises(HttpError::class,fn()=>$identity->stepUp($ref,'admin',$pw,$future));
    $db->execute('DELETE FROM identity_role_permissions WHERE role_id=?',[$role['id']]);$db->execute('DELETE FROM identity_roles WHERE id=?',[$role['id']]);
});
test('F11 pending recovery login activates without step-up and trusted staff device cannot bypass MFA',function()use($identity,$db):void{
    cleanJobs($db);[$user,$ref,$pw,$codes]=rootFixture();$device=$identity->trustDevice($ref,'admin');$pending=Id::uuid();
    same('mfa',$identity->login('admin@example.invalid',$pw,'admin',$pending,'f11-device',$device['device_token'])['stage']);
    $identity->verifyMfa($pending,'admin',$codes[0]);same(null,$identity->repository->session($pending,'admin')['verified_at']);
    raises(HttpError::class,fn()=>$identity->manageRole($pending,'admin',null,'Denied after recovery',['account.view']));
    $identity->stepUp($pending,'admin',$pw,$codes[1]);same(true,(int)$identity->repository->session($pending,'admin')['verified']>0);
});
test('F11 role grants cannot cross assigned customer or delegate beyond actor resources',function()use($identity,$db):void{
    cleanJobs($db);[$user,$ref]=rootFixture();$pw=bin2hex(random_bytes(20));$identity->register('scope-f11@example.invalid','Scope fixture',$pw,'f11-scope');
    $target=$db->execute("SELECT id FROM identity_users WHERE email='scope-f11@example.invalid'")->fetchColumn();
    $customer=$db->execute('SELECT customer_id FROM identity_memberships WHERE user_id=?',[$target])->fetchColumn();$other=Id::uuid();
    $db->execute('INSERT INTO identity_customers(id,name,currency) VALUES (?,?,?)',[$other,'Other fixture','TRY']);
    $role=$identity->manageRole($ref,'admin',null,'Scoped synthetic',['services.view'])['id'];
    $assignment=$db->execute('SELECT id FROM identity_assignments WHERE user_id=?',[$user])->fetchColumn();
    $db->execute('INSERT INTO identity_resource_scopes VALUES (?,?,?)',['f11-service',$customer,'f11-department']);
    raises(HttpError::class,fn()=>$identity->assignRole($ref,'admin',$target,$role,null,['permission'=>'services.view','customer'=>$customer,'service'=>'f11-service','department'=>'f11-department']));
    $db->execute('INSERT INTO identity_grants VALUES (?,?,?,?,?,?)',[Id::uuid(),$assignment,'services.view',$customer,'*','f11-department']);
    same(403,raises(HttpError::class,fn()=>$identity->assignRole($ref,'admin',$target,$role,$customer,['permission'=>'services.view','customer'=>$other]))->status);
    raises(HttpError::class,fn()=>$identity->assignRole($ref,'admin',$target,$role,null,['permission'=>'services.view','customer'=>$customer,'service'=>'*','department'=>'other-department']));
    $identity->assignRole($ref,'admin',$target,$role,null,['permission'=>'services.view','customer'=>$customer,'service'=>'f11-service','department'=>'f11-department']);
    $db->execute('DELETE FROM identity_grants WHERE assignment_id IN (SELECT id FROM identity_assignments WHERE role_id=?)',[$role]);$db->execute('DELETE FROM identity_assignments WHERE role_id=?',[$role]);$db->execute('DELETE FROM identity_role_permissions WHERE role_id=?',[$role]);$db->execute('DELETE FROM identity_roles WHERE id=?',[$role]);
});
runTests();
