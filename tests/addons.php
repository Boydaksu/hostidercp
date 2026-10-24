<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/catalog-fixture.php';require __DIR__.'/pricing-fixture.php';
use Hostider\Core\Id;use Hostider\Core\Database\Migrator;use Hostider\Core\Http\HttpError;use Hostider\Finance\Currency;
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F2-B/C standalone addon has its own explicit tax pricing and sale policy',function()use($db): void {
    $f=pricingFixture($db);$create=$f['create'];$once=$create('cycle-create',['name'=>'One time','kind'=>'ONCE','interval_count'=>0]);$addon=$create('addon-create',['name'=>'Synthetic independent add-on','standalone'=>true]);$create('price-publish',['item_id'=>$addon,'cycle_id'=>$once,'currency'=>'USD','amount'=>'5']);
    $create('addon-policy',['addon_id'=>$addon,'category_id'=>$f['category'],'pricing_strategy'=>'FIXED_PER_CURRENCY','source_currency'=>'USD','activation_policy'=>'MANUAL','refund_policy'=>'staff_approval','sale_status'=>'ACTIVE','version'=>1]);
    $snapshot=$f['quotes']->plan($f['customer'],new Currency('USD'),[['product_id'=>$addon,'cycle_id'=>$once,'quantity'=>1]],[],$f['now'])->jsonSerialize();same('600',$snapshot['total_minor']);same('MANUAL',$snapshot['lines'][0]['activation_policy']);same('ONCE',$snapshot['lines'][0]['sources'][0]['charge_kind']);same($f['tax'],$snapshot['lines'][0]['tax_policy']['id']);
    same(409,raises(HttpError::class,fn()=>$f['quotes']->plan($f['customer'],new Currency('USD'),[['product_id'=>$addon,'cycle_id'=>$once,'quantity'=>1]],[],$f['now'],true))->status);
});
test('F2-B/C one-time linked addon is charged initially and excluded from renewal',function()use($db): void {
    $f=pricingFixture($db);$create=$f['create'];$once=$create('cycle-create',['name'=>'One time setup','kind'=>'ONCE','interval_count'=>0]);$addon=$create('addon-create',['name'=>'Synthetic setup','standalone'=>false]);$create('addon-link',['product_id'=>$f['product'],'addon_id'=>$addon]);$create('price-publish',['item_id'=>$addon,'cycle_id'=>$once,'currency'=>'USD','amount'=>'2']);
    $cart=$f['cart'];$cart[0]['addons']=[['id'=>$addon,'cycle_id'=>$once]];$initial=$f['quotes']->plan($f['customer'],new Currency('USD'),$cart,[],$f['now'])->jsonSerialize();$renewal=$f['quotes']->plan($f['customer'],new Currency('USD'),$cart,[],$f['now'],true)->jsonSerialize();same('600',$initial['total_minor']);same('360',$renewal['total_minor']);same(2,count($initial['lines'][0]['sources']));same(1,count($renewal['lines'][0]['sources']));
    $cart[0]['addons'][]=['id'=>$addon,'cycle_id'=>$f['cycle']];same(422,raises(HttpError::class,fn()=>$f['quotes']->plan($f['customer'],new Currency('USD'),$cart,[],$f['now']))->status);
});
test('F2-B/C variant-only product exposes independently priced cycles without a base price',function()use($db): void {
    $f=pricingFixture($db);$create=$f['create'];$cycle=$create('cycle-create',['name'=>'Calendar month','kind'=>'MONTHS','interval_count'=>1]);$product=$create('product-create',['name'=>'Synthetic variant-only hosting','category_id'=>$f['category'],'product_type'=>'HOSTING','pricing_strategy'=>'FIXED_PER_CURRENCY','source_currency'=>'USD','activation_policy'=>'MANUAL','refund_policy'=>'none']);$variant=$create('variant-create',['name'=>'Small hosting','product_id'=>$product]);$create('price-publish',['item_id'=>$variant,'cycle_id'=>$cycle,'currency'=>'USD','amount'=>'5']);$create('product-status',['id'=>$product,'version'=>1,'sale_status'=>'ACTIVE']);$configuration=$f['service']->repository->configuration($product);same([],$configuration['base_cycles']);same($cycle,$configuration['cycles'][0]['id']);$cart=[['product_id'=>$product,'variant_id'=>$variant,'cycle_id'=>$cycle,'quantity'=>1]];same('600',$f['quotes']->plan($f['customer'],new Currency('USD'),$cart,[],$f['now'])->jsonSerialize()['total_minor']);
    unset($cart[0]['variant_id']);same(409,raises(HttpError::class,fn()=>$f['quotes']->plan($f['customer'],new Currency('USD'),$cart,[],$f['now']))->status);
});
runTests();
