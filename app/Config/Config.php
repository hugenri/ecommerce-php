<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Única clase autorizada a leer $_ENV.
 *
 * Toda lectura del entorno ocurre aquí; nunca fuera de este módulo.
 */
final class Config
{
    public function string(string $key): string
    {
        if (!isset($_ENV[$key])) {
            throw new RuntimeException("{$key} no está configurado.");
        }

        return (string) $_ENV[$key];
    }

    public function stringOr(string $key, string $default): string
    {
        return isset($_ENV[$key]) ? (string) $_ENV[$key] : $default;
    }

    public function intOr(string $key, int $default): int
    {
        return isset($_ENV[$key]) ? (int) $_ENV[$key] : $default;
    }

    public function boolOr(string $key, bool $default): bool
    {
        return isset($_ENV[$key])
            ? (bool) filter_var((string) $_ENV[$key], FILTER_VALIDATE_BOOLEAN)
            : $default;
    }
}
