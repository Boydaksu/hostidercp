<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
final readonly class SessionSnapshot
{
    public function __construct(public array $data, public int $version, public string $reference) {}
}
