<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
final readonly class Operation
{
    public function __construct(public string $id, public string $action, public ?string $resourceId = null, public array $configuration = [])
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id)) {
            throw new \InvalidArgumentException('Operation UUID required');
        }
    }
}
