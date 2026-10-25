<?php
declare(strict_types=1);
require_once __DIR__.'/domains-fixture.php';
use Hostider\Core\{Config,Id};
use Hostider\Core\Security\{Principal,AccessGrant};
use Hostider\Finance\{Currency,Money};
use Hostider\Cloud\{Catalog,Engine,Worker};
function cloudFixture($db,bool $attach=true,int $quantity=1,?array $fixedSpec=null,?array $fixedPolicy=null): array
{
    $f=serviceFixture($db);static $keys=[];$schema=$db->execute('SELECT DATABASE()')->fetchColumn();$keys[$schema]??=base64_encode(random_bytes(32));
    $c=$f['config'];$f['config']=new Config('test',$c->url,$keys[$schema],$c->dsn,$c->dbUser,$c->dbPassword);
    $commerce=new \Hostider\Commerce\Services($db,$f['config']);foreach(['quotes','finance','billing','orders'] as $name){$f[$name]=$commerce->$name;}
    $product=$f['create']('product-create',['name'=>'Synthetic Cloud','category_id'=>$f['category'],'product_type'=>'CLOUD','pricing_strategy'=>'FIXED_THEN_FX','source_currency'=>'USD','activation_policy'=>'AUTO','refund_policy'=>'staff_approval']);
    $f['create']('price-publish',['item_id'=>$product,'cycle_id'=>$f['cycle'],'currency'=>'USD','amount'=>'9']);$f['create']('product-status',['id'=>$product,'version'=>1,'sale_status'=>'ACTIVE']);
    $policy=$fixedPolicy??['pids'=>[1,2,3],'osids'=>[1,2],'locations'=>[1],'actions'=>['CREATE','START','STOP','RESTART','REINSTALL','UPGRADE','TERMINATE'],'custom'=>false,'free_upgrade_targets'=>[1,2],'termination_wait_seconds'=>1];
    $catalog=(new Catalog($db,$f['config']))->configure($product,$policy);$spec=$fixedSpec??['pid'=>1,'osid'=>1,'locationid'=>1,'type'=>'ondemand'];
    $price=$db->execute('SELECT id FROM catalog_prices WHERE item_id=? AND cycle_id=? ORDER BY version DESC LIMIT 1',[$product,$f['cycle']])->fetchColumn();
    (new \Hostider\Catalog\CloudOffers($db))->publish($f['config'],$catalog,$price,$spec);
    $order=$db->transaction(function()use($f,$product,$quantity): array{
        $q=$f['quotes']->issue($f['quotes']->plan($f['customer'],new Currency('USD'),[['product_id'=>$product,'cycle_id'=>$f['cycle'],'quantity'=>$quantity]],[],$f['now']));
        $o=$f['orders']->submit($q['id'],$f['customer'],['name'=>'Synthetic Cloud owner','currency'=>'USD'],$f['actor'],Id::uuid(),$f['now']);$m=new Money($q['total_minor'],new Currency('USD'));
        $p=$f['finance']->recordTestCollection($f['customer'],$m,Id::uuid(),$f['actor'],Id::uuid());$f['orders']->fund($o['id'],$f['customer'],$m,'COLLECTION',$p,Id::uuid(),$f['actor'],Id::uuid());return $o;
    });
    $order['currency']='USD';$item=$db->execute('SELECT * FROM order_items WHERE order_id=?',[$order['id']])->fetch();$line=json_decode($item['snapshot'],true,64,JSON_THROW_ON_ERROR);
    $repository=new \Hostider\Services\Repository($db,$f['config']);$service=$db->transaction(fn()=>$repository->materialize($order,$item,$line,1,$f['category'],$f['actor'],Id::uuid()));
    $grants=[new AccessGrant('services.manage',[$f['customer']],[$service['id']])];$actor=new Principal('cloud-staff',['services.manage'],staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time(),grants:$grants);
    $client=new Principal('cloud-client',['services.manage'],customerId:$f['customer'],expiresAt:time()+3600,verifiedAt:time(),grants:$grants);
    $engine=new Engine($db,$f['config']);$instance=$attach?$engine->attach($service['id'],$f['customer'],$catalog,$spec,$actor):null;
    return array_replace($f,compact('order','item','line','service','repository','product','actor','client','catalog','policy','spec','engine','instance'));
}
function activateCloud($db,array $f): string
{
    $op=$f['engine']->enqueue($f['instance'],'CREATE',[],$f['actor']);$w=new Worker($db,$f['config']);same(true,$w->once($op));same(true,$w->once($op));same('SUCCEEDED',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());return $op;
}
function cloudAction(array $f,string $kind,array $data=[]): string
{
    $confirmation=$f['engine']->prepare($f['instance'],$kind,$data,$f['actor']);return $f['engine']->enqueue($f['instance'],$kind,$data,$f['actor'],$confirmation);
}
function cloudEnv(array $f): array{return array_replace(getenv(),['APP_ENV'=>'test','APP_URL'=>$f['config']->url,'APP_KEY'=>$f['config']->key,'DB_DSN'=>$f['config']->dsn,'DB_USER'=>$f['config']->dbUser,'DB_PASSWORD'=>$f['config']->dbPassword]);}
