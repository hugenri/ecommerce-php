<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\User;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\User;

class ToggleUserActiveUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $id, int $actorId): ?User
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            return null;
        }

        if ($user->isActive()) {
            if ($id === $actorId) {
                throw new \DomainException('No puedes desactivar tu propia cuenta.');
            }

            if ($user->hasRole('admin') && $this->isLastActiveAdmin($id)) {
                throw new \DomainException('No se puede desactivar el último administrador del sistema.');
            }

            $user = $user->deactivate();
        } else {
            $user = $user->activate();
        }

        return $this->userRepository->save($user);
    }

    private function isLastActiveAdmin(int $exceptId): bool
    {
        $activeAdmins = 0;

        foreach ($this->userRepository->all() as $row) {
            if (($row['role'] ?? '') !== 'admin') {
                continue;
            }

            if (!(bool) ($row['is_active'] ?? false)) {
                continue;
            }

            if ((int) ($row['user_id'] ?? 0) === $exceptId) {
                continue;
            }

            $activeAdmins++;
        }

        return $activeAdmins === 0;
    }
}
