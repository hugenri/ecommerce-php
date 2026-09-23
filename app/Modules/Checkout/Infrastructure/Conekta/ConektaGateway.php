<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Conekta;

use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentCheckout;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;
use App\Modules\Checkout\Domain\PaymentOrder;

/**
 * Adaptador que hace accesible al ConektaClient mediante
 * PaymentGatewayInterface para las capas superiores.
 */
final class ConektaGateway implements PaymentGatewayInterface
{
    public function __construct(private ConektaClient $client) {}

    public function createOrder(PaymentOrder $order): string
    {
        return $this->client->createOrder($order)->orderId();
    }

    public function createRedirectCheckout(PaymentOrder $order): PaymentCheckout
    {
        return $this->client->createOrder($order);
    }

    public function captureOrder(string $orderId): PaymentCapture
    {
        throw new \RuntimeException('Conekta no utiliza captura server-to-server.');
    }

    public function getOrder(string $orderId): PaymentCapture
    {
        return $this->client->getOrder($orderId);
    }

    public function classifyEvent(string $eventType): ?string
    {
        return match ($eventType) {
            'charge.created' => self::EVENT_CHARGE_CREATED,
            'order.paid', 'charge.paid' => self::EVENT_PAID,
            'order.declined', 'charge.declined' => self::EVENT_FAILED,
            'order.canceled', 'charge.canceled', 'order.voided' => self::EVENT_CANCELLED,
            'order.expired', 'charge.expired' => self::EVENT_EXPIRED,
            default => null,
        };
    }
}
