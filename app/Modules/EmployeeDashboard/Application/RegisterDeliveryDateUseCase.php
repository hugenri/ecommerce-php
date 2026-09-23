<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Application;

use App\Modules\Deliveries\Application\RegisterDeliveryDateUseCase as DeliveriesRegisterDeliveryDateUseCase;
use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;

class RegisterDeliveryDateUseCase
{
    public function __construct(
        private EmployeeDashboardRepositoryInterface $repository,
        private DeliveriesRegisterDeliveryDateUseCase $deliveriesRegisterDeliveryDate,
    ) {}

    public function execute(int $deliveryId, string $date, int $userId): void
    {
        $this->assertCanModify($deliveryId, $userId);
        $this->deliveriesRegisterDeliveryDate->execute($deliveryId, $date);
    }

    private function assertCanModify(int $deliveryId, int $userId): void
    {
        $delivery = $this->repository->findById($deliveryId);
        if (!$delivery) {
            throw new \DomainException('Entrega no encontrada.');
        }

        if ((int) ($delivery['user_id'] ?? 0) !== $userId) {
            throw new \DomainException('Solo puedes modificar entregas asignadas a ti.');
        }

        if (($delivery['status'] ?? '') === 'cancelled' || ($delivery['sale_status'] ?? '') === 'cancelled') {
            throw new \DomainException('No se puede modificar una entrega cancelada.');
        }
    }
}
