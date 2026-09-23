<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Modules\Checkout\Application\Services\PaymentGatewayResolver;
use App\Modules\Checkout\Domain\PaymentProviderRegistry;

/**
 * Registra la infraestructura de pagos.
 */
class PaymentServiceProvider
{
    public static function register(Container $container): void
    {
        $container->singleton(PaymentProviderRegistry::class);
        $container->singleton(PaymentGatewayResolver::class);
    }
}