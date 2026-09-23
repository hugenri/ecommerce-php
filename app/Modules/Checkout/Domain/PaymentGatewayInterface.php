<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Abstracción de cualquier proveedor de pago.
 *
 * Los Use Cases y Controllers dependen de esta interfaz, nunca del
 * cliente HTTP concreto (PaypalClient), manteniendo el desacoplamiento.
 */
interface PaymentGatewayInterface
{
    public const EVENT_PAID = 'paid';

    public const EVENT_FAILED = 'failed';

    public const EVENT_CANCELLED = 'cancelled';

    public const EVENT_EXPIRED = 'expired';

    public const EVENT_CHARGE_CREATED = 'charge_created';

    public function createOrder(PaymentOrder $order): string;

    /**
     * Crea un checkout embebido (Integration) para el proveedor y devuelve
     * el resultado con el identificador de orden y el checkoutRequestId
     * necesario para inicializar el componente en el navegador (Conekta.js)
     * dentro del dominio propio.
     */
    public function createRedirectCheckout(PaymentOrder $order): PaymentCheckout;

    public function captureOrder(string $orderId): PaymentCapture;

    public function getOrder(string $orderId): PaymentCapture;

    /**
     * Clasifica un evento de la pasarela en un resultado abstracto de pago
     * (EVENT_* de esta interfaz) o null para eventos que la aplicación no
     * debe procesar. Mantiene los nombres de evento del proveedor fuera del
     * dominio.
     */
    public function classifyEvent(string $eventType): ?string;
}