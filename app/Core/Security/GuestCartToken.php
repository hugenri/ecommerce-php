<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Config\AppConfig;

/**
 * Identidad del carrito de invitado.
 *
 * Emite una cookie UUIDv4 (httponly, SameSite=Lax, 180 días) que referencia
 * la fila `carts.guest_token` del carrito persistente. No crea la cookie en
 * cada petición: get() solo la emite cuando el carrito lo necesita (al agregar
 * el primer ítem), mientras que current() únicamente lee la cookie existente.
 */
final class GuestCartToken
{
    public const COOKIE_NAME = 'guest_cart_token';

    private const LIFETIME_DAYS = 180;

    public function __construct(
        private AppConfig $appConfig,
    ) {}

    /**
     * Token actual de la cookie, o null si el visitante no tiene uno.
     */
    public function current(): ?string
    {
        $value = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!is_string($value) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Devuelve el token actual o crea y emite uno nuevo.
     */
    public function get(): string
    {
        $current = $this->current();
        if ($current !== null) {
            return $current;
        }

        $token = $this->generate();
        $this->send($token, self::LIFETIME_DAYS * 24 * 3600);
        $_COOKIE[self::COOKIE_NAME] = $token;

        return $token;
    }

    /**
     * Revoca la cookie (tras el merge al iniciar sesión o al limpiar el carrito).
     */
    public function clear(): void
    {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return;
        }

        $this->send('', time() - 3600);
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    public function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function send(string $value, int $lifetime): void
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        setcookie(
            self::COOKIE_NAME,
            $value,
            [
                'expires' => $lifetime > 0 ? time() + $lifetime : $lifetime,
                'path' => $this->appConfig->basePath() ?: '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}