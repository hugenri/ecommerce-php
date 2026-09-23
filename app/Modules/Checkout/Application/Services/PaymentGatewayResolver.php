<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Services;

use App\Core\Container;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;

/**
 * Resuelve la implementación concreta de PaymentGatewayInterface a partir
 * del identificador de un proveedor de pago.
 *
 * Centraliza aquí el mapeo proveedor -> gateway, de modo que los Use Cases
 * y el Controller no dependan de una implementación concreta. Un nuevo
 * proveedor con pasarela se registra añadiendo su entrada en la lista.
 */
final class PaymentGatewayResolver
{
    /**
     * @var array<string, class-string<PaymentGatewayInterface>>
     */
    private const GATEWAYS = [
        'paypal' => \App\Modules\Checkout\Infrastructure\Paypal\PaypalGateway::class,
        'conekta' => \App\Modules\Checkout\Infrastructure\Conekta\ConektaGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function resolve(string $providerId): ?PaymentGatewayInterface
    {
        $gatewayClass = self::GATEWAYS[$providerId] ?? null;

        if ($gatewayClass === null) {
            return null;
        }

        return $this->container->make($gatewayClass);
    }
}
