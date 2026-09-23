<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Persistence;

use App\Core\Database\Database;
use App\Modules\Checkout\Domain\Address;
use App\Modules\Checkout\Domain\AddressRepositoryInterface;

class AddressRepository implements AddressRepositoryInterface
{
    public function __construct(
        private Database $db,
    ) {}

    public function findByCustomer(int $customerId): array
    {
        $rows = $this->db->select(
            'SELECT * FROM addresses WHERE customer_id = :customer_id ORDER BY is_default DESC, address_id ASC',
            ['customer_id' => $customerId]
        );
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function findById(int $addressId): ?Address
    {
        $row = $this->db->selectOne(
            'SELECT * FROM addresses WHERE address_id = :id LIMIT 1',
            ['id' => $addressId]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function update(Address $address): Address
    {
        $data = [
            'street' => $address->getStreet(),
            'number' => $address->getNumber(),
            'neighborhood' => $address->getNeighborhood(),
            'municipality' => $address->getMunicipality(),
            'state' => $address->getState(),
            'zip_code' => $address->getZipCode(),
            'reference' => $address->getReference(),
            'is_default' => $address->isDefault() !== null ? ($address->isDefault() ? 1 : 0) : null,
            'alias' => $address->getAlias(),
        ];

        $this->db->update('addresses', $data, ['address_id' => $address->getAddressId()]);
        return $address;
    }

    public function delete(int $addressId): void
    {
        $this->db->delete('addresses', ['address_id' => $addressId]);
    }

    public function setDefault(int $customerId, int $addressId): void
    {
        $this->db->update('addresses', ['is_default' => 0], ['customer_id' => $customerId]);
        $this->db->update('addresses', ['is_default' => 1], ['address_id' => $addressId]);
    }

    public function create(Address $address): Address
    {
        $data = [
            'customer_id' => $address->getCustomerId(),
            'street' => $address->getStreet(),
            'number' => $address->getNumber(),
            'neighborhood' => $address->getNeighborhood(),
            'municipality' => $address->getMunicipality(),
            'state' => $address->getState(),
            'zip_code' => $address->getZipCode(),
            'reference' => $address->getReference(),
            'is_default' => $address->isDefault() !== null ? ($address->isDefault() ? 1 : 0) : null,
            'alias' => $address->getAlias(),
        ];

        $id = $this->db->insert('addresses', $data);
        return $address->withAddressId((int) $id);
    }

    private function hydrate(array $row): Address
    {
        return new Address(
            addressId: (int) $row['address_id'],
            customerId: (int) $row['customer_id'],
            street: $row['street'],
            number: $row['number'],
            neighborhood: $row['neighborhood'],
            municipality: $row['municipality'],
            state: $row['state'],
            zipCode: $row['zip_code'],
            reference: $row['reference'] ?? null,
            isDefault: isset($row['is_default']) ? (bool) $row['is_default'] : null,
            alias: $row['alias'] ?? null,
        );
    }
}
