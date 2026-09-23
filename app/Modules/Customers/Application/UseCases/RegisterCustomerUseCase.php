<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\CustomerRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;

class RegisterCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private PasswordHasherInterface $passwordHasher,
        private SendCustomerVerificationEmailUseCase $sendVerificationEmail,
    ) {}

    public function execute(
        string $firstName,
        string $lastNamePaternal,
        string $email,
        string $password,
        ?string $lastNameMaternal = null,
        ?string $phone = null,
    ): Customer {
        $existing = $this->customerRepository->findByEmail($email);
        if ($existing) {
            throw new \DomainException('El email ya está registrado.');
        }

        $hashedPassword = $this->passwordHasher->hash($password);

        $customer = new Customer(
            customerId: null,
            firstName: $firstName,
            lastNamePaternal: $lastNamePaternal,
            email: $email,
            password: $hashedPassword,
            lastNameMaternal: $lastNameMaternal,
            phone: $phone,
            active: true,
        );

        $customer = $this->customerRepository->create($customer);

        $this->sendVerificationEmail->execute($customer);

        return $customer;
    }
}
