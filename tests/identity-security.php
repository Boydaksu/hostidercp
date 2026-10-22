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
    $db->execute('INSERT INTO identity_grants VALUES (?,?,?,?,?,?)',[Id::uuid(),$assignment,'services.view',$customer,'','']);
    same(403,raises(HttpError::class,fn()=>$identity->assignRole($ref,'admin',$target,$role,$customer,['permission'=>'services.view','customer'=>$other]))->status);
    raises(HttpError::class,fn()=>$identity->assignRole($ref,'admin',$target,$role,null,['permission'=>'services.view','customer'=>$customer,'service'=>'*','department'=>'other-department']));
    $identity->assignRole($ref,'admin',$target,$role,null,['permission'=>'services.view','customer'=>$customer,'service'=>'f11-service','department'=>'f11-department']);
    $db->execute('DELETE FROM identity_grants WHERE assignment_id IN (SELECT id FROM identity_assignments WHERE role_id=?)',[$role]);$db->execute('DELETE FROM identity_assignments WHERE role_id=?',[$role]);$db->execute('DELETE FROM identity_role_permissions WHERE role_id=?',[$role]);$db->execute('DELETE FROM identity_roles WHERE id=?',[$role]);
});
test('F11 confirmation binds actor session payload version expiry and rolls back validation writes',function()use($identity,$db):void{
    cleanJobs($db);[$user,$ref,$pw,$codes]=rootFixture();$coordinator=new \Hostider\Identity\CriticalActions($identity);
    $payload=['name'=>'F11 reviewed role','permissions'=>['account.view']];$command=fn():array=>$identity->manageRole($ref,'admin',null,$payload['name'],$payload['permissions']);
    same(403,raises(HttpError::class,fn()=>$coordinator->execute($ref,'admin','role-save',$payload,null,$command))->status);
    $review=$coordinator->prepare($ref,'admin','role-save',$payload,$command);same(0,(int)$db->execute('SELECT COUNT(*) FROM identity_roles WHERE name=?',[$payload['name']])->fetchColumn());
    $approval=(object)['id'=>$review['id'],'accepted'=>true];$changed=$payload;$changed['name']='Different target';
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($ref,'admin','role-save',$changed,$approval,$command))->status);
    $other=Id::uuid();$identity->login('admin@example.invalid',$pw,'admin',$other,'f11-other');$identity->verifyMfa($other,'admin',$codes[0]);$identity->stepUp($other,'admin',$pw,$codes[1]);
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($other,'admin','role-save',$payload,$approval,$command))->status);
    $clientPw=bin2hex(random_bytes(20));$identity->register('other-actor@example.invalid','Other actor',$clientPw,'other-actor');$client=$db->execute("SELECT id FROM identity_users WHERE email='other-actor@example.invalid'")->fetchColumn();$identity->completeToken(notificationToken($client,'verify'),'verify');$clientRef=Id::uuid();$identity->login('other-actor@example.invalid',$clientPw,'client',$clientRef,'other-actor');$identity->stepUp($clientRef,'client',$clientPw,'');
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($clientRef,'client','role-save',$payload,$approval,$command))->status);
    $db->execute('UPDATE identity_confirmations SET expires_at=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6)) WHERE id=?',[$review['id']]);
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($ref,'admin','role-save',$payload,$approval,$command))->status);
    $role=$db->execute("SELECT id FROM identity_roles WHERE name='Alt Kullanıcı'")->fetchColumn();$edit=['id'=>$role,'name'=>'Alt Kullanıcı','permissions'=>['account.view']];$editCommand=fn():array=>$identity->manageRole($ref,'admin',$role,$edit['name'],$edit['permissions']);
    $review=$coordinator->prepare($ref,'admin','role-save',$edit,$editCommand);$db->execute('UPDATE identity_roles SET version=version+1 WHERE id=?',[$role]);
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($ref,'admin','role-save',$edit,(object)['id'=>$review['id'],'accepted'=>true],$editCommand))->status);
});
test('F11 confirmation consumption business writes and audit are atomic and replay is single use',function()use($identity,$db):void{
    cleanJobs($db);[$user,$ref]=rootFixture();$coordinator=new \Hostider\Identity\CriticalActions($identity);$payload=['name'=>'F11 atomic role','permissions'=>['account.view']];$command=fn():array=>$identity->manageRole($ref,'admin',null,$payload['name'],$payload['permissions']);
    $review=$coordinator->prepare($ref,'admin','role-save',$payload,$command);$approval=(object)['id'=>$review['id'],'accepted'=>true];
    raises(RuntimeException::class,fn()=>$coordinator->execute($ref,'admin','role-save',$payload,$approval,function()use($command):array{$command();throw new RuntimeException('synthetic failure');}));
    same(null,$db->execute('SELECT consumed_at FROM identity_confirmations WHERE id=?',[$review['id']])->fetchColumn());
    same(0,(int)$db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='critical.confirmed'")->fetchColumn());
    same(0,(int)$db->execute('SELECT COUNT(*) FROM identity_roles WHERE name=?',[$payload['name']])->fetchColumn());
    $role=$coordinator->execute($ref,'admin','role-save',$payload,$approval,$command)['id'];
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($ref,'admin','role-save',$payload,$approval,$command))->status);
    same(1,(int)$db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='critical.confirmed'")->fetchColumn());
    $db->execute('DELETE FROM identity_role_permissions WHERE role_id=?',[$role]);$db->execute('DELETE FROM identity_roles WHERE id=?',[$role]);
});
test('F11 two concurrent confirmation consumers commit only one critical mutation',function()use($identity,$identityConfig,$db):void{
    cleanJobs($db);[$user,$ref]=rootFixture();$payload=['name'=>'F11 concurrent role','permissions'=>['account.view']];
    $review=(new \Hostider\Identity\CriticalActions($identity))->prepare($ref,'admin','role-save',$payload,fn():array=>$identity->manageRole($ref,'admin',null,$payload['name'],$payload['permissions']));
    $file=dirname(__DIR__).'/storage/confirmation-'.bin2hex(random_bytes(8)).'.json';file_put_contents($file,json_encode(['key'=>$identityConfig->key,'reference'=>$ref,'confirmation'=>$review['id'],'payload'=>$payload],JSON_THROW_ON_ERROR));
    $children=[];try{
        for($i=0;$i<2;++$i){$pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/confirmation-child.php',$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$children[]=[$p,$pipes];}
        $results=[];foreach($children as[$p,$pipes]){$results[]=stream_get_contents($pipes[1]);same('',stream_get_contents($pipes[2]));fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($p));}sort($results);same(['200','409'],$results);
        same(1,(int)$db->execute('SELECT COUNT(*) FROM identity_roles WHERE name=?',[$payload['name']])->fetchColumn());
    }finally{unlink($file);$role=$db->execute('SELECT id FROM identity_roles WHERE name=?',[$payload['name']])->fetchColumn();if($role){$db->execute('DELETE FROM identity_role_permissions WHERE role_id=?',[$role]);$db->execute('DELETE FROM identity_roles WHERE id=?',[$role]);}}
});
test('F11 trusted customer login and renewal do not grant critical freshness',function()use($identity,$db):void{
    cleanIdentity();cleanJobs($db);$pw=bin2hex(random_bytes(20));$identity->register('trusted-f11@example.invalid','Trusted fixture',$pw,'f11-trusted');$id=$db->execute("SELECT id FROM identity_users WHERE email='trusted-f11@example.invalid'")->fetchColumn();$identity->completeToken(notificationToken($id,'verify'),'verify');
    $ref=Id::uuid();$identity->login('trusted-f11@example.invalid',$pw,'client',$ref,'f11-trusted');$setup=$identity->enroll($ref,'client',$pw);$codes=$identity->confirmEnrollment($ref,'client',Totp::code($setup['secret'],intdiv(time(),30)));
    same(null,$identity->repository->session($ref,'client')['verified_at']);$identity->stepUp($ref,'client',$pw,$codes['recovery_codes'][0]);$device=$identity->trustDevice($ref,'client');$next=Id::uuid();
    same('active',$identity->login('trusted-f11@example.invalid',$pw,'client',$next,'f11-trusted',$device['device_token'])['stage']);
    $identity->locked($next,'client',fn()=>null);same(null,$identity->repository->session($next,'client')['verified_at']);
    raises(HttpError::class,fn()=>$identity->createKey($next,'client',['account.view'],60));
    $identity->stepUp($next,'client',$pw,$codes['recovery_codes'][1]);same(true,isset($identity->createKey($next,'client',['account.view'],60)['id']));
});
test('F11 stale customer policy staff target and membership approvals are rejected',function()use($identity,$db):void{
    cleanJobs($db);[$user,$ref]=rootFixture();$pw=bin2hex(random_bytes(20));$identity->register('policy-f11@example.invalid','Policy fixture',$pw,'f11-policy');$target=$db->execute("SELECT id FROM identity_users WHERE email='policy-f11@example.invalid'")->fetchColumn();$identity->completeToken(notificationToken($target,'verify'),'verify');$clientRef=Id::uuid();$login=$identity->login('policy-f11@example.invalid',$pw,'client',$clientRef,'f11-policy');$customer=$login['customer_id'];$identity->stepUp($clientRef,'client',$pw,'');$coordinator=new \Hostider\Identity\CriticalActions($identity);
    $payload=['customer'=>$customer,'name'=>'Policy fixture','mfa_required'=>true,'version'=>1];$command=fn():array=>(function()use($identity,$clientRef,$customer):array{$identity->updateAccount($clientRef,'client',$customer,'Policy fixture',true,1);return ['ok'=>true];})();
    $review=$coordinator->prepare($clientRef,'client','account-update',$payload,$command);$db->execute('UPDATE identity_customers SET version=version+1 WHERE id=?',[$customer]);
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($clientRef,'client','account-update',$payload,(object)['id'=>$review['id'],'accepted'=>true],$command))->status);
    $payload=['user'=>$target,'enabled'=>true,'administrative'=>false];$command=function()use($identity,$ref,$target):array{$identity->staff($ref,'admin',$target,true,false);return ['ok'=>true];};
    $review=$coordinator->prepare($ref,'admin','staff-save',$payload,$command);$db->execute('UPDATE identity_users SET display_name=? WHERE id=?',['Changed fixture',$target]);
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($ref,'admin','staff-save',$payload,(object)['id'=>$review['id'],'accepted'=>true],$command))->status);
    $sub=Id::uuid();$db->execute('INSERT INTO identity_users(id,email,display_name,verified_at) VALUES (?,?,?,UTC_TIMESTAMP(6))',[$sub,'member-f11@example.invalid','Member fixture']);$db->execute('INSERT INTO identity_credentials(user_id,password_hash) VALUES (?,?)',[$sub,\Hostider\Identity\Passwords::hash(bin2hex(random_bytes(20)))]);$db->execute('INSERT INTO identity_memberships VALUES (?,?,FALSE)',[$sub,$customer]);
    $payload=['customer'=>$customer,'user'=>$sub];$command=function()use($identity,$clientRef,$customer,$sub):array{$identity->removeMembership($clientRef,'client',$customer,$sub);return ['ok'=>true];};
    $review=$coordinator->prepare($clientRef,'client','member-remove',$payload,$command);$db->execute('UPDATE identity_users SET identity_version=identity_version+1 WHERE id=?',[$sub]);
    same(409,raises(HttpError::class,fn()=>$coordinator->execute($clientRef,'client','member-remove',$payload,(object)['id'=>$review['id'],'accepted'=>true],$command))->status);
});
runTests();
