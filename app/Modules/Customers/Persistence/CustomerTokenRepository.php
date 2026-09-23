<?php

declare(strict_types=1);

namespace App\Modules\Customers\Persistence;

use App\Core\Database\Database;
use App\Modules\Customers\Domain\CustomerToken;
use App\Modules\Customers\Domain\CustomerTokenRepositoryInterface;

class CustomerTokenRepository implements CustomerTokenRepositoryInterface
{
    protected string $table = 'customer_tokens';

    public function __construct(
        private Database $db,
    ) {}

    public function createToken(int $customerId, string $tokenType, string $tokenHash, \DateTimeImmutable $expiresAt): CustomerToken
    {
        $now = $this->now();

        $id = $this->db->insert($this->table, [
            'customer_id' => $customerId,
            'token_type' => $tokenType,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'sent_at' => $now,
        ]);

        return new CustomerToken(
            id: (int) $id,
            customerId: $customerId,
            tokenType: $tokenType,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            sentAt: new \DateTimeImmutable($now),
        );
    }

    public function findValidToken(string $tokenHash, string $tokenType): ?CustomerToken
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE token_hash = :tokenHash
                  AND token_type = :tokenType
                  AND used_at IS NULL
                  AND expires_at > :now
                ORDER BY customer_token_id DESC
                LIMIT 1";

        $row = $this->db->selectOne($sql, [
            'tokenHash' => $tokenHash,
            'tokenType' => $tokenType,
            'now' => $this->now(),
        ]);

        return $row ? $this->hydrate($row) : null;
    }

    public function findLatestToken(int $customerId, string $tokenType): ?CustomerToken
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE customer_id = :customerId
                  AND token_type = :tokenType
                ORDER BY customer_token_id DESC
                LIMIT 1";

        $row = $this->db->selectOne($sql, [
            'customerId' => $customerId,
            'tokenType' => $tokenType,
        ]);

        return $row ? $this->hydrate($row) : null;
    }

    public function markAsUsed(int $customerTokenId): void
    {
        $this->db->update($this->table, [
            'used_at' => $this->now(),
        ], ['customer_token_id' => $customerTokenId]);
    }

    public function deleteActiveTokens(int $customerId, string $tokenType): void
    {
        $sql = "DELETE FROM {$this->table}
                WHERE customer_id = :customerId
                  AND token_type = :tokenType
                  AND used_at IS NULL";

        $this->db->query($sql, [
            'customerId' => $customerId,
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

    private function hydrate(array $row): CustomerToken
    {
        return new CustomerToken(
            id: (int) $row['customer_token_id'],
            customerId: (int) $row['customer_id'],
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