<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\AppConfig;
use App\Config\ConektaConfig;
use App\Config\Config;
use App\Config\DatabaseConfig;
use App\Config\MailConfig;
use App\Config\PaypalConfig;
use App\Core\Container;

/**
 * Registra todas las clases de configuración de la aplicación.
 */
class ConfigServiceProvider
{
    public static function register(Container $container): void
    {
        $container->singleton(Config::class);
        $container->singleton(AppConfig::class);
        $container->singleton(MailConfig::class);
        $container->singleton(DatabaseConfig::class);
        $container->singleton(PaypalConfig::class);
        $container->singleton(ConektaConfig::class);
    }
}