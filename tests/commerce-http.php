<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/support/HttpTestServer.php';require __DIR__.'/database.php';require __DIR__.'/catalog-fixture.php';require __DIR__.'/commerce-fixture.php';
use Hostider\Commerce\Services;
use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Migrator;
$db=integrationDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();$key=base64_encode(random_bytes(32));$config=new Config('test','https://test.example.invalid',$key,getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');$services=new Services($db,$config);$admin=commerceAdmin($services);$buyer=commerceUser($services,'http-buyer');$outsider=commerceUser($services,'http-outsider');$catalog=catalogFixture($db);$catalog['create']('price-publish',['item_id'=>$catalog['product'],'cycle_id'=>$catalog['cycle'],'currency'=>'TRY','amount'=>'100']);
$assignment=$db->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL LIMIT 1',[$admin['user']])->fetchColumn();foreach(['finance.view','finance.approve','billing.view','wallet.view','orders.view','identity.customers.view','billing.documents.manage'] as $permission){$db->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope) VALUES (?,?,?,?)',[Id::uuid(),$assignment,$permission,$buyer['customer']]);}
$root=dirname(__DIR__);$env=array_merge(getenv(),['APP_ENV'=>'test','APP_URL'=>'https://test.example.invalid','APP_KEY'=>$key,'DB_DSN'=>getenv('TEST_DB_DSN'),'DB_USER'=>getenv('TEST_DB_USER')?:'root','DB_PASSWORD'=>getenv('TEST_DB_PASSWORD')?:'','PROVISIONING_ALLOWED'=>'false','PAYMENT_LIVE_ALLOWED'=>'false','EXTERNAL_MAIL_ALLOWED'=>'false']);$server=new HttpTestServer($root,$env,__DIR__.'/commerce-router.php');$jars=['admin'=>[],'client'=>[]];$tokens=['admin'=>'','client'=>''];
function commerceHttp(string $path,?array $data=null,?string $key=null,bool $csrf=true): array
{
    global $server,$jars,$tokens;$context=str_contains($path,'/admin/')?'admin':'client';$headers=[];$options=[];
    if($jars[$context]){$options[CURLOPT_COOKIE]=implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($jars[$context]),$jars[$context]));}
    if($data!==null){$headers=['Content-Type: application/json','Idempotency-Key: '.($key??Id::uuid())];if($csrf){$headers[]='X-CSRF-Token: '.$tokens[$context];}$options += [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode((object)$data,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>$headers];}
    [$status,$raw,$responseHeaders]=$server->request($path,$options);foreach($responseHeaders as $line){if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$m)){$jars[$context][$m[1]]=$m[2];}}$body=is_string($raw)?json_decode($raw,true):null;if(isset($body['data']['csrf'])){$tokens[$context]=$body['data']['csrf'];}return [$status,$body,$responseHeaders,$raw];
}
function httpCommand(string $context,string $action,array $payload,?string $customer=null): array
{
    $data=['action'=>$action,'payload'=>(object)$payload];if($customer!==null){$data['customer']=$customer;}$review=commerceHttp('/api/v1/'.$context.'/commerce/prepare',$data);if($review[0]!==200){return $review;}$data['confirmation']=(object)['id'=>$review[1]['data']['id'],'accepted'=>true];return commerceHttp('/api/v1/'.$context.'/commerce/execute',$data);
}
try{
    same(200,commerceHttp('/api/v1/health')[0]);
    test('F2-G HTTP requires login fresh step-up CSRF and scoped customer ownership',function()use($buyer,$outsider): void {
        same(401,commerceHttp('/api/v1/client/commerce/wallet')[0]);same(200,commerceHttp('/api/v1/client/identity/bootstrap')[0]);same(200,commerceHttp('/api/v1/client/identity/login',['email'=>$buyer['email'],'password'=>$buyer['password']])[0]);
        $data=['action'=>'attempt-create','payload'=>(object)['minor'=>'100']];same(403,commerceHttp('/api/v1/client/commerce/prepare',$data)[0]);same(200,commerceHttp('/api/v1/client/identity/step-up',['password'=>$buyer['password'],'code'=>''])[0]);same(403,commerceHttp('/api/v1/client/commerce/prepare',$data,csrf:false)[0]);
        $data['customer']=$outsider['customer'];same(403,commerceHttp('/api/v1/client/commerce/prepare',$data)[0]);same(403,commerceHttp('/api/v1/client/commerce/bills?customer='.$outsider['customer'])[0]);same(200,commerceHttp('/api/v1/client/commerce/catalog')[0]);
    });
    test('F2-G HTTP quote and order work and approved command replay is atomic',function()use($db,$catalog): void {
        $payload=['cart'=>[['product_id'=>$catalog['product'],'cycle_id'=>$catalog['cycle'],'quantity'=>1]],'promotions'=>[]];$before=(int)$db->execute('SELECT COUNT(*) FROM pricing_quotes')->fetchColumn();$review=commerceHttp('/api/v1/client/commerce/prepare',['action'=>'quote-create','payload'=>(object)$payload]);same(200,$review[0]);same($before,(int)$db->execute('SELECT COUNT(*) FROM pricing_quotes')->fetchColumn());
        $data=['action'=>'quote-create','payload'=>(object)$payload,'confirmation'=>(object)['id'=>$review[1]['data']['id'],'accepted'=>true]];$key=Id::uuid();$GLOBALS['reviewReplay']=['data'=>$data,'key'=>$key];$quote=commerceHttp('/api/v1/client/commerce/execute',$data,$key);same(200,$quote[0]);same('12000',$quote[1]['data']['total_minor']);same($quote[1]['data'],commerceHttp('/api/v1/client/commerce/execute',$data,$key)[1]['data']);same(409,commerceHttp('/api/v1/client/commerce/execute',$data)[0]);
        $order=httpCommand('client','order-submit',['quote_id'=>$quote[1]['data']['id']]);same(200,$order[0]);$GLOBALS['httpCommerceOrder']=$order[1]['data'];same('AWAITING_PAYMENT',$order[1]['data']['status']);
    });
    test('F2-G HTTP topup bank approval mixed funding and ready fact use actual services',function()use($admin,$buyer,$db): void {
        $attempt=httpCommand('client','attempt-create',['minor'=>'15000']);same(200,$attempt[0]);$notice=httpCommand('client','bank-notify',['attempt_id'=>$attempt[1]['data']['id'],'bank_reference'=>'synthetic-http-bank-'.Id::uuid()]);same(200,$notice[0]);same('0',commerceHttp('/api/v1/client/commerce/wallet')[1]['data']['balance_minor']);
        same(200,commerceHttp('/api/v1/admin/identity/bootstrap')[0]);same(200,commerceHttp('/api/v1/admin/identity/login',['email'=>$admin['email'],'password'=>$admin['password']])[0]);same(200,commerceHttp('/api/v1/admin/identity/mfa',['code'=>$admin['recovery'][0]])[0]);same(200,commerceHttp('/api/v1/admin/identity/step-up',['password'=>$admin['password'],'code'=>$admin['recovery'][1]])[0]);
        $approved=httpCommand('admin','bank-approve',['notice_id'=>$notice[1]['data']['id'],'version'=>1],$buyer['customer']);same(200,$approved[0]);same('15000',commerceHttp('/api/v1/client/commerce/wallet')[1]['data']['balance_minor']);
        $paid=httpCommand('client','wallet-pay',['order_id'=>$GLOBALS['httpCommerceOrder']['id'],'minor'=>'12000']);same(200,$paid[0]);same('READY_FOR_PROVISIONING',$paid[1]['data']['order']['status']);same('3000',commerceHttp('/api/v1/client/commerce/wallet')[1]['data']['balance_minor']);
        same(1,(int)$db->execute("SELECT COUNT(*) FROM order_events WHERE order_id=? AND kind='ready'",[$GLOBALS['httpCommerceOrder']['id']])->fetchColumn());
    });
    test('F2-G HTTP official PDF download is attachment and another customer cannot fetch it',function()use($buyer,$outsider,&$jars,&$tokens): void {
        $order=$GLOBALS['httpCommerceOrder'];$start=httpCommand('admin','document-start',['request_id'=>$order['payment_request_id']],$buyer['customer']);same(200,$start[0]);$pdf="%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        same(200,httpCommand('admin','document-chunk',['upload_id'=>$start[1]['data']['id'],'offset'=>0,'base64'=>base64_encode($pdf)],$buyer['customer'])[0]);$document=httpCommand('admin','document-finish',['upload_id'=>$start[1]['data']['id'],'number'=>'SYNTHETIC-HTTP-1','date'=>'2026-10-22','type'=>'EXTERNAL_OFFICIAL_PDF'],$buyer['customer']);same(200,$document[0]);$download=commerceHttp('/api/v1/client/commerce/document?id='.$document[1]['data']['id']);same(200,$download[0]);same($pdf,$download[3]);same(true,(bool)array_filter($download[2],fn(string $line): bool=>str_starts_with(strtolower($line),'content-disposition: attachment;')));
        $savedJar=$jars['client'];$savedToken=$tokens['client'];$jars['client']=[];$tokens['client']='';commerceHttp('/api/v1/client/identity/bootstrap');same(200,commerceHttp('/api/v1/client/identity/login',['email'=>$outsider['email'],'password'=>$outsider['password']])[0]);same(404,commerceHttp('/api/v1/client/commerce/document?id='.$document[1]['data']['id'])[0]);same(404,commerceHttp('/api/v1/client/commerce/bill?id='.$order['payment_request_id'])[0]);same(404,commerceHttp('/api/v1/client/commerce/order?id='.$order['id'])[0]);$jars['client']=$savedJar;$tokens['client']=$savedToken;
    });
    test('F2-G HTTP real PayTR callback and unsupported privileged commands remain closed',function()use($db): void {
        $before=(int)$db->execute('SELECT COUNT(*) FROM finance_payments')->fetchColumn();same(403,commerceHttp('/api/v1/webhooks/paytr',['status'=>'success','minor'=>'999999'])[0]);same($before,(int)$db->execute('SELECT COUNT(*) FROM finance_payments')->fetchColumn());same(403,httpCommand('client','expense-create',['category_id'=>Id::uuid(),'minor'=>'1','currency'=>'TRY','reference'=>'unauthorized','description'=>'Denied'])[0]);same(200,commerceHttp('/client/commerce')[0]);same(200,commerceHttp('/admin/commerce')[0]);
    });
    test('F2-G HTTP panel JavaScript and stylesheet are actually served by the local router',function(): void {
        $script=commerceHttp('/assets/commerce.js');same(200,$script[0]);same(true,str_starts_with($script[3],"'use strict';"));
        $style=commerceHttp('/assets/commerce.css');same(200,$style[0]);same(true,str_contains($style[3],'.identity-link'));
    });
    test('F2-G company ledger is staff-only and exposes exact minor strings',function(): void {
        same(403,commerceHttp('/api/v1/client/commerce/ledger')[0]);$ledger=commerceHttp('/api/v1/admin/commerce/ledger');same(200,$ledger[0]);same(true,count($ledger[1]['data'])>0);foreach($ledger[1]['data'] as $journal){foreach($journal['lines'] as $line){same(true,is_string($line['amount_minor']));}}
    });
    test('Review HTTP reconciliation lists open cases and exposes scoped evidence after atomic resolution',function()use($db,$services,$buyer): void {
        $case=$db->transaction(function()use($services,$buyer): string {$actor=new \Hostider\Core\Security\Principal('review-http',[]);$payment=$services->finance->recordTestCollection($buyer['customer'],new \Hostider\Finance\Money('100',new \Hostider\Finance\Currency('TRY')),Id::uuid(),$actor,Id::uuid());return $services->finance->reconcile($payment,'LATE_OR_EXCESS_PAYMENT',$actor,Id::uuid());});$rows=commerceHttp('/api/v1/admin/commerce/reconciliations')[1]['data'];$found=array_values(array_filter($rows,fn(array $row): bool=>$row['id']===$case));same('OPEN',$found[0]['status']);$evidence='Synthetic statement line checked through staff HTTP review';$result=httpCommand('admin','reconciliation-resolve',['case_id'=>$case,'resolution'=>'CREDIT_WALLET','evidence'=>$evidence],$buyer['customer']);same(200,$result[0]);$detail=commerceHttp('/api/v1/admin/commerce/reconciliation?id='.$case.'&customer='.$buyer['customer']);same(200,$detail[0]);same('RESOLVED',$detail[1]['data']['status']);same($evidence,$detail[1]['data']['evidence']);same(hash('sha256',$evidence),$detail[1]['data']['evidence_sha256']);same(409,httpCommand('admin','reconciliation-resolve',['case_id'=>$case,'resolution'=>'CREDIT_WALLET','evidence'=>$evidence],$buyer['customer'])[0]);same(403,commerceHttp('/api/v1/client/commerce/reconciliation?id='.$case)[0]);
    });
    test('Review HTTP idempotent replay still requires fresh step-up',function()use($db,$buyer): void {
        $db->execute('UPDATE identity_sessions SET verified_at=TIMESTAMPADD(SECOND,-301,UTC_TIMESTAMP(6)) WHERE user_id=? AND context=\'client\'',[$buyer['user']]);$cached=$GLOBALS['reviewReplay'];same(403,commerceHttp('/api/v1/client/commerce/execute',$cached['data'],$cached['key'])[0]);
    });
    test('F2-G production commerce mutation gate rejects before opening business database',function(): void {
        $config=testConfig('production');$router=new \Hostider\Core\Http\Router();(new \Hostider\Commerce\Controller(fn()=>throw new RuntimeException('Database must not open'),$config))->routes($router);
        same(503,raises(\Hostider\Core\Http\HttpError::class,fn()=>$router->dispatch(new \Hostider\Core\Http\Request('POST','/api/v1/admin/commerce/prepare'),Id::uuid()))->status);
    });
    $exit=executeTests(failFast:true);
}finally{$server->stop();}exit($exit);
