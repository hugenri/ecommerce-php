<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Admin;

use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Checkout\Domain\AddressRepositoryInterface;

class GetSaleUseCase
{
    public function __construct(
        private SaleRepositoryInterface $saleRepository,
        private AddressRepositoryInterface $addressRepository,
    ) {}

    public function execute(int $saleId): ?array
    {
        $sale = $this->saleRepository->findSaleById($saleId);
        if (!$sale) return null;

        $details = $this->saleRepository->findDetailsBySaleId($saleId);
        $delivery = $this->saleRepository->findDeliveryBySaleId($saleId);
        $address = $this->addressRepository->findById($sale->getAddressId());

        return [
            'sale' => $sale,
            'details' => $details,
            'delivery' => $delivery,
            'address' => $address,
        ];
    }
}
