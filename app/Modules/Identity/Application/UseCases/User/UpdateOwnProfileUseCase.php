<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\User;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;

class UpdateOwnProfileUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $userId, string $name, string $email, ?string $phone = null): ?User
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            return null;
        }

        $email = strtolower(trim($email));

        if ($email !== $user->getEmail() && $this->userRepository->emailExists($email, $userId)) {
            throw new \DomainException('El correo electrónico ya está registrado.');
        }

        $user = $user->updateProfile(
            name: trim($name),
            email: $email,
            phone: trim($phone ?? '') ?: null,
        );

        return $this->userRepository->save($user);
    }
}
