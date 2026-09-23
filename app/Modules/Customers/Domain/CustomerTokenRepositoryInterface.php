<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

interface CustomerTokenRepositoryInterface
{
    public function createToken(int $customerId, string $tokenType, string $tokenHash, \DateTimeImmutable $expiresAt): CustomerToken;

    public function findValidToken(string $tokenHash, string $tokenType): ?CustomerToken;

    public function findLatestToken(int $customerId, string $tokenType): ?CustomerToken;

    public function markAsUsed(int $customerTokenId): void;

    public function deleteActiveTokens(int $customerId, string $tokenType): void;

    public function deleteExpiredTokens(): void;
}