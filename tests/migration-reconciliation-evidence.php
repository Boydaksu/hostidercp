<?php
declare(strict_types=1);
require_once __DIR__.'/harness.php';
require_once __DIR__.'/migration-reconciliation-fixture.php';

test('F6-C different target contents with identical summaries have different evidence fingerprints',function(): void{
    $f=reconciliationFixture();
    reconciliationMutate($f,'user','user-a',static function(array $p): array{$p['email']='first@synthetic.example.invalid';return $p;});
    $first=reconciliationResult($f);
    reconciliationMutate($f,'user','user-a',static function(array $p): array{$p['email']='second@synthetic.example.invalid';return $p;});
    $second=reconciliationResult($f);
    same('REVIEW_REQUIRED',$first['content']['status']);same('REVIEW_REQUIRED',$second['content']['status']);
    same($first['content']['source_target_counts'],$second['content']['source_target_counts']);
    same($first['content']['differences'],$second['content']['differences']);
    same(false,$first['fingerprint']===$second['fingerprint']);
    foreach(['first@','second@'] as $private){same(false,str_contains(json_encode([$first,$second],JSON_THROW_ON_ERROR),$private));}
});

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{$exit=executeTests();}finally{reconciliationCleanup();}exit($exit);
}
