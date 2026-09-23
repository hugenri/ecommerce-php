<?php

declare(strict_types=1);

namespace App\Core\Security;

class Sanitizer
{
    public static function trim(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    public static function clean_data(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value)
                ? self::clean_data($value)
                : self::trim($value);
        }

        return $data;
    }
}
