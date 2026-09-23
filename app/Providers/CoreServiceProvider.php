<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database\Database;
use App\Core\Http\Response;
use App\Core\Maintenance\GuestCartMaintenance;
use App\Core\Security\CsrfTokenSeeder;
use App\Core\Security\GuestCartToken;
use App\Core\Security\PasswordHasher;
use App\Core\Security\TokenGenerator;
use App\Framework\Security\TokenGeneratorInterface;
use App\Http\Request;
use App\Modules\Identity\Domain\PasswordHasherInterface;
use App\Modules\Settings\Application\Services\SettingsService;

/**
 * Registra la infraestructura común del framework.
 */
class CoreServiceProvider
{
    public static function register(Container $container): void
    {
        $container->singleton(Database::class);
        // Request singleton: evita leer dos veces php://input.
        $container->singleton(Request::class);
        $container->singleton(Response::class);
        $container->singleton(GuestCartToken::class);
        $container->singleton(SettingsService::class);
        $container->singleton(GuestCartMaintenance::class);
        $container->singleton(CsrfTokenSeeder::class);
        $container->bind(PasswordHasherInterface::class, PasswordHasher::class);
        $container->bind(TokenGeneratorInterface::class, TokenGenerator::class);
    }
}