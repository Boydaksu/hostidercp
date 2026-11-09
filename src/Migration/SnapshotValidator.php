<?php
declare(strict_types=1);
namespace Hostider\Migration;

use Hostider\Finance\{Currency, Money, Unsigned};

/** Complete synthetic snapshot validation only; never loads or repairs records. */
final class SnapshotValidator
{
    private const SCHEMAS = [
        'customer' => ['status' => 'fact:ACTIVE,INACTIVE', 'currency' => 'currency'],
        'user' => ['kind' => 'enum:CUSTOMER,STAFF', 'status' => 'fact:ACTIVE,INACTIVE', 'email' => 'email', 'password_format' => 'enum:NONE', 'reset_required' => 'true', 'verification_required' => 'true', 'target_admin' => 'false', 'target_mfa' => 'false'],
        'membership' => ['customer' => 'id', 'user' => 'id', 'status' => 'fact:ACTIVE,INACTIVE'],
        'order' => ['customer' => 'id', 'payment_state' => 'fact:UNPAID,PARTIAL,PAID', 'total' => 'money'],
        'service' => ['customer' => 'id', 'order' => 'id', 'status' => 'fact:ACTIVE,SUSPENDED,TERMINATED', 'origin' => 'enum:IMPORTED', 'provider' => 'enum:CPANEL,VOYAR,RESELLERCLUB,DOMAINNAMEAPI', 'account' => 'id'],
        'resource' => ['customer' => 'id', 'service' => 'id', 'provider' => 'enum:CPANEL,VOYAR,RESELLERCLUB,DOMAINNAMEAPI', 'account' => 'id', 'external_id' => 'id', 'status' => 'fact:ACTIVE,SUSPENDED,TERMINATED,IN_FLIGHT', 'origin' => 'enum:IMPORTED', 'create_allowed' => 'false'],
        'payment' => ['customer' => 'id', 'amount' => 'money', 'state' => 'fact:CONFIRMED,PENDING,FAILED', 'external_ref' => 'id', 'evidence_sha256' => 'sha', 'source_currency' => 'currency', 'historical_fx' => 'fx'],
        'allocation' => ['customer' => 'id', 'payment' => 'id', 'order' => 'id', 'amount' => 'money'],
        'refund' => ['customer' => 'id', 'payment' => 'id', 'amount' => 'money', 'evidence_sha256' => 'sha'],
        'wallet' => ['customer' => 'id', 'liability' => 'money', 'evidence_sha256' => 'sha', 'source_currency' => 'currency', 'historical_fx' => 'fx'],
        'document' => ['customer' => 'id', 'order' => 'id', 'kind' => 'enum:PROFORMA,OFFICIAL_PDF', 'number' => 'id', 'amount' => 'money', 'created_at' => 'time', 'sha256' => 'sha'],
        'ticket' => ['customer' => 'id', 'requester' => 'id', 'created_at' => 'time'],
        'message' => ['customer' => 'id', 'ticket' => 'id', 'author' => 'id', 'author_kind' => 'enum:CUSTOMER,STAFF', 'kind' => 'enum:PUBLIC_REPLY,INTERNAL_NOTE', 'visibility' => 'fact:PUBLIC,PRIVATE', 'sequence' => 'positive', 'created_at' => 'time', 'body' => 'text'],
        'attachment' => ['customer' => 'id', 'ticket' => 'id', 'message' => 'id', 'sha256' => 'sha', 'size' => 'positive', 'mime' => 'text', 'observed_mime' => 'text', 'relative_path' => 'text', 'symlink' => 'bool', 'file_state' => 'fact:PRESENT,MISSING,CORRUPT'],
    ];

