<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';
use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Migrator;
use Hostider\Identity\{IdentityService,Totp};
$db=integrationDb(); (new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
$key=base64_encode(random_bytes(32));$config=new Config('test','https://test.example.invalid',$key,getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');
$identity=new IdentityService($db,$config);
foreach (['identity_impersonations','identity_api_credentials','identity_devices','identity_sessions','identity_notifications','identity_security_events','identity_recovery','identity_mfa','identity_tokens','identity_resource_scopes','identity_grants','identity_assignments','identity_staff','identity_memberships','identity_customers','identity_credentials','identity_users'] as $t) {$db->execute('DELETE FROM '.$t);}
$db->execute('UPDATE identity_settings SET bootstrapped=FALSE');$db->execute('DELETE FROM rate_limits');
$password=bin2hex(random_bytes(20));$identity->bootstrap('admin@example.invalid','Synthetic administrator',$password);
$port=33320;$root=dirname(__DIR__);$env=array_merge(getenv(),['APP_ENV'=>'test','APP_URL'=>'https://test.example.invalid','APP_KEY'=>$key,'DB_DSN'=>getenv('TEST_DB_DSN'),'DB_USER'=>getenv('TEST_DB_USER')?:'root','DB_PASSWORD'=>getenv('TEST_DB_PASSWORD')?:'','PROVISIONING_ALLOWED'=>'false','PAYMENT_LIVE_ALLOWED'=>'false','EXTERNAL_MAIL_ALLOWED'=>'false']);
$pipes=[];$process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root.'/public',__DIR__.'/identity-router.php'],[0=>['pipe','r'],1=>['file',$root.'/storage/identity-http.stdout','a'],2=>['file',$root.'/storage/identity-http.stderr','a']],$pipes,$root,$env);fclose($pipes[0]);
$cookies=[];$csrf='';
function identityHttp(string $path,?array $data=null,?string $requestKey=null,bool $sendCsrf=true): array {
    global $port,$cookies,$csrf;
    $c=curl_init('http://127.0.0.1:'.$port.$path);$responseHeaders=[];$headers=[];
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_HEADERFUNCTION=>static function($c,string $line)use(&$responseHeaders):int{$responseHeaders[]=$line;return strlen($line);}]);
    if($cookies){curl_setopt($c,CURLOPT_COOKIE,implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($cookies),$cookies)));}
    if($data!==null){$headers=['Content-Type: application/json','Idempotency-Key: '.($requestKey??Id::uuid())];if($sendCsrf){$headers[]='X-CSRF-Token: '.$csrf;}curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode((object)$data,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>$headers]);}
    $raw=curl_exec($c);$code=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
    foreach($responseHeaders as $line){if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$line,$m)){$cookies[$m[1]]=$m[2];}}
    if($raw===false){return [0,null,$responseHeaders];}
    $body=json_decode($raw,true);if(isset($body['data']['csrf'])){$csrf=$body['data']['csrf'];}return[$code,$body,$responseHeaders,$raw];
}
try {
    for($i=0;$i<40;++$i){if(identityHttp('/api/v1/health')[0]===200){break;}usleep(100000);}
    test('F1-F real admin HTTP MFA enrollment login idempotency and protected views',function()use($password):void{
        same(200,identityHttp('/api/v1/admin/identity/bootstrap')[0]);
        same(403,identityHttp('/api/v1/admin/identity/login',['email'=>'admin@example.invalid','password'=>$password],sendCsrf:false)[0]);
        $login=identityHttp('/api/v1/admin/identity/login',['email'=>'admin@example.invalid','password'=>$password]);same(200,$login[0]);same('enroll',$login[1]['data']['stage']);
        same(403,identityHttp('/api/v1/admin/identity/users')[0]);
        $key=Id::uuid();$enroll=identityHttp('/api/v1/admin/identity/enroll',['password'=>$password],$key);same(200,$enroll[0]);
        same(409,identityHttp('/api/v1/admin/identity/enroll',['password'=>$password],$key)[0]);
        $secret=$enroll[1]['data']['secret'];$confirmed=identityHttp('/api/v1/admin/identity/enroll-confirm',['code'=>Totp::code($secret,intdiv(time(),30))]);same(200,$confirmed[0]);same(8,count($confirmed[1]['data']['recovery_codes']));
        same(200,identityHttp('/api/v1/admin/identity/step-up',['password'=>$password,'code'=>$confirmed[1]['data']['recovery_codes'][7]])[0]);
        same(200,identityHttp('/api/v1/admin/identity/users')[0]);
        same(422,identityHttp('/api/v1/admin/identity/customer-create',['name'=>'Bad','currency'=>'TRY','principal'=>['staff'=>true]])[0]);
        $operation=Id::uuid();$first=identityHttp('/api/v1/admin/identity/customer-create',['name'=>'Synthetic HTTP customer','currency'=>'TRY'],$operation);same(200,$first[0]);
        $second=identityHttp('/api/v1/admin/identity/customer-create',['currency'=>'TRY','name'=>'Synthetic HTTP customer'],$operation);same($first[1]['data']['id'],$second[1]['data']['id']);
        same(409,identityHttp('/api/v1/admin/identity/customer-create',['name'=>'Changed','currency'=>'TRY'],$operation)[0]);
        same([],identityHttp('/api/v1/admin/identity/customers')[1]['data']);
    });
    test('F1-F API bearer key output once and revocation and cookie-context isolation',function():void{
        $result=identityHttp('/api/v1/admin/identity/key-create',['scopes'=>['identity.users.view'],'seconds'=>3600]);same(200,$result[0]);$key=$result[1]['data'];
        $c=curl_init('http://127.0.0.1:33320/api/v1/integration/identity');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key['key']]]);curl_exec($c);same(200,curl_getinfo($c,CURLINFO_RESPONSE_CODE));curl_close($c);
        same(200,identityHttp('/api/v1/admin/identity/key-change',['id'=>$key['id']])[0]);
        same(401,identityHttp('/api/v1/client/identity/me')[0]);
        same(200,identityHttp('/api/v1/admin/identity/logout',[])[0]);same(401,identityHttp('/api/v1/admin/identity/me')[0]);
    });
    test('F1-F panel HTML and protected rendering policies are real separate entry points',function():void{
        foreach(['admin','client']as$ctx){$r=identityHttp('/'.$ctx);same(200,$r[0]);same(true,str_contains($r[3],'identity-context" content="'.$ctx));same(true,str_contains(strtolower(implode('',$r[2])),'content-security-policy:'));}
    });
    test('F1-F client signup verification account optimistic update and password logout over HTTP',function()use($db,$config):void{
        global $cookies,$csrf; $cookies=[];$csrf='';
        same(200,identityHttp('/api/v1/client/identity/bootstrap')[0]);$password=bin2hex(random_bytes(20));
        same(200,identityHttp('/api/v1/client/identity/register',['email'=>'http-client@example.invalid','name'=>'Synthetic HTTP client','password'=>$password])[0]);
        same(401,identityHttp('/api/v1/client/identity/login',['email'=>'http-client@example.invalid','password'=>$password])[0]);
        $row=$db->execute("SELECT n.* FROM identity_notifications n JOIN identity_users u ON u.id=n.user_id WHERE u.email='http-client@example.invalid' AND n.template='verify'")->fetch();
        $crypto=new \Hostider\Core\Security\Crypto(base64_decode($config->key,true));$token=json_decode($crypto->decrypt($row['body_encrypted'],'notification:'.$row['id']),true)['token'];
        same(200,identityHttp('/api/v1/client/identity/token-complete',['purpose'=>'verify','token'=>$token])[0]);
        same(200,identityHttp('/api/v1/client/identity/login',['email'=>'http-client@example.invalid','password'=>$password])[0]);
        same(403,identityHttp('/api/v1/client/identity/users')[0]);
        $account=identityHttp('/api/v1/client/identity/account')[1]['data'];
        $update=['customer'=>$account['id'],'name'=>'Updated synthetic account','mfa_required'=>false,'version'=>(int)$account['version']];
        same(403,identityHttp('/api/v1/client/identity/account-update',$update)[0]);
        same(200,identityHttp('/api/v1/client/identity/step-up',['password'=>$password])[0]);
        same(200,identityHttp('/api/v1/client/identity/account-update',$update)[0]);
        same(409,identityHttp('/api/v1/client/identity/account-update',$update)[0]);
        same('Updated synthetic account',identityHttp('/api/v1/client/identity/account')[1]['data']['name']);
        same(200,identityHttp('/api/v1/client/identity/password',['current'=>$password,'next'=>bin2hex(random_bytes(20))])[0]);
        same(401,identityHttp('/api/v1/client/identity/me')[0]);
    });
    $exitCode=executeTests();
} finally {proc_terminate($process);proc_close($process);}
exit($exitCode);
