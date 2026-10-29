<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
require __DIR__.'/migration-recovery-target-fixture.php';
use Hostider\Migration\Contract;
use Hostider\Migration\Cutover\{Preflight, Simulation};
use Hostider\Migration\Operations\RecoveryInspector;
use Hostider\Migration\Reconciliation\Report;
use Hostider\Migration\Synthetic\Fixture;

function preflightCase(Closure $work): void
{
    try {
        $f = targetFixture();
        putenv('SYNTHETIC_PREFLIGHT_ALLOWED=1');
        $f['context'] = targetContext($f);
        $f['recovery_reader'] = RecoveryInspector::open($f['context'], targetPolicy($f), $f['target_password']);
        $f['preflight'] = preflightOpen($f);
        $work($f);
    } finally {
        putenv('APP_ENV=test'); putenv('SYNTHETIC_IMPORT_ALLOWED=1');
        putenv('SYNTHETIC_BACKUP_ALLOWED=1'); putenv('SYNTHETIC_RECOVERY_ALLOWED=1');
        putenv('SYNTHETIC_PREFLIGHT_ALLOWED=1'); targetCleanup();
    }
}
function preflightOpen(array $f, ?array $checkpoint = null, ?int $expires = null, string $purpose = 'SYNTHETIC_PREFLIGHT_ONLY', ?Fixture $fixture = null): Preflight
{
    return Preflight::open($f['reader'], $f['recovery_reader'], $f['context'],
        $checkpoint ?? $f['loaded']['checkpoint'], $expires ?? time() + 120, $purpose, $fixture);
}
function preflightContent(array $f): array
{
    return $f['preflight']->evaluate(Simulation::named('baseline'))->content;
}
function preflightBlocked(array $content, string $reason): void
{
    same('BLOCKED', $content['status']); same([$reason], $content['reasons']);
    same(false, $content['cutover_allowed']); same(false, $content['effects_allowed']);
    same([], $content['evidence']); same('BLOCKED', $content['real_gate_status']);
}

