<?php
declare(strict_types=1);
require '/workspace/repo/tests/support-b.php';
require_once '/workspace/repo/tools/ci/lib.php';
$name='SUP-006 native HTTP permission revoke session version and stale step-up cut access';
echo 'DIAGNOSTIC_SOURCE '.json_encode(ciSourceProof(getenv('GITHUB_SHA')),JSON_THROW_ON_ERROR).PHP_EOL;
try{($GLOBALS['tests'][$name])();echo 'DIAGNOSTIC_PASS '.$name.PHP_EOL;$exit=0;}
catch(Throwable $e){$safe='REDACTED';if(preg_match('/^Assertion failed: ([0-9]+) != ([0-9]+)$/D',$e->getMessage(),$m)){$safe='expected='.$m[1].' actual='.$m[2];}echo 'DIAGNOSTIC_FAIL '.$name.' '.$safe.PHP_EOL;foreach($e->getTrace() as $t){if(isset($t['file'],$t['line'])&&str_contains($t['file'],'/tests/')){echo 'DIAGNOSTIC_LOCATION '.basename($t['file']).':'.$t['line'].PHP_EOL;break;}}$exit=1;}
echo 'DIAGNOSTIC_SOURCE '.json_encode(ciSourceProof(getenv('GITHUB_SHA')),JSON_THROW_ON_ERROR).PHP_EOL;
exit($exit);
