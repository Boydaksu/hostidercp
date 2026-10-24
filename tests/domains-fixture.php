<?php
declare(strict_types=1);
require_once __DIR__.'/services-fixture.php';
function isolatedDomainDb(): \Hostider\Core\Database\Database
{
    $db=integrationDb();$schema='hostider_f0_test_domain_'.bin2hex(random_bytes(6));
    $db->execute('CREATE DATABASE '.$schema.' CHARACTER SET utf8mb4');
    putenv('TEST_DB_DSN='.preg_replace('/dbname=[a-zA-Z0-9_]+/','dbname='.$schema,getenv('TEST_DB_DSN')));
    $db=integrationDb();register_shutdown_function(static function()use($db,$schema): void{$db->execute('DROP DATABASE '.$schema);});return $db;
}
function domainFixture(\Hostider\Core\Database\Database $db): array
{
    $f=serviceFixture($db);$product=$f['create']('product-create',['name'=>'Synthetic domain','category_id'=>$f['category'],'product_type'=>'DOMAIN','pricing_strategy'=>'FIXED_THEN_FX','source_currency'=>'USD','activation_policy'=>'AUTO','refund_policy'=>'staff_approval']);
    $f['create']('price-publish',['item_id'=>$product,'cycle_id'=>$f['cycle'],'currency'=>'USD','amount'=>'5']);$f['create']('product-status',['id'=>$product,'version'=>1,'sale_status'=>'ACTIVE']);
    $cart=[['product_id'=>$product,'cycle_id'=>$f['cycle'],'quantity'=>1]];
    $order=$db->transaction(function()use($f,$cart): array{
        $quote=$f['quotes']->issue($f['quotes']->plan($f['customer'],new \Hostider\Finance\Currency('USD'),$cart,[],$f['now']));
        $order=$f['orders']->submit($quote['id'],$f['customer'],['name'=>'Synthetic domain customer','currency'=>'USD'],$f['actor'],\Hostider\Core\Id::uuid(),$f['now']);
        $money=new \Hostider\Finance\Money($quote['total_minor'],new \Hostider\Finance\Currency('USD'));
        $payment=$f['finance']->recordTestCollection($f['customer'],$money,\Hostider\Core\Id::uuid(),$f['actor'],\Hostider\Core\Id::uuid());
        $f['orders']->fund($order['id'],$f['customer'],$money,'COLLECTION',$payment,\Hostider\Core\Id::uuid(),$f['actor'],\Hostider\Core\Id::uuid());return $order;
    });
    $order=$f['orders']->repository->find($order['id'],$f['customer']);$order['currency']='USD';
    $item=$db->execute('SELECT * FROM order_items WHERE order_id=?',[$order['id']])->fetch();$line=json_decode($item['snapshot'],true,64,JSON_THROW_ON_ERROR);
    $service=$db->transaction(fn()=>$f['repository']->materialize($order,$item,$line,1,$f['category'],$f['actor'],\Hostider\Core\Id::uuid()));
    $grants=[];foreach(['services.view','services.manage'] as $permission){$grants[]=new \Hostider\Core\Security\AccessGrant($permission,[$f['customer']],[$service['id']]);}
    $actor=new \Hostider\Core\Security\Principal('synthetic-domain-owner',['services.view','services.manage'],$f['customer'],expiresAt:time()+3600,verifiedAt:time(),grants:$grants);
    $registry=new \Hostider\Domains\Registry($db,$f['config']);
    $policy=new \Hostider\Domains\TldPolicy(['availability','register','renew','initiateTransfer','status','nameservers'],['name','email','country'],null,null);
    $rule=$registry->configure('example','resellerclub',$policy);$name='d'.str_replace('-','',\Hostider\Core\Id::uuid()).'.example';
    $contact=['name'=>'Synthetic owner','email'=>'synthetic@example.invalid','country'=>'TR'];$ns=['ns1.example','ns2.example'];
    $attach=fn()=>$db->transaction(fn()=>$registry->attach($service['id'],$f['customer'],$name,$rule,$contact,$ns,$actor,\Hostider\Core\Id::uuid()));
    return array_replace($f,compact('order','item','line','service','actor','registry','policy','rule','name','contact','ns','attach'));
}
