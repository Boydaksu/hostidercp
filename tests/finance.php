<?php
declare(strict_types=1);
require __DIR__.'/harness.php'; require __DIR__.'/database.php';
use Hostider\Core\{Id,Idempotency};
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
use Hostider\Finance\{Currency,Decimal,FinanceService,Ledger,Money};
$db=integrationDb(); (new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
$finance=new FinanceService($db,testConfig('test'));
function finMoney(string $minor): Money { return new Money($minor,new Currency('TRY')); }
function finFixture(string $minor='100000'): array {
    global $db,$finance;
    $customer=Id::uuid(); $actor=new Principal('finance-test',[],customerId:$customer);
    return $db->transaction(function()use($customer,$actor,$minor,$finance): array {
        $wallet=$finance->openWallet($customer,new Currency('TRY'),$actor);
        $payment=$finance->recordTestCollection($customer,finMoney($minor),Id::uuid(),$actor,Id::uuid());
        return [$customer,$actor,$wallet,$payment];
    });
}
function finRace(array $input): array {
    $children=[];
    try {
        for($i=0;$i<2;++$i) {
            $pipes=[]; $process=proc_open([PHP_BINARY,__DIR__.'/finance-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if (!is_resource($process)) { throw new RuntimeException('Child unavailable'); }
            fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR).PHP_EOL); fflush($pipes[0]);
            $children[]=[$process,$pipes];
        }
        foreach($children as [$process,$pipes]) { same("READY\n",str_replace("\r\n","\n",fgets($pipes[1]))); }
        foreach($children as [$process,$pipes]) { fwrite($pipes[0],"GO\n"); fclose($pipes[0]); }
        $out=[];
        foreach($children as [$process,$pipes]) {
            $out[]=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            same('',$error); same(0,proc_close($process));
        }
        $children=[]; sort($out); return $out;
    } finally { foreach($children as [$process,$pipes]) { if(is_resource($process)){proc_terminate($process);} } }
}
test('F2-A immutable journals balance and rollback also removes audit and outbox',function()use($db,$finance): void {
    [$customer,$actor,$wallet,$payment]=finFixture(); $before=$db->execute('SELECT COUNT(*) FROM finance_journals')->fetchColumn();
    raises(RuntimeException::class,fn()=>$db->transaction(function()use($finance,$customer,$payment,$actor): void {
        $finance->creditWallet($customer,$payment,finMoney('20000'),Id::uuid(),$actor,Id::uuid()); throw new RuntimeException('Interrupted commit');
    }));
    same('0',$finance->repository->balance($wallet)); same($before,$db->execute('SELECT COUNT(*) FROM finance_journals')->fetchColumn());
    same(0,(int)$db->execute("SELECT COUNT(*) FROM (SELECT journal_id,SUM(CASE WHEN side='DEBIT' THEN amount_minor ELSE -amount_minor END) total FROM finance_lines GROUP BY journal_id HAVING total<>0) invalid")->fetchColumn());
    foreach(['finance_journals','finance_lines','finance_payments','finance_wallets'] as $table) {
        raises(PDOException::class,fn()=>$db->execute('DELETE FROM '.$table.' LIMIT 1'));
    }
    raises(PDOException::class,fn()=>$db->execute("UPDATE finance_payments SET amount_minor=1 WHERE id=?",[$payment]));
    raises(InvalidArgumentException::class,fn()=>$db->transaction(fn()=>(new Ledger($db))->post(Id::uuid(),new Currency('TRY'),[['account'=>'cash','side'=>'DEBIT','minor'=>'2'],['account'=>'unallocated','side'=>'CREDIT','minor'=>'1']],$actor,Id::uuid())));
});
test('F2-A DB PHP JSON roundtrip preserves amounts above Number safe integer',function()use($db,$finance): void {
    [$customer,$actor,$wallet,$payment]=finFixture('9007199254740993');
    $db->transaction(fn()=>$finance->creditWallet($customer,$payment,finMoney('9007199254740993'),Id::uuid(),$actor,Id::uuid()));
    same('9007199254740993',$finance->repository->balance($wallet));
    same('9007199254740993',json_decode(json_encode(finMoney($finance->repository->balance($wallet)),JSON_THROW_ON_ERROR),true)['minor']);
});
test('F2-A duplicate collection and payload conflicts cannot create a second payment',function()use($db,$finance): void {
    [$customer,$actor]=finFixture(); $external=Id::uuid();
    $first=$db->transaction(fn()=>$finance->recordTestCollection($customer,finMoney('100'),$external,$actor,Id::uuid()));
    same($first,$db->transaction(fn()=>$finance->recordTestCollection($customer,finMoney('100'),$external,$actor,Id::uuid())));
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$finance->recordTestCollection($customer,finMoney('101'),$external,$actor,Id::uuid())))->status);
    same(['200','200'],finRace(['mode'=>'collect','customer'=>$customer,'minor'=>'200','external'=>Id::uuid()]));
});
test('F2-A independent processes cannot overdraw the same wallet',function()use($db,$finance): void {
    [$customer,$actor,$wallet,$payment]=finFixture();
    $db->transaction(fn()=>$finance->creditWallet($customer,$payment,finMoney('100000'),Id::uuid(),$actor,Id::uuid()));
    same(['200','409'],finRace(['mode'=>'spend','customer'=>$customer,'minor'=>'80000']));
    same('20000',$finance->repository->balance($wallet));
});
test('F2-A mixed allocation and wallet liability never double-count cash',function()use($db,$finance): void {
    [$customer,$actor,$wallet,$payment]=finFixture(); $request=Id::uuid(); $reference=Id::uuid();
    $db->transaction(function()use($customer,$actor,$payment,$request,$reference,$finance): void {
        $finance->creditWallet($customer,$payment,finMoney('40000'),Id::uuid(),$actor,Id::uuid());
        $finance->spendWallet($customer,$request,finMoney('40000'),$reference,$actor,Id::uuid());
        $finance->spendWallet($customer,$request,finMoney('40000'),$reference,$actor,Id::uuid());
        $finance->allocatePayment($customer,$request,$payment,finMoney('60000'),Id::uuid(),$actor,Id::uuid());
    });
    same('100000',$finance->repository->allocated($request)); same('0',$finance->repository->balance($wallet));
    $sum=$db->execute("SELECT CAST(SUM(l.amount_minor) AS CHAR) FROM finance_lines l JOIN finance_journals j ON j.id=l.journal_id JOIN finance_payments p ON p.journal_id=j.id WHERE p.id=? AND l.account='cash' AND l.side='DEBIT'",[$payment])->fetchColumn(); same('100000',$sum);
    same('0',$db->transaction(fn()=>$finance->repository->available($finance->repository->payment($payment))));
});
test('F2-A concurrent refunds reserve at most original unallocated collection',function()use($db,$finance): void {
    [$customer,$actor,$wallet,$payment]=finFixture();
    same(['200','409'],finRace(['mode'=>'refund','customer'=>$customer,'payment'=>$payment,'minor'=>'80000']));
    same('20000',$db->transaction(fn()=>$finance->repository->available($finance->repository->payment($payment))));
    $reference=Id::uuid(); $first=$db->transaction(fn()=>$finance->requestOriginalRefund($payment,finMoney('20000'),$reference,$actor,Id::uuid()));
    same($first,$db->transaction(fn()=>$finance->requestOriginalRefund($payment,finMoney('20000'),$reference,$actor,Id::uuid())));
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$finance->requestOriginalRefund($payment,finMoney('1'),Id::uuid(),$actor,Id::uuid())))->status);
});
test('F2-A impersonation and production test collections fail closed',function()use($db,$finance): void {
    [$customer,$actor,$wallet,$payment]=finFixture(); $imp=new Principal('finance-test',[],customerId:$customer,impersonating:true);
    same(403,raises(HttpError::class,fn()=>$db->transaction(fn()=>$finance->creditWallet($customer,$payment,finMoney('1'),Id::uuid(),$imp,Id::uuid())))->status);
    foreach(['staging','production'] as $environment) {
        $service=new FinanceService($db,testConfig($environment));
        same(403,raises(HttpError::class,fn()=>$db->transaction(fn()=>$service->recordTestCollection($customer,finMoney('1'),Id::uuid(),$actor,Id::uuid())))->status);
    }
    $other=Id::uuid(); $db->transaction(fn()=>$finance->openWallet($other,new Currency('TRY'),$actor));
    same(403,raises(HttpError::class,fn()=>$db->transaction(fn()=>$finance->creditWallet($other,$payment,finMoney('1'),Id::uuid(),$actor,Id::uuid())))->status);
});
test('F2-A persistent idempotency commits collection audit and outbox once',function()use($db,$finance): void {
    [$customer,$actor]=finFixture(); $reference=Id::uuid(); $key=bin2hex(random_bytes(16)); $before=(int)$db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='finance.posted'")->fetchColumn();
    $call=fn()=>(new Idempotency($db))->run($actor->actorId,'finance-test',$key,['customer'=>$customer],fn()=>['id'=>$finance->recordTestCollection($customer,finMoney('100'),$reference,$actor,Id::uuid())]);
    same($call(),$call()); same($before+1,(int)$db->execute("SELECT COUNT(*) FROM audit_logs WHERE action='finance.posted'")->fetchColumn());
});
test('F2-A foreign collection preserves immutable source amount and FX receipt',function()use($db,$finance): void {
    [$customer,$actor]=finFixture();
    $fx=new \Hostider\Finance\FxSnapshot(new Currency('USD'),new Currency('TRY'),new Decimal('43'),new Decimal('0.02'),'synthetic-usdtry',new DateTimeImmutable('2026-10-22T00:00:00Z'),\Hostider\Finance\Rounding::HalfUp);
    $source=new Money('300',new Currency('USD')); $converted=$fx->convert($source); same('13158',$converted->minor);
    $payment=$db->transaction(fn()=>$finance->recordTestCollection($customer,$converted,Id::uuid(),$actor,Id::uuid(),$source,$fx));
    $row=$db->execute('SELECT p.*,f.rate FROM finance_payments p JOIN finance_fx_snapshots f ON f.id=p.fx_snapshot_id WHERE p.id=?',[$payment])->fetch();
    same('300',(string)$row['original_minor']); same('USD',$row['original_currency']); same('13158',(string)$row['amount_minor']); same('43.000000000000',$row['rate']);
    same('300',(string)$db->execute("SELECT amount_minor FROM finance_lines WHERE journal_id=? AND account='cash'",[$row['original_journal_id']])->fetchColumn());
    same('USD',$db->execute('SELECT currency FROM finance_journals WHERE id=?',[$row['original_journal_id']])->fetchColumn());
    same(0,(int)$db->execute("SELECT COUNT(*) FROM finance_lines WHERE journal_id=? AND account='cash'",[$row['journal_id']])->fetchColumn());
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$finance->requestOriginalRefund($payment,finMoney('13158'),Id::uuid(),$actor,Id::uuid())))->status);
});
test('F2-A wallet compensation appends linked reversal and respects original spend limit',function()use($db,$finance): void {
    [$customer,$actor,$wallet,$payment]=finFixture(); $request=Id::uuid();
    $db->transaction(function()use($customer,$actor,$payment,$request,$finance): void {
        $finance->creditWallet($customer,$payment,finMoney('100000'),Id::uuid(),$actor,Id::uuid());
        $finance->spendWallet($customer,$request,finMoney('80000'),Id::uuid(),$actor,Id::uuid());
    });
    $entry=$db->execute('SELECT wallet_entry_id FROM finance_allocations WHERE payment_request_id=?',[$request])->fetchColumn(); $reference=Id::uuid();
    $first=$db->transaction(fn()=>$finance->creditWalletSpend($customer,$entry,finMoney('70000'),$reference,$actor,Id::uuid()));
    same($first,$db->transaction(fn()=>$finance->creditWalletSpend($customer,$entry,finMoney('70000'),$reference,$actor,Id::uuid())));
    same('90000',$finance->repository->balance($wallet));
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$finance->creditWalletSpend($customer,$entry,finMoney('10001'),Id::uuid(),$actor,Id::uuid())))->status);
    same('-80000',(string)$db->execute('SELECT amount_minor FROM finance_wallet_entries WHERE id=?',[$entry])->fetchColumn());
});
runTests();
