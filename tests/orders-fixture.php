<?php
declare(strict_types=1);
use Hostider\Billing\BillingService;
use Hostider\Finance\FinanceService;
use Hostider\Orders\OrderService;
function ordersFixture(\Hostider\Core\Database\Database $db): array
{
    $f=pricingFixture($db); $config=testConfig('test'); $finance=new FinanceService($db,$config); $billing=new BillingService($db,$config,$finance); $orders=new OrderService($db,$billing,$f['quotes']);
    $actor=new \Hostider\Core\Security\Principal('order-test',[],customerId:$f['customer']);
    $plan=$f['quotes']->plan($f['customer'],new \Hostider\Finance\Currency('USD'),$f['cart'],[],$f['now']);
    $quote=$db->transaction(fn()=>$f['quotes']->issue($plan));
    $order=$db->transaction(fn()=>$orders->submit($quote['id'],$f['customer'],['name'=>'Synthetic customer','currency'=>'USD'],$actor,\Hostider\Core\Id::uuid(),$f['now']));
    $db->transaction(fn()=>$finance->openWallet($f['customer'],new \Hostider\Finance\Currency('USD'),$actor));
    return array_replace($f,compact('config','finance','billing','orders','actor','quote','order'));
}
