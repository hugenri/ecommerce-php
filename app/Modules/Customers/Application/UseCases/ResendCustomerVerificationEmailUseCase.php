<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Domain\CustomerRepositoryInterface;

class ResendCustomerVerificationEmailUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private SendCustomerVerificationEmailUseCase $sendVerificationEmail,
    ) {}

    public function execute(string $email): void
    {
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer || $customer->isEmailVerified()) {
            return;
        }

        $this->sendVerificationEmail->execute($customer);
    }
}