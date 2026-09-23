<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\User;

use App\Modules\Identity\Domain\UserRepositoryInterface;

class ListUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(
        int $page = 1,
        int $perPage = 5,
        string $search = '',
        string $sortBy = 'user_id',
        string $sortDir = 'ASC',
        array $filters = []
    ): array {
        return $this->userRepository->paginate($page, $perPage, $search, $sortBy, $sortDir, $filters);
    }
}
