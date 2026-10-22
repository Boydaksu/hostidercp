<?php
declare(strict_types=1);
use Hostider\Core\Id;
use Hostider\Core\Security\Principal;
use Hostider\Catalog\CatalogService;
function catalogFixture(\Hostider\Core\Database\Database $db): array
{
    $service=new CatalogService($db); $actor=new Principal('catalog-test',[]);
    $create=fn(string $action,array $data): string=>$db->transaction(fn()=>$service->execute($action,$data,$actor,Id::uuid()))['id'];
    $tax=$create('tax-create',['name'=>'Synthetic VAT','rate'=>'0.20','inclusive'=>false]);
    $category=$create('category-create',['name'=>'Synthetic hosting','tax_id'=>$tax]);
    $cycle=$create('cycle-create',['name'=>'30 days','kind'=>'DAYS','interval_count'=>30]);
    $product=$create('product-create',['name'=>'Synthetic package','category_id'=>$category,'product_type'=>'HOSTING','pricing_strategy'=>'FIXED_THEN_FX','source_currency'=>'USD','activation_policy'=>'AUTO','refund_policy'=>'staff_approval']);
    $create('price-publish',['item_id'=>$product,'cycle_id'=>$cycle,'currency'=>'USD','amount'=>'3']);
    $db->transaction(fn()=>$service->execute('product-status',['id'=>$product,'version'=>1,'sale_status'=>'ACTIVE'],$actor,Id::uuid()));
    return compact('tax','category','cycle','product','create','actor','service');
}
