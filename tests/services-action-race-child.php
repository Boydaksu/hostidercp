<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';require __DIR__.'/database.php';
use Hostider\Core\{Config,Id};use Hostider\Core\Security\{Principal,AccessGrant};
try{
    $f=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);$db=integrationDb();$config=new Config('test','https://test.example.invalid',$f['key'],getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');$db->execute('SET timestamp='.(int)$f['timestamp']);$permissions=['services.renew','services.terminate'];$grants=[];foreach($permissions as $permission){$grants[]=new AccessGrant($permission,[$f['customer']],[$f['service']]);}$actor=new Principal('service-race-staff',$permissions,staff:true,mfa:true,expiresAt:time()+3600,verifiedAt:time(),grants:$grants,administrative:true);
    try{if($argv[2]==='renew'){(new \Hostider\Services\Renewals($db,$config))->request($f['service'],$f['customer'],$actor,Id::uuid(),new DateTimeImmutable('now',new DateTimeZone('UTC')));}else{(new \Hostider\Services\Actions($db,$config))->request($f['service'],$f['customer'],'TERMINATE',$actor,Id::uuid(),true);}echo json_encode(['status'=>200],JSON_THROW_ON_ERROR);}catch(\Hostider\Core\Http\HttpError $e){echo json_encode(['status'=>$e->status],JSON_THROW_ON_ERROR);}
}catch(Throwable){fwrite(STDERR,'LIFECYCLE_RACE_CHILD_FAILED');exit(1);}
