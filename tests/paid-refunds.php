<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/catalog-fixture.php';require __DIR__.'/pricing-fixture.php';require __DIR__.'/orders-fixture.php';
use Hostider\Billing\Credits;
use Hostider\Core\Id;
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{AccessGrant,Principal};
use Hostider\Finance\{Currency,Money};
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function paidRefundFixture(): array {
    global $db;$f=ordersFixture($db);$actor=$f['actor'];$customer=$f['customer'];
    $db->transaction(function()use($f,$actor,$customer): void {$payment=$f['finance']->recordTestCollection($customer,new Money('360',new Currency('USD')),Id::uuid(),$actor,Id::uuid());$f['finance']->creditWallet($customer,$payment,new Money('144',new Currency('USD')),Id::uuid(),$actor,Id::uuid());$f['orders']->fund($f['order']['id'],$customer,new Money('144',new Currency('USD')),'WALLET',null,Id::uuid(),$actor,Id::uuid());$f['orders']->fund($f['order']['id'],$customer,new Money('216',new Currency('USD')),'COLLECTION',$payment,Id::uuid(),$actor,Id::uuid());});
    $staff=new Principal('refund-test',['finance.approve'],$customer,staff:true,mfa:true,expiresAt:time()+600,verifiedAt:time(),grants:[new AccessGrant('finance.approve',[$customer])]);$credits=new Credits($db,$f['billing'],$f['finance'],$f['orders']);return $f+compact('staff','credits');
}
test('F2-F paid mixed refund distributes to original sources and preserves the original bill',function()use($db): void {
    $f=paidRefundFixture();$request=$f['order']['payment_request_id'];$reference=Id::uuid();$before=$f['billing']->detail($request,$f['customer']);$wallet=$f['finance']->repository->wallet($f['customer'],false);
    $plan=$db->transaction(fn()=>$f['credits']->plan($request,$f['customer'],new Money('180',new Currency('USD'))));same('150',$plan['net_minor']);same('30',$plan['tax_minor']);$sources=[];foreach($plan['sources'] as $source){$sources[$source['wallet_entry_id']===null?'external':'wallet']=$source['refund_minor'];}same(['external'=>'108','wallet'=>'72'],array_replace(['external'=>'0','wallet'=>'0'],$sources));same('0',$f['finance']->repository->balance($wallet['id']));
    $first=$db->transaction(fn()=>$f['credits']->issue($request,$f['customer'],new Money('180',new Currency('USD')),$reference,$f['staff'],Id::uuid()));same($first['id'],$db->transaction(fn()=>$f['credits']->issue($request,$f['customer'],new Money('180',new Currency('USD')),$reference,$f['staff'],Id::uuid()))['id']);same('72',$f['finance']->repository->balance($wallet['id']));
    $after=$f['billing']->detail($request,$f['customer']);same($before['snapshot'],$after['snapshot']);same('360',$after['total_minor']);same('180',$after['refunded_minor']);same('PARTIAL',$after['refund_status']);same('REQUIRES_APPROVAL',$f['orders']->repository->find($f['order']['id'],$f['customer'])['status']);
    $db->transaction(fn()=>$f['credits']->issue($request,$f['customer'],new Money('180',new Currency('USD')),Id::uuid(),$f['staff'],Id::uuid()));same('FULL',$f['billing']->detail($request,$f['customer'])['refund_status']);same('CANCELLED',$f['orders']->repository->find($f['order']['id'],$f['customer'])['status']);same('144',$f['finance']->repository->balance($wallet['id']));
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['credits']->issue($request,$f['customer'],new Money('1',new Currency('USD')),Id::uuid(),$f['staff'],Id::uuid())))->status);
});
test('F2-F interrupted paid refund rolls back credit note wallet credit and external reserve',function()use($db): void {
    $f=paidRefundFixture();$request=$f['order']['payment_request_id'];$wallet=$f['finance']->repository->wallet($f['customer'],false);
    raises(RuntimeException::class,fn()=>$db->transaction(function()use($f,$request): void {$f['credits']->issue($request,$f['customer'],new Money('180',new Currency('USD')),Id::uuid(),$f['staff'],Id::uuid());throw new RuntimeException('Interrupted refund');}));
    same('0',$f['finance']->repository->balance($wallet['id']));same('0',$f['billing']->detail($request,$f['customer'])['refunded_minor']);same('READY_FOR_PROVISIONING',$f['orders']->repository->find($f['order']['id'],$f['customer'])['status']);
    same(403,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['credits']->issue($request,$f['customer'],new Money('1',new Currency('USD')),Id::uuid(),$f['actor'],Id::uuid())))->status);
});
test('F2-F parallel paid refund requests never exceed the original mixed payment',function()use($db): void {
    $f=paidRefundFixture();$input=['request'=>$f['order']['payment_request_id'],'customer'=>$f['customer']];$children=[];
    for($i=0;$i<2;++$i){$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/refund-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR)."\n");fflush($pipes[0]);$children[]=[$process,$pipes];}
    foreach($children as [$process,$pipes]){same("READY\n",str_replace("\r\n","\n",fgets($pipes[1])));}foreach($children as [$process,$pipes]){fwrite($pipes[0],"GO\n");fclose($pipes[0]);}
    $out=[];foreach($children as [$process,$pipes]){$out[]=stream_get_contents($pipes[1]);same('',stream_get_contents($pipes[2]));fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($process));}sort($out);same(['200','409'],$out);same('280',$f['billing']->detail($input['request'],$f['customer'])['refunded_minor']);
});
runTests();
