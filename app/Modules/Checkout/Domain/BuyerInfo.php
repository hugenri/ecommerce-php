<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Datos del comprador adjuntos a una orden de pago.
 *
 * Permite a los Use Cases transportar la información del cliente sin
 * depender del módulo Customers ni de la pasarela concreta.
 */
final class BuyerInfo
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
        private readonly ?string $phone = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }
}