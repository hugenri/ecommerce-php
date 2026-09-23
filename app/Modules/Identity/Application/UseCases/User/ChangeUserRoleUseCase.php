<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\User;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\User;

class ChangeUserRoleUseCase
{
    private const ALLOWED_ROLES = ['admin', 'employee'];

    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $id, string $newRole): ?User
    {
        if (!in_array($newRole, self::ALLOWED_ROLES, true)) {
            return null;
        }

        $user = $this->userRepository->findById($id);

        if (!$user) {
            return null;
        }

        if ($user->hasRole('admin') && $newRole !== 'admin' && $this->userRepository->countAdmins() <= 1) {
            throw new \DomainException('No se puede cambiar el rol del último administrador.');
        }

        $user = $user->changeRole($newRole);

        return $this->userRepository->save($user);
    }
}
