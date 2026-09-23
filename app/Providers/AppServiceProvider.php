<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;

/**
 * Orquesta el registro de todos los Service Providers de la aplicación.
 * No registra bindings directamente.
 */
class AppServiceProvider
{
    public static function register(Container $container): void
    {
        ConfigServiceProvider::register($container);
        CoreServiceProvider::register($container);
        SessionServiceProvider::register($container);
        RepositoryServiceProvider::register($container);
        PaymentServiceProvider::register($container);
    }
}