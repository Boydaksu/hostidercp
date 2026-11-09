<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/tests/harness.php';
require __DIR__.'/lib.php';
$all = json_decode(file_get_contents(__DIR__.'/suites.json'), true, 512, JSON_THROW_ON_ERROR);
test('CI docs profile remains lightweight with explicit security checks', function () use ($all): void {
    same('docs', ciPlan(['README.md', 'docs/adr/ADR-023-ci-exact-source.md'], $all)['profile']);
    same([], ciPlan(['docs/F6_PLAN.md'], $all)['suites']);
});
test('CI Support leaf includes native security and migration suites', function () use ($all): void {
    $plan = ciPlan(['src/Support/UI/Views.php', 'public/assets/support.js', 'docs/F6_PLAN.md'], $all);
    same('support', $plan['profile']);
    foreach (['identity-security', 'session-concurrency', 'support-http', 'support-e', 'support-migrations'] as $suite) {
        same(true, in_array('tests/'.$suite.'.php', $plan['suites'], true));
    }
});
test('CI shared unknown dependency rename migration and empty diffs are broad', function () use ($all): void {
    foreach ([[], ['src/Core/Queue/Worker.php'], ['src/Identity/Service.php'], ['src/Finance/Service.php'], ['unknown'], ['docs/x.php'], ['tools/browser/package-lock.json'], ['migrations/055_example.php'], ['src/Support/X.php', 'src/Core/X.php'], ["docs/x.md\ninjected"]] as $paths) {
        same('broad', ciPlan($paths, $all)['profile']); same($all, ciPlan($paths, $all)['suites']);
    }
});
test('CI FULL suite registry preserves every original workflow suite', function () use ($all): void {
    same(count($all), count(array_unique($all)));
    foreach ($all as $suite) { same(true, is_file(dirname(__DIR__, 2).'/'.$suite)); }
    preg_match_all('/^      - run: php (tests\/[^\r\n]+)/m', file_get_contents(dirname(__DIR__, 2).'/.github/workflows/f0.yml'), $matches);
    same($all, $matches[1]); same(true, count($all) >= 52);
});
test('CI source malformed unreachable and wrong checkout SHA fail closed', function (): void {
    foreach (['', 'main', str_repeat('a', 39), str_repeat('A', 40), str_repeat('0', 40), "e0d460d0aea05f496135d169ecbb64ce94659cc9\n"] as $sha) { raises(RuntimeException::class, fn() => ciSourceProof($sha)); }
    raises(RuntimeException::class, fn() => ciSourceProof('e0d460d0aea05f496135d169ecbb64ce94659cc9'));
});
test('CI exact clean SHA and tree pass while changed tracked source fails closed', function (): void {
    $previous = getcwd();
    $directory = dirname(__DIR__, 2).'/storage/ci-guard-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    try {
        chdir($directory);
        ciGit(['init', '--quiet']);
        file_put_contents('fixture.txt', "synthetic CI source\n");
        ciGit(['add', 'fixture.txt']);
        ciGit(['-c', 'user.name=CI synthetic test', '-c', 'user.email=ci@example.invalid', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'Synthetic source guard fixture']);
        $sha = ciGit(['rev-parse', 'HEAD']);
        $proof = ciSourceProof($sha);
        same($sha, $proof['checkout_sha']); same(ciGit(['rev-parse', 'HEAD^{tree}']), $proof['tree']);
        file_put_contents('fixture.txt', "changed before test start\n");
        raises(RuntimeException::class, fn() => ciSourceProof($sha));
    } finally { chdir($previous); }
});
exit(executeTests());
