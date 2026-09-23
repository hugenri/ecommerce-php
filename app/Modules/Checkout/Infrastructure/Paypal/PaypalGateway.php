<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Paypal;

use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentCheckout;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;
use App\Modules\Checkout\Domain\PaymentOrder;

/**
 * Adaptador que hace accesible al PaypalClient mediante
 * PaymentGatewayInterface para las capas superiores.
 */
final class PaypalGateway implements PaymentGatewayInterface
{
    public function __construct(private PaypalClient $client) {}

    public function createOrder(PaymentOrder $order): string
    {
        return $this->client->createOrder($order);
    }

    public function createRedirectCheckout(PaymentOrder $order): PaymentCheckout
    {
        // PayPal no utiliza checkout redirigido; el flujo se orquesta
        // vía createOrder + captureOrder. Únicamente se expone para
        // cumplir el contrato de la interfaz.
        return new PaymentCheckout(
            orderId: $this->client->createOrder($order),
            checkoutRequestId: '',
        );
    }

    public function captureOrder(string $orderId): PaymentCapture
    {
        return $this->client->captureOrder($orderId);
    }

    public function getOrder(string $orderId): PaymentCapture
    {
        return $this->client->getOrder($orderId);
    }

    public function classifyEvent(string $eventType): ?string
    {
        return null;
    }
}