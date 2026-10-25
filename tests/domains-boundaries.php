<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/domains-fixture.php';
use Hostider\Core\Database\Migrator;use Hostider\Core\Http\HttpError;use Hostider\Services\Transfers;
$db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F4 ownership generic transfer application lock rejects DOMAIN and CLOUD before policy or ownership mutation',function()use($db): void{
    foreach(['DOMAIN','CLOUD'] as $type){$f=domainFixture($db);
        if($type==='CLOUD'){$product=$f['create']('product-create',['name'=>'Synthetic cloud boundary','category_id'=>$f['category'],'product_type'=>'CLOUD','pricing_strategy'=>'FIXED_THEN_FX','source_currency'=>'USD','activation_policy'=>'AUTO','refund_policy'=>'staff_approval']);$db->execute('UPDATE svc_services SET product_id=? WHERE id=?',[$product,$f['service']['id']]);}
        // Exercise the common application lock invoked by both start and accept, not a UI check.
        $lock=new ReflectionMethod(Transfers::class,'lock');
        $error=raises(HttpError::class,fn()=>$db->transaction(fn()=>$lock->invoke(new Transfers($db,$f['config']),$f['service']['id'],$f['customer'])));
        same('MODULE_TRANSFER_WORKFLOW_REQUIRED',$error->errorCode);
        same($f['customer'],$db->execute('SELECT customer_id FROM svc_services WHERE id=?',[$f['service']['id']])->fetchColumn());
        same(0,(int)$db->execute('SELECT COUNT(*) FROM svc_transfers WHERE service_id=?',[$f['service']['id']])->fetchColumn());
    }
    Transfers::requireSupportedProduct('HOSTING');
});
test('F4 attach rechecks unpaid partial refunded and cancelled canonical sources before any domain write',function()use($db): void{
    foreach(['UNPAID','PARTIAL','REFUND','CANCEL'] as $scenario){$f=domainFixture($db);
        if($scenario==='UNPAID'){$db->execute("UPDATE billing_request_states SET status='ISSUED' WHERE request_id=?",[$f['order']['payment_request_id']]);}
        elseif($scenario==='PARTIAL'){$db->execute("UPDATE billing_request_states SET status='PARTIALLY_PAID' WHERE request_id=?",[$f['order']['payment_request_id']]);}
        elseif($scenario==='REFUND'){$db->execute("UPDATE orders SET refund_status='FULL' WHERE id=?",[$f['order']['id']]);}
        else{$db->execute("UPDATE orders SET status='CANCELLED' WHERE id=?",[$f['order']['id']]);}
        same('REGISTRATION_SOURCE_INELIGIBLE',raises(HttpError::class,$f['attach'])->errorCode);
        same(0,(int)$db->execute('SELECT COUNT(*) FROM domains WHERE service_id=?',[$f['service']['id']])->fetchColumn());
    }
});
test('F4 attach rejects changed customer and manual pending service without weakening old model',function()use($db): void{
    $f=domainFixture($db);$db->execute("UPDATE svc_services SET status='PENDING_APPROVAL' WHERE id=?",[$f['service']['id']]);same('REGISTRATION_SOURCE_INELIGIBLE',raises(HttpError::class,$f['attach'])->errorCode);
    $f=domainFixture($db);$other=\Hostider\Core\Id::uuid();$db->execute("INSERT INTO identity_customers(id,name,currency) VALUES (?,'Synthetic other owner','USD')",[$other]);$db->execute('UPDATE svc_services SET customer_id=? WHERE id=?',[$other,$f['service']['id']]);same(404,raises(HttpError::class,$f['attach'])->status);
});
runTests();
