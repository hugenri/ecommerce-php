<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Domain\CustomerRepositoryInterface;

class SearchCustomersUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function execute(string $query, int $limit = 10): array
    {
        return $this->customerRepository->search($query, $limit);
    }
}
