<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\User;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;
use App\Modules\Identity\Domain\User;

class CreateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher
    ) {}

    public function execute(array $data): User
    {
        $email = strtolower(trim($data['email']));

        if ($this->userRepository->emailExists($email)) {
            throw new \DomainException('El correo electrónico ya está registrado.');
        }

        $user = new User(
            id: null,
            name: trim($data['name']),
            email: $email,
            password: $this->passwordHasher->hash($data['password'] ?? ''),
            role: in_array($data['role'] ?? '', ['admin', 'employee']) ? $data['role'] : 'employee',
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
            phone: trim($data['phone'] ?? ''),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        return $this->userRepository->save($user);
    }
}
