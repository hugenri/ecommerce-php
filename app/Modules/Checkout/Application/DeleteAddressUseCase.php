<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application;

use App\Modules\Checkout\Domain\AddressRepositoryInterface;

class DeleteAddressUseCase
{
    public function __construct(
        private AddressRepositoryInterface $addressRepository,
    ) {}

    public function execute(int $addressId, int $customerId): void
    {
        $address = $this->addressRepository->findById($addressId);
        if (!$address) {
            throw new \DomainException('Dirección no encontrada.');
        }
        if ($address->getCustomerId() !== $customerId) {
            throw new \DomainException('No tienes permiso para eliminar esta dirección.');
        }

        $this->addressRepository->delete($addressId);
    }
}
