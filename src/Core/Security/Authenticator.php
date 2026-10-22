<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Http\Request;
interface Authenticator
{
    public function authenticate(Request $request): ?Principal;
}
