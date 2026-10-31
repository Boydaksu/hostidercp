<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('zend.exception_ignore_args','1');
use Hostider\Migration\Synthetic\Sandbox;
use Hostider\Migration\Operations\{Snapshot,Vault,Destination};
use Hostider\Core\Security\Crypto;
try{$p=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$source=Snapshot::open(Sandbox::open(...$p['authority']),...[...$p['reader'],Vault::open(...$p['samples'])]);$vault=Vault::open(...$p['vault']);$crypto=new Crypto(base64_decode($p['key'],true),'synthetic-v1');
    if($p['mode']==='RESTORE'){$t=$p['target'];$w=\Hostider\Core\Database\Database::connect(new \Hostider\Core\Config('test','https://synthetic.example.invalid',base64_encode(random_bytes(32)),'mysql:host=127.0.0.1;port='.$t['params'][0].';dbname='.$t['params'][1].';charset=utf8mb4',$t['witness_user'],$t['witness_password']));$result=$vault->restore($p['backup_id'],$crypto,'synthetic-v1',Destination::open(...[...$t['params'],Vault::open(...$t['vault']),$w]),$p['fault']);}
    else{$result=$vault->backup($source,$crypto,'synthetic-v1',$p['fault']);}echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){echo '{"state":"FAILED","reason":"F6D_CHILD_DENIED"}'.PHP_EOL;exit(1);}
