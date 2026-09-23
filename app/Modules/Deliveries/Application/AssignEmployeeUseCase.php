<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Application;

use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class AssignEmployeeUseCase
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    public function execute(int $deliveryId, int $userId): void
    {
        $delivery = $this->deliveryRepository->findById($deliveryId);
        if (!$delivery) {
            throw new \DomainException('Entrega no encontrada.');
        }

        if (!$this->deliveryRepository->employeeExistsAndActive($userId)) {
            throw new \DomainException('Empleado no encontrado o inactivo.');
        }

        $this->deliveryRepository->assignEmployee($deliveryId, $userId);
    }
}
