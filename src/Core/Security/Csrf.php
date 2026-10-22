<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Http\Request;
final class Csrf
{
    public static function token(): string { return bin2hex(random_bytes(32)); }
    public static function verify(Request $request, string $expected): void
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $provided = $request->header('x-csrf-token');
        if (strlen($expected) !== 64 || strlen($provided) !== 64 || !hash_equals($expected, $provided)) {
            throw new HttpError(403, 'CSRF_FAILED');
        }
    }
}
