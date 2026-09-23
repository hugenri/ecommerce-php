<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Describe un método de pago disponible en el checkout.
 *
 * DTO de solo lectura: la vista itera el catálogo (PaymentProviderRegistry)
 * para renderizar las tarjetas de pago y el frontend decide el flujo a partir
 * de estos atributos, sin hardcodear un proveedor específico.
 */
final class PaymentProvider
{
    public const TYPE_GATEWAY = 'gateway';

    public const CONFIRMATION_SYNC = 'sync';

    public const CONFIRMATION_WEBHOOK = 'webhook';

    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $icon,
        private readonly string $type,
        private readonly string $confirmationMode,
        private readonly bool $requiresRedirect,
        private readonly ?string $createOrderEndpoint = null,
        private readonly ?string $captureEndpoint = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function confirmationMode(): string
    {
        return $this->confirmationMode;
    }

    public function requiresRedirect(): bool
    {
        return $this->requiresRedirect;
    }

    public function createOrderEndpoint(): ?string
    {
        return $this->createOrderEndpoint;
    }

    public function captureEndpoint(): ?string
    {
        return $this->captureEndpoint;
    }

    public function isGateway(): bool
    {
        return $this->type === self::TYPE_GATEWAY;
    }
}
