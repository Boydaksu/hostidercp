<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
final readonly class Principal
{
    public function __construct(public string $actorId, public array $permissions, public ?string $customerId = null,
        public array $departments = [], public bool $staff = false, public bool $mfa = false,
        public int $expiresAt = 0, public int $verifiedAt = 0, public bool $impersonating = false) {}
}
