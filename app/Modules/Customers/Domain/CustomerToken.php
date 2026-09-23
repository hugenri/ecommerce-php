<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

class CustomerToken
{
    public const EMAIL_VERIFICATION = 'email_verification';

    public const PASSWORD_RESET = 'password_reset';

    public const REMEMBER_ME = 'remember_me';

    public function __construct(
        private readonly ?int $id,
        private readonly int $customerId,
        private readonly string $tokenType,
        private readonly string $tokenHash,
        private readonly ?\DateTimeImmutable $expiresAt,
        private readonly ?\DateTimeImmutable $sentAt = null,
        private readonly ?\DateTimeImmutable $usedAt = null,
        private readonly ?\DateTimeImmutable $createdAt = null,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getCustomerId(): int { return $this->customerId; }
    public function getTokenType(): string { return $this->tokenType; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function getUsedAt(): ?\DateTimeImmutable { return $this->usedAt; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}