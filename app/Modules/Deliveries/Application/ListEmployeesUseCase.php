<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Application;

use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class ListEmployeesUseCase
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    /** @return array */
    public function execute(): array
    {
        return $this->deliveryRepository->findActiveEmployees();
    }
}
