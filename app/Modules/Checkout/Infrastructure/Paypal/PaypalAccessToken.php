<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Paypal;

use DateTimeImmutable;

/**
 * Token de acceso OAuth2 de PayPal con su expiración.
 */
final class PaypalAccessToken
{
    public function __construct(
        private readonly string $token,
        private readonly DateTimeImmutable $expiresAt,
    ) {}

    public static function issue(string $token, int $expiresIn): self
    {
        return new self($token, (new DateTimeImmutable())->modify('+' . $expiresIn . ' seconds'));
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isExpired(): bool
    {
        return (new DateTimeImmutable()) >= $this->expiresAt;
    }
}