<?php
declare(strict_types=1);
namespace Hostider\Migration;

final readonly class SourceIdentity
{
    public function __construct(public string $instance, public Entity $entity, public string $id)
    {
        Contract::id($instance); Contract::id($id);
    }

    public function key(): string { return Contract::hash([$this->instance, $this->entity->value, $this->id]); }
}
