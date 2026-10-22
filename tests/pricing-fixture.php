<?php
declare(strict_types=1);
use Hostider\Finance\{Currency,Decimal,FxSnapshot,Rounding};
use Hostider\Pricing\{ExchangeRatePolicy,FxRateRepository,PriceResolver,QuoteService};
function pricingFixture(\Hostider\Core\Database\Database $db): array
{
    $f=catalogFixture($db); $fx=new FxRateRepository($db,testConfig('test')); $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $latest=$db->execute('SELECT MAX(observed_at) FROM pricing_fx_rates')->fetchColumn();
    if($latest && new DateTimeImmutable($latest,new DateTimeZone('UTC'))>=$now){$now=(new DateTimeImmutable($latest,new DateTimeZone('UTC')))->modify('+3 seconds');}
    $db->transaction(fn()=>$fx->publishSynthetic(new FxSnapshot(new Currency('USD'),new Currency('TRY'),new Decimal('43'),new Decimal('0.02'),'synthetic-usdtry',$now,Rounding::HalfUp),true));
    $resolver=new PriceResolver($f['service']->repository,$fx,new ExchangeRatePolicy(3600)); $quotes=new QuoteService($db,$f['service']->repository,$resolver);
    $cart=[['product_id'=>$f['product'],'cycle_id'=>$f['cycle'],'quantity'=>1]];
    return $f+compact('fx','now','resolver','quotes','cart')+['customer'=>\Hostider\Core\Id::uuid()];
}
