<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

final class LoginResult
{
    public const SUCCESS = 'success';
    public const INVALID_CREDENTIALS = 'invalid_credentials';
    public const ACCOUNT_DISABLED = 'account_disabled';
    public const EMAIL_NOT_VERIFIED = 'email_not_verified';

    private function __construct(
        private string $status,
        private ?Customer $customer = null
    ) {}

    public static function success(Customer $customer): self
    {
        return new self(self::SUCCESS, $customer);
    }

    public static function invalidCredentials(): self
    {
        return new self(self::INVALID_CREDENTIALS);
    }

    public static function accountDisabled(): self
    {
        return new self(self::ACCOUNT_DISABLED);
    }

    public static function emailNotVerified(): self
    {
        return new self(self::EMAIL_NOT_VERIFIED);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::SUCCESS;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }
}