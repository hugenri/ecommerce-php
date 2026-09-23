<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\Authentication;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;
use App\Modules\Identity\Domain\User;

class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher
    ) {}

    public function execute(array $data): User
    {
        $email = strtolower(trim($data['email']));

        if ($this->userRepository->findByEmail($email)) {
            throw new \DomainException('El correo electrónico ya está registrado.');
        }

        $user = new User(
            id: null,
            name: trim($data['name']),
            email: $email,
            password: $this->passwordHasher->hash($data['password']),
            role: 'employee',
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        return $this->userRepository->save($user);
    }
}
