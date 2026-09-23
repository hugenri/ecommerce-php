<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Domain\CustomerRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;

class ChangePasswordUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private PasswordHasherInterface $hasher,
    ) {}

    public function execute(
        int $customerId,
        string $currentPassword,
        string $newPassword,
    ): void {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            throw new \DomainException('Cliente no encontrado.');
        }

        if (!$customer->verifyPassword($currentPassword, $this->hasher)) {
            throw new \DomainException('La contraseña actual no es correcta.');
        }

        $hashed = $this->hasher->hash($newPassword);
        $updated = $customer->setPassword($hashed);
        $this->customerRepository->update($updated);
    }
}
