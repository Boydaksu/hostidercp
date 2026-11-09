<?php
declare(strict_types=1);
namespace Hostider\Migration\Cutover;
use Hostider\Migration\Contract;
use Hostider\Migration\Reconciliation\Report;

/** Instance-local offline protocol only. It never transfers operational authority. */
final class Rehearsal
{
    public const STAGES = [
        'PREPARE', 'PIN_SNAPSHOT', 'REQUEST_SOURCE_STOP', 'CHECK_IN_FLIGHT',
        'FINAL_DELTA', 'CHECK_SINGLE_ACTOR', 'FINALIZE',
    ];
    private readonly string $key;
    private array $events = [];
    private ?string $pin = null;
    private ?string $failure = null;

    private function __construct(
        private readonly Preflight $preflight,
        private readonly string $id,
        private readonly int $expires,
        private readonly array $realGates,
    ) { $this->key = random_bytes(32); }

    public static function open(
        Preflight $preflight, string $rehearsalId, int $expires,
        string $purpose = 'OFFLINE_SYNTHETIC_REHEARSAL',
    ): self {
        self::flags(); Contract::sha($rehearsalId);
        Contract::require($purpose === 'OFFLINE_SYNTHETIC_REHEARSAL', 'P1_PURPOSE_DENIED');
        Contract::require($expires > time() && $expires <= time() + 300, 'P1_LEASE_DENIED');
        $c = $preflight->evaluate(Simulation::named('baseline'))->content;
        Contract::require($c['status'] === 'SYNTHETIC_PREFLIGHT_COMPLETE', 'P1_PREFLIGHT_BLOCKED');
        return new self($preflight, Contract::hash(['hostider.rehearsal.v1', $rehearsalId]), $expires, $c['real_gates']);
    }

    private static function flags(): void
    {
        Contract::require(getenv('APP_ENV') === 'test'
            && getenv('SYNTHETIC_REHEARSAL_ALLOWED') === '1', 'P1_ENVIRONMENT_DENIED');
    }
    private function guard(): void
    {
        Contract::require($this->failure === null, 'P1_SESSION_BLOCKED');
        self::flags();
        Contract::require($this->expires > time(), 'P1_LEASE_EXPIRED');
    }
    private function mac(array $payload): string
    {
        return hash_hmac('sha256', 'hostider.rehearsal-event.v1:'.Contract::hash($payload), $this->key);
    }
    private function previous(): string
    {
        return $this->events === [] ? Contract::hash(['hostider.rehearsal-start.v1', $this->id])
            : Contract::hash($this->events[count($this->events)-1]['payload']);
    }
    private function observe(Simulation $simulation): string
    {
        $this->guard();
        $r = $this->preflight->evaluate($simulation)->jsonSerialize(); $c = $r['content'];
        Contract::require($c['status'] === 'SYNTHETIC_PREFLIGHT_COMPLETE', 'P1_PREFLIGHT_BLOCKED');
        Contract::require($c['effects_allowed'] === false && $c['cutover_allowed'] === false
            && $c['real_gate_status'] === 'BLOCKED' && $c['real_gates'] === $this->realGates
            && $c['global_snapshot_atomicity'] === 'NOT_VERIFIED', 'P1_SCOPE_DENIED');
        Contract::require($this->pin === null || $this->pin === $r['fingerprint'], 'P1_EVIDENCE_PIN_CHANGED');
        return $r['fingerprint'];
    }
    private function fail(\Throwable $error): never
    {
        $code = preg_match('/^P1_[A-Z0-9_]+$/D', $error->getMessage()) === 1
            ? $error->getMessage() : 'P1_EVIDENCE_UNAVAILABLE';
        $this->failure ??= $code;
        throw new \RuntimeException($code);
    }

    /** Ephemeral sealed event, not a report, persisted credential or live command. */
    public function issue(string $stage, Simulation $simulation): array
    {
        try {
            $this->guard(); $ordinal = count($this->events)+1;
            Contract::require(($stage === (self::STAGES[$ordinal-1] ?? null)), 'P1_EVENT_ORDER');
            $pin = $this->observe($simulation);
            $payload = [
                'contract' => 'hostider.rehearsal-event.v1', 'classification' => 'SYNTHETIC',
                'rehearsal_sha256' => $this->id, 'ordinal' => $ordinal, 'stage' => $stage,
                'previous_sha256' => $this->previous(), 'preflight_sha256' => $pin,
                'expires_at' => $this->expires, 'simulation' => 'NAMED_FIXTURE_ONLY',
                'effects_allowed' => false,
            ];
            return ['payload' => $payload, 'mac' => $this->mac($payload)];
        } catch (\Throwable $error) { $this->fail($error); }
    }

