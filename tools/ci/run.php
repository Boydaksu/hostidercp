<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
$all = json_decode(file_get_contents(__DIR__.'/suites.json'), true, 512, JSON_THROW_ON_ERROR);
$profile = $argv[1] ?? '';
if (!in_array($profile, ['support', 'broad'], true)) { throw new RuntimeException('Unknown runnable FAST profile'); }
$plan = $profile === 'support' ? ciPlan(['src/Support/Service.php'], $all) : ciPlan([], $all);
foreach ($plan['suites'] as $suite) {
    ciSourceProof(getenv('GITHUB_SHA') ?: '');
    if (!in_array($suite, $all, true) || !is_file($suite)) { throw new RuntimeException('Unregistered suite'); }
    echo 'FAST_SUITE '.$suite.PHP_EOL;
    $process = proc_open([PHP_BINARY, $suite], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) { exit(1); }
}
ciSourceProof(getenv('GITHUB_SHA') ?: '');
echo 'FAST only; FULL ACCEPTANCE still required for final candidate'.PHP_EOL;
