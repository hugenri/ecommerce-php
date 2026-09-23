<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Application;

use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class GetDeliveryUseCase
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    public function execute(int $deliveryId): ?array
    {
        $delivery = $this->deliveryRepository->findById($deliveryId);
        if (!$delivery) {
            return null;
        }

        return [
            'delivery' => $delivery,
            'products' => $this->deliveryRepository->findProductsBySaleId((int) $delivery['sale_id']),
            'employees' => $this->deliveryRepository->findActiveEmployees(),
        ];
    }
}
