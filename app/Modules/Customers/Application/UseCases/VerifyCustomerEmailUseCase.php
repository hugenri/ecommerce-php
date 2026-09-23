<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Core\Database\Database;
use App\Modules\Customers\Domain\CustomerRepositoryInterface;
use App\Modules\Customers\Domain\CustomerToken;
use App\Modules\Customers\Domain\CustomerTokenRepositoryInterface;

class VerifyCustomerEmailUseCase
{
    public function __construct(
        private CustomerTokenRepositoryInterface $customerTokenRepository,
        private CustomerRepositoryInterface $customerRepository,
        private Database $database,
    ) {}

    public function execute(string $token): void
    {
        if ($token === '') {
            throw new \DomainException('El enlace de verificación no es válido o ya fue utilizado.');
        }

        $hash = hash('sha256', $token);

        $customerToken = $this->customerTokenRepository->findValidToken($hash, CustomerToken::EMAIL_VERIFICATION);

        if (!$customerToken) {
            throw new \DomainException('El enlace de verificación no es válido o ya fue utilizado.');
        }

        $this->database->transaction(function () use ($customerToken) {
            $this->customerTokenRepository->markAsUsed($customerToken->getId());
            $this->customerRepository->markEmailVerified($customerToken->getCustomerId());
        });
    }
}