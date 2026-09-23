<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\User;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;

class ChangeUserPasswordUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher
    ) {}

    public function execute(int $id, string $currentPassword, string $newPassword): bool
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            return false;
        }

        if (!$user->verifyPassword($currentPassword, $this->passwordHasher)) {
            return false;
        }

        $user = $user->setPassword($this->passwordHasher->hash($newPassword));
        $this->userRepository->save($user);

        return true;
    }
}
