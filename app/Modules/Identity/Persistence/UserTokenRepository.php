<?php

declare(strict_types=1);

namespace App\Modules\Identity\Persistence;

use App\Core\Database\Database;
use App\Modules\Identity\Domain\UserToken;
use App\Modules\Identity\Domain\UserTokenRepositoryInterface;

class UserTokenRepository implements UserTokenRepositoryInterface
{
    protected string $table = 'user_tokens';

    public function __construct(
        private Database $db,
    ) {}

    public function createToken(int $userId, string $tokenType, string $tokenHash, \DateTimeImmutable $expiresAt): UserToken
    {
        $now = $this->now();

        $id = $this->db->insert($this->table, [
            'user_id' => $userId,
            'token_type' => $tokenType,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'sent_at' => $now,
        ]);

        return new UserToken(
            id: (int) $id,
            userId: $userId,
            tokenType: $tokenType,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            sentAt: new \DateTimeImmutable($now),
        );
    }

    public function findValidToken(string $tokenHash, string $tokenType): ?UserToken
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE token_hash = :tokenHash
                  AND token_type = :tokenType
                  AND used_at IS NULL
                  AND expires_at > :now
                ORDER BY user_token_id DESC
                LIMIT 1";

        $row = $this->db->selectOne($sql, [
            'tokenHash' => $tokenHash,
            'tokenType' => $tokenType,
            'now' => $this->now(),
        ]);

        return $row ? $this->hydrate($row) : null;
    }

    public function findLatestToken(int $userId, string $tokenType): ?UserToken
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :userId
                  AND token_type = :tokenType
                ORDER BY user_token_id DESC
                LIMIT 1";

        $row = $this->db->selectOne($sql, [
            'userId' => $userId,
            'tokenType' => $tokenType,
        ]);

        return $row ? $this->hydrate($row) : null;
    }

    public function markAsUsed(int $userTokenId): void
    {
        $this->db->update($this->table, [
            'used_at' => $this->now(),
        ], ['user_token_id' => $userTokenId]);
    }

    public function deleteActiveTokens(int $userId, string $tokenType): void
    {
        $sql = "DELETE FROM {$this->table}
                WHERE user_id = :userId
                  AND token_type = :tokenType
                  AND used_at IS NULL";

        $this->db->query($sql, [
            'userId' => $userId,
            'tokenType' => $tokenType,
        ]);
    }

    public function deleteExpiredTokens(): void
    {
        $this->db->query(
            "DELETE FROM {$this->table} WHERE expires_at < :now",
            ['now' => $this->now()]
        );
    }

    private function hydrate(array $row): UserToken
    {
        return new UserToken(
            id: (int) $row['user_token_id'],
            userId: (int) $row['user_id'],
            tokenType: $row['token_type'],
            tokenHash: $row['token_hash'],
            expiresAt: new \DateTimeImmutable($row['expires_at']),
            sentAt: isset($row['sent_at'])
                ? new \DateTimeImmutable($row['sent_at'])
                : null,
            usedAt: isset($row['used_at'])
                ? new \DateTimeImmutable($row['used_at'])
                : null,
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable($row['created_at'])
                : null,
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}