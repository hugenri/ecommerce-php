<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

interface AddressRepositoryInterface
{
    /** @return Address[] */
    public function findByCustomer(int $customerId): array;

    public function findById(int $addressId): ?Address;

    public function create(Address $address): Address;

    public function update(Address $address): Address;

    public function delete(int $addressId): void;

    public function setDefault(int $customerId, int $addressId): void;
}
