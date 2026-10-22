<?php
declare(strict_types=1);
namespace Hostider\Core;
use Hostider\Core\Http\HttpError;
final class Validation
{
    public static function fields(array $input, array $allowed, array $required): void
    {
        if (array_diff(array_keys($input), $allowed) || array_diff($required, array_keys($input))) {
            throw new HttpError(422, 'INVALID_FIELDS');
        }
    }
    public static function text(mixed $value, int $max = 191): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || trim($value) === '' || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new HttpError(422, 'INVALID_TEXT');
        }
        return $value;
    }
}
