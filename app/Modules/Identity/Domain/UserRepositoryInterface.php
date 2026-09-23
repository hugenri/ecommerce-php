<?php

namespace App\Modules\Identity\Domain;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;
    /**
     * Busca un usuario por correo sin importar su estado.
     *
     * Uso exclusivo para el flujo de autenticación.
     * Para el resto del sistema utilizar findByEmail().
     */

    public function findByEmailIncludingInactive(string $email): ?User;

    public function save(User $user): User;

    public function delete(int $id): bool;

    public function emailExists(string $email, int $exceptId = 0): bool;

    public function countAdmins(): int;

    public function search(string $query, int $limit = 10): array;

    public function all(): array;

    /** @return array{data: array, meta: array} */
    public function paginate(
        int $page = 1,
        int $perPage = 5,
        string $search = '',
        string $sortBy = 'user_id',
        string $sortDir = 'ASC',
        array $filters = []
    ): array;
}
