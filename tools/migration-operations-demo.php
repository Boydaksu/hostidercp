<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';require dirname(__DIR__).'/tests/migration-operations-fixture.php';
use Hostider\Migration\Synthetic\Sandbox;
$start=hrtime(true);
try{$f=operationsFixture();$backup=$f['vault']->backup($f['source'],$f['crypto'],'synthetic-v1');$target=operationsTarget($f);$restore=$f['vault']->restore($backup['backup_id'],$f['crypto'],'synthetic-v1',$target['target']);$denied=false;try{Sandbox::open(...$target['params']);}catch(InvalidArgumentException){$denied=true;}
    echo json_encode(['classification'=>'SYNTHETIC','backup'=>$backup,'restore'=>$restore,'actual_native_sandbox_activation_denied'=>$denied,'elapsed_ms'=>intdiv(hrtime(true)-$start,1000000),'production_rpo_rto'=>'NOT_VERIFIED'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{operationsCleanup();}
