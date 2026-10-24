<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Security\{AccessGrant,Principal};
use Hostider\Domains\DomainRunner;
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
try {
    $config=Config::fromEnvironment();
    if ($config->environment!=='test' || !str_contains($config->dsn,'dbname=hostider_f0_test')) { throw new RuntimeException('Isolated tests only'); }
    $db=Database::connect($config);$db->execute('SET SESSION innodb_lock_wait_timeout=2');
    if (($argv[1]??'')==='renew-request') {
        $service=$argv[2];$customer=$argv[3];$actor=new Principal('concurrent-renewal',['services.renew'],customerId:$customer,expiresAt:time()+3600,verifiedAt:time(),grants:[new AccessGrant('services.renew',[$customer],[$service])]);
        $renewal=(new \Hostider\Services\Renewals($db,$config))->request($service,$customer,$actor,Id::uuid(),new DateTimeImmutable('now',new DateTimeZone('UTC')));
        echo json_encode(['renewal'=>$renewal['id']],JSON_THROW_ON_ERROR);
    } else { echo json_encode(['worked'=>(new DomainRunner($db,$config))->once(new Principal('concurrent-domain-worker',[]),$argv[1]??null)],JSON_THROW_ON_ERROR); }
} catch (Throwable) { echo '{"error":"CHILD_FAILED"}'; exit(1); }
