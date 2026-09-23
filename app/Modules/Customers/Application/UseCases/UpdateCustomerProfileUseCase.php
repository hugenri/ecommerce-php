<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Domain\CustomerRepositoryInterface;

class UpdateCustomerProfileUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function execute(
        int $customerId,
        string $firstName,
        string $lastNamePaternal,
        ?string $lastNameMaternal,
        ?string $phone,
    ): void {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            throw new \DomainException('Cliente no encontrado.');
        }

        $updated = $customer->withProfile(
            firstName: $firstName,
            lastNamePaternal: $lastNamePaternal,
            lastNameMaternal: $lastNameMaternal,
            phone: $phone,
        );

        $this->customerRepository->update($updated);
    }
}