test('F6-E-P0 actual source and restored readers complete synthetic checks with every real gate open', function(): void {
    preflightCase(function(array $f): void {
        $c = preflightContent($f);
        same('SYNTHETIC_PREFLIGHT_COMPLETE', $c['status']); same([], $c['reasons']);
        same($f['context']->verified['logical_sha256'], $c['evidence']['observed_logical_sha256']);
        same(Preflight::GATES, array_keys($c['real_gates']));
        foreach ($c['real_gates'] as $gate => $row) {
            same(in_array($gate, ['NATIVE_RUNTIME_RECOVERY', 'LIVE_CUTOVER_AUTHORIZATION'], true) ? 'BLOCKED' : 'OPEN', $row['status']);
            same('NOT_VERIFIED_REAL_ENVIRONMENT', $row['evidence']);
        }
        same('OPEN', $c['real_gates']['G-O03']['status']);
        same(false, $c['cutover_allowed']); same(false, $c['effects_allowed']);
        same(0, $c['provider_operations_dispatched']); same(0, $c['native_writes']);
        same('BLOCKED', $c['native_runtime_recovery']);
        same('NOT_VERIFIED', $c['global_snapshot_atomicity']);
        same(false, in_array($c['status'], ['PRODUCTION_READY', 'CUTOVER_GO'], true));
    });
});
test('F6-E-P0 repeated evaluation preserves source restored evidence and deterministic report', function(): void {
    preflightCase(function(array $f): void {
        $source = reconciliationState($f); $target = $f['recovery_reader']->inspect()->jsonSerialize()['fingerprint'];
        $a = $f['preflight']->evaluate(Simulation::named('baseline'))->jsonSerialize();
        same($a, $f['preflight']->evaluate(Simulation::named('baseline'))->jsonSerialize());
        same($source, reconciliationState($f));
        same($target, $f['recovery_reader']->inspect()->jsonSerialize()['fingerprint']);
    });
});
foreach ([
    'missing-gate' => 'F6E_MANDATORY_GATE_MISSING',
    'duplicate-actor' => 'F6E_ACTOR_CONFLICT',
    'missing-actor' => 'F6E_ACTOR_CONFLICT',
    'unknown-callback' => 'F6E_CALLBACK_UNRESOLVED',
    'inflight-provider' => 'F6E_PROVIDER_UNRESOLVED',
    'incomplete-delta' => 'F6E_DELTA_INCOMPLETE',
    'forged-gates' => 'F6E_UNTRUSTED_GATE_CLAIMS',
    'authority-escalation' => 'F6E_PURPOSE_DENIED',
] as $scenario => $reason) {
    test('F6-E-P0 simulation rejects '.$scenario.' without granting live authority', function() use ($scenario, $reason): void {
        preflightCase(function(array $f) use ($scenario, $reason): void {
            preflightBlocked($f['preflight']->evaluate(Simulation::named($scenario))->content, $reason);
        });
    });
}
test('F6-E-P0 arbitrary JSON flags and rehashed PASS Report cannot enter the trusted reader boundary', function(): void {
    preflightCase(function(array $f): void {
        $fake = new Report(['status'=>'PASS', 'classification'=>'SYNTHETIC', 'G-O03'=>true, 'all_gates'=>'CLOSED']);
        raises(TypeError::class, fn() => $f['preflight']->evaluate(json_decode('{"all_gates":true}', true)));
        raises(TypeError::class, fn() => $f['preflight']->evaluate($fake));
        raises(TypeError::class, fn() => Preflight::open($fake, $f['recovery_reader'], $f['context'], $f['loaded']['checkpoint'], time()+120));
        same('OPEN', preflightContent($f)['real_gates']['G-O03']['status']);
        raises(InvalidArgumentException::class, fn() => Simulation::named('CUTOVER_GO'));
    });
});
test('F6-E-P0 missing or forged checkpoint fields cannot be hidden by an APPLIED run', function(): void {
    preflightCase(function(array $f): void {
        $missing = $f['loaded']['checkpoint']; unset($missing['hash']);
        raises(InvalidArgumentException::class, fn() => preflightOpen($f, $missing));
        $bad = $f['loaded']['checkpoint']; $bad['hash'] = str_repeat('0', 64);
        $p = preflightOpen($f, $bad);
        preflightBlocked($p->evaluate(Simulation::named('baseline'))->content, 'F6E_RECONCILIATION_BLOCKED');
    });
});
test('F6-E-P0 changed source snapshot and tampered snapshot root fail existing reconciliation', function(): void {
    preflightCase(function(array $f): void {
        $p = preflightOpen($f, fixture: Fixture::named('new-snapshot'));
        preflightBlocked($p->evaluate(Simulation::named('baseline'))->content, 'F6E_RECONCILIATION_BLOCKED');
        $f['setup']->execute("UPDATE f6b_source_snapshots SET snapshot_hash=REPEAT('0',64)");
        preflightBlocked(preflightContent($f), 'F6E_RECONCILIATION_BLOCKED');
    });
});
foreach (['mapping', 'provenance', 'checkpoint', 'count', 'owner', 'privacy', 'finance', 'currency'] as $fault) {
    test('F6-E-P0 actual source '.$fault.' uncertainty cannot complete preflight', function() use ($fault): void {
        preflightCase(function(array $f) use ($fault): void {
            if ($fault === 'mapping') { $f['setup']->execute("UPDATE f6b_mappings SET evidence_hash=REPEAT('0',64)"); }
            if ($fault === 'provenance') { $f['setup']->execute("UPDATE f6b_provenance SET envelope_hash=REPEAT('0',64)"); }
            if ($fault === 'checkpoint') { $f['setup']->execute("UPDATE f6b_runs SET checkpoint_hash=REPEAT('0',64)"); }
            if ($fault === 'count') { $f['root']->execute('DELETE FROM `'.$f['schema'].'`.f6b_history ORDER BY id DESC LIMIT 1'); }
            if ($fault === 'owner') { $f['setup']->execute("UPDATE f6b_entities SET owner_key=REPEAT('0',64) WHERE entity_type='attachment'"); }
            if ($fault === 'privacy') {
                reconciliationMutate($f, 'message', 'private-a', static function(array $p): array {
                    $p['visibility']=['state'=>'KNOWN','value'=>'PUBLIC']; return $p;
                });
            }
            if (in_array($fault, ['finance','currency'], true)) {
                reconciliationMutate($f, 'wallet', 'wallet-a', static function(array $p) use ($fault): array {
                    $p['liability'][$fault === 'finance' ? 'minor' : 'currency'] = $fault === 'finance' ? '9999' : 'USD';
                    return $p;
                });
            }
            $before = reconciliationState($f);
            preflightBlocked(preflightContent($f), 'F6E_RECONCILIATION_BLOCKED');
            same($before, reconciliationState($f));
        });
    });
}
test('F6-E-P0 same-count restored target tamper cannot reuse a formerly complete evaluation', function(): void {
    preflightCase(function(array $f): void {
        same('SYNTHETIC_PREFLIGHT_COMPLETE', preflightContent($f)['status']);
        $before = $f['recovery_reader']->inspect()->content;
        $f['target']['setup']->execute("UPDATE f6b_entities SET payload_hash=REPEAT('0',64) ORDER BY target_id LIMIT 1");
        $after = $f['recovery_reader']->inspect()->content;
        same('DIFFERENCES', $after['immutable_evidence']);
        same(false, $before['observed_target_evidence_sha256'] === $after['observed_target_evidence_sha256']);
        foreach ($before['source_target_counts'] as $table=>$count) { same($count['target'], $after['source_target_counts'][$table]['target']); }
        preflightBlocked(preflightContent($f), 'F6E_RECOVERY_BLOCKED');
    });
});
test('F6-E-P0 separately valid readers with different logical roots cannot be joined', function(): void {
    preflightCase(function(array $f): void {
        $g = targetFixture(); $ctx = targetContext($g);
        $reader = RecoveryInspector::open($ctx, targetPolicy($g), $g['target_password']);
        same('PASS', $f['reader']->inspect(Fixture::named('baseline'))->content['status']);
        same('PASS', $reader->inspect()->content['status']);
        same(false, $ctx->verified['logical_sha256'] === $f['context']->verified['logical_sha256']);
        $p = Preflight::open($f['reader'], $reader, $ctx, $f['loaded']['checkpoint'], time()+120);
        preflightBlocked($p->evaluate(Simulation::named('baseline'))->content, 'F6E_LOGICAL_ROOT_MISMATCH');
    });
});
test('F6-E-P0 expired preflight lease blocks previously valid evidence without caching', function(): void {
    preflightCase(function(array $f): void {
        $p = preflightOpen($f, expires: time()+1);
        usleep(1100000);
        preflightBlocked($p->evaluate(Simulation::named('baseline'))->content, 'F6E_EVIDENCE_EXPIRED');
        raises(InvalidArgumentException::class, fn() => preflightOpen($f, expires: time()-1));
        raises(InvalidArgumentException::class, fn() => preflightOpen($f, expires: time()+301));
    });
});
test('F6-E-P0 expired target marker fails closed', function(): void {
    preflightCase(function(array $f): void {
        $f['target']['setup']->execute('UPDATE f6d_restore_marker SET expires_at=TIMESTAMPADD(SECOND,-1,UTC_TIMESTAMP(6))');
        preflightBlocked(preflightContent($f), 'F6E_RECOVERY_BLOCKED');
    });
});
test('F6-E-P0 test-only purpose and environment are rechecked at evaluation', function(): void {
    preflightCase(function(array $f): void {
        raises(InvalidArgumentException::class, fn() => preflightOpen($f, purpose: 'LIVE_CUTOVER'));
        foreach (['APP_ENV','SYNTHETIC_IMPORT_ALLOWED','SYNTHETIC_BACKUP_ALLOWED','SYNTHETIC_RECOVERY_ALLOWED','SYNTHETIC_PREFLIGHT_ALLOWED'] as $flag) {
            $old = getenv($flag); putenv($flag.'=denied');
            try {
                same('BLOCKED', preflightContent($f)['status']);
                raises(InvalidArgumentException::class, fn() => preflightOpen($f));
            } finally { putenv($flag.'='.$old); }
        }
    });
});
test('F6-E-P0 report excludes private bodies paths identities and cryptographic credentials', function(): void {
    preflightCase(function(array $f): void {
        $report = $f['preflight']->evaluate(Simulation::named('baseline'))->jsonSerialize();
        $text = json_encode($report, JSON_THROW_ON_ERROR);
        $outer = json_decode($f['capsule'], true, 16, JSON_THROW_ON_ERROR);
        foreach ([$f['target_password'], $f['secret'], $f['schema'], $f['target']['schema'],
            $f['binding']['account'], base64_encode($f['key']), base64_encode($f['recovery_secret']),
            $outer['signature'], $outer['ciphertext'], 'SYNTHETIC message private-a', 'synthetic/private-a.pdf',
            'user-a@synthetic.example.invalid', operationsPath($f['vault'])] as $secret) {
            same(false, str_contains($text, $secret));
        }
        same('SYNTHETIC_PREFLIGHT_COMPLETE', $report['content']['status']);
        $ids = $f['root']->execute('SELECT ID FROM information_schema.PROCESSLIST WHERE USER=?', [$f['reader_params'][1]])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) { $f['root']->execute('KILL CONNECTION '.(int)$id); }
        $errorReport = $f['preflight']->evaluate(Simulation::named('baseline'))->content;
        same('BLOCKED', $errorReport['status']); same([], $errorReport['evidence']);
        same(false, str_contains(json_encode($errorReport, JSON_THROW_ON_ERROR), $f['schema']));
    });
});
try { $exit=executeTests(); } finally { targetCleanup(); }
exit($exit);
