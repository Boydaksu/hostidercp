<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\{Config,Id,SafeLogger};
use Hostider\Core\Database\{Database,Migrator};
use Hostider\Identity\IdentityService;
ini_set('display_errors','0'); ini_set('log_errors','0'); ini_set('zend.exception_ignore_args','1');
try {
    if (PHP_SAPI!=='cli' || ($argv[1] ?? '')!=='bootstrap') { fwrite(STDERR,"Usage: php bin/identity.php bootstrap < private-input.json\n"); exit(2); }
    $config=Config::fromEnvironment(); $db=Database::connect($config); (new Migrator($db,dirname(__DIR__).'/migrations'))->assertReady();
    $raw=stream_get_contents(STDIN,8193); if (strlen($raw)>8192) { throw new InvalidArgumentException('Input limit'); }
    $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    \Hostider\Core\Validation::fields($input,['email','name','password'],['email','name','password']);
    foreach($input as $v) { if(!is_string($v)){throw new InvalidArgumentException('Input types');} }
    $id=(new IdentityService($db,$config))->bootstrap($input['email'],$input['name'],$input['password']);
    sodium_memzero($raw); unset($input); echo json_encode(['admin_id'=>$id,'mfa'=>'Enrollment required at first login'],JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) { (new SafeLogger(static fn(string $line)=>fwrite(STDERR,$line.PHP_EOL)))->failure(Id::uuid(),$e); exit(1); }
