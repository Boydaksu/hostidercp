<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/catalog-fixture.php';require __DIR__.'/commerce-fixture.php';
use Hostider\Commerce\Services;
use Hostider\Core\Id;
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Finance\{Currency,Money};
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();$services=new Services($db,testConfig('test'));$admin=commerceAdmin($services);
test('F2-G prepare is side effect free for business records and consumes one bound confirmation',function()use($db,$services,$admin): void {
    $before=(int)$db->execute('SELECT COUNT(*) FROM catalog_categories')->fetchColumn();$payload=['name'=>'Confirmed synthetic category'];$review=commercePrepare($services,$admin,'admin','category-create',$payload,null);same($before,(int)$db->execute('SELECT COUNT(*) FROM catalog_categories')->fetchColumn());
    same(403,raises(HttpError::class,fn()=>commerceExecute($services,$admin,'admin','category-create',$payload,null,$review['id'],false))->status);same($before,(int)$db->execute('SELECT COUNT(*) FROM catalog_categories')->fetchColumn());
    $result=commerceExecute($services,$admin,'admin','category-create',$payload,null,$review['id']);same($before+1,(int)$db->execute('SELECT COUNT(*) FROM catalog_categories')->fetchColumn());same(409,raises(HttpError::class,fn()=>commerceExecute($services,$admin,'admin','category-create',$payload,null,$review['id']))->status);
});
test('F2-G changed payload expired actor session and stale catalog reject without writes',function()use($db,$services,$admin): void {
    $payload=['name'=>'Unchanged'];$review=commercePrepare($services,$admin,'admin','category-create',$payload,null);
    same(403,raises(HttpError::class,fn()=>commerceExecute($services,$admin,'admin','category-create',['name'=>'Tampered'],null,$review['id']))->status);
    $db->execute('UPDATE commerce_confirmations SET expires_at=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6)) WHERE id=?',[$review['id']]);same(409,raises(HttpError::class,fn()=>commerceExecute($services,$admin,'admin','category-create',$payload,null,$review['id']))->status);
    $review=commercePrepare($services,$admin,'admin','category-create',$payload,null);catalogFixture($db);same(409,raises(HttpError::class,fn()=>commerceExecute($services,$admin,'admin','category-create',$payload,null,$review['id']))->status);
    $other=commerceUser($services,'other');same(403,raises(HttpError::class,fn()=>commerceExecute($services,$other,'client','category-create',$payload,null,$review['id']))->status);
});
test('F2-G customer quote and order are real and wallet state changes invalidate confirmation',function()use($db,$services): void {
    $client=commerceUser($services,'buyer');$f=catalogFixture($db);$f['create']('price-publish',['item_id'=>$f['product'],'cycle_id'=>$f['cycle'],'currency'=>'TRY','amount'=>'100']);$cart=[['product_id'=>$f['product'],'cycle_id'=>$f['cycle'],'quantity'=>1]];$payload=['cart'=>$cart,'promotions'=>[]];
    $review=commercePrepare($services,$client,'client','quote-create',$payload,$client['customer']);same('12000',$review['plan']['proposed']['total_minor']);$quote=commerceExecute($services,$client,'client','quote-create',$payload,$client['customer'],$review['id']);
    $payload=['quote_id'=>$quote['id']];$review=commercePrepare($services,$client,'client','order-submit',$payload,$client['customer']);$order=commerceExecute($services,$client,'client','order-submit',$payload,$client['customer'],$review['id']);same('AWAITING_PAYMENT',$order['status']);
    $p=$services->identity->locked($client['reference'],'client',fn(array $u,array $s)=>$services->identity->principal($u,$s));
    $db->transaction(function()use($services,$client,$p): void {$services->finance->openWallet($client['customer'],new Currency('TRY'),$p);$payment=$services->finance->recordTestCollection($client['customer'],new Money('13000',new Currency('TRY')),Id::uuid(),$p,Id::uuid());$services->finance->creditWallet($client['customer'],$payment,new Money('13000',new Currency('TRY')),Id::uuid(),$p,Id::uuid());});
    $payload=['order_id'=>$order['id'],'minor'=>'12000'];$before=$services->finance->repository->allocated($order['payment_request_id']);$review=commercePrepare($services,$client,'client','wallet-pay',$payload,$client['customer']);same($before,$services->finance->repository->allocated($order['payment_request_id']));
    $db->transaction(function()use($services,$client,$p): void {$payment=$services->finance->recordTestCollection($client['customer'],new Money('1',new Currency('TRY')),Id::uuid(),$p,Id::uuid());$services->finance->creditWallet($client['customer'],$payment,new Money('1',new Currency('TRY')),Id::uuid(),$p,Id::uuid());});
    same(409,raises(HttpError::class,fn()=>commerceExecute($services,$client,'client','wallet-pay',$payload,$client['customer'],$review['id']))->status);same($before,$services->finance->repository->allocated($order['payment_request_id']));
    $review=commercePrepare($services,$client,'client','wallet-pay',$payload,$client['customer']);$result=commerceExecute($services,$client,'client','wallet-pay',$payload,$client['customer'],$review['id']);same('READY_FOR_PROVISIONING',$result['order']['status']);
    $GLOBALS['commerceBuyer']=$client;$GLOBALS['commerceOrder']=$order;
});
test('F2-G customer scope and impersonation cannot prepare monetary changes',function()use($db,$services,$admin): void {
    $buyer=$GLOBALS['commerceBuyer'];$other=commerceUser($services,'outsider');$order=$GLOBALS['commerceOrder'];
    same(403,raises(HttpError::class,fn()=>commercePrepare($services,$other,'client','wallet-pay',['order_id'=>$order['id'],'minor'=>'1'],$buyer['customer']))->status);
    $assignment=$db->execute('SELECT a.id FROM identity_assignments a WHERE a.user_id=? AND a.customer_id IS NULL LIMIT 1',[$admin['user']])->fetchColumn();$db->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope) VALUES (?,?,?,?)',[Id::uuid(),$assignment,'identity.impersonate',$buyer['customer']]);
    $services->identity->impersonate($admin['reference'],'admin',$buyer['customer'],$buyer['user'],'Synthetic financial denial test');
    same(403,raises(HttpError::class,fn()=>commercePrepare($services,$admin,'admin','attempt-create',['minor'=>'1'],$buyer['customer']))->status);
    $db->execute('UPDATE identity_impersonations SET ended_at=UTC_TIMESTAMP(6) WHERE session_id IN (SELECT id FROM identity_sessions WHERE user_id=?) AND ended_at IS NULL',[$admin['user']]);
});
test('F2-G customer-targeted promotion requires that permission customer grant',function()use($db,$services,$admin): void {
    $buyer=$GLOBALS['commerceBuyer'];$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$payload=['code'=>'SCOPE_'.strtoupper(str_replace('-','',Id::uuid())),'rate'=>'0.1','period_scope'=>'ALL','combinable'=>false,'max_uses'=>1,'per_customer_limit'=>1,'starts_at'=>$now->modify('-1 day')->format('Y-m-d H:i:s'),'ends_at'=>$now->modify('+1 day')->format('Y-m-d H:i:s'),'customer_id'=>$buyer['customer']];
    same(403,raises(HttpError::class,fn()=>commercePrepare($services,$admin,'admin','promotion-create',$payload,null))->status);
    $assignment=$db->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL LIMIT 1',[$admin['user']])->fetchColumn();$db->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope) VALUES (?,?,?,?)',[Id::uuid(),$assignment,'catalog.manage',$buyer['customer']]);$review=commercePrepare($services,$admin,'admin','promotion-create',$payload,null);$result=commerceExecute($services,$admin,'admin','promotion-create',$payload,null,$review['id']);same($buyer['customer'],$db->execute('SELECT customer_id FROM catalog_promotions WHERE id=?',[$result['id']])->fetchColumn());
});
runTests();
