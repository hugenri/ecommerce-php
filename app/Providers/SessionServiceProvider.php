<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Session\Store\SessionStore;
use App\Core\SessionManager;
use App\Framework\Session\SessionManagerInterface;
use App\Framework\Session\Store\SessionStoreInterface;

/**
 * Registra los bindings de la sesión. No inicia la sesión aquí.
 */
class SessionServiceProvider
{
    public static function register(Container $container): void
    {
        $container->singleton(SessionManagerInterface::class, SessionManager::class);
        $container->bind(SessionStoreInterface::class, SessionStore::class);
    }
}