<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/catalog-fixture.php';require __DIR__.'/pricing-fixture.php';require __DIR__.'/orders-fixture.php';
use Hostider\Billing\{DocumentService,DocumentStore};
use Hostider\Catalog\BillingCycle;
use Hostider\Core\Id;
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Finance\{CashService,Currency,Money};
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F2-F official PDF is stored outside webroot with owner scope checksum and encrypted metadata',function()use($db): void {
    $f=ordersFixture($db);$root=dirname(__DIR__).'/storage/official-test-'.Id::uuid();$store=new DocumentStore($root,dirname(__DIR__).'/public');$documents=new DocumentService($db,$f['config'],$store,$f['billing']->repository);$actor=new Principal('document-test',[],staff:true);
    $id=$db->transaction(fn()=>$documents->start($f['order']['payment_request_id'],$f['customer'],$actor));$pdf="%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    $db->transaction(fn()=>$documents->chunk($id,0,substr($pdf,0,20),$actor));$db->transaction(fn()=>$documents->chunk($id,20,substr($pdf,20),$actor));
    $document=$db->transaction(fn()=>$documents->finish($id,'SYNTHETIC-2026-1','2026-10-22','EXTERNAL_OFFICIAL_PDF',$actor,Id::uuid()));
    same($pdf,$documents->download($document,$f['customer'])['bytes']);$list=$documents->list($f['order']['payment_request_id'],$f['customer']);same(hash('sha256',$pdf),$list[0]['sha256']);same('SYNTHETIC-2026-1',$list[0]['metadata']['number']);
    same(false,str_contains($db->execute('SELECT metadata_encrypted FROM billing_official_documents WHERE id=?',[$document])->fetchColumn(),'SYNTHETIC'));
    same(404,raises(HttpError::class,fn()=>$documents->download($document,Id::uuid()))->status);same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$documents->chunk($id,strlen($pdf),'x',$actor)))->status);
    file_put_contents($root.'/'.$id.'.pdf',str_replace('Catalog','Catxxxx',$pdf));same(409,raises(HttpError::class,fn()=>$documents->download($document,$f['customer']))->status);
});
test('F2-F upload traversal non-PDF active document and interrupted file offset fail closed',function()use($db): void {
    $f=ordersFixture($db);$root=dirname(__DIR__).'/storage/official-test-'.Id::uuid();$store=new DocumentStore($root,dirname(__DIR__).'/public');$documents=new DocumentService($db,$f['config'],$store,$f['billing']->repository);$actor=new Principal('document-test',[],staff:true);
    same(422,raises(HttpError::class,fn()=>$store->append('../escape',0,'x'))->status);same(422,raises(HttpError::class,fn()=>$store->validate('<html>bad</html>'))->status);same(422,raises(HttpError::class,fn()=>$store->validate("%PDF-1.4\n/JavaScript (bad)\n%%EOF"))->status);
    $id=$db->transaction(fn()=>$documents->start($f['order']['payment_request_id'],$f['customer'],$actor));
    raises(RuntimeException::class,fn()=>$db->transaction(function()use($documents,$id,$actor): void {$documents->chunk($id,0,'%PDF-1.4',$actor);throw new RuntimeException('Interrupted metadata commit');}));
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$documents->chunk($id,0,'%PDF-1.4',$actor)))->status);
    raises(RuntimeException::class,fn()=>new DocumentStore(dirname(__DIR__).'/public',dirname(__DIR__).'/public'));
});
test('F2-F cash reports separate collection sales wallet liability and costs',function()use($db): void {
    $f=ordersFixture($db);$cash=new CashService($db,$f['config']);$before=$cash->report()['currencies']['USD'];
    $payment=$db->transaction(fn()=>$f['finance']->recordTestCollection($f['customer'],new Money('1000',new Currency('USD')),Id::uuid(),$f['actor'],Id::uuid()));
    $db->transaction(fn()=>$f['finance']->creditWallet($f['customer'],$payment,new Money('1000',new Currency('USD')),Id::uuid(),$f['actor'],Id::uuid()));
    $after=$cash->report()['currencies']['USD'];same('1000',(string)(new \Hostider\Finance\Decimal($after['cash_minor']))->subtract(new \Hostider\Finance\Decimal($before['cash_minor'])));same($before['revenue_minor'],$after['revenue_minor']);same('1000',(string)(new \Hostider\Finance\Decimal($after['wallet_liability_minor']))->subtract(new \Hostider\Finance\Decimal($before['wallet_liability_minor']??'0')));
    $category=$db->transaction(fn()=>$cash->category('Synthetic expense','EXPENSE',$f['actor']));$reference=Id::uuid();$entry=$db->transaction(fn()=>$cash->expense($category,new Money('123',new Currency('USD')),$reference,'Synthetic paid expense',$f['actor'],Id::uuid()));same($entry,$db->transaction(fn()=>$cash->expense($category,new Money('123',new Currency('USD')),$reference,'Synthetic paid expense',$f['actor'],Id::uuid())));
    same('INTERNAL_FINANCE_NOT_STATUTORY_ACCOUNTING',$cash->report()['report_type']);
});
test('F2-F recurring payable is not paid cash expense and calendar anchor survives February',function()use($db): void {
    $f=ordersFixture($db);$cash=new CashService($db,$f['config']);$category=$db->transaction(fn()=>$cash->category('Synthetic monthly','EXPENSE',$f['actor']));$first=new DateTimeImmutable('2027-01-31T10:00:00Z');$rule=$db->transaction(fn()=>$cash->recurring($category,new Money('100',new Currency('USD')),'MONTHS',1,$first,$f['actor']));$before=$cash->report();
    $due=$db->transaction(fn()=>$cash->materialize($rule,$first,$f['actor']));same($before,$cash->report());same('2027-02-28 10:00:00.000000',$db->execute('SELECT next_due_at FROM finance_recurring_rules WHERE id=?',[$rule])->fetchColumn());
    $db->transaction(fn()=>$cash->materialize($rule,new DateTimeImmutable('2027-02-28T10:00:00Z'),$f['actor']));same('2027-03-31 10:00:00.000000',$db->execute('SELECT next_due_at FROM finance_recurring_rules WHERE id=?',[$rule])->fetchColumn());
    $paid=$db->transaction(fn()=>$cash->payDue($due,$f['actor'],Id::uuid()));same($paid,$db->transaction(fn()=>$cash->payDue($due,$f['actor'],Id::uuid())));
    same('2027-03-02',BillingCycle::next($first,'DAYS',30)->format('Y-m-d'));
});
runTests();
