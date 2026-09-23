<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTO;

use App\Modules\Identity\Domain\User;

class AuthenticationResult
{
    public function __construct(
        private User $user,
        private array $sessionData,
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSessionData(): array
    {
        return $this->sessionData;
    }
}
