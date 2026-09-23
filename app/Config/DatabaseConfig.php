<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Configuración de la base de datos.
 */
final class DatabaseConfig
{
    public function __construct(private Config $config) {}

    public function driver(): string
    {
        return $this->config->string('DB_DRIVER');
    }

    public function host(): string
    {
        return $this->config->string('DB_HOST');
    }

    public function port(): string
    {
        return $this->config->string('DB_PORT');
    }

    public function database(): string
    {
        return $this->config->string('DB_DATABASE');
    }

    public function username(): string
    {
        return $this->config->string('DB_USERNAME');
    }

    public function password(): string
    {
        return $this->config->stringOr('DB_PASSWORD', '');
    }

    public function charset(): string
    {
        return $this->config->stringOr('CHARSET', 'utf8mb4');
    }

    public function tablePrefix(): string
    {
        return $this->config->stringOr('PREFIX', '');
    }
}
