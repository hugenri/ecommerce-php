<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Configuración de la pasarela de pago PayPal.
 *
 * Las credenciales provienen exclusivamente de variables de entorno
 * leídas a través de Config (única clase autorizada para acceder a $_ENV).
 */
final class PaypalConfig
{
    private const SANDBOX_BASE_URL = 'https://api-m.sandbox.paypal.com';

    private const LIVE_BASE_URL = 'https://api-m.paypal.com';

    public function __construct(private Config $config) {}

    public function mode(): string
    {
        $mode = $this->config->string('PAYPAL_MODE');

        if (!in_array($mode, ['sandbox', 'live'], true)) {
            throw new RuntimeException("PAYPAL_MODE debe ser 'sandbox' o 'live'.");
        }

        return $mode;
    }

    public function clientId(): string
    {
        return $this->config->string('PAYPAL_CLIENT_ID');
    }

    public function clientSecret(): string
    {
        return $this->config->string('PAYPAL_CLIENT_SECRET');
    }

    public function currency(): string
    {
        return $this->config->stringOr('PAYPAL_CURRENCY', 'MXN');
    }

    public function apiBaseUrl(): string
    {
        return $this->mode() === 'live' ? self::LIVE_BASE_URL : self::SANDBOX_BASE_URL;
    }
}