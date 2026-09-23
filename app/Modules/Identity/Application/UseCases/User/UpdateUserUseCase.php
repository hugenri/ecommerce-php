<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\User;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\User;

class UpdateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $id, array $data): ?User
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            return null;
        }

        $email = strtolower(trim($data['email'] ?? $user->getEmail()));
        if ($email !== $user->getEmail() && $this->userRepository->emailExists($email, $id)) {
            throw new \DomainException('El correo electrónico ya está registrado.');
        }

        $role = $data['role'] ?? $user->getRole();
        if (!in_array($role, ['admin', 'employee'])) {
            $role = $user->getRole();
        }

        $user = $user->updateProfile(
            name: trim($data['name'] ?? $user->getName()),
            email: $email,
            phone: trim($data['phone'] ?? $user->getPhone() ?? '') ?: null,
        );

        $user = $user->changeRole($role);

        if (isset($data['is_active'])) {
            $user = (bool) $data['is_active'] ? $user->activate() : $user->deactivate();
        }

        return $this->userRepository->save($user);
    }
}
