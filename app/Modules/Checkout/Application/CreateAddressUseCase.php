<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application;

use App\Modules\Checkout\Domain\Address;
use App\Modules\Checkout\Domain\AddressRepositoryInterface;

class CreateAddressUseCase
{
    public function __construct(
        private AddressRepositoryInterface $addressRepository,
    ) {}

    public function execute(
        int $customerId,
        string $street,
        string $number,
        string $neighborhood,
        string $municipality,
        string $state,
        string $zipCode,
        ?string $reference = null,
        ?bool $isDefault = null,
        ?string $alias = null,
    ): Address {
        $address = new Address(
            addressId: null,
            customerId: $customerId,
            street: $street,
            number: $number,
            neighborhood: $neighborhood,
            municipality: $municipality,
            state: $state,
            zipCode: $zipCode,
            reference: $reference,
            isDefault: $isDefault,
            alias: $alias,
        );

        return $this->addressRepository->create($address);
    }
}
