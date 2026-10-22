<?php
declare(strict_types=1);
namespace Hostider\Core;

final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(self::normalize($value), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): string
    {
        // Object decoding preserves {} vs [] and numeric property names.
        return self::encode(json_decode($json, false, 32, JSON_THROW_ON_ERROR));
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_float($value)) {
            throw new \InvalidArgumentException('Use integer or decimal strings');
        }
        if ($value instanceof \stdClass) {
            $fields = get_object_vars($value);
            ksort($fields, SORT_STRING);
            $object = new \stdClass();
            foreach ($fields as $name => $item) {
                $object->{(string) $name} = self::normalize($item);
            }
            return $object;
        }
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value, SORT_STRING);
                return self::normalize((object) $value);
            }
            return array_map(self::normalize(...), $value);
        }
        if (!is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException('Unsupported canonical value');
        }
        return $value;
    }
}
