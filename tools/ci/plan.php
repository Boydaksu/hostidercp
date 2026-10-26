<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
$all = json_decode(file_get_contents(__DIR__.'/suites.json'), true, 512, JSON_THROW_ON_ERROR);
$before = getenv('CI_BEFORE') ?: '';
$after = getenv('GITHUB_SHA') ?: '';
try {
    if (!preg_match('/\A[a-f0-9]{40}\z/D', $before) || $before === str_repeat('0', 40)) { throw new RuntimeException('Unknown diff base'); }
    ciSourceProof($after);
    // Include both old and new paths for moves; never narrow a shared-file rename.
    $diff = ciGit(['diff', '--no-renames', '--name-only', '-z', $before, $after]);
    $paths = $diff === '' ? [] : explode("\0", rtrim($diff, "\0"));
    $plan = ciPlan($paths, $all);
} catch (Throwable) {
    $plan = ciPlan([], $all);
}
echo json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
$output = getenv('GITHUB_OUTPUT');
if ($output !== false && $output !== '') { file_put_contents($output, 'profile='.$plan['profile'].PHP_EOL, FILE_APPEND); }
$summary = getenv('GITHUB_STEP_SUMMARY');
if ($summary !== false && $summary !== '') {
    file_put_contents($summary, "## FAST scope\n\n".$plan['profile'].' — '.$plan['reason']."\n\n".count($plan['suites'])." PHP suites. This is not FULL ACCEPTANCE.\n", FILE_APPEND);
}
