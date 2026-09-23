<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\CustomerRepositoryInterface;

class GetCustomerProfileUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function execute(int $id): ?Customer
    {
        return $this->customerRepository->findById($id);
    }
}
