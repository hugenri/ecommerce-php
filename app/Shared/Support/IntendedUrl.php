<?php

declare(strict_types=1);

namespace App\Shared\Support;

class IntendedUrl
{
    public const SESSION_KEY = 'redirect_after_login';

    private const AUTH_PAGES = [
        '/login',
        '/register',
        '/logout',
        '/reset-password',
        '/customer/verify-email',
        '/customer/resend-verification',
    ];

    public static function remember(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }

        if (isset($_SESSION['customer'])) {
            return;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode(parse_url($requestUri, PHP_URL_PATH) ?? '');
        if (in_array($path, self::AUTH_PAGES, true)) {
            return;
        }

        if (isset($_SESSION[self::SESSION_KEY])) {
            return;
        }

        $_SESSION[self::SESSION_KEY] = $requestUri;
    }
}
