<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\Authentication;

use App\Core\Database\Database;
use App\Modules\Identity\Domain\PasswordHasherInterface;
use App\Modules\Identity\Domain\UserToken;
use App\Modules\Identity\Domain\UserTokenRepositoryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;

class ResetPasswordUseCase
{
    public function __construct(
        private UserTokenRepositoryInterface $userTokenRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private Database $database,
    ) {}

    public function execute(string $token, string $newPassword): bool
    {
        if ($token === '') {
            return false;
        }

        $hash = hash('sha256', $token);

        $userToken = $this->userTokenRepository->findValidToken($hash, UserToken::PASSWORD_RESET);

        if (!$userToken) {
            return false;
        }

        $this->database->transaction(function () use ($userToken, $newPassword) {
            $this->userTokenRepository->markAsUsed($userToken->getId());

            $user = $this->userRepository->findById($userToken->getUserId());

            if (!$user) {
                return;
            }

            $user = $user->setPassword($this->passwordHasher->hash($newPassword));
            $this->userRepository->save($user);
        });

        return true;
    }
}