<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/catalog-fixture.php';require __DIR__.'/pricing-fixture.php';require __DIR__.'/orders-fixture.php';
use Hostider\Core\{Id,Idempotency};
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Finance\{Money,Currency};
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function orderAmount(string $minor): Money { return new Money($minor,new Currency('USD')); }
test('F2-D quote produces one immutable request and separate order state',function()use($db): void {
    $f=ordersFixture($db);same('AWAITING_PAYMENT',$f['order']['status']);$id=$f['order']['id'];
    $again=$db->transaction(fn()=>$f['orders']->submit($f['quote']['id'],$f['customer'],['name'=>'Changed'],$f['actor'],Id::uuid(),$f['now']));same($id,$again['id']);
    $request=$f['billing']->detail($f['order']['payment_request_id'],$f['customer']);same('ISSUED',$request['status']);same('360',$request['total_minor']);same('PROFORMA',$request['document_type']);same(false,$request['official_invoice_issued']);same('Synthetic customer',$request['customer_snapshot']['name']);
    $f['create']('price-publish',['item_id'=>$f['product'],'cycle_id'=>$f['cycle'],'currency'=>'USD','amount'=>'9']);
    same($request,$f['billing']->detail($f['order']['payment_request_id'],$f['customer']));
    same(false,str_contains($db->execute('SELECT customer_encrypted FROM billing_requests WHERE id=?',[$request['id']])->fetchColumn(),'Synthetic customer'));
    raises(PDOException::class,fn()=>$db->execute('UPDATE billing_requests SET total_minor=1 WHERE id=?',[$request['id']]));
});
test('F2-D wallet and external partial allocations close once with one readiness fact',function()use($db): void {
    $f=ordersFixture($db);$order=$f['order']['id'];$customer=$f['customer'];$actor=$f['actor'];
    $payment=$db->transaction(fn()=>$f['finance']->recordTestCollection($customer,orderAmount('360'),Id::uuid(),$actor,Id::uuid()));
    $db->transaction(fn()=>$f['finance']->creditWallet($customer,$payment,orderAmount('144'),Id::uuid(),$actor,Id::uuid()));
    $partial=$db->transaction(fn()=>$f['orders']->fund($order,$customer,orderAmount('144'),'WALLET',null,Id::uuid(),$actor,Id::uuid()));same('PARTIALLY_PAID',$partial['order']['status']);same('144',$partial['payment_request']['paid_minor']);
    same(0,(int)$db->execute("SELECT COUNT(*) FROM order_events WHERE order_id=? AND kind='ready'",[$order])->fetchColumn());
    $key=bin2hex(random_bytes(16));$reference=Id::uuid();$call=fn()=>(new Idempotency($db))->run($actor->actorId,'order-fund',$key,['order'=>$order,'minor'=>'216'],fn()=>$f['orders']->fund($order,$customer,orderAmount('216'),'COLLECTION',$payment,$reference,$actor,Id::uuid()));
    $paid=$call();same($paid,$call());same('READY_FOR_PROVISIONING',$paid['order']['status']);same('PAID',$paid['payment_request']['status']);same('360',$paid['payment_request']['paid_minor']);
    same(1,(int)$db->execute("SELECT COUNT(*) FROM order_events WHERE order_id=? AND kind='ready'",[$order])->fetchColumn());
    same(1,(int)$db->execute("SELECT COUNT(*) FROM outbox_events WHERE aggregate_id=? AND event_type='orders.ready'",[$order])->fetchColumn());
    same(0,(int)$db->execute("SELECT COUNT(*) FROM jobs WHERE job_type LIKE 'provider.%'")->fetchColumn());
});
test('F2-D failed transaction leaves no partial funding or ready event',function()use($db): void {
    $f=ordersFixture($db);$payment=$db->transaction(fn()=>$f['finance']->recordTestCollection($f['customer'],orderAmount('360'),Id::uuid(),$f['actor'],Id::uuid()));
    raises(RuntimeException::class,fn()=>$db->transaction(function()use($f,$payment): void {$f['orders']->fund($f['order']['id'],$f['customer'],orderAmount('360'),'COLLECTION',$payment,Id::uuid(),$f['actor'],Id::uuid());throw new RuntimeException('Interrupted commit');}));
    same('ISSUED',$f['billing']->detail($f['order']['payment_request_id'],$f['customer'])['status']);same('0',$f['finance']->repository->allocated($f['order']['payment_request_id']));
    same('360',$db->transaction(fn()=>$f['finance']->repository->available($f['finance']->repository->payment($payment))));
    same(0,(int)$db->execute("SELECT COUNT(*) FROM order_events WHERE order_id=? AND kind='ready'",[$f['order']['id']])->fetchColumn());
});
test('F2-D overpayment stays unallocated and cancellation preserves historical request',function()use($db): void {
    $f=ordersFixture($db);$payment=$db->transaction(fn()=>$f['finance']->recordTestCollection($f['customer'],orderAmount('400'),Id::uuid(),$f['actor'],Id::uuid()));
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['orders']->fund($f['order']['id'],$f['customer'],orderAmount('400'),'COLLECTION',$payment,Id::uuid(),$f['actor'],Id::uuid())))->status);
    same('400',$db->transaction(fn()=>$f['finance']->repository->available($f['finance']->repository->payment($payment))));
    $cancelled=$db->transaction(fn()=>$f['orders']->cancel($f['order']['id'],$f['customer'],$f['actor'],Id::uuid()));same('CANCELLED',$cancelled['status']);same('VOIDED',$f['billing']->detail($f['order']['payment_request_id'],$f['customer'])['status']);
    same('360',$f['billing']->detail($f['order']['payment_request_id'],$f['customer'])['total_minor']);
});
test('F2-D wrong customer and impersonated funding cannot access order or bill',function()use($db): void {
    $f=ordersFixture($db);same(404,raises(HttpError::class,fn()=>$f['billing']->detail($f['order']['payment_request_id'],Id::uuid()))->status);
    same(404,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['orders']->fund($f['order']['id'],Id::uuid(),orderAmount('1'),'WALLET',null,Id::uuid(),$f['actor'],Id::uuid())))->status);
    $imp=new \Hostider\Core\Security\Principal('order-test',[],customerId:$f['customer'],impersonating:true);
    same(403,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['orders']->fund($f['order']['id'],$f['customer'],orderAmount('1'),'WALLET',null,Id::uuid(),$imp,Id::uuid())))->status);
});
test('F2-D independent duplicate funding processes emit readiness once',function()use($db): void {
    $f=ordersFixture($db);$payment=$db->transaction(fn()=>$f['finance']->recordTestCollection($f['customer'],orderAmount('360'),Id::uuid(),$f['actor'],Id::uuid()));
    $input=['order'=>$f['order']['id'],'customer'=>$f['customer'],'minor'=>'360','payment'=>$payment,'key'=>bin2hex(random_bytes(16)),'reference'=>Id::uuid()];$children=[];
    for($i=0;$i<2;++$i){$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/order-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR)."\n");fflush($pipes[0]);$children[]=[$process,$pipes];}
    foreach($children as [$process,$pipes]){same("READY\n",str_replace("\r\n","\n",fgets($pipes[1])));}
    foreach($children as [$process,$pipes]){fwrite($pipes[0],"GO\n");fclose($pipes[0]);}
    $out=[];foreach($children as [$process,$pipes]){$out[]=stream_get_contents($pipes[1]);same('',stream_get_contents($pipes[2]));fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($process));}same(['200','200'],$out);
    same(1,(int)$db->execute("SELECT COUNT(*) FROM order_events WHERE order_id=? AND kind='ready'",[$f['order']['id']])->fetchColumn());
    same('360',$f['finance']->repository->allocated($f['order']['payment_request_id']));
});
runTests();
