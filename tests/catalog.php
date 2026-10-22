<?php
declare(strict_types=1);
require __DIR__.'/harness.php'; require __DIR__.'/database.php'; require __DIR__.'/catalog-fixture.php';
use Hostider\Core\Id;
use Hostider\Core\Database\Migrator;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
$db=integrationDb(); (new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F2-B catalog models separate variants options addons and billing cycles',function()use($db): void {
    $f=catalogFixture($db); $create=$f['create'];
    $variant=$create('variant-create',['name'=>'Large','product_id'=>$f['product']]);
    $group=$create('group-create',['name'=>'Disk','product_id'=>$f['product'],'required'=>true]);
    $option=$create('option-create',['name'=>'100 GB','group_id'=>$group]);
    $addon=$create('addon-create',['name'=>'Extra backup','standalone'=>true]); $create('addon-link',['product_id'=>$f['product'],'addon_id'=>$addon]);
    foreach([$variant,$option,$addon] as $item){$create('price-publish',['item_id'=>$item,'cycle_id'=>$f['cycle'],'currency'=>'USD','amount'=>'1.50']);}
    $config=$f['service']->repository->configuration($f['product']);
    same($variant,$config['variants'][0]['id']); same($option,$config['options'][0]['id']); same($addon,$config['addons'][0]['id']);
    same('1',$config['groups'][0]['required']===1?'1':(string)$config['groups'][0]['required']);
    foreach([['ONCE',0],['MONTHS',1],['DAYS',45]] as [$kind,$interval]) {
        $id=$create('cycle-create',['name'=>$kind,'kind'=>$kind,'interval_count'=>$interval]); same($kind,$f['service']->repository->find('cycle',$id)['kind']);
    }
});
test('F2-B immutable price publications are independent of cost proposals',function()use($db): void {
    $f=catalogFixture($db); $create=$f['create']; $repo=$f['service']->repository;
    $before=$repo->price($f['product'],$f['cycle'],'USD');
    $create('cost-propose',['item_id'=>$f['product'],'cost_currency'=>'USD','cost_amount'=>'4','proposed_sale_amount'=>'6','source_reference'=>'synthetic-cost']);
    same($before,$repo->price($f['product'],$f['cycle'],'USD'));
    $create('price-publish',['item_id'=>$f['product'],'cycle_id'=>$f['cycle'],'currency'=>'USD','amount'=>'6']);
    same(2,(int)$repo->price($f['product'],$f['cycle'],'USD')['version']);
    same('3.0000000000',$db->execute('SELECT amount FROM catalog_prices WHERE id=?',[$before['id']])->fetchColumn());
    raises(PDOException::class,fn()=>$db->execute('UPDATE catalog_prices SET amount=99 WHERE id=?',[$before['id']]));
    $calendar=$create('cycle-create',['name'=>'Calendar month','kind'=>'MONTHS','interval_count'=>1]);
    $create('price-publish',['item_id'=>$f['product'],'cycle_id'=>$calendar,'currency'=>'TRY','amount'=>'200']);
    same(null,$repo->price($f['product'],$f['cycle'],'TRY')); same('200.0000000000',$repo->price($f['product'],$calendar,'TRY')['amount']);
});
test('F2-B provider mappings stay commercial and do not create resources',function()use($db): void {
    $f=catalogFixture($db); $f['create']('provider-map',['item_id'=>$f['product'],'provider'=>'cpanel','plan_reference'=>'synthetic-package']);
    same('cpanel',$db->execute('SELECT provider FROM catalog_provider_mappings WHERE item_id=?',[$f['product']])->fetchColumn());
    same('0',$db->execute("SELECT CAST(COUNT(*) AS CHAR) FROM outbox_events WHERE event_type LIKE 'provider.%'")->fetchColumn());
});
test('F2-B tax inheritance sale status and stale versions are explicit',function()use($db): void {
    $f=catalogFixture($db); $repo=$f['service']->repository;
    same($f['tax'],$repo->product($f['product'])['tax']['id']);
    $db->transaction(fn()=>$f['service']->execute('product-status',['id'=>$f['product'],'version'=>2,'sale_status'=>'CLOSED'],$f['actor'],Id::uuid()));
    same('CLOSED',$repo->product($f['product'])['sale_status']);
    same(409,raises(HttpError::class,fn()=>$db->transaction(fn()=>$f['service']->execute('product-status',['id'=>$f['product'],'version'=>2,'sale_status'=>'ACTIVE'],$f['actor'],Id::uuid())))->status);
});
test('F2-B promotion policies and quotas persist without assuming redemption',function()use($db): void {
    $f=catalogFixture($db); $id=$f['create']('promotion-create',['code'=>'SYNTHETIC_'.strtoupper(str_replace('-','',Id::uuid())),'rate'=>'0.10','period_scope'=>'FIRST','combinable'=>false,'max_uses'=>1,'per_customer_limit'=>1,'starts_at'=>'2026-01-01 00:00:00','ends_at'=>'2027-01-01 00:00:00','product_id'=>$f['product'],'category_id'=>$f['category']]);
    $promotion=$f['service']->repository->find('promotion',$id); same('FIRST',$promotion['period_scope']); same(1,(int)$promotion['max_uses']);
    same(0,(int)$db->execute('SELECT COUNT(*) FROM catalog_redemptions WHERE promotion_id=?',[$id])->fetchColumn());
});
test('F2-B rejected and interrupted catalog writes leave no partial entities',function()use($db): void {
    $f=catalogFixture($db); $service=$f['service']; $before=(int)$db->execute('SELECT COUNT(*) FROM catalog_items')->fetchColumn();
    raises(RuntimeException::class,fn()=>$db->transaction(function()use($f,$service): void {$service->execute('variant-create',['name'=>'Interrupted','product_id'=>$f['product']],$f['actor'],Id::uuid()); throw new RuntimeException('Interrupted transaction');}));
    same($before,(int)$db->execute('SELECT COUNT(*) FROM catalog_items')->fetchColumn());
    same(403,raises(HttpError::class,fn()=>$db->transaction(fn()=>$service->execute('category-create',['name'=>'Rejected'],new Principal('catalog-test',[],impersonating:true),Id::uuid())))->status);
    same(422,raises(HttpError::class,fn()=>$f['create']('cycle-create',['name'=>'Invalid','kind'=>'ONCE','interval_count'=>1]))->status);
    same(422,raises(HttpError::class,fn()=>$f['create']('tax-create',['name'=>'Invalid','rate'=>'1.1','inclusive'=>true]))->status);
});
runTests();
