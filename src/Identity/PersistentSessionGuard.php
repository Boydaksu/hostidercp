<?php
declare(strict_types=1);
namespace Hostider\Identity;
use Hostider\Core\Security\{SessionGuard,SessionSnapshot};
final readonly class PersistentSessionGuard implements SessionGuard
{
    public function __construct(private Repository $repository,private string $context) {}
    public function assertActive(SessionSnapshot $session): void
    {
        $row=$this->repository->session($session->reference,$this->context);
        $this->repository->validate($this->repository->user($row['user_id']),$row);
    }
}
