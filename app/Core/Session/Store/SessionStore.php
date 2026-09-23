<?php

declare(strict_types=1);

namespace App\Core\Session\Store;

use App\Core\Database\Database;
use App\Framework\Session\Store\SessionStoreInterface;

class SessionStore implements SessionStoreInterface
{
    public function __construct(
        private Database $db,
        private int $lifetime = 7200,
    ) {}

    public function save(int $userId, array $sessionData): bool
    {
        $this->cleanExpired($userId);

        $data = [
            'user_id' => $userId,
            'session_id' => $sessionData['session_id'],
            'csrf_token' => $sessionData['csrf_token'],
            'ip_address' => $sessionData['ip_address'],
            'user_agent_hash' => $sessionData['user_agent_hash'],
            'expires_at' => $sessionData['expires_at'],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $sql = "INSERT INTO user_sessions 
                (user_id, session_id, csrf_token, ip_address, user_agent_hash, expires_at, created_at) 
                VALUES 
                (:user_id, :session_id, :csrf_token, :ip_address, :user_agent_hash, :expires_at, :created_at)
                ON DUPLICATE KEY UPDATE
                csrf_token = VALUES(csrf_token),
                ip_address = VALUES(ip_address),
                expires_at = VALUES(expires_at),
                updated_at = NOW()";

        $result = $this->db->query($sql, $data);
        return $result->rowCount() > 0;
    }

    public function delete(int $userId, string $sessionId): bool
    {
        $sql = "DELETE FROM user_sessions 
                WHERE user_id = :user_id 
                AND session_id = :session_id";

        $result = $this->db->query($sql, [
            'user_id' => $userId,
            'session_id' => $sessionId,
        ]);

        return $result->rowCount() > 0;
    }

    public function getActiveSessions(int $userId): array
    {
        $sql = "SELECT * FROM user_sessions 
                WHERE user_id = :user_id 
                AND expires_at > NOW() 
                ORDER BY created_at DESC";

        return $this->db->select($sql, ['user_id' => $userId]);
    }

    public function deleteOtherSessions(int $userId, string $currentSessionId): bool
    {
        $sql = "DELETE FROM user_sessions 
                WHERE user_id = :user_id 
                AND session_id != :session_id";

        $result = $this->db->query($sql, [
            'user_id' => $userId,
            'session_id' => $currentSessionId,
        ]);

        return $result->rowCount() > 0;
    }

    public function cleanExpired(?int $userId = null): void
    {
        $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";

        if ($userId) {
            $sql .= " AND user_id = :user_id";
            $this->db->query($sql, ['user_id' => $userId]);
        } else {
            $this->db->query($sql);
        }
    }
}
