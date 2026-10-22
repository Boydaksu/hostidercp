<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';
use Hostider\Core\{CanonicalJson, Idempotency};
use Hostider\Core\Security\{Authorization, Principal, AccessGrant};
use Hostider\Core\Http\HttpError;

test('F01-AUTH staff or administrator status never grants customer access', function (): void {
    $a = new Authorization();
    foreach ([false, true] as $admin) {
        $p = new Principal('staff',['view'],staff:true,mfa:true,expiresAt:2000,administrative:$admin);
        raises(HttpError::class,fn()=>$a->require($p,'view','a',now:1000));
    }
});
test('F01-AUTH grants bind operation customer service and department together', function (): void {
    $a = new Authorization();
    $p = new Principal('staff',['view','edit'],departments:['support','finance'],staff:true,mfa:true,expiresAt:2000,
        grants:[new AccessGrant('view',['a'],['svc1'],['support'])]);
    $a->require($p,'view','a','support',now:1000,service:'svc1');
    foreach ([['edit','a','support','svc1'],['view','b','support','svc1'],['view','a','finance','svc1'],['view','a','support','svc2']] as [$op,$c,$d,$s]) {
        raises(HttpError::class,fn()=>$a->require($p,$op,$c,$d,now:1000,service:$s));
    }
    raises(HttpError::class,fn()=>$a->require($p,'view',now:1000,service:'svc1'));
});
test('F01-AUTH explicit all-customer grant is operation-bound and not impersonation', function (): void {
    $a = new Authorization();
    $p = new Principal('staff',['view','edit'],staff:true,mfa:true,expiresAt:2000,grants:[new AccessGrant('view',['*'])]);
    $a->require($p,'view','a',now:1000); $a->require($p,'view','b',now:1000);
    raises(HttpError::class,fn()=>$a->require($p,'edit','a',now:1000));
    raises(HttpError::class,fn()=>$a->require($p,'view','a',now:1000,service:'s'));
});
test('F01-AUTH impersonation cannot inherit admin or cross effective account', function (): void {
    $a = new Authorization();
    $p = new Principal('staff',['admin','edit'],customerId:'a',staff:true,mfa:true,expiresAt:2000,verifiedAt:999,
        impersonating:true,grants:[new AccessGrant('view',['*'])],effectivePermissions:['view'],administrative:true);
    $a->require($p,'view','a',now:1000);
    raises(HttpError::class,fn()=>$a->require($p,'view','b',now:1000));
    raises(HttpError::class,fn()=>$a->require($p,'admin','a',now:1000));
    raises(HttpError::class,fn()=>$a->require($p,'view','a',now:1000,administrative:true));
    raises(HttpError::class,fn()=>$a->require($p,'view','a',privileged:true,now:1000));
});
test('F01-AUTH customer service scope and explicit administrator boundary', function (): void {
    $a = new Authorization();
    $p = new Principal('customer',['view'],customerId:'a',expiresAt:2000,grants:[new AccessGrant('view',['a'],['s'])]);
    $a->require($p,'view','a',service:'s',now:1000);
    raises(HttpError::class,fn()=>$a->require($p,'view','b',service:'s',now:1000));
    raises(HttpError::class,fn()=>$a->require($p,'view','a',now:1000,administrative:true));
});
test('F01-JSON nested object order canonicalizes without collapsing types or lists', function (): void {
    same(CanonicalJson::fromJson('{"b":{"z":1,"a":2},"a":3}'),CanonicalJson::fromJson('{"a":3,"b":{"a":2,"z":1}}'));
    foreach ([['{}','[]'],['{"0":"x"}','["x"]'],['[1,2]','[2,1]'],['1','"1"'],['true','1'],['null','"null"']] as [$a,$b]) {
        same(false,CanonicalJson::fromJson($a) === CanonicalJson::fromJson($b));
    }
    same(Idempotency::fingerprint(['b'=>1,'a'=>2]),hash('sha256','{"a":2,"b":1}'));
    raises(InvalidArgumentException::class,fn()=>CanonicalJson::fromJson('{"amount":1.5}'));
});
runTests();
