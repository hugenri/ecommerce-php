<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Resultado de una captura o consulta de orden en el proveedor de pago.
 */
final class PaymentCapture
{
    public function __construct(
        private readonly string $orderId,
        private readonly string $status,
        private readonly float $amount,
        private readonly string $currency,
        private readonly ?string $captureId,
        private readonly ?string $reference = null,
        private readonly ?string $paymentMethod = null,
    ) {}

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCaptureId(): ?string
    {
        return $this->captureId;
    }

    /**
     * Referencia del método de pago (OXXO o SPEI CLABE) cuando aplica.
     */
    public function getReference(): ?string
    {
        return $this->reference;
    }

    /**
     * Método de pago granular del proveedor (paypal | card | cash |
     * bank_transfer) cuando la pasarela lo expone.
     */
    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }
}