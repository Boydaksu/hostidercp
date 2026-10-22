<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Http\HttpError;
final class Authorization
{
    public function require(?Principal $principal, string $permission, ?string $customer = null,
        ?string $department = null, bool $privileged = false, ?int $now = null): void
    {
        $now ??= time();
        if ($principal === null || $principal->actorId === '' || $principal->expiresAt <= $now) {
            throw new HttpError(401, 'AUTHENTICATION_REQUIRED');
        }
        if (($principal->staff && !$principal->mfa) || !in_array($permission, $principal->permissions, true)
            || ($customer !== null && !$principal->staff && $principal->customerId !== $customer)
            || ($department !== null && !in_array($department, $principal->departments, true))
            || ($privileged && ($principal->impersonating || $principal->verifiedAt > $now || $principal->verifiedAt <= $now - 300))) {
            throw new HttpError(403, 'ACCESS_DENIED');
        }
    }
}
