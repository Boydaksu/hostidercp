<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';
use Hostider\Commerce\EventSink;use Hostider\Core\Id;use Hostider\Core\Database\Migrator;use Hostider\Core\Queue\{Outbox,Queue,Worker};
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F2 local outbox facts are delivered once and readiness never provisions a service',function()use($db): void {
    cleanJobs($db);$order=Id::uuid();$event=Id::uuid();$outbox=new Outbox($db,new Queue($db));$db->transaction(fn()=>$outbox->append($event,'orders.ready',$order,['order_id'=>$order],Id::uuid()));same(true,$outbox->dispatchOne());$worker=new Worker($db,new Queue($db));$sink=new EventSink($db);$sink->register($worker);same(true,$worker->once());same('SUCCEEDED',$db->execute("SELECT status FROM jobs WHERE job_type='orders.ready'")->fetchColumn());same('HELD_FOR_F3',$db->execute('SELECT status FROM commerce_event_receipts WHERE event_uuid=?',[$event])->fetchColumn());same('SUCCEEDED',$sink->record('orders.ready',['order_id'=>$order]));same(1,(int)$db->execute('SELECT COUNT(*) FROM commerce_event_receipts WHERE event_uuid=?',[$event])->fetchColumn());same(0,(int)$db->execute("SELECT COUNT(*) FROM jobs WHERE job_type LIKE 'provider.%'")->fetchColumn());
});
test('F2 invented job and mismatched payload cannot create a trusted ready receipt',function()use($db): void {
    $sink=new EventSink($db);same('FAILED',$sink->record('orders.ready',['order_id'=>Id::uuid()]));same('FAILED',$sink->record('orders.ready',['order_id'=>'untrusted']));same('FAILED',$sink->record('provider.create',['order_id'=>Id::uuid()]));
});
test('F2 local mail fact remains held and does not claim delivery',function()use($db): void {
    $id=Id::uuid();$event=Id::uuid();$db->transaction(fn()=>(new Outbox($db,new Queue($db)))->append($event,'identity.mail.held',Id::uuid(),['notification_id'=>$id],Id::uuid()));same('SUCCEEDED',(new EventSink($db))->record('identity.mail.held',['notification_id'=>$id]));same('MAIL_HELD',$db->execute('SELECT status FROM commerce_event_receipts WHERE event_uuid=?',[$event])->fetchColumn());
});
runTests();