    private function verify(array $event, int $ordinal, string $previous, ?string $pin): array
    {
        Contract::require(count($event) === 2 && isset($event['payload'], $event['mac'])
            && is_array($event['payload']) && is_string($event['mac'])
            && preg_match('/^[a-f0-9]{64}$/D', $event['mac']) === 1, 'P1_EVENT_FORMAT');
        $p = $event['payload'];
        Contract::fields($p, ['contract', 'classification', 'rehearsal_sha256', 'ordinal', 'stage',
            'previous_sha256', 'preflight_sha256', 'expires_at', 'simulation', 'effects_allowed']);
        foreach (['rehearsal_sha256', 'previous_sha256', 'preflight_sha256'] as $field) { Contract::sha($p[$field]); }
        Contract::require(is_int($p['ordinal']) && $p['ordinal'] > 0 && is_int($p['expires_at'])
            && is_string($p['stage']) && in_array($p['stage'], self::STAGES, true), 'P1_EVENT_FORMAT');
        Contract::require($p['contract'] === 'hostider.rehearsal-event.v1'
            && $p['classification'] === 'SYNTHETIC' && $p['effects_allowed'] === false
            && $p['simulation'] === 'NAMED_FIXTURE_ONLY', 'P1_EVENT_SCOPE');
        // Authenticate bounded scalar fields before incorporating any payload into the trace.
        Contract::require(hash_equals($this->mac($p), $event['mac']), 'P1_EVENT_AUTHENTICITY');
        Contract::require($p['rehearsal_sha256'] === $this->id, 'P1_REHEARSAL_MISMATCH');
        Contract::require($p['ordinal'] === $ordinal && $ordinal <= count(self::STAGES)
            && $p['stage'] === self::STAGES[$ordinal-1], 'P1_EVENT_ORDER');
        Contract::require($p['previous_sha256'] === $previous, 'P1_CHAIN_MISMATCH');
        Contract::require($p['expires_at'] === $this->expires, 'P1_EVENT_LEASE');
        Contract::require($pin === null || $p['preflight_sha256'] === $pin, 'P1_EVIDENCE_PIN_CHANGED');
        return $p;
    }

    public function accept(#[\SensitiveParameter] array $event, Simulation $simulation): void
    {
        try {
            $this->guard();
            $p = $this->verify($event, count($this->events)+1, $this->previous(), $this->pin);
            $observed = $this->observe($simulation);
            Contract::require($p['preflight_sha256'] === $observed, 'P1_EVIDENCE_PIN_CHANGED');
            $this->pin ??= $observed;
            $this->events[] = $event;
        } catch (\Throwable $error) { $this->fail($error); }
    }

    public function finish(): Report
    {
        $trace = [];
        try {
            $this->guard();
            $previous = Contract::hash(['hostider.rehearsal-start.v1', $this->id]);
            foreach ($this->events as $i => $event) {
                $p = $this->verify($event, $i+1, $previous, $this->pin);
                $hash = Contract::hash($p);
                $trace[] = ['payload' => $p, 'event_sha256' => $hash];
                $previous = $hash;
            }
            Contract::require(count($this->events) === count(self::STAGES), 'P1_MISSING_EVENTS');
            $this->observe(Simulation::named('baseline'));
        } catch (\Throwable $error) {
            $this->failure ??= preg_match('/^P1_[A-Z0-9_]+$/D', $error->getMessage()) === 1
                ? $error->getMessage() : 'P1_EVIDENCE_UNAVAILABLE';
        }
        return new Report([
            'classification' => 'SYNTHETIC', 'contract' => 'hostider.cutover-rehearsal.v1',
            'status' => $this->failure === null ? 'SYNTHETIC_REHEARSAL_COMPLETE' : 'BLOCKED',
            'verification_scope' => 'OFFLINE_INSTANCE_LOCAL_SEQUENCE_ONLY',
            'rehearsal_sha256' => $this->id,
            'events_expected' => count(self::STAGES), 'events_accepted' => count($this->events),
            'trace' => $trace, 'trace_sha256' => Contract::hash($trace),
            'reasons' => $this->failure === null ? [] : [$this->failure],
            'real_gates' => $this->realGates, 'real_gate_status' => 'BLOCKED',
            'cutover_allowed' => false, 'effects_allowed' => false,
            'native_writes' => 0, 'provider_operations_dispatched' => 0,
            'global_snapshot_atomicity' => 'NOT_VERIFIED',
            'durable_replay_protection' => 'NOT_GRANTED_INSTANCE_LOCAL_ONLY',
        ]);
    }
}
