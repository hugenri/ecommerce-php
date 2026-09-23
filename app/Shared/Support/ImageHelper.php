<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Resuelve la URL pública de una imagen almacenada.
 *
 * Si la ruta ya es absoluta (http/https), apunta a la raíz (/...) o es un
 * data URI, se devuelve tal cual. En cualquier otro caso se antepone /public/.
 */
final class ImageHelper
{
    public static function url(?string $path): string
    {
        if ($path === null || $path === '') {
            return $path ?? '';
        }

        if (preg_match('~^(https?://|data:|/)~i', $path)) {
            return $path;
        }

        return '/public/' . ltrim($path, '/');
    }
}