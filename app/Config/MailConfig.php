<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Configuración del servicio de correo.
 */
final class MailConfig
{
    public function __construct(private Config $config) {}

    public function host(): string
    {
        return $this->config->stringOr('MAIL_HOST', '');
    }

    public function port(): int
    {
        return $this->config->intOr('MAIL_PORT', 587);
    }

    public function username(): string
    {
        return $this->config->stringOr('MAIL_USERNAME', '');
    }

    public function password(): string
    {
        return $this->config->stringOr('MAIL_PASSWORD', '');
    }

    public function from(): string
    {
        return $this->config->string('MAIL_FROM');
    }

    public function fromName(): string
    {
        return $this->config->stringOr('MAIL_FROM_NAME', 'Ecommerce');
    }

    public function encryption(): string
    {
        return $this->config->stringOr('MAIL_ENCRYPTION', '');
    }
}
