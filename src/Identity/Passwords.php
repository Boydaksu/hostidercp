<?php
declare(strict_types=1);
namespace Hostider\Identity;
use Hostider\Core\Http\HttpError;
final class Passwords
{
    private const OPTIONS=['memory_cost'=>65536,'time_cost'=>3,'threads'=>1];
    public static function hash(#[\SensitiveParameter] string $password): string
    {
        if (strlen($password)<12 || strlen($password)>1024) { throw new HttpError(422,'PASSWORD_POLICY'); }
        return password_hash($password,PASSWORD_ARGON2ID,self::OPTIONS);
    }
    public static function verify(#[\SensitiveParameter] string $password,string $hash): bool
    {
        return strlen($password)<=1024 && password_verify($password,$hash);
    }
    public static function needsRehash(string $hash): bool { return password_needs_rehash($hash,PASSWORD_ARGON2ID,self::OPTIONS); }
}
