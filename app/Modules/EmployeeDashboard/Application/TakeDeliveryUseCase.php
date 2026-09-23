<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Application;

use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;

class TakeDeliveryUseCase
{
    public function __construct(
        private EmployeeDashboardRepositoryInterface $repository,
    ) {}

    public function execute(int $deliveryId, int $userId): void
    {
        $delivery = $this->repository->findById($deliveryId);
        if (!$delivery) {
            throw new \DomainException('Entrega no encontrada.');
        }

        if (($delivery['status'] ?? '') === 'cancelled' || ($delivery['sale_status'] ?? '') === 'cancelled') {
            throw new \DomainException('No se puede tomar una entrega cancelada.');
        }

        if (($delivery['user_id'] ?? null) !== null) {
            throw new \DomainException('Esta entrega ya tiene un empleado asignado.');
        }

        if (!$this->repository->employeeExistsAndActive($userId)) {
            throw new \DomainException('Empleado no encontrado o inactivo.');
        }

        $this->repository->assignEmployee($deliveryId, $userId);
    }
}
