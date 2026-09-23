<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Registro de auditoría de una transacción de pago.
 *
 * Almacena los datos persistentes de la tabla payment_transactions:
 * identificador, venta asociada, proveedor, estados, montos, la referencia
 * OXXO/CLABE cuando aplica, y el snapshot del cliente, dirección y artículos
 * necesario para materializar la venta (binario: se persiste mediante
 * dehydrate/hydrate del repositorio).
 */
class PaymentTransaction
{
    public function __construct(
        private readonly ?int $paymentTransactionId,
        private ?int $saleId,
        private string $provider,
        private ?string $paymentMethod = null,
        private string $providerOrderId = '',
        private ?string $providerCaptureId = null,
        private float $amount = 0.0,
        private string $currency = 'MXN',
        private string $status = 'pending',
        private ?int $customerId = null,
        private ?int $addressId = null,
        private array $items = [],
        private ?string $reference = null,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getPaymentTransactionId(): ?int
    {
        return $this->paymentTransactionId;
    }

    public function getSaleId(): ?int
    {
        return $this->saleId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getProviderOrderId(): string
    {
        return $this->providerOrderId;
    }

    public function getProviderCaptureId(): ?string
    {
        return $this->providerCaptureId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function getAddressId(): ?int
    {
        return $this->addressId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withPaymentTransactionId(int $paymentTransactionId): self
    {
        return $this->cloneWith(['paymentTransactionId' => $paymentTransactionId]);
    }

    public function withStatus(string $status): self
    {
        return $this->cloneWith(['status' => $status]);
    }

    public function withSaleId(?int $saleId): self
    {
        return $this->cloneWith(['saleId' => $saleId]);
    }

    public function withProviderCaptureId(?string $providerCaptureId): self
    {
        return $this->cloneWith(['providerCaptureId' => $providerCaptureId]);
    }

    public function withCustomerId(?int $customerId): self
    {
        return $this->cloneWith(['customerId' => $customerId]);
    }

    public function withAddressId(?int $addressId): self
    {
        return $this->cloneWith(['addressId' => $addressId]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function withItems(array $items): self
    {
        return $this->cloneWith(['items' => $items]);
    }

    public function withReference(?string $reference): self
    {
        return $this->cloneWith(['reference' => $reference]);
    }

    public function withPaymentMethod(?string $paymentMethod): self
    {
        return $this->cloneWith(['paymentMethod' => $paymentMethod]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            paymentTransactionId: $overrides['paymentTransactionId'] ?? $this->paymentTransactionId,
            saleId: $overrides['saleId'] ?? $this->saleId,
            provider: $overrides['provider'] ?? $this->provider,
            paymentMethod: $overrides['paymentMethod'] ?? $this->paymentMethod,
            providerOrderId: $overrides['providerOrderId'] ?? $this->providerOrderId,
            providerCaptureId: $overrides['providerCaptureId'] ?? $this->providerCaptureId,
            amount: $overrides['amount'] ?? $this->amount,
            currency: $overrides['currency'] ?? $this->currency,
            status: $overrides['status'] ?? $this->status,
            customerId: $overrides['customerId'] ?? $this->customerId,
            addressId: $overrides['addressId'] ?? $this->addressId,
            items: $overrides['items'] ?? $this->items,
            reference: $overrides['reference'] ?? $this->reference,
            createdAt: $overrides['createdAt'] ?? $this->createdAt,
            updatedAt: $overrides['updatedAt'] ?? $this->updatedAt,
        );
    }
}
