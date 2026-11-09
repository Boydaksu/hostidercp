<?php
declare(strict_types=1);

// CI tooling only; never loaded by the application.
function ciGit(array $arguments): string
{
    $pipes = [];
    $process = proc_open(array_merge(['git'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start git'); }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException('Git verification failed: '.trim($stderr)); }
    return rtrim($stdout, "\r\n");
}

function ciSourceProof(string $requested): array
{
    if (preg_match('/\A[a-f0-9]{40}\z/D', $requested) !== 1) { throw new RuntimeException('Expected exact lowercase 40-character application commit SHA'); }
    $commit = ciGit(['rev-parse', '--verify', $requested.'^{commit}']);
    $head = ciGit(['rev-parse', 'HEAD']);
    $tree = ciGit(['rev-parse', $requested.'^{tree}']);
    if ($commit !== $requested || $head !== $requested || ciGit(['rev-parse', 'HEAD^{tree}']) !== $tree) {
        throw new RuntimeException('Requested application commit/tree differs from checkout');
    }
    if (ciGit(['status', '--porcelain', '--untracked-files=no']) !== '') { throw new RuntimeException('Tracked source changed before/after verification'); }
    return ['application_sha' => $requested, 'checkout_sha' => $head, 'tree' => $tree];
}

function ciPlan(array $paths, array $all): array
{
    // Only explicit, reviewed leaf boundaries can narrow. Every other path is broad.
    if ($paths === []) { return ['profile' => 'broad', 'suites' => $all, 'reason' => 'empty/unknown diff']; }
    $onlyDocs = true; $onlySupport = true;
    foreach ($paths as $path) {
        if (!is_string($path) || $path === '' || str_contains($path, "\n") || str_contains($path, "\r")) {
            return ['profile' => 'broad', 'suites' => $all, 'reason' => 'unrecognized path'];
        }
        $doc = $path === 'README.md' || preg_match('~\Adocs/[^\x00]+\.(?:md|json|txt)\z~D', $path) === 1;
        $onlyDocs = $onlyDocs && $doc;
        $support = str_starts_with($path, 'src/Support/') || $path === 'public/assets/support.js'
            || preg_match('~\Atests/support-[a-z0-9-]+\.(?:php|cjs)\z~D', $path) === 1;
        $onlySupport = $onlySupport && ($support || $doc);
    }
    if ($onlyDocs) { return ['profile' => 'docs', 'suites' => [], 'reason' => 'documentation-only; security/text checks remain active']; }
    if ($onlySupport) {
        $names = ['run', 'hardening-unit', 'session', 'session-concurrency', 'session-http', 'integration', 'identity', 'identity-security', 'identity-http', 'http', 'support-migrations', 'support-http', 'support-e'];
        $selected = array_values(array_filter($all, static fn(string $suite): bool => in_array(basename($suite, '.php'), $names, true)));
        return ['profile' => 'support', 'suites' => $selected, 'reason' => 'explicit Support leaf plus native Identity/HTTP/queue/migration safety'];
    }
    return ['profile' => 'broad', 'suites' => $all, 'reason' => 'shared, dependency, workflow, migration, non-Support or unknown change'];
}
