<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

class Sale
{
    public function __construct(
        private readonly ?int $saleId,
        private string $saleCode,
        private int $customerId,
        private int $addressId,
        private string $paymentMethod,
        private string $paymentStatus = 'paid',
        private ?\DateTimeImmutable $saleDate = null,
        private float $subtotal = 0.0,
        private float $tax = 0.0,
        private float $total = 0.0,
        private string $status = 'pending',
        private ?string $notes = null,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getSaleId(): ?int { return $this->saleId; }
    public function getSaleCode(): string { return $this->saleCode; }
    public function getCustomerId(): int { return $this->customerId; }
    public function getAddressId(): int { return $this->addressId; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function getSaleDate(): ?\DateTimeImmutable { return $this->saleDate; }
    public function getSubtotal(): float { return $this->subtotal; }
    public function getTax(): float { return $this->tax; }
    public function getTotal(): float { return $this->total; }
    public function getStatus(): string { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    /**
     * Indica si el pago pendiente de la venta venció su ventana de pago.
     *
     * Una venta se considera expirada únicamente si sigue pendiente de pago
     * (payment_status = pending y estado = pending) y su fecha de venta superó
     * la ventana expresada en horas. Es una regla de presentación evaluada en
     * lectura; nunca escribe 'cancelled' en la base de datos. El webhook de
     * Conekta sigue siendo la única vía que promueve o cancela la venta.
     */
    public function isPaymentExpired(int $expiryHours): bool
    {
        if ($this->paymentStatus !== 'pending' || $this->status !== 'pending') {
            return false;
        }

        $saleDate = $this->saleDate;
        if ($saleDate === null) {
            return false;
        }

        $compareAt = $saleDate->modify('+' . $expiryHours . ' hours');

        return (new \DateTimeImmutable()) >= $compareAt;
    }

    public function withSaleId(int $saleId): self
    {
        return $this->cloneWith(['saleId' => $saleId]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            saleId: $overrides['saleId'] ?? $this->saleId,
            saleCode: $overrides['saleCode'] ?? $this->saleCode,
            customerId: $overrides['customerId'] ?? $this->customerId,
            addressId: $overrides['addressId'] ?? $this->addressId,
            paymentMethod: $overrides['paymentMethod'] ?? $this->paymentMethod,
            paymentStatus: $overrides['paymentStatus'] ?? $this->paymentStatus,
            saleDate: $overrides['saleDate'] ?? $this->saleDate,
            subtotal: $overrides['subtotal'] ?? $this->subtotal,
            tax: $overrides['tax'] ?? $this->tax,
            total: $overrides['total'] ?? $this->total,
            status: $overrides['status'] ?? $this->status,
            notes: $overrides['notes'] ?? $this->notes,
            createdAt: $overrides['createdAt'] ?? $this->createdAt,
            updatedAt: $overrides['updatedAt'] ?? $this->updatedAt,
        );
    }
}
