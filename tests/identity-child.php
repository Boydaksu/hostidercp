<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php'; require __DIR__.'/database.php';
use Hostider\Core\Config;
use Hostider\Core\Http\HttpError;
use Hostider\Identity\IdentityService;
$f=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); $db=integrationDb();
$c=new Config('test','https://test.example.invalid',$f['key'],getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');
$identity=new IdentityService($db,$c);
try {
    if ($argv[2]==='mutation') {
        $identity->locked($f['reference'],'admin',function(array $u,array $s) use($identity,$f,$db): void {
            $identity->authorize($u,$s,'identity.users.manage',privileged:true);
            file_put_contents($f['directory'].'/locked','1');
            $deadline=hrtime(true)+5_000_000_000;
            while(!file_exists($f['directory'].'/release')) { if(hrtime(true)>$deadline){throw new RuntimeException('Barrier timeout');} usleep(10000); }
            $db->execute("UPDATE identity_users SET display_name='mutation-committed' WHERE id=?",[$u['id']]);
        });
    } else {
        file_put_contents($f['directory'].'/revoker-started','1');
        $db->transaction(function() use($identity,$f): void { $identity->repository->user($f['user'],true); $identity->invalidate($f['user']); });
    }
    echo 'OK';
} catch(HttpError $e) { echo (string)$e->status; }
