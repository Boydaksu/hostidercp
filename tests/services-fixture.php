<?php
declare(strict_types=1);
require_once __DIR__.'/catalog-fixture.php';require_once __DIR__.'/pricing-fixture.php';require_once __DIR__.'/orders-fixture.php';
function serviceFixture(\Hostider\Core\Database\Database $db,int $quantity=1,bool $manual=false,bool $paid=true): array
{
    $f=ordersFixture($db);if($manual){$f['create']('product-update',['id'=>$f['product'],'name'=>'Manual mock hosting','category_id'=>$f['category'],'pricing_strategy'=>'FIXED_THEN_FX','source_currency'=>'USD','activation_policy'=>'MANUAL','refund_policy'=>'staff_approval','version'=>2]);}
    $cart=$f['cart'];$cart[0]['quantity']=$quantity;$quote=$db->transaction(fn()=>$f['quotes']->issue($f['quotes']->plan($f['customer'],new \Hostider\Finance\Currency('USD'),$cart,[],$f['now'])));$orders=new \Hostider\Orders\OrderService($db,$f['billing'],$f['quotes'],'test');$order=$db->transaction(fn()=>$orders->submit($quote['id'],$f['customer'],['name'=>'Synthetic service customer','currency'=>'USD'],$f['actor'],\Hostider\Core\Id::uuid(),$f['now']));
    if($paid){$db->transaction(function()use($f,$quote,$order,$orders): void{$money=new \Hostider\Finance\Money($quote['total_minor'],new \Hostider\Finance\Currency('USD'));$payment=$f['finance']->recordTestCollection($f['customer'],$money,\Hostider\Core\Id::uuid(),$f['actor'],\Hostider\Core\Id::uuid());$orders->fund($order['id'],$f['customer'],$money,'COLLECTION',$payment,\Hostider\Core\Id::uuid(),$f['actor'],\Hostider\Core\Id::uuid());});}
    $order=$orders->repository->find($order['id'],$f['customer']);$order['currency']='USD';$item=$db->execute('SELECT * FROM order_items WHERE order_id=? ORDER BY id LIMIT 1',[$order['id']])->fetch();$line=json_decode($item['snapshot'],true,64,JSON_THROW_ON_ERROR);$repository=new \Hostider\Services\Repository($db,$f['config']);return array_replace($f,compact('order','item','line','repository','orders','quote'));
}
