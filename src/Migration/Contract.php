<?php
declare(strict_types=1);
namespace Hostider\Migration;

use Hostider\Core\CanonicalJson;

/** Pure synthetic contract primitives. No adapter, storage or effects. */
final class Contract
{
    public const VERSION = 'hostider.synthetic.v1';
    public const TRANSFORM = 'synthetic.v1';

    /** @phpstan-assert true $condition */
    public static function require(bool $condition, string $code): void
    {
        if (!$condition) { throw new \InvalidArgumentException($code); }
    }

    public static function fields(array $data, array $required, array $optional = []): void
    {
        self::require(array_diff($required, array_keys($data)) === [] && array_diff(array_keys($data), [...$required, ...$optional]) === [], 'FIELDS');
    }

    public static function id(mixed $value): string
    {
        self::require(is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $value) === 1, 'IDENTITY');
        return $value;
    }

    public static function sha(mixed $value): string
    {
        self::require(is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1, 'CHECKSUM');
        return $value;
    }

    public static function timestamp(mixed $value): string
    {
        self::require(is_string($value), 'TIMESTAMP');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        self::require($date !== false && $date->format('Y-m-d\TH:i:s\Z') === $value, 'TIMESTAMP');
        return $value;
    }

    public static function hash(mixed $value): string { return hash('sha256', CanonicalJson::encode($value)); }

    public static function text(mixed $value): string
    {
        self::require(is_string($value) && strlen($value) <= 8192 && preg_match('//u', $value) === 1 && !str_contains($value, "\0"), 'TEXT_ENCODING');
        return $value;
    }
}
