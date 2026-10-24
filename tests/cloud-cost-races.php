<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/cloud-cost-fixture.php';
use Hostider\Core\{Id};use Hostider\Core\Database\{Database,Migrator};use Hostider\Finance\{Decimal,Rounding};
$db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F4-E one four eight independent PHP processes dedupe submit cost journal and outbox',function()use($db): void{
    $measure=[];foreach([1,4,8] as $n){$f=costFixture($db);$u=costUsage($db,$f,submit:false);$submitted=costChildren($f,['submit',$f['instance'],$u['reference']],$n);$ids=array_column($submitted['results'],'id');same(1,count(array_unique($ids)));$id=$ids[0];$run=costChildren($f,['work',$id],$n);
        same('COSTED_MOCK',$db->execute('SELECT status FROM cloud_cost_work WHERE id=?',[$id])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_usage_costs WHERE id=?',[$id])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM finance_journals WHERE reference=?',['cloud-cost:'.$id])->fetchColumn());same(1,(int)$db->execute("SELECT COUNT(*) FROM outbox_events WHERE event_type='cloud-cost.recorded' AND aggregate_id=?",[$id])->fetchColumn());
        $lat=array_column($run['results'],'elapsed_us');sort($lat);$quantile=static fn(int $p): int=>$lat[max(0,intdiv($p*count($lat)+99,100)-1)];
        $measure[]=['processes'=>$n,'sample_count'=>count($lat),'work_wall_us'=>$run['wall_us'],'submit_wall_us'=>$submitted['wall_us'],'p50_worker_us'=>$quantile(50),'p95_worker_us'=>$quantile(95),'costs'=>1,'journals'=>1,'outbox_facts'=>1,'costs_per_second'=>(string)(new Decimal('1000000'))->divide(new Decimal((string)$run['wall_us']),6,Rounding::HalfUp)];
    }echo 'MEASURE_COST '.json_encode($measure,JSON_THROW_ON_ERROR).PHP_EOL;
});
test('F4-E parallel overlapping usage refs serialize on the resource and cannot double accrue',function()use($db): void{
    $f=costFixture($db);$a=costUsage($db,$f,'overlap-a');$b=costUsage($db,$f,'overlap-b');$r=costChildrenJobs($f,[['work',$a['work']],['work',$b['work']]]);foreach($r['results'] as $result){same(true,$result['checked']);}
    $states=$db->execute('SELECT status FROM cloud_cost_work WHERE id IN (?,?) ORDER BY status',[$a['work'],$b['work']])->fetchAll(PDO::FETCH_COLUMN);same(['COSTED_MOCK','REVIEW'],$states);same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_usage_costs WHERE instance_id=?',[$f['instance']])->fetchColumn());
});
test('F4-E SKIP LOCKED admission never waits or creates a partial journal',function()use($db): void{
    $f=costFixture($db);$u=costUsage($db,$f);$db->pdo->beginTransaction();
    try{$db->execute('SELECT id FROM cloud_cost_work WHERE id=? FOR UPDATE',[$u['work']]);$r=costChildren($f,['work',$u['work']],1);same(false,$r['results'][0]['checked']);same(0,(int)$db->execute('SELECT COUNT(*) FROM finance_journals WHERE reference=?',['cloud-cost:'.$u['work']])->fetchColumn());}finally{$db->pdo->rollBack();}
    costProcess($db,$f,$u);
});
test('F4-E overlapping bounded crons rotate 101 blocked works and reach healthy metering',function()use($db): void{
    $f=costFixture($db);
    $plan=$db->execute("EXPLAIN SELECT * FROM cloud_cost_work FORCE INDEX(ix_cloud_cost_admission) WHERE admissible=TRUE ORDER BY checked_at,created_at,id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetchAll();foreach($plan as $row){same(false,str_contains($row['Extra']??'','filesort'));}
    for($n=0;$n<101;++$n){$f['cost']->submit($f['instance'],'missing-meter-'.$n);}$u=costUsage($db,$f,'healthy-last');$r=costChildren($f,['run'],2);foreach($r['results'] as $result){same(true,$result['checked']<=100);}
    same('COSTED_MOCK',$db->execute('SELECT status FROM cloud_cost_work WHERE id=?',[$u['work']])->fetchColumn());same(101,(int)$db->execute("SELECT COUNT(*) FROM cloud_cost_work WHERE instance_id=? AND status='UNVERIFIED'",[$f['instance']])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_usage_costs WHERE instance_id=?',[$f['instance']])->fetchColumn());
    $cli=domainCli($f,['cloud-costs:work','--max-operations=5','--max-seconds=1']);same(true,$cli['checked']<=5);echo 'MEASURE_COST_FAIR '.json_encode(['blocked'=>101,'healthy_costs'=>1,'overlap_crons'=>2,'wall_us'=>$r['wall_us'],'checked'=>array_column($r['results'],'checked'),'db_retry_counts'=>array_column($r['results'],'db_retry_counts'),'admission_plan'=>$plan],JSON_THROW_ON_ERROR).PHP_EOL;
});
test('F4-E consumed cost receipt is not proposal completion and independent proposal cron recovers',function()use($db): void{
    $f=costFixture($db);$u=costUsage($db,$f);costProcess($db,$f,$u);$q=new \Hostider\Core\Queue\Queue($db);$db->transaction(fn()=>$q->enqueue('cloud-cost.recorded',['cost_id'=>$u['work']],'cost-fact:'.$u['work'],Id::uuid()));$w=new \Hostider\Core\Queue\Worker($db,$q);(new \Hostider\CloudCosts\EventSink($db,$f['config']))->register($w);same(true,$w->once());same('SUCCEEDED',$db->execute('SELECT status FROM jobs WHERE dedupe_key=?',['cost-fact:'.$u['work']])->fetchColumn());
    same(0,(int)$db->execute('SELECT COUNT(*) FROM catalog_cloud_cost_links WHERE cost_id=?',[$u['work']])->fetchColumn());$priceCount=$db->execute('SELECT COUNT(*) FROM catalog_prices')->fetchColumn();$r=domainCli($f,['cloud-costs:propose','--margin-rate=0.2','--max-operations=100','--max-seconds=20']);same(true,$r['checked']>0);same(1,(int)$db->execute('SELECT COUNT(*) FROM catalog_cloud_cost_links WHERE cost_id=?',[$u['work']])->fetchColumn());same($priceCount,$db->execute('SELECT COUNT(*) FROM catalog_prices')->fetchColumn());$before=$db->execute('SELECT COUNT(*) FROM catalog_cloud_cost_links')->fetchColumn();domainCli($f,['cloud-costs:propose','--margin-rate=0.2','--max-operations=100','--max-seconds=20']);same($before,$db->execute('SELECT COUNT(*) FROM catalog_cloud_cost_links')->fetchColumn());raises(InvalidArgumentException::class,fn()=>$f['cost']->run(101,20));raises(\Hostider\Core\Http\HttpError::class,fn()=>(new \Hostider\Catalog\CloudCostProposals($db,$f['config']))->run());
});
runTests();
