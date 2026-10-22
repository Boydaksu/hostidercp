<?php
declare(strict_types=1);
// Synthetic demo only. Never point this command at a customer database.
require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\{Config,Id,SafeLogger};
use Hostider\Core\Database\{Database,Migrator};
use Hostider\Core\Security\Crypto;
use Hostider\Identity\IdentityService;
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
$handle=null;$file=dirname(__DIR__).'/storage/identity-demo.json';
try {
    if(PHP_SAPI!=='cli'){throw new RuntimeException('CLI only');}
    $config=Config::fromEnvironment();
    if($config->environment!=='test'||!preg_match('/dbname=hostider_f0_test_demo(?:;|$)/',$config->dsn)) {throw new RuntimeException('Dedicated synthetic database required');}
    $db=Database::connect($config);(new Migrator($db,dirname(__DIR__).'/migrations'))->assertReady();
    if($db->execute('SELECT 1 FROM identity_users LIMIT 1')->fetchColumn()){throw new RuntimeException('Empty identity database required');}
    $old=umask(0077);$handle=fopen($file,'x');umask($old);
    if($handle===false){throw new RuntimeException('Exclusive private output unavailable');}
    $identity=new IdentityService($db,$config);$admin=bin2hex(random_bytes(20));$client=bin2hex(random_bytes(20));
    $db->transaction(function()use($db,$identity,$config,$admin,$client,$handle):void{
        $identity->bootstrap('demo-admin@example.invalid','Synthetic demo administrator',$admin);
        $identity->register('demo-client@example.invalid','Synthetic demo customer',$client,'isolated-demo');
        $row=$db->execute("SELECT * FROM identity_notifications WHERE template='verify'")->fetch();
        $crypto=new Crypto(base64_decode($config->key,true));$token=json_decode($crypto->decrypt($row['body_encrypted'],'notification:'.$row['id']),true,32,JSON_THROW_ON_ERROR)['token'];
        $identity->completeToken($token,'verify');
        $json=json_encode(['synthetic_only'=>true,'admin'=>['email'=>'demo-admin@example.invalid','password'=>$admin,'mfa'=>'Enrollment required at first login'],'client'=>['email'=>'demo-client@example.invalid','password'=>$client]],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
        if(fwrite($handle,$json)!==strlen($json)||!fflush($handle)){throw new RuntimeException('Private output failed');}
    });
    fclose($handle);$handle=null;echo "Synthetic demo prepared. Credentials are in storage/identity-demo.json; restrict file access and delete after use.\n";
}catch(Throwable $e){if(is_resource($handle)){fclose($handle);unlink($file);}(new SafeLogger(static fn(string $line)=>fwrite(STDERR,$line.PHP_EOL)))->failure(Id::uuid(),$e);exit(1);}
