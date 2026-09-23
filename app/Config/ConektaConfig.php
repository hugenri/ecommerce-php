<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Configuración de la pasarela de pago Conekta.
 *
 * Las credenciales provienen exclusivamente de variables de entorno
 * leídas a través de Config (única clase autorizada para acceder a $_ENV).
 */
final class ConektaConfig
{
    private const SANDBOX_BASE_URL = 'https://api.conekta.io';

    private const LIVE_BASE_URL = 'https://api.conekta.io';

    public const UNIT_PRICE_MULTIPLIER = 100;

    public function __construct(private Config $config) {}

    public function mode(): string
    {
        $mode = $this->config->string('CONEKTA_MODE');

        if (!in_array($mode, ['sandbox', 'live'], true)) {
            throw new RuntimeException("CONEKTA_MODE debe ser 'sandbox' o 'live'.");
        }

        return $mode;
    }

    public function secretKey(): string
    {
        return $this->config->string('CONEKTA_SECRET_KEY');
    }

    public function webhookPublicKey(): string
    {
        return $this->config->string('CONEKTA_WEBHOOK_PUBLIC_KEY');
    }

    public function publicKey(): string
    {
        return $this->config->string('CONEKTA_PUBLIC_KEY');
    }

    public function currency(): string
    {
        return $this->config->stringOr('CONEKTA_CURRENCY', 'MXN');
    }

    public function apiBaseUrl(): string
    {
        return $this->mode() === 'live' ? self::LIVE_BASE_URL : self::SANDBOX_BASE_URL;
    }
}