    public function validate(array $envelopes): ValidationReport
    {
        Contract::require(array_is_list($envelopes) && count($envelopes) > 0 && count($envelopes) <= 10000, 'SNAPSHOT_SIZE');
        $index = []; $contexts = []; $fingerprints = []; $reasons = [];
        foreach ($envelopes as $envelope) {
            Contract::require($envelope instanceof SourceEnvelope, 'ENVELOPE_TYPE');
            $key = $envelope->identity->key();
            Contract::require(!isset($index[$key]), 'DUPLICATE_IDENTITY');
            $index[$key] = $envelope;
            $instance = $envelope->identity->instance;
            $context = $envelope->contextHash();
            Contract::require(!isset($contexts[$instance]) || $contexts[$instance] === $context, 'SNAPSHOT_MISMATCH');
            $contexts[$instance] = $context;
            $fingerprints[$key] = $envelope->fingerprint;
            foreach ($envelope->metadata['uncertainty'] as $flag) { $reasons[] = 'SOURCE_'.$flag; }
            if (Fact::fromArray($envelope->metadata['timezone'])->state !== Knowledge::Known) { $reasons[] = 'TIMEZONE_UNVERIFIED'; }
            $schema = self::SCHEMAS[$envelope->identity->entity->value];
            Contract::fields($envelope->payload, array_keys($schema));
            foreach ($schema as $field => $type) { $this->value($envelope->payload[$field], $type, $reasons); }
        }
        // Validate after all identities are indexed: input ordering never changes results.
        $resources = []; $emails = []; $wallets = []; $externalPayments = []; $sequences = []; $times = [];
        $allocated = []; $refunded = []; $funded = [];
        foreach ($index as $key => $e) {
            $p = $e->payload; $entity = $e->identity->entity;
            if (isset($p['customer'])) { $this->parent($index, $e, Entity::Customer, $p['customer']); }
            switch ($entity) {
                case Entity::Customer: break;
                case Entity::User:
                    $email = strtolower($p['email']);
                    $emailKey = Contract::hash([$e->identity->instance, $email]);
                    if (isset($emails[$emailKey])) { $reasons[] = 'EMAIL_CONFLICT'; }
                    $emails[$emailKey] = true;
                    break;
                case Entity::Membership:
                    $u = $this->parent($index, $e, Entity::User, $p['user']);
                    Contract::require($u->payload['kind'] === 'CUSTOMER', 'STAFF_MEMBERSHIP');
                    break;
                case Entity::Order: break;
                case Entity::Service:
                    $this->owned($index, $e, Entity::Order, $p['order']);
                    break;
                case Entity::Resource:
                    $service = $this->owned($index, $e, Entity::Service, $p['service']);
                    Contract::require($p['provider'] === $service->payload['provider'] && $p['account'] === $service->payload['account'], 'PROVIDER_ACCOUNT_CONFLICT');
                    $external = Contract::hash([$e->identity->instance, $p['provider'], $p['account'], $p['external_id']]);
                    Contract::require(!isset($resources[$external]), 'EXTERNAL_RESOURCE_CONFLICT');
                    $resources[$external] = true;
                    if (Fact::fromArray($p['status'])->value === 'IN_FLIGHT') { $reasons[] = 'PROVIDER_IN_FLIGHT'; }
                    break;
                case Entity::Payment:
                    $external = Contract::hash([$e->identity->instance, $p['external_ref']]);
                    Contract::require(!isset($externalPayments[$external]), 'PAYMENT_REFERENCE_CONFLICT');
                    $externalPayments[$external] = true;
                    $this->fx($p, $p['amount'], $reasons);
                    break;
                case Entity::Allocation:
                    $payment = $this->owned($index, $e, Entity::Payment, $p['payment']);
                    $order = $this->owned($index, $e, Entity::Order, $p['order']);
                    $amount = $this->money($p['amount']);
                    Contract::require($amount->currency->code === $payment->payload['amount']['currency'] && $amount->currency->code === $order->payload['total']['currency'], 'CURRENCY_MISMATCH');
                    Contract::require(Fact::fromArray($payment->payload['state'])->value === 'CONFIRMED', 'PAYMENT_UNVERIFIED');
                    $this->sum($allocated, $payment->identity->key(), $amount);
                    $this->sum($funded, $order->identity->key(), $amount);
                    break;
                case Entity::Refund:
                    $payment = $this->owned($index, $e, Entity::Payment, $p['payment']);
                    Contract::require(Fact::fromArray($payment->payload['state'])->value === 'CONFIRMED', 'REFUND_UNVERIFIED');
                    Contract::require($p['amount']['currency'] === $payment->payload['amount']['currency'], 'CURRENCY_MISMATCH');
                    $this->sum($refunded, $payment->identity->key(), $this->money($p['amount']));
                    break;
                case Entity::Wallet:
                    $customer = $this->parent($index, $e, Entity::Customer, $p['customer']);
                    Contract::require($p['liability']['currency'] === $customer->payload['currency'], 'WALLET_CURRENCY');
                    $owner = $customer->identity->key();
                    Contract::require(!isset($wallets[$owner]), 'WALLET_CONFLICT'); $wallets[$owner] = true;
                    $this->fx($p, $p['liability'], $reasons);
                    break;
                case Entity::Document:
                    $order = $this->owned($index, $e, Entity::Order, $p['order']);
                    Contract::require($p['amount']['minor'] === $order->payload['total']['minor'] && $p['amount']['currency'] === $order->payload['total']['currency'], 'DOCUMENT_AMOUNT');
                    break;
                case Entity::Ticket:
                    $this->parent($index, $e, Entity::User, $p['requester']);
                    if (!$this->member($index, $e, $p['requester'])) { $reasons[] = 'REQUESTER_REVIEW'; }
                    break;
                case Entity::Message:
                    $ticket = $this->owned($index, $e, Entity::Ticket, $p['ticket']);
                    $author = $this->parent($index, $e, Entity::User, $p['author']);
                    if ($p['author_kind'] !== $author->payload['kind'] || ($p['author_kind'] === 'CUSTOMER' && !$this->member($index, $e, $p['author']))) { $reasons[] = 'AUTHOR_REVIEW'; }
                    $visibility = Fact::fromArray($p['visibility']);
                    Contract::require($p['kind'] !== 'INTERNAL_NOTE' || ($visibility->state === Knowledge::Known && $visibility->value === 'PRIVATE' && $p['author_kind'] === 'STAFF' && $author->payload['kind'] === 'STAFF'), 'PRIVATE_NOTE_EXPOSURE');
                    if ($p['kind'] === 'PUBLIC_REPLY' && ($visibility->state !== Knowledge::Known || $visibility->value !== 'PUBLIC')) { $reasons[] = 'MESSAGE_VISIBILITY_CONFLICT'; }
                    Contract::require($p['created_at'] >= $ticket->payload['created_at'], 'MESSAGE_TIME');
                    $ticketKey = $ticket->identity->key();
                    Contract::require(!isset($sequences[$ticketKey][$p['sequence']]), 'MESSAGE_SEQUENCE');
                    $sequences[$ticketKey][$p['sequence']] = true;
                    $times[$ticketKey][$p['sequence']] = $p['created_at'];
                    break;
                case Entity::Attachment:
                    $ticket = $this->owned($index, $e, Entity::Ticket, $p['ticket']);
                    $message = $this->owned($index, $e, Entity::Message, $p['message']);
                    Contract::require($message->payload['ticket'] === $ticket->identity->id, 'ATTACHMENT_RELATION');
                    $path = $p['relative_path'];
                    if (preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\.[A-Za-z0-9]+$~D', $path) !== 1 || $p['symlink']) { $reasons[] = 'UNSAFE_ATTACHMENT_PATH'; }
                    if ($p['mime'] !== 'application/pdf' || $p['observed_mime'] !== $p['mime']) { $reasons[] = 'ATTACHMENT_MIME_REVIEW'; }
                    if (Fact::fromArray($p['file_state'])->value !== 'PRESENT') { $reasons[] = 'ATTACHMENT_UNAVAILABLE'; }
                    break;
            }
        }
        foreach ($times as $sequenceTimes) {
            ksort($sequenceTimes, SORT_NUMERIC); $previous = null;
            foreach ($sequenceTimes as $time) { Contract::require($previous === null || $time >= $previous, 'MESSAGE_SEQUENCE_TIME'); $previous = $time; }
        }
        foreach ($index as $key => $e) {
            $p = $e->payload;
            if ($e->identity->entity === Entity::Payment) {
                foreach ([$allocated, $refunded] as $totals) {
                    if (isset($totals[$key])) { Contract::require(Unsigned::compare($totals[$key]->minor, $p['amount']['minor']) <= 0, 'PAYMENT_LIMIT'); }
                }
                if (isset($refunded[$key])) { $reasons[] = 'REFUND_RECONCILIATION_REQUIRED'; }
            }
            if ($e->identity->entity === Entity::Order) {
                $amount = $funded[$key]->minor ?? '0';
                $comparison = Unsigned::compare($amount, $p['total']['minor']);
                Contract::require($comparison <= 0, 'OVERALLOCATION');
                $state = Fact::fromArray($p['payment_state'])->value;
                Contract::require($state !== 'PAID' || ($comparison === 0 && $amount !== '0'), 'PAID_WITHOUT_EVIDENCE');
                Contract::require($state !== 'UNPAID' || $amount === '0', 'PAYMENT_STATE_CONFLICT');
                Contract::require($state !== 'PARTIAL' || ($amount !== '0' && $comparison < 0), 'PAYMENT_STATE_CONFLICT');
            }
        }
        ksort($fingerprints, SORT_STRING);
        return new ValidationReport($reasons, count($index), Contract::hash($fingerprints));
    }

    private function value(mixed $value, string $type, array &$reasons): void
    {
        if (str_starts_with($type, 'enum:')) { Contract::require(is_string($value) && in_array($value, explode(',', substr($type, 5)), true), 'ENUM_VALUE'); return; }
        if (str_starts_with($type, 'fact:') || $type === 'fx') {
            Contract::require(is_array($value), 'FACT_TYPE'); $fact = Fact::fromArray($value);
            if ($fact->state !== Knowledge::Known) { $reasons[] = 'VALUE_'.$fact->state->value; return; }
            if ($type === 'fx') { Contract::require(preg_match('/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,12})?$/D', $fact->value) === 1 && trim(str_replace('.', '', $fact->value), '0') !== '', 'FX_VALUE'); }
            else { Contract::require(in_array($fact->value, explode(',', substr($type, 5)), true), 'FACT_VALUE'); }
            return;
        }
        switch ($type) {
            case 'id': Contract::id($value); break;
            case 'sha': Contract::sha($value); break;
            case 'time': Contract::timestamp($value); break;
            case 'text': Contract::text($value); break;
            case 'email':
                Contract::text($value);
                Contract::require(filter_var($value, FILTER_VALIDATE_EMAIL) !== false && str_ends_with(strtolower($value), '.invalid'), 'SYNTHETIC_EMAIL'); break;
            case 'currency': Contract::require(is_string($value), 'CURRENCY_TYPE'); new Currency($value); break;
            case 'money': Contract::require(is_array($value), 'MONEY_TYPE'); $this->money($value); break;
            case 'true': Contract::require($value === true, 'BOOLEAN_POLICY'); break;
            case 'false': Contract::require($value === false, 'BOOLEAN_POLICY'); break;
            case 'bool': Contract::require(is_bool($value), 'BOOLEAN_TYPE'); break;
            case 'positive': Contract::require(is_int($value) && $value > 0 && $value <= 2147483647, 'POSITIVE_INTEGER'); break;
            default: throw new \LogicException('SCHEMA_TYPE');
        }
    }

    private function money(array $data): Money
    {
        Contract::fields($data, ['minor', 'currency']);
        Contract::require(is_string($data['minor']) && is_string($data['currency']), 'MONEY_TYPE');
        $money = new Money($data['minor'], new Currency($data['currency']));
        Contract::require(!str_starts_with($money->minor, '-'), 'NEGATIVE_AMOUNT');
        return $money;
    }

    private function parent(array $index, SourceEnvelope $source, Entity $type, string $id): SourceEnvelope
    {
        $key = (new SourceIdentity($source->identity->instance, $type, $id))->key();
        Contract::require(isset($index[$key]), 'MISSING_RELATION');
        return $index[$key];
    }

    private function owned(array $index, SourceEnvelope $source, Entity $type, string $id): SourceEnvelope
    {
        $parent = $this->parent($index, $source, $type, $id);
        Contract::require($parent->payload['customer'] === $source->payload['customer'], 'OWNERSHIP_CONFLICT');
        return $parent;
    }

    private function member(array $index, SourceEnvelope $source, string $user): bool
    {
        foreach ($index as $e) {
            if ($e->identity->instance === $source->identity->instance && $e->identity->entity === Entity::Membership
                && $e->payload['customer'] === $source->payload['customer'] && $e->payload['user'] === $user
                && Fact::fromArray($e->payload['status'])->value === 'ACTIVE') { return true; }
        }
        return false;
    }

    private function sum(array &$totals, string $key, Money $amount): void
    {
        $totals[$key] = isset($totals[$key]) ? $totals[$key]->add($amount) : $amount;
    }

    private function fx(array $payload, array $amount, array &$reasons): void
    {
        $fact = Fact::fromArray($payload['historical_fx']);
        if ($fact->state !== Knowledge::Known) { $reasons[] = 'HISTORICAL_FX_UNVERIFIED'; }
        if ($payload['source_currency'] !== $amount['currency']) { $reasons[] = 'CROSS_CURRENCY_RECONCILIATION'; }
        // No conversion, current-rate lookup or ledger writes, even for a known rate.
    }
}
