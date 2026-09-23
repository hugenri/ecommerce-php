<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

interface CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer;

    public function findByEmail(string $email): ?Customer;

    public function create(Customer $customer): Customer;

    /** @return array{data: array, meta: array} */
    public function paginate(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'customers.name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array;

    /** @return Customer[] */
    public function search(string $query, int $limit = 10): array;

    public function activate(int $id): ?Customer;

    public function deactivate(int $id): ?Customer;

    public function update(Customer $customer): Customer;

    public function updateLoginTimestamp(int $id): void;

    public function markEmailVerified(int $customerId): void;
}
