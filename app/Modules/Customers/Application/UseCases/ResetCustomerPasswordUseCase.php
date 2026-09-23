<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Core\Database\Database;
use App\Modules\Customers\Domain\CustomerRepositoryInterface;
use App\Modules\Customers\Domain\CustomerToken;
use App\Modules\Customers\Domain\CustomerTokenRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;

class ResetCustomerPasswordUseCase
{
    public function __construct(
        private CustomerTokenRepositoryInterface $customerTokenRepository,
        private CustomerRepositoryInterface $customerRepository,
        private PasswordHasherInterface $passwordHasher,
        private Database $database,
    ) {}

    public function execute(string $token, string $newPassword): bool
    {
        if ($token === '') {
            return false;
        }

        $hash = hash('sha256', $token);

        $customerToken = $this->customerTokenRepository->findValidToken($hash, CustomerToken::PASSWORD_RESET);

        if (!$customerToken) {
            return false;
        }

        $this->database->transaction(function () use ($customerToken, $newPassword) {
            $this->customerTokenRepository->markAsUsed($customerToken->getId());

            $customer = $this->customerRepository->findById($customerToken->getCustomerId());

            if (!$customer) {
                return;
            }

            $customer = $customer->setPassword($this->passwordHasher->hash($newPassword));
            $this->customerRepository->update($customer);
        });

        return true;
    }
}