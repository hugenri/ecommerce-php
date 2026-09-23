<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\User;

use App\Modules\Identity\Domain\UserRepositoryInterface;

class DeleteUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $id): bool
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            return false;
        }

        if ($user->hasRole('admin') && $this->userRepository->countAdmins() <= 1) {
            throw new \DomainException('No se puede eliminar el último administrador del sistema.');
        }

        return $this->userRepository->delete($id);
    }
}
