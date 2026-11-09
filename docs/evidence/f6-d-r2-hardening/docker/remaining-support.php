<?php
declare(strict_types=1);
require '/workspace/repo/tests/support-b.php';
require '/workspace/repo/tests/support-b-post-audit.php';
require '/workspace/repo/tests/support-c.php';
require '/workspace/repo/tests/support-d.php';
require '/workspace/repo/tools/ci/lib.php';
ciSourceProof(getenv('GITHUB_SHA'));
$names=array_keys($GLOBALS['tests']);
$start=array_search('F5-B additive 049 to 050 upgrade replay and old checksums are preserved',$names,true);
if($start===false){throw new RuntimeException('Continuation boundary missing');}
$GLOBALS['tests']=array_slice($GLOBALS['tests'],$start,null,true);
echo 'LOCAL_CONTINUATION unmodified closures from failed upgrade boundary; not full support-http PASS'.PHP_EOL;
$result=executeTests();
ciSourceProof(getenv('GITHUB_SHA'));
exit($result);
