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
function domainFixture(\Hostider\Core\Database\Database $db,int $years=1,?\Hostider\Core\Config $sharedConfig=null): array
{
    $f=serviceFixture($db);
    $hostingOrder=$f['order'];
    static $keys=[];$schema=$db->execute('SELECT DATABASE()')->fetchColumn();$keys[$schema]??=base64_encode(random_bytes(32));
    $c=$f['config'];$f['config']=$sharedConfig??new \Hostider\Core\Config('test',$c->url,$keys[$schema],$c->dsn,$c->dbUser,$c->dbPassword);
    $f['repository']=new \Hostider\Services\Repository($db,$f['config']);
    $commerce=new \Hostider\Commerce\Services($db,$f['config']);
    foreach(['quotes','finance','billing','orders'] as $name){$f[$name]=$commerce->$name;}
    $f['cycle']=$f['create']('cycle-create',['name'=>'Synthetic annual domain','kind'=>'MONTHS','interval_count'=>12*$years]);$product=$f['create']('product-create',['name'=>'Synthetic domain','category_id'=>$f['category'],'product_type'=>'DOMAIN','pricing_strategy'=>'FIXED_THEN_FX','source_currency'=>'USD','activation_policy'=>'AUTO','refund_policy'=>'staff_approval']);
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
    $grants=[];foreach(['services.view','services.manage','services.renew'] as $permission){$grants[]=new \Hostider\Core\Security\AccessGrant($permission,[$f['customer']],[$service['id']]);}
    $actor=new \Hostider\Core\Security\Principal('synthetic-domain-owner',['services.view','services.manage','services.renew'],$f['customer'],expiresAt:time()+3600,verifiedAt:time(),grants:$grants);
    $registry=new \Hostider\Domains\Registry($db,$f['config']);
    $policy=new \Hostider\Domains\TldPolicy(['availability','register','renew','initiateTransfer','status','nameservers'],['name','email','country'],null,null);
    $rule=$registry->configure('example','resellerclub',$policy);$name='d'.str_replace('-','',\Hostider\Core\Id::uuid()).'.example';
    $contact=['name'=>'Synthetic owner','email'=>'synthetic@example.invalid','country'=>'TR'];$ns=['ns1.example','ns2.example'];
    $attach=fn()=>$db->transaction(fn()=>$registry->attach($service['id'],$f['customer'],$name,$rule,$contact,$ns,$actor,\Hostider\Core\Id::uuid()));
    return array_replace($f,compact('order','item','line','service','actor','registry','policy','rule','name','contact','ns','attach','hostingOrder'));
}

function paidDomainRenewal($db, array $f): array
{
    $staff = new \Hostider\Core\Security\Principal('domain-policy-staff', ['services.policy'], staff:true, mfa:true, expiresAt:time()+3600, verifiedAt:time(), administrative:true);
    $exists = $db->execute("SELECT 1 FROM svc_policies WHERE scope_kind='SERVICE' AND scope_id=?", [$f['service']['id']])->fetchColumn();
    if (!$exists) { $db->transaction(fn()=>(new \Hostider\Services\Policies($db,$f['config']))->set('SERVICE',$f['service']['id'],['price_protected'=>false,'price_notice_required'=>false],0,$staff)); }
    $renewal = (new \Hostider\Services\Renewals($db,$f['config']))->request($f['service']['id'],$f['customer'],$f['actor'],\Hostider\Core\Id::uuid(),$f['now']);
    $order = $f['orders']->repository->find($renewal['order_id'],$f['customer']);
    $bill = $f['billing']->detail($order['payment_request_id'],$f['customer']);
    if ($bill['status'] !== 'PAID') {
        $db->transaction(function() use($f,$order,$bill): void {
            $money = new \Hostider\Finance\Money($bill['total_minor'],new \Hostider\Finance\Currency($bill['currency']));
            $payment = $f['finance']->recordTestCollection($f['customer'],$money,\Hostider\Core\Id::uuid(),$f['actor'],\Hostider\Core\Id::uuid());
            $f['orders']->fund($order['id'],$f['customer'],$money,'COLLECTION',$payment,\Hostider\Core\Id::uuid(),$f['actor'],\Hostider\Core\Id::uuid());
        });
    }
    return $renewal;
}
function domainCli(array $f, array $arguments): array
{
    $env = array_replace(getenv(), ['APP_ENV'=>'test','APP_URL'=>$f['config']->url,'APP_KEY'=>$f['config']->key,
        'DB_DSN'=>$f['config']->dsn,'DB_USER'=>$f['config']->dbUser,'DB_PASSWORD'=>$f['config']->dbPassword]);
    $pipes=[];
    $process=proc_open(array_merge([PHP_BINARY,dirname(__DIR__).'/bin/console.php'],$arguments),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);
    if (!is_resource($process)) { throw new RuntimeException('CLI process unavailable'); }
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    same(0,proc_close($process));same('',$err);
    return json_decode($out,true,32,JSON_THROW_ON_ERROR);
}
