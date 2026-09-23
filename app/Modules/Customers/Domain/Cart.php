<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

/**
 * Carrito persistente de un cliente (customer_id) o de un invitado
 * (guest_token). Cada cliente tiene un único carrito abierto.
 */
final class Cart
{
    public function __construct(
        private int $cartId,
        private ?int $customerId,
        private ?string $guestToken,
        private string $status,
    ) {}

    public function getCartId(): int
    {
        return $this->cartId;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function getGuestToken(): ?string
    {
        return $this->guestToken;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}