<?php
declare(strict_types=1);
namespace Hostider\Migration;

final readonly class Fact
{
    private function __construct(public Knowledge $state, public ?string $value) {}

    public static function fromArray(array $data): self
    {
        Contract::fields($data, ['state', 'value']);
        Contract::require(is_string($data['state']), 'KNOWLEDGE');
        $state = Knowledge::tryFrom($data['state']);
        Contract::require($state !== null, 'KNOWLEDGE');
        Contract::require($state === Knowledge::Known ? is_string($data['value']) && $data['value'] !== '' : $data['value'] === null, 'KNOWLEDGE_VALUE');
        if ($data['value'] !== null) { Contract::text($data['value']); }
        return new self($state, $data['value']);
    }
}
