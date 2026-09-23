<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\Authentication;

use App\Framework\Session\Store\SessionStoreInterface;

class LogoutUserUseCase
{
    public function __construct(
        private SessionStoreInterface $sessionStore
    ) {}

    public function execute(?int $userId, string $sessionId): void
    {
        if ($userId) {
            $this->sessionStore->delete($userId, $sessionId);
        }
    }
}
