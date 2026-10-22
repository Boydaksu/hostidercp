<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';
use Hostider\Core\{Config, Container, Id, Idempotency, SafeLogger, Validation};
use Hostider\Core\Http\{Request, Response, Router, Kernel, HttpError};
use Hostider\Core\Security\{Authorization, Principal, Csrf, Crypto, EffectPolicy};
use Hostider\Providers\Contract\{Operation, ProviderResult, PaymentGateway, HostingProvider, DomainRegistrar, ComputeProvider};
use Hostider\Providers\Mock\{Paytr, Cpanel, ResellerClub, DomainNameApi, Voyar};

test('config rejects absent environment/key and staging live flags', function (): void {
    raises(InvalidArgumentException::class, fn() => new Config('', '', '', '', '', ''));
    $c = testConfig();
    foreach (['true', 'false'] as $flag) { same(is_string($flag), true); }
    raises(InvalidArgumentException::class, fn() => new Config('staging', $c->url, $c->key, $c->dsn, 'test', '', true));
    same('staging', $c->environment);
});
test('effect policy denies every live effect in all environments', function (): void {
    foreach (['test','staging','production'] as $env) {
        $p = new EffectPolicy(testConfig($env));
        foreach (['provisioning','payments','mail'] as $effect) {
            raises(RuntimeException::class, fn() => $p->assertAllowed($effect, false));
            $p->assertAllowed($effect, true);
        }
        raises(LogicException::class, fn() => $p->assertAllowed('typo', true));
    }
});
test('production flags cannot bypass open F0 provider gate', function (): void {
    $c = testConfig('production');
    $p = new EffectPolicy(new Config('production', $c->url, $c->key, $c->dsn, 'test', '', true, true, true));
    raises(RuntimeException::class, fn() => $p->assertAllowed('provisioning', false));
});
test('DI shares services and detects circular/missing/duplicate bindings', function (): void {
    $c = new Container(); $c->bind('a', fn() => new stdClass()); same($c->get('a'), $c->get('a'));
    raises(LogicException::class, fn() => $c->bind('a', fn() => new stdClass()));
    raises(LogicException::class, fn() => $c->get('missing'));
    $c->bind('cycle', fn(Container $x) => $x->get('cycle'));
    raises(LogicException::class, fn() => $c->get('cycle'));
});
test('router middleware wraps handler in declaration order', function (): void {
    $order = []; $r = new Router();
    $r->add('GET','/probe', function (Request $q, string $t) use (&$order): Response { $order[] = 'handler'; return Response::data(['ok'=>true], $t); }, [
        function (Request $q, Closure $next) use (&$order): Response { $order[] = 'before'; $res = $next($q); $order[] = 'after'; return $res; }
    ]);
    same(200, $r->dispatch(new Request('GET','/probe'), Id::uuid())->status);
    same(['before','handler','after'], $order);
    same(404, raises(HttpError::class, fn() => $r->dispatch(new Request('GET','/none'), Id::uuid()))->status);
    same(405, raises(HttpError::class, fn() => $r->dispatch(new Request('POST','/probe'), Id::uuid()))->status);
});
test('kernel error envelope and logger never leak message/body/headers', function (): void {
    $lines = []; $r = new Router();
    $r->add('GET','/error', fn() => throw new RuntimeException('password=private customer@example.invalid'));
    $k = new Kernel($r, new SafeLogger(function (string $line) use (&$lines): void { $lines[] = $line; }));
    $res = $k->handle(new Request('GET','/error', ['authorization'=>'secret'], 'PII'));
    same(500, $res->status); same(['data','meta','errors'], array_keys($res->envelope));
    same(false, str_contains(json_encode([$res->envelope,$lines]), 'private'));
    same(false, str_contains(json_encode($lines), 'secret'));
    same(404, $k->handle(new Request('GET','/none'))->status);
});
test('JSON media type/object/depth/size and validation are bounded', function (): void {
    same(['value'=>1], (new Request('POST','/', ['content-type'=>'application/json'], '{"value":1}'))->json());
    foreach (['[]','null','{','1'] as $body) { raises(HttpError::class, fn() => (new Request('POST','/', ['content-type'=>'application/json'], $body))->json()); }
    same(415, raises(HttpError::class, fn() => (new Request('POST','/'))->json())->status);
    same(413, raises(HttpError::class, fn() => (new Request('POST','/', [], str_repeat('x',65537)))->json())->status);
    raises(HttpError::class, fn() => Validation::fields(['customer_id'=>'other'], ['name'], ['name']));
    raises(HttpError::class, fn() => Validation::text("a\n"));
    same('Hostider', Validation::text('Hostider'));
});
test('CSRF denies missing/wrong tokens and allows safe reads', function (): void {
    $token = Csrf::token(); Csrf::verify(new Request('GET','/'), '');
    raises(HttpError::class, fn() => Csrf::verify(new Request('POST','/'), $token));
    raises(HttpError::class, fn() => Csrf::verify(new Request('DELETE','/', ['x-csrf-token'=>str_repeat('0',64)]), $token));
    Csrf::verify(new Request('POST','/', ['x-csrf-token'=>$token]), $token);
});
test('authorization denies anonymous/expired/missing permission/customer scope', function (): void {
    $a = new Authorization();
    raises(HttpError::class, fn() => $a->require(null,'view'));
    raises(HttpError::class, fn() => $a->require(new Principal('actor',['view'],'a'),'view','a'));
    $p = new Principal('actor',['view'],'a', expiresAt:2000);
    $a->require($p,'view','a',now:1000);
    raises(HttpError::class, fn() => $a->require($p,'view','b',now:1000));
    raises(HttpError::class, fn() => $a->require($p,'edit','a',now:1000));
});
test('staff requires MFA, department membership and fresh step-up', function (): void {
    $a = new Authorization();
    raises(HttpError::class, fn() => $a->require(new Principal('staff',['view'],staff:true,expiresAt:2000),'view',now:1000));
    $p = new Principal('staff',['view'],departments:['support'],staff:true,mfa:true,expiresAt:2000,verifiedAt:999);
    $a->require($p,'view',department:'support',privileged:true,now:1000);
    raises(HttpError::class, fn() => $a->require($p,'view',department:'finance',now:1000));
    raises(HttpError::class, fn() => $a->require($p,'view',privileged:true,now:1400));
    $imp = new Principal('staff',['view'],staff:true,mfa:true,expiresAt:2000,verifiedAt:999,impersonating:true);
    raises(HttpError::class, fn() => $a->require($imp,'view',privileged:true,now:1000));
});
test('AEAD roundtrip randomized nonce, tamper/context/key binding', function (): void {
    $c = new Crypto(random_bytes(32)); $e = $c->encrypt('private-value','provider:one');
    same('private-value', $c->decrypt($e,'provider:one'));
    same(false, $e === $c->encrypt('private-value','provider:one'));
    raises(RuntimeException::class, fn() => $c->decrypt($e,'provider:two'));
    raises(RuntimeException::class, fn() => (new Crypto(random_bytes(32)))->decrypt($e,'provider:one'));
    raises(RuntimeException::class, fn() => $c->decrypt(substr($e,0,-4).'AAAA','provider:one'));
    raises(InvalidArgumentException::class, fn() => new Crypto('short'));
});
test('canonical fingerprint ignores map ordering and preserves lists/types', function (): void {
    same(Idempotency::fingerprint(['b'=>['z'=>1,'a'=>2],'a'=>3]), Idempotency::fingerprint(['a'=>3,'b'=>['a'=>2,'z'=>1]]));
    same(false, Idempotency::fingerprint([1,2]) === Idempotency::fingerprint([2,1]));
    same(false, Idempotency::fingerprint([1]) === Idempotency::fingerprint(['1']));
    raises(InvalidArgumentException::class, fn() => Idempotency::fingerprint([1.5]));
});
foreach ([Paytr::class=>PaymentGateway::class, Cpanel::class=>HostingProvider::class, ResellerClub::class=>DomainRegistrar::class, DomainNameApi::class=>DomainRegistrar::class, Voyar::class=>ComputeProvider::class] as $class=>$interface) {
    test('provider contract ' . $class, function () use ($class,$interface): void {
        $schema = json_decode(file_get_contents(dirname(__DIR__) . '/contracts/provider_result.schema.json'),true,32,JSON_THROW_ON_ERROR);
        foreach (['SUCCESS','DEFINITIVE_FAILURE','UNKNOWN','UNSUPPORTED'] as $scenario) {
            $p = new $class(new EffectPolicy(testConfig()),$scenario); same(true, $p instanceof $interface);
            same(false,$p->capabilities()['liveValidated']);
            foreach ($p->capabilities()['actions'] as $action) {
                $op = new Operation(Id::uuid(),$action);
                $res = $p->{$action}($op); same($scenario,$res->status);
                same($res, $p->{$action}($op));
                $json = $res->jsonSerialize();
                same([], array_values(array_diff($schema['required'],array_keys($json))));
                same([], array_values(array_diff(array_keys($json),array_keys($schema['properties']))));
                same(true, in_array($json['status'],$schema['properties']['status']['enum'],true));
                same(true, strtotime($json['recordedAt']) !== false);
                raises(LogicException::class, fn() => $p->execute(new Operation($op->id,$action,configuration:['different'=>true])));
            }
            same(count($p->capabilities()['actions']),$p->calls);
            same('UNSUPPORTED',$p->execute(new Operation(Id::uuid(),'unknown'))->status);
        }
    });
}
test('mock PayTR never accepts live callback as verified payment', function (): void {
    raises(LogicException::class, fn() => (new Paytr(new EffectPolicy(testConfig())))->verifyCallback(['status'=>'success']));
});
test('provider normalized results reject unrecognized status/provider/PII error', function (): void {
    raises(InvalidArgumentException::class, fn() => new ProviderResult(Id::uuid(),'voyar','PAID'));
    raises(InvalidArgumentException::class, fn() => new ProviderResult(Id::uuid(),'unknown','SUCCESS'));
    raises(InvalidArgumentException::class, fn() => new ProviderResult(Id::uuid(),'voyar','UNKNOWN',safeErrorCode:'email@example.invalid'));
});
runTests();
