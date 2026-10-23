<?php
declare(strict_types=1);
require __DIR__.'/database.php';require dirname(__DIR__).'/bootstrap/autoload.php';
$db=integrationDb();$db->execute('SET SESSION innodb_lock_wait_timeout=1');$input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
try{$db->transaction(fn()=>(new \Hostider\Core\Database\VersionFence($db))->advance($input['aggregate']));echo '200';}catch(PDOException $e){echo (string)($e->errorInfo[1]??0);}
