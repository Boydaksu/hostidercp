<?php
declare(strict_types=1);
namespace Hostider\Migration\Cutover;
use Hostider\Migration\Contract;
use Hostider\Migration\Synthetic\Fixture;
use Hostider\Migration\Operations\{RecoveryAuthorization, RecoveryContext, RecoveryInspector};
use Hostider\Migration\Reconciliation\{Inspector, Report};

/** Read-only composition of existing concrete inspectors. No live gate enrollment. */
final readonly class Preflight
{
    public const GATES = [
        'G-O03', 'G-O01', 'G-O02', 'G-O04',
        'G-V01', 'G-V02', 'G-V03', 'G-V04', 'G-P01', 'G-P02',
        'G-C01', 'G-R01', 'G-D01', 'G-X01', 'G-X02', 'G-T01',
        'FINANCE_RECONCILIATION', 'SUP-010', 'OPS-002', 'OPS-003', 'OPS-004',
        'SINGLE_ACTIVE_ACTOR', 'CALLBACK_ROUTING', 'FINAL_DELTA',
        'NATIVE_RUNTIME_RECOVERY', 'ORIGINAL_ARCHIVE_BYTES', 'ISSUER_RECOVERY',
        'OFFSITE_KEY_CUSTODY', 'PITR_RPO_RTO', 'LIVE_CUTOVER_AUTHORIZATION',
    ];

    private function __construct(
        private Inspector $reconciliation,
        private RecoveryInspector $recovery,
        private RecoveryContext $reference,
        private array $checkpoint,
        private int $expires,
        private Fixture $fixture,
    ) {}

    public static function open(
        Inspector $reconciliation,
        RecoveryInspector $recovery,
        RecoveryContext $reference,
        array $checkpoint,
        int $expires,
        string $purpose = 'SYNTHETIC_PREFLIGHT_ONLY',
        ?Fixture $fixture = null,
    ): self {
        self::flags();
        Contract::require($purpose === 'SYNTHETIC_PREFLIGHT_ONLY', 'F6E_PURPOSE_DENIED');
        Contract::require($expires > time() && $expires <= time() + 300, 'F6E_LEASE_DENIED');
        Contract::fields($checkpoint, ['run_id', 'target_snapshot', 'cursor', 'version', 'hash']);
        foreach (['run_id', 'target_snapshot', 'hash'] as $field) { Contract::sha($checkpoint[$field]); }
        Contract::require(is_int($checkpoint['cursor']) && $checkpoint['cursor'] > 0
            && is_int($checkpoint['version']) && $checkpoint['version'] > 0, 'F6E_CHECKPOINT_INPUT');
        $reference->fence();
        return new self($reconciliation, $recovery, $reference, $checkpoint, $expires, $fixture ?? Fixture::named('baseline'));
    }

    private static function flags(): void
    {
        RecoveryAuthorization::flags();
        Contract::require(getenv('SYNTHETIC_PREFLIGHT_ALLOWED') === '1', 'F6E_ENVIRONMENT_DENIED');
    }

    private function fence(): void
    {
        self::flags();
        Contract::require($this->expires > time(), 'F6E_EVIDENCE_EXPIRED');
        $this->reference->fence();
    }

    public function evaluate(Simulation $simulation): Report
    {
        $reasons = []; $evidence = [];
        try {
            $this->fence();
            $facts = $simulation->observations();
            Contract::require($facts['purpose'] === 'SYNTHETIC_PREFLIGHT_ONLY', 'F6E_PURPOSE_DENIED');
            Contract::require($facts['real_gate_claims'] === [], 'F6E_UNTRUSTED_GATE_CLAIMS');
            Contract::require($facts['inventory'] === self::GATES, 'F6E_MANDATORY_GATE_MISSING');
            Contract::require($facts['provisioning_actors'] === 1 && $facts['billing_actors'] === 1, 'F6E_ACTOR_CONFLICT');
            Contract::require($facts['callback'] === 'SETTLED', 'F6E_CALLBACK_UNRESOLVED');
            Contract::require($facts['provider'] === 'RECONCILED', 'F6E_PROVIDER_UNRESOLVED');
            Contract::require($facts['delta'] === 'COMPLETE', 'F6E_DELTA_INCOMPLETE');

            // Fresh concrete readers, not caller-provided JSON/Report or rehashed PASS flags.
            $before = $this->reconciliation->inspect($this->fixture, $this->checkpoint)->jsonSerialize();
            $restored = $this->recovery->inspect()->jsonSerialize();
            $after = $this->reconciliation->inspect($this->fixture, $this->checkpoint)->jsonSerialize();
            $this->fence();
            Contract::require($before['fingerprint'] === $after['fingerprint'], 'F6E_SOURCE_CHANGED_DURING_READ');
            $source = $after['content']; $target = $restored['content']; $verified = $this->reference->verified;
            Contract::require($source['status'] === 'PASS' && ($source['integrity'] ?? null) === 'MATCHED'
                && ($source['finance']['equal'] ?? false) === true, 'F6E_RECONCILIATION_BLOCKED');
            Contract::require($target['status'] === 'PASS' && ($target['immutable_evidence'] ?? null) === 'MATCHED'
                && ($target['finance']['equal'] ?? false) === true, 'F6E_RECOVERY_BLOCKED');
            Contract::require($source['classification'] === 'SYNTHETIC' && $target['classification'] === 'SYNTHETIC'
                && $source['contract'] === 'hostider.reconciliation.v1'
                && $target['contract'] === 'hostider.readonly-recovery.v1'
                && $target['verification_scope'] === 'ACTUAL_RESTORED_DB_READ_ONLY'
                && $source['effects_allowed'] === false && $target['effects_allowed'] === false, 'F6E_SCOPE_DENIED');
            Contract::require($source['observed_target_evidence_sha256'] === $verified['logical_sha256']
                && $target['observed_target_evidence_sha256'] === $verified['logical_sha256'], 'F6E_LOGICAL_ROOT_MISMATCH');
            Contract::require(Contract::hash($source['source_snapshot_roots']) === $verified['source_roots_sha256']
                && $source['target_snapshot'] === $verified['original_target_snapshot']
                && $target['source_roots_sha256'] === $verified['source_roots_sha256']
                && $target['original_target_snapshot'] === $verified['original_target_snapshot']
                && $target['backup_id'] === $verified['backup_id'], 'F6E_SOURCE_RECOVERY_BINDING');
            $evidence = [
                'reconciliation_sha256' => $after['fingerprint'],
                'recovery_sha256' => $restored['fingerprint'],
                'observed_logical_sha256' => $verified['logical_sha256'],
            ];
        } catch (\Throwable $error) {
            // Never serialize nested exceptions, SQL, identifiers, evidence bodies or credentials.
            $reasons[] = preg_match('/^F6E_[A-Z0-9_]+$/D', $error->getMessage()) === 1
                ? $error->getMessage() : 'F6E_EVIDENCE_UNAVAILABLE';
        }
        $gates = [];
        foreach (self::GATES as $gate) {
            $gates[$gate] = [
                'status' => in_array($gate, ['NATIVE_RUNTIME_RECOVERY', 'LIVE_CUTOVER_AUTHORIZATION'], true) ? 'BLOCKED' : 'OPEN',
                'evidence' => 'NOT_VERIFIED_REAL_ENVIRONMENT',
            ];
        }
        return new Report([
            'classification' => 'SYNTHETIC', 'contract' => 'hostider.cutover-preflight.v1',
            'status' => $reasons === [] ? 'SYNTHETIC_PREFLIGHT_COMPLETE' : 'BLOCKED',
            'verification_scope' => 'OFFLINE_SYNTHETIC_POINT_IN_TIME_ONLY',
            'reasons' => $reasons, 'evidence' => $evidence, 'real_gates' => $gates,
            'real_gate_status' => 'BLOCKED', 'effects_allowed' => false, 'cutover_allowed' => false,
            'native_runtime_recovery' => 'BLOCKED', 'provider_operations_dispatched' => 0,
            'native_writes' => 0, 'executable_object_inventory' => 'NOT_VERIFIED_SELECT_ONLY',
            'global_snapshot_atomicity' => 'NOT_VERIFIED',
        ]);
    }
}
