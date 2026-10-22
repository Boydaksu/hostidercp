<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
/** F1 supplies the authoritative revocation/identity-version check. */
interface SessionGuard
{
    public function assertActive(SessionSnapshot $session): void;
}
