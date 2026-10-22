<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Http\HttpError;
final class Authorization
{
    public function require(?Principal $principal, string $permission, ?string $customer = null,
        ?string $department = null, bool $privileged = false, ?int $now = null,
        ?string $service = null, bool $administrative = false): void
    {
        $now ??= time();
        if ($principal === null || $principal->actorId === '' || $principal->expiresAt <= $now) {
            throw new HttpError(401, 'AUTHENTICATION_REQUIRED');
        }
        $permissions = $principal->impersonating ? $principal->effectivePermissions : $principal->permissions;
        $needsGrant = ($principal->staff && $customer !== null) || $service !== null || $principal->impersonating;
        $covered = false;
        foreach ($principal->grants as $grant) {
            if ($grant instanceof AccessGrant && $grant->covers($permission, $customer, $service, $department)) {
                $covered = true;
            }
        }
        if (($principal->staff && !$principal->mfa) || !in_array($permission, $permissions, true)
            || ($customer !== null && (!$principal->staff || $principal->impersonating) && $principal->customerId !== $customer)
            || ($service !== null && $customer === null) || ($needsGrant && !$covered)
            || ($department !== null && !in_array($department, $principal->departments, true))
            || ($administrative && (!$principal->administrative || !$principal->staff || $principal->impersonating))
            || ($privileged && ($principal->impersonating || $principal->verifiedAt > $now || $principal->verifiedAt <= $now - 300))) {
            throw new HttpError(403, 'ACCESS_DENIED');
        }
    }
}
