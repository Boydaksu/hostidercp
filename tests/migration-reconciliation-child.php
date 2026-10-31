<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('zend.exception_ignore_args','1');
use Hostider\Migration\Synthetic\{Sandbox,Fixture};
use Hostider\Migration\Reconciliation\Inspector;
try{$p=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$sandbox=Sandbox::open(...$p['authority']);echo json_encode(Inspector::open($sandbox,...$p['reader'])->inspect(Fixture::named('baseline')),JSON_THROW_ON_ERROR).PHP_EOL;}
catch(Throwable){echo '{"error":"F6C_CHILD_DENIED"}'.PHP_EOL;exit(1);}
