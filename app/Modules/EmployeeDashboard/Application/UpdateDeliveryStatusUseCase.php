<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Application;

use App\Modules\Deliveries\Application\UpdateDeliveryStatusUseCase as DeliveriesUpdateDeliveryStatusUseCase;
use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;

class UpdateDeliveryStatusUseCase
{
    private const ALLOWED_TRANSITIONS = [
        'pending' => 'preparing',
        'preparing' => 'shipped',
        'shipped' => 'delivered',
    ];

    public function __construct(
        private EmployeeDashboardRepositoryInterface $repository,
        private DeliveriesUpdateDeliveryStatusUseCase $deliveriesUpdateStatus,
    ) {}

    public function execute(int $deliveryId, string $status, int $userId): void
    {
        if ($status === 'cancelled') {
            throw new \DomainException('El empleado no puede cancelar pedidos.');
        }

        if ($status === 'pending') {
            throw new \DomainException('No puedes regresar el estado de la entrega.');
        }

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

        $current = $delivery['status'] ?? '';
        $expected = self::ALLOWED_TRANSITIONS[$current] ?? null;

        if ($status !== $expected) {
            throw new \DomainException('Solo se permite avanzar al siguiente estado de la entrega.');
        }

        $this->deliveriesUpdateStatus->execute($deliveryId, $status);

        if ($status === 'shipped') {
            $this->repository->registerShippingDate($deliveryId, date('Y-m-d H:i:s'));
        }

        if ($status === 'delivered') {
            $this->repository->registerDeliveryDate($deliveryId, date('Y-m-d H:i:s'));
        }
    }
}
