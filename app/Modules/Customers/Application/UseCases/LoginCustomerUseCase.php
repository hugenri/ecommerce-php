<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Domain\CustomerRepositoryInterface;
use App\Modules\Customers\Domain\LoginResult;
use App\Modules\Identity\Domain\PasswordHasherInterface;

class LoginCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private PasswordHasherInterface $passwordHasher,
    ) {}

    public function execute(string $email, string $password): LoginResult
    {
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer) {
            return LoginResult::invalidCredentials();
        }

        if (!$customer->verifyPassword($password, $this->passwordHasher)) {
            return LoginResult::invalidCredentials();
        }

        if (!$customer->isActive()) {
            return LoginResult::accountDisabled();
        }

        if (!$customer->isEmailVerified()) {
            return LoginResult::emailNotVerified();
        }

        $this->customerRepository->updateLoginTimestamp($customer->getCustomerId());

        return LoginResult::success($customer);
    }
}