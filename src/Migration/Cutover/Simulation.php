<?php
declare(strict_types=1);
namespace Hostider\Migration\Cutover;
use Hostider\Migration\Contract;

/** Named synthetic observations only. Never operational evidence or gate authority. */
final readonly class Simulation
{
    private function __construct(private array $facts) {}

    public static function named(string $name): self
    {
        Contract::require(in_array($name, [
            'baseline', 'missing-gate', 'duplicate-actor', 'missing-actor',
            'unknown-callback', 'inflight-provider', 'incomplete-delta',
            'forged-gates', 'authority-escalation',
        ], true), 'F6E_SIMULATION_DENIED');
        $facts = [
            'inventory' => Preflight::GATES,
            'provisioning_actors' => 1, 'billing_actors' => 1,
            'callback' => 'SETTLED', 'provider' => 'RECONCILED', 'delta' => 'COMPLETE',
            'real_gate_claims' => [], 'purpose' => 'SYNTHETIC_PREFLIGHT_ONLY',
        ];
        if ($name === 'missing-gate') { array_pop($facts['inventory']); }
        if ($name === 'duplicate-actor') { $facts['provisioning_actors'] = 2; $facts['billing_actors'] = 2; }
        if ($name === 'missing-actor') { $facts['provisioning_actors'] = 0; $facts['billing_actors'] = 0; }
        if ($name === 'unknown-callback') { $facts['callback'] = 'UNKNOWN'; }
        if ($name === 'inflight-provider') { $facts['provider'] = 'IN_FLIGHT'; }
        if ($name === 'incomplete-delta') { $facts['delta'] = 'INCOMPLETE'; }
        if ($name === 'forged-gates') { $facts['real_gate_claims'] = array_fill_keys(Preflight::GATES, 'CLOSED'); }
        if ($name === 'authority-escalation') { $facts['purpose'] = 'LIVE_CUTOVER'; }
        return new self($facts);
    }

    public function observations(): array { return $this->facts; }
}
