<?php
declare(strict_types=1);
namespace Hostider\Migration\Synthetic;
use Hostider\Migration\{Contract, SourceEnvelope};

/** No caller-controlled rows, paths, URLs, SQL or credentials. */
final readonly class Fixture
{
    public const BASE_SHA256 = '91b125b1016dc3947ab4cd886cbfc13a7c6f09a2a0f1b19989e704601020caf7';
    private function __construct(private array $rows) {}

    public static function named(string $name): self
    {
        Contract::require(in_array($name, ['baseline','reversed','changed-content','new-snapshot','other-instance','rival-resource','rival-payment','rival-email','cross-owner','unknown-payment','unknown-provider','currency-mismatch','allocation-limit','refund-limit','refund-review','unknown-fx','private-conflict','private-unknown','missing-file','corrupt-file','legacy-credential','staff-admin','duplicate-source'], true), 'F6B_FIXTURE_DENIED');
        $bytes = file_get_contents(dirname(__DIR__, 3).'/tests/fixtures/migration-synthetic-v1.json');
        Contract::require($bytes !== false && hash_equals(self::BASE_SHA256, hash('sha256', $bytes)), 'F6B_FIXTURE_INTEGRITY');
        $rows = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR)['entities'];
        foreach ($rows as &$r) {
            $id = $r['source_id']; $p = &$r['payload'];
            if ($name === 'changed-content' && $id === 'customer-a') { $p['status']['value'] = 'INACTIVE'; }
            if ($name === 'new-snapshot') { $r['snapshot'] = 'synthetic-snapshot-2'; }
            if (in_array($name, ['other-instance','rival-resource','rival-payment','rival-email'], true)) {
                $r['instance'] = 'synthetic-whmcs-b';
                if (isset($p['email']) && $name !== 'rival-email') { $p['email'] = 'other-'.$p['email']; }
                if (isset($p['external_ref']) && $name !== 'rival-payment') { $p['external_ref'] = 'other-'.$p['external_ref']; }
                if (isset($p['account']) && $name !== 'rival-resource') { $p['account'] = 'other-'.$p['account']; }
            }
            if ($name === 'cross-owner' && $id === 'resource-cpanel') { $p['customer'] = 'customer-b'; }
            if ($name === 'unknown-payment' && $id === 'payment-a') { $p['state'] = ['state'=>'UNKNOWN','value'=>null]; }
            if ($name === 'unknown-provider' && $id === 'resource-voyar') { $p['status'] = ['state'=>'UNKNOWN','value'=>null]; }
            if ($name === 'currency-mismatch' && $id === 'allocation-a') { $p['amount']['currency'] = 'USD'; }
            if ($name === 'allocation-limit' && $id === 'allocation-a') { $p['amount']['minor'] = '6001'; }
            if ($name === 'unknown-fx' && $id === 'wallet-a') { $p['historical_fx'] = ['state'=>'UNVERIFIABLE','value'=>null]; }
            if ($name === 'private-conflict' && $id === 'public-a') { $p['visibility']['value'] = 'PRIVATE'; }
            if ($name === 'private-unknown' && $id === 'private-a') { $p['visibility'] = ['state'=>'UNKNOWN','value'=>null]; }
            if ($name === 'missing-file' && $id === 'attachment-public-a') { $p['file_state']['value'] = 'MISSING'; }
            if ($name === 'corrupt-file' && $id === 'attachment-public-a') { $p['file_state']['value'] = 'CORRUPT'; }
            if ($name === 'legacy-credential' && $id === 'user-a') { $p['session'] = 'SYNTHETIC_FORBIDDEN_FIELD'; }
            if ($name === 'staff-admin' && $id === 'staff-a') { $p['target_admin'] = true; }
            $r['content_sha256'] = Contract::hash($p); unset($p);
        }
        unset($r);
        if (in_array($name, ['refund-limit','refund-review'], true)) {
            $refund = $rows[0]; $refund['entity']='refund'; $refund['source_id']='refund-a';
            $refund['payload']=['customer'=>'customer-a','payment'=>'payment-a','amount'=>['minor'=>$name === 'refund-limit' ? '7000' : '1000','currency'=>'TRY'],'evidence_sha256'=>hash('sha256','SYNTHETIC refund proof')];
            $refund['content_sha256']=Contract::hash($refund['payload']); $rows[]=$refund;
        }
        if ($name === 'duplicate-source') { $rows[]=$rows[0]; }
        if ($name === 'reversed') { $rows=array_reverse($rows); }
        return new self($rows);
    }
    public function envelopes(): array { return array_map(SourceEnvelope::fromArray(...), $this->rows); }
    public function digest(): string { $hashes=array_map(Contract::hash(...),$this->rows);sort($hashes,SORT_STRING);return Contract::hash($hashes); }
}
