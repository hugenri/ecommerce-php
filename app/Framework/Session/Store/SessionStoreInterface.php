<?php

declare(strict_types=1);

namespace App\Framework\Session\Store;

interface SessionStoreInterface
{
    /** @param array{session_id: string, csrf_token: string, ip_address: string, user_agent_hash: string, expires_at: string} $sessionData */
    public function save(int $userId, array $sessionData): bool;

    public function delete(int $userId, string $sessionId): bool;

    public function getActiveSessions(int $userId): array;

    public function deleteOtherSessions(int $userId, string $currentSessionId): bool;

    public function cleanExpired(?int $userId = null): void;
}
