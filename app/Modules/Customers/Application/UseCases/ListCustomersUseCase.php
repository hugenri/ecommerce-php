<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Domain\CustomerRepositoryInterface;

class ListCustomersUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function execute(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'customers.name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array {
        return $this->customerRepository->paginate($page, $perPage, $search, $sortBy, $sortDir, $filters);
    }
}
