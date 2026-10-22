<?php
declare(strict_types=1);
require __DIR__.'/harness.php'; require __DIR__.'/database.php'; require __DIR__.'/catalog-fixture.php'; require __DIR__.'/pricing-fixture.php';
use Hostider\Core\Id;
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Finance\{Currency,Decimal,FxSnapshot,Rounding};
use Hostider\Pricing\{ExchangeRatePolicy,FxRateRepository,TaxCalculator,RenewalPricePolicy};
$db=integrationDb(); (new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F2-C USD source converts to TRY with spread and tax without floating point',function()use($db): void {
    $f=pricingFixture($db); $snapshot=$f['quotes']->plan($f['customer'],new Currency('TRY'),$f['cart'],[],$f['now'])->jsonSerialize();
    same('13158',$snapshot['net_minor']); same('2632',$snapshot['tax_minor']); same('15790',$snapshot['total_minor']);
    same('USD',$snapshot['lines'][0]['sources'][0]['source_currency']); same('43',$snapshot['lines'][0]['sources'][0]['fx']['rate']);
    $usd=$f['quotes']->plan($f['customer'],new Currency('USD'),$f['cart'],[],$f['now'])->jsonSerialize(); same('360',$usd['total_minor']);
});
test('F2-C independent TRY price has configured priority over source FX',function()use($db): void {
    $f=pricingFixture($db); $f['create']('price-publish',['item_id'=>$f['product'],'cycle_id'=>$f['cycle'],'currency'=>'TRY','amount'=>'100']);
    $snapshot=$f['quotes']->plan($f['customer'],new Currency('TRY'),$f['cart'],[],$f['now'])->jsonSerialize(); same('12000',$snapshot['total_minor']); same(null,$snapshot['lines'][0]['sources'][0]['fx']);
});
test('F2-C quote expires at exactly fifteen minutes and historical quote does not reprice',function()use($db): void {
    $f=pricingFixture($db); $plan=$f['quotes']->plan($f['customer'],new Currency('TRY'),$f['cart'],[],$f['now']); $quote=$db->transaction(fn()=>$f['quotes']->issue($plan));
    same('15790',$f['quotes']->get($quote['id'],$f['customer'],$f['now']->modify('+899 seconds'))['total_minor']);
    same(409,raises(HttpError::class,fn()=>$f['quotes']->get($quote['id'],$f['customer'],$f['now']->modify('+900 seconds')))->status);
    $later=$f['now']->modify('+1 second'); $db->transaction(fn()=>$f['fx']->publishSynthetic(new FxSnapshot(new Currency('USD'),new Currency('TRY'),new Decimal('50'),new Decimal('0'),'synthetic-new',$later,Rounding::HalfUp),true));
    same('15790',$f['quotes']->get($quote['id'],$f['customer'],$later)['total_minor']);
    same('18000',$f['quotes']->plan($f['customer'],new Currency('TRY'),$f['cart'],[],$later,true)->jsonSerialize()['total_minor']);
    same(404,raises(HttpError::class,fn()=>$f['quotes']->get($quote['id'],Id::uuid(),$later))->status);
});
test('F2-C tax included excluded and minor rounding conserve the total',function(): void {
    $calc=new TaxCalculator(); $currency=new Currency('TRY'); $tax=['id'=>Id::uuid(),'version'=>'1','rate'=>'0.2','inclusive'=>true];
    $result=$calc->calculate(new Decimal('40'),$currency,$tax); same('3333',$result['net_minor']); same('667',$result['tax_minor']); same('4000',$result['total_minor']);
    $tax['inclusive']=false; $result=$calc->calculate(new Decimal('0.025'),$currency,$tax); same('3',$result['net_minor']); same('1',$result['tax_minor']); same('4',$result['total_minor']);
    $tax['rate']='0'; $tax['inclusive']=true; same('0',$calc->calculate(new Decimal('0'),$currency,$tax)['tax_minor']);
    same(409,raises(HttpError::class,fn()=>$calc->calculate(new Decimal('1'),$currency,null))->status);
});
test('F2-C FX missing stale untrusted future and live synthetic sources fail closed',function()use($db): void {
    $f=pricingFixture($db); $policy=new ExchangeRatePolicy(60);
    same(409,raises(HttpError::class,fn()=>$f['fx']->resolve(new Currency('USD'),new Currency('EUR'),$policy,$f['now']))->status);
    same(409,raises(HttpError::class,fn()=>$f['fx']->resolve(new Currency('USD'),new Currency('TRY'),$policy,$f['now']->modify('+60 seconds')))->status);
    $later=$f['now']->modify('+1 second'); $db->transaction(fn()=>$f['fx']->publishSynthetic(new FxSnapshot(new Currency('USD'),new Currency('TRY'),new Decimal('43'),new Decimal('0'),'synthetic-untrusted',$later,Rounding::HalfUp),false));
    same(409,raises(HttpError::class,fn()=>$f['fx']->resolve(new Currency('USD'),new Currency('TRY'),$policy,$later))->status);
    $trusted=$f['now']->modify('+2 seconds'); $db->transaction(fn()=>$f['fx']->publishSynthetic(new FxSnapshot(new Currency('USD'),new Currency('TRY'),new Decimal('43'),new Decimal('0'),'synthetic-future',$trusted,Rounding::HalfUp),true));
    same(409,raises(HttpError::class,fn()=>$f['fx']->resolve(new Currency('USD'),new Currency('TRY'),$policy,$f['now']))->status);
    same(409,raises(HttpError::class,fn()=>(new FxRateRepository($db,testConfig('production')))->resolve(new Currency('USD'),new Currency('TRY'),$policy,$trusted))->status);
});
test('F2-C required options and cross-product selections are enforced server side',function()use($db): void {
    $f=pricingFixture($db); $group=$f['create']('group-create',['name'=>'Disk','product_id'=>$f['product'],'required'=>true]);
    same(422,raises(HttpError::class,fn()=>$f['quotes']->plan($f['customer'],new Currency('TRY'),$f['cart'],[],$f['now']))->status);
    $option=$f['create']('option-create',['name'=>'100 GB','group_id'=>$group]); $f['create']('price-publish',['item_id'=>$option,'cycle_id'=>$f['cycle'],'currency'=>'USD','amount'=>'1']);
    $cart=$f['cart']; $cart[0]['options']=[$option]; same('21053',$f['quotes']->plan($f['customer'],new Currency('TRY'),$cart,[],$f['now'])->jsonSerialize()['total_minor']);
    $cart[0]['options']=[$option,$option]; same(422,raises(HttpError::class,fn()=>$f['quotes']->plan($f['customer'],new Currency('TRY'),$cart,[],$f['now']))->status);
});
test('F2-C financial planning has no database side effects',function()use($db): void {
    $f=pricingFixture($db); $counts=fn(): array=>array_map(fn(string $table): int=>(int)$db->execute('SELECT COUNT(*) FROM '.$table)->fetchColumn(),['pricing_quotes','finance_journals','outbox_events','audit_logs','catalog_redemptions']);
    $before=$counts(); $f['quotes']->plan($f['customer'],new Currency('TRY'),$f['cart'],[],$f['now']); same($before,$counts());
});
test('F2-C promotion conflicts and parallel redemption enforce the single-use quota',function()use($db): void {
    $f=pricingFixture($db); $codes=[];
    for($i=0;$i<2;++$i) {$code='TEST_'.strtoupper(str_replace('-','',Id::uuid()));$codes[]=$code;$f['create']('promotion-create',['code'=>$code,'rate'=>'0.10','period_scope'=>'FIRST','combinable'=>false,'max_uses'=>1,'per_customer_limit'=>1,'starts_at'=>$f['now']->modify('-1 day')->format('Y-m-d H:i:s'),'ends_at'=>$f['now']->modify('+1 day')->format('Y-m-d H:i:s'),'product_id'=>$f['product']]);}
    same(409,raises(HttpError::class,fn()=>$f['quotes']->plan($f['customer'],new Currency('TRY'),$f['cart'],$codes,$f['now']))->status);
    $plan=$f['quotes']->plan($f['customer'],new Currency('TRY'),$f['cart'],[$codes[0]],$f['now'])->jsonSerialize(); same('14210',$plan['total_minor']);
    $input=['promotions'=>$plan['promotions'],'customer'=>$f['customer'],'now'=>$f['now']->format('Y-m-d H:i:s.u')]; $children=[];
    for($i=0;$i<2;++$i){$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/promotion-child.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR)."\n");fflush($pipes[0]);$children[]=[$process,$pipes];}
    foreach($children as [$process,$pipes]){same("READY\n",str_replace("\r\n","\n",fgets($pipes[1])));}
    foreach($children as [$process,$pipes]){fwrite($pipes[0],"GO\n");fclose($pipes[0]);}
    $out=[];foreach($children as [$process,$pipes]){$out[]=stream_get_contents($pipes[1]);same('',stream_get_contents($pipes[2]));fclose($pipes[1]);fclose($pipes[2]);same(0,proc_close($process));}sort($out);same(['200','409'],$out);
});
test('F2-C renewal protection and notice explicitly gate next-period pricing',function()use($db): void {
    $f=pricingFixture($db); $old=$f['quotes']->plan($f['customer'],new Currency('USD'),$f['cart'],[],$f['now']);
    $f['create']('price-publish',['item_id'=>$f['product'],'cycle_id'=>$f['cycle'],'currency'=>'USD','amount'=>'4']);
    $new=$f['quotes']->plan($f['customer'],new Currency('USD'),$f['cart'],[],$f['now'],true); $policy=new RenewalPricePolicy();
    same('360',$policy->resolve($new,$old,true,false,false)->jsonSerialize()['total_minor']); same('480',$policy->resolve($new,null,false,true,true)->jsonSerialize()['total_minor']);
    same(409,raises(HttpError::class,fn()=>$policy->resolve($new,null,false,true,false))->status);
});
runTests();
