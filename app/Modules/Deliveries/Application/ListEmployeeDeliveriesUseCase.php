<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Application;

use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class ListEmployeeDeliveriesUseCase
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    public function execute(int $userId, int $page = 1, int $perPage = 10): array
    {
        return $this->deliveryRepository->findByEmployee($userId, $page, $perPage);
    }
}
