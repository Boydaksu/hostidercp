<?php
declare(strict_types=1);
require_once __DIR__.'/cloud-fixture.php';
use Hostider\CloudCosts\{Engine as CostEngine,Store as CostStore,Tariffs};
use Hostider\Providers\Mock\CloudMetering;
function costAdmin($db,$config): array
{
    static $admins=[];$schema=$db->execute('SELECT DATABASE()')->fetchColumn();if(isset($admins[$schema])){return $admins[$schema];}
    $identity=new \Hostider\Identity\IdentityService($db,$config);$password=bin2hex(random_bytes(20));$email='synthetic-cost-admin@example.invalid';$user=$identity->bootstrap($email,'Synthetic cost admin',$password);$reference=\Hostider\Core\Id::uuid();$identity->login($email,$password,'admin',$reference,\Hostider\Core\Id::uuid());$factor=$identity->enroll($reference,'admin',$password);$codes=$identity->confirmEnrollment($reference,'admin',\Hostider\Identity\Totp::code($factor['secret'],intdiv(time(),30)))['recovery_codes'];$identity->stepUp($reference,'admin',$password,array_pop($codes));
    return $admins[$schema]=compact('user','reference');
}
function costFixture($db,array $changes=[],bool $activate=true): array
{
    $f=cloudFixture($db);if($activate){activateCloud($db,$f);}
    $admin=costAdmin($db,$f['config']);$i=$f['engine']->store->instance($f['instance'],false);$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$now=CostStore::date($now->format('Y-m-d H:i:s'));
    $policy=array_replace(['currency'=>'USD','hourly'=>'0.2','quantum_seconds'=>1,'minimum_seconds'=>0,'minimum_minor'=>'0','rounding'=>'HALF_UP','tax_rate'=>'0','addons'=>['ipv4'=>['hourly'=>'0.05','max_quantity'=>2]],'fees'=>['setup'=>['minor'=>'25','max_quantity'=>1]],'valid_from'=>$now->modify('-2 days')->format('Y-m-d H:i:s'),'valid_until'=>$now->modify('+365 days')->format('Y-m-d H:i:s'),'max_age_seconds'=>86400,'spec_hash'=>$i['fingerprint'],'fx'=>null,'provenance'=>'MOCK'],$changes);
    $tariff=(new Tariffs($db,$f['config']))->publish($i['account_id'],$policy,$admin['reference']);
    return array_replace($f,compact('admin','i','now','tariff'),['cost_policy'=>$policy,'cost'=>new CostEngine($db,$f['config']),'meter'=>new CloudMetering($db,$f['config'])]);
}
function costUsage($db,array $f,string $reference='usage-1',int $sequence=1,?array $changes=null,bool $submit=true): array
{
    $proof=(new CostStore($db,$f['config']))->binding($f['engine']->store->instance($f['instance'],false));
    $u=array_replace(['instance_id'=>$f['instance'],'resource_ref'=>$proof['provider_resource_ref']??'unverified-resource','task_ref'=>$proof['provider_task_ref']??'unverified-task','tariff_id'=>$f['tariff'],'sequence'=>$sequence,'starts_at'=>$f['now']->modify('-2 hours')->format('Y-m-d H:i:s'),'ends_at'=>$f['now']->modify('-1 hour')->format('Y-m-d H:i:s'),'observed_at'=>$f['now']->format('Y-m-d H:i:s'),'addons'=>[],'fees'=>[],'provenance'=>'MOCK'],$changes??[]);
    $event=$f['meter']->record($f['i']['account_id'],'USAGE',$reference,$u);$work=$submit?$f['cost']->submit($f['instance'],$reference):null;return compact('u','event','work','reference');
}
function costProcess($db,array $f,array $usage): array{same(true,$f['cost']->once($usage['work']));same('COSTED_MOCK',$db->execute('SELECT status FROM cloud_cost_work WHERE id=?',[$usage['work']])->fetchColumn());return $f['cost']->cost($usage['work']);}
function costInvoice($db,array $f,array $usages,string $reference='invoice-1',array $changes=[]): string
{
    $first=$usages[0]['u'];$last=$usages[count($usages)-1]['u'];$sum=new \Hostider\Finance\Decimal('0');foreach($usages as $u){try{$c=$f['cost']->cost($u['work']);$sum=$sum->add(new \Hostider\Finance\Decimal((string)$c['total_minor']));}catch(\Hostider\Core\Http\HttpError){}}
    $p=array_replace(['instance_id'=>$f['instance'],'resource_ref'=>$first['resource_ref'],'task_ref'=>$first['task_ref'],'usage_refs'=>array_column($usages,'reference'),'currency'=>$f['cost_policy']['currency'],'total_minor'=>(string)$sum,'starts_at'=>$first['starts_at'],'ends_at'=>$last['ends_at'],'observed_at'=>$f['now']->format('Y-m-d H:i:s'),'final'=>true,'provenance'=>'MOCK'],$changes);
    return $f['meter']->record($f['i']['account_id'],'INVOICE',$reference,$p);
}
function costFinanceHash($db): string
{
    $v=[];foreach(['orders','order_items','billing_requests','billing_request_states','svc_prices','finance_payments','finance_wallets'] as $t){$v[$t]=$db->execute('SELECT * FROM '.$t.' ORDER BY 1')->fetchAll();}return hash('sha256',\Hostider\Core\CanonicalJson::encode($v));
}
function costChildren(array $f,array $args,int $count): array{return costChildrenJobs($f,array_fill(0,$count,$args));}
function costChildrenJobs(array $f,array $jobs): array
{
    $start=hrtime(true);$children=[];$failures=[];
    foreach($jobs as $args){$pipes=[];$p=proc_open(array_merge([PHP_BINARY,__DIR__.'/cloud-cost-child.php'],$args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,cloudEnv($f));if(!is_resource($p)){$failures[]=['exit'=>-1,'code'=>'COST_CHILD_UNAVAILABLE'];break;}fclose($pipes[0]);$children[]=[$p,$pipes];}
    $results=[];
    foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);$r=json_decode($out,true,32);if($exit!==0||$err!==''||!is_array($r)){$failures[]=['exit'=>$exit,'code'=>is_array($r)?($r['code']??'UNKNOWN'):'INVALID_RESULT'];}else{$results[]=$r;}}
    if($failures!==[]){throw new RuntimeException('Cost children drained before failure: '.json_encode($failures,JSON_THROW_ON_ERROR));}
    return ['results'=>$results,'wall_us'=>intdiv(hrtime(true)-$start,1000)];
}
