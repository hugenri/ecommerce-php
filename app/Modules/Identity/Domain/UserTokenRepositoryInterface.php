<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

interface UserTokenRepositoryInterface
{
    public function createToken(int $userId, string $tokenType, string $tokenHash, \DateTimeImmutable $expiresAt): UserToken;

    public function findValidToken(string $tokenHash, string $tokenType): ?UserToken;

    public function findLatestToken(int $userId, string $tokenType): ?UserToken;

    public function markAsUsed(int $userTokenId): void;

    public function deleteActiveTokens(int $userId, string $tokenType): void;

    public function deleteExpiredTokens(): void;
}