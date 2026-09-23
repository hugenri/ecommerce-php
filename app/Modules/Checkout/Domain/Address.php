<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

class Address
{
    public function __construct(
        private readonly ?int $addressId,
        private int $customerId,
        private string $street,
        private string $number,
        private string $neighborhood,
        private string $municipality,
        private string $state,
        private string $zipCode,
        private ?string $reference = null,
        private ?bool $isDefault = null,
        private ?string $alias = null,
    ) {}

    public function getAddressId(): ?int { return $this->addressId; }
    public function getCustomerId(): int { return $this->customerId; }
    public function getStreet(): string { return $this->street; }
    public function getNumber(): string { return $this->number; }
    public function getNeighborhood(): string { return $this->neighborhood; }
    public function getMunicipality(): string { return $this->municipality; }
    public function getState(): string { return $this->state; }
    public function getZipCode(): string { return $this->zipCode; }
    public function getReference(): ?string { return $this->reference; }
    public function isDefault(): ?bool { return $this->isDefault; }
    public function getAlias(): ?string { return $this->alias; }

    public function withAddressId(int $addressId): self
    {
        return new self(
            addressId: $addressId,
            customerId: $this->customerId,
            street: $this->street,
            number: $this->number,
            neighborhood: $this->neighborhood,
            municipality: $this->municipality,
            state: $this->state,
            zipCode: $this->zipCode,
            reference: $this->reference,
            isDefault: $this->isDefault,
            alias: $this->alias,
        );
    }

    public function getFullAddress(): string
    {
        $addr = "{$this->street} #{$this->number}, {$this->neighborhood}, {$this->municipality}, {$this->state}, CP {$this->zipCode}";
        if ($this->reference) {
            $addr .= " ({$this->reference})";
        }
        return $addr;
    }
}
