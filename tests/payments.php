<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/catalog-fixture.php';require __DIR__.'/pricing-fixture.php';require __DIR__.'/orders-fixture.php';
use Hostider\Core\{CanonicalJson,Id};
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{AccessGrant,Principal};
use Hostider\Finance\{Currency,Money};
use Hostider\Payments\{PaymentService,PaytrBoundary};
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
function paymentFixture(): array {
    global $db;$f=ordersFixture($db);$payments=new PaymentService($db,$f['config'],$f['finance'],$f['billing'],$f['orders']);
    return $f+compact('payments');
}
function fixtureSignature(array $f,array $payload): array {
    unset($payload['signature']);$payload['signature']=hash_hmac('sha256','hostider-test-fixture-v1:'.CanonicalJson::encode($payload),base64_decode($f['config']->key,true));return $payload;
}
function pendingAttempt(array $f,?string $order,string $minor): array {
    global $db; $attempt=$db->transaction(fn()=>$f['payments']->createAttempt($f['customer'],new Money($minor,new Currency('USD')),$order,$f['actor'],Id::uuid()));
    return fixtureSignature($f,['attempt_id'=>$attempt['id'],'customer_id'=>$f['customer'],'external_reference'=>Id::uuid(),'minor'=>$minor,'currency'=>'USD','status'=>'SUCCESS']);
}
function bankActor(string $customer): Principal { return new Principal('bank-test',['finance.approve'],$customer,staff:true,mfa:true,expiresAt:time()+600,verifiedAt:time(),grants:[new AccessGrant('finance.approve',[$customer])]); }
test('F2-E pending topup and bank notification cannot credit unverified money',function()use($db): void {
    $f=paymentFixture();$fixture=pendingAttempt($f,null,'1000');$wallet=$f['finance']->repository->wallet($f['customer'],false);
    $notice=$db->transaction(fn()=>$f['payments']->notifyBank($fixture['attempt_id'],$f['customer'],'synthetic-bank-'.Id::uuid(),$f['actor'],Id::uuid()));same('PENDING',$notice['status']);same('0',$f['finance']->repository->balance($wallet['id']));
    same(401,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['payments']->approveBank($notice['id'],$f['customer'],1,$f['actor'],Id::uuid())))->status);
    $approved=$db->transaction(fn()=>$f['payments']->approveBank($notice['id'],$f['customer'],1,bankActor($f['customer']),Id::uuid()));same('APPROVED',$approved['status']);same('1000',$f['finance']->repository->balance($wallet['id']));
    $again=$db->transaction(fn()=>$f['payments']->approveBank($notice['id'],$f['customer'],1,bankActor($f['customer']),Id::uuid()));same($approved['payment_id'],$again['payment_id']);same('1000',$f['finance']->repository->balance($wallet['id']));
});
test('F2-E verified duplicate fixture records one collection and one readiness event',function()use($db): void {
    $f=paymentFixture();$fixture=pendingAttempt($f,$f['order']['id'],'360');$call=fn()=>$db->transaction(fn()=>$f['payments']->acceptTestFixture($fixture,$f['actor'],Id::uuid()));$first=$call();same($first,$call());same($first,$call());same('SUCCEEDED',$first['status']);
    same(1,(int)$db->execute('SELECT COUNT(*) FROM finance_payments WHERE external_reference=?',[$fixture['external_reference']])->fetchColumn());same(1,(int)$db->execute("SELECT COUNT(*) FROM order_events WHERE order_id=? AND kind='ready'",[$f['order']['id']])->fetchColumn());
});
test('F2-E bad signature amount currency wrong customer and fabricated success never collect',function()use($db): void {
    $f=paymentFixture();$fixture=pendingAttempt($f,$f['order']['id'],'360');$before=(int)$db->execute('SELECT COUNT(*) FROM finance_payments')->fetchColumn();
    $bad=$fixture;$bad['signature']=str_repeat('0',64);same(403,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['payments']->acceptTestFixture($bad,$f['actor'],Id::uuid())))->status);
    foreach(['minor'=>'361','currency'=>'TRY','customer_id'=>Id::uuid(),'attempt_id'=>Id::uuid()] as $key=>$value){$bad=$fixture;$bad[$key]=$value;$bad=fixtureSignature($f,$bad);raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['payments']->acceptTestFixture($bad,$f['actor'],Id::uuid())));}
    same($before,(int)$db->execute('SELECT COUNT(*) FROM finance_payments')->fetchColumn());same('ISSUED',$f['billing']->detail($f['order']['payment_request_id'],$f['customer'])['status']);
});
test('F2-E failed payment leaves order awaiting funds and does not topup wallet',function()use($db): void {
    $f=paymentFixture();$fixture=pendingAttempt($f,$f['order']['id'],'360');$fixture['status']='FAILURE';$fixture=fixtureSignature($f,$fixture);
    same(['status'=>'FAILED'],$db->transaction(fn()=>$f['payments']->acceptTestFixture($fixture,$f['actor'],Id::uuid())));same('AWAITING_PAYMENT',$f['orders']->repository->find($f['order']['id'],$f['customer'])['status']);
    $fixture['status']='SUCCESS';$fixture=fixtureSignature($f,$fixture);same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['payments']->acceptTestFixture($fixture,$f['actor'],Id::uuid())))->status);
});
test('F2-E late payment after cancellation remains unallocated for reconciliation',function()use($db): void {
    $f=paymentFixture();$fixture=pendingAttempt($f,$f['order']['id'],'360');$db->transaction(fn()=>$f['orders']->cancel($f['order']['id'],$f['customer'],$f['actor'],Id::uuid()));
    $result=$db->transaction(fn()=>$f['payments']->acceptTestFixture($fixture,$f['actor'],Id::uuid()));same('UNALLOCATED_RECONCILIATION_REQUIRED',$result['allocation']);
    same('360',$db->transaction(fn()=>$f['finance']->repository->available($f['finance']->repository->payment($result['payment_id']))));
    same(1,(int)$db->execute("SELECT COUNT(*) FROM finance_reconciliations WHERE subject_id=? AND code='LATE_OR_EXCESS_PAYMENT'",[$result['payment_id']])->fetchColumn());
    same('CANCELLED',$f['orders']->repository->find($f['order']['id'],$f['customer'])['status']);
});
test('F2-E test fixture acceptance is forbidden in staging and production and PayTR stays shut',function()use($db): void {
    $f=paymentFixture();$fixture=pendingAttempt($f,null,'100');
    foreach(['staging','production'] as $environment){$payments=new PaymentService($db,testConfig($environment),$f['finance'],$f['billing'],$f['orders']);same(403,raises(HttpError::class,fn()=>$db->transaction(fn()=>$payments->acceptTestFixture($fixture,$f['actor'],Id::uuid())))->status);}
    $boundary=new PaytrBoundary();same(403,raises(HttpError::class,fn()=>$boundary->acceptCallback(['status'=>'success']))->status);same(503,raises(HttpError::class,fn()=>$boundary->createSession([]))->status);same(503,raises(HttpError::class,fn()=>$boundary->refund([]))->status);
});
runTests();
