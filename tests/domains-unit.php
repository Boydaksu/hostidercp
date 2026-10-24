<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
use Hostider\Domains\{DomainName,TldPolicy};
use Hostider\Core\Http\HttpError;
test('F4-A canonical IDNA Unicode A-label case and root dot share one identity',function(): void{
    same('xn--bcher-kva.example',(new DomainName('BÜCHER.example.'))->ascii);
    same((new DomainName('bücher.example'))->ascii,(new DomainName('xn--bcher-kva.EXAMPLE'))->ascii);
    same('xn--fa-hia.example',(new DomainName('faß.example'))->ascii);
    same('example.com',(new DomainName('EXAMPLE。COM'))->ascii);
    same(true,(new DomainName('example.co.uk'))->under('co.uk'));
    same(false,(new DomainName('evilco.uk'))->under('co.uk'));
});
test('F4-A malformed IDNA labels URLs IPs whitespace and length fail closed',function(): void{
    foreach(['','localhost','https://example.com','a..com','-a.com','a-.com','a_b.com','127.0.0.1','a.com..','a .com',"a\0.com",'a@b.com','xn--.com',str_repeat('a',64).'.com',str_repeat('a.',126).'com',"a\u{200D}b.com"] as $input){raises(InvalidArgumentException::class,fn()=>new DomainName($input));}
});
test('DOMAIN-003 internal TLD capability and required contact validation are fail closed',function(): void{
    $policy=new TldPolicy(['availability','register','status'],['name','email','country'],null,null);
    $policy->requireAction('register');same(null,$policy->snapshot()['grace_days']);
    foreach(['dns','epp','privacy','initiateTransfer','invented'] as $action){same(422,raises(HttpError::class,fn()=>$policy->requireAction($action))->status);}
    $policy->validateContact(['name'=>'Synthetic contact','email'=>'owner@example.invalid','country'=>'TR']);
    foreach([[],['name'=>'A','email'=>'invalid','country'=>'TR'],['name'=>'A','email'=>'a@example.invalid','country'=>'tr'],['name'=>'A','email'=>'a@example.invalid','country'=>'TR','token'=>'private']] as $contact){raises(HttpError::class,fn()=>$policy->validateContact($contact));}
    raises(InvalidArgumentException::class,fn()=>new TldPolicy(['register','register'],[],null,null));
    raises(InvalidArgumentException::class,fn()=>new TldPolicy(['invented'],[],null,null));
    raises(InvalidArgumentException::class,fn()=>new TldPolicy([],[], -1,null));
});
runTests();
