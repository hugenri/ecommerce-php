<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Configuración general de la aplicación.
 */
final class AppConfig
{
    public function __construct(private Config $config) {}

    public function appUrl(): string
    {
        return $this->config->string('APP_URL');
    }

    public function appName(): string
    {
        return $this->config->stringOr('APP_NAME', 'Ecommerce');
    }

    public function environment(): string
    {
        return $this->config->stringOr('APP_ENV', 'production');
    }

    public function debug(): bool
    {
        return $this->environment() === 'development';
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function basePath(): string
    {
        return $this->config->stringOr('APP_BASE_PATH', '');
    }

    public function sessionName(): string
    {
        return $this->config->stringOr('SESSION_NAME', 'APP_SESSION');
    }

    public function sessionLifetime(): int
    {
        return $this->config->intOr('SESSION_LIFETIME', 7200);
    }

    /**
     * Fuerza el flag Secure en la cookie de sesión.
     *
     * En producción se asume que el sitio se sirve exclusivamente por HTTPS,
     * por lo que Secure se fuerza sin depender de la detección del proxy/terminador
     * TLS. En otros entornos puede forzarse de forma explícita con la variable
     * SESSION_FORCE_SECURE; si es false/ausente, Secure se decide por detección
     * de HTTPS directo o del header X-Forwarded-Proto en SessionManager.
     */
    public function sessionForceSecure(): bool
    {
        return $this->isProduction() || $this->config->boolOr('SESSION_FORCE_SECURE', false);
    }

    /**
     * Ventana (en horas) en la que una venta pendiente de pago puede
     * completarse antes de considerarse expirada en Mis Pedidos.
     *
     * Solo aplica a pagos de efectivo (OXXO) o SPEI, cuyo cliente ya tiene su
     * referencia para pagar en tienda/transferencia. El default de 96 horas
     * (4 días) deja un margen sobre la ventana real de 3 días que Conekta
     * otorga a un pago OXXO. PayPal y tarjeta no expiran: su botón
     * "Completar pago" permanece mientras la venta esté pendiente. Es una
     * regla de presentación: nunca persiste el estado 'cancelled' por
     * antigüedad.
     */
    public function pendingPaymentExpiryHours(): int
    {
        return $this->config->intOr('PENDING_PAYMENT_EXPIRY_HOURS', 96);
    }
}
