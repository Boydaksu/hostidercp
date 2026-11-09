<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
try {
    $proof = ciSourceProof(getenv('APPLICATION_SHA') ?: '');
    $proof['workflow_sha'] = getenv('WORKFLOW_SHA') ?: '';
    $proof['run_id'] = getenv('GITHUB_RUN_ID') ?: 'local';
    $proof['php'] = PHP_VERSION;
    $json = json_encode($proof, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    echo 'SOURCE_PROOF '.$json.PHP_EOL;
    if (!is_dir('storage/ci-source')) { mkdir('storage/ci-source', 0700, true); }
    file_put_contents('storage/ci-source/proof.json', $json.PHP_EOL);
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage().PHP_EOL); exit(1); }
