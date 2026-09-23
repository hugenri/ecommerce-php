<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Application;

use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;

class GetDeliveryUseCase
{
    public function __construct(
        private EmployeeDashboardRepositoryInterface $repository,
    ) {}

    /** @return array{delivery: array, products: array}|null */
    public function execute(int $deliveryId, int $userId): ?array
    {
        $delivery = $this->repository->findById($deliveryId);
        if (!$delivery) {
            return null;
        }

        if (!$this->isVisibleToEmployee($delivery, $userId)) {
            throw new \DomainException('No tienes permisos para ver esta entrega.');
        }

        return [
            'delivery' => $delivery,
            'products' => $this->repository->findProductsBySaleId((int) $delivery['sale_id']),
        ];
    }

    private function isVisibleToEmployee(array $delivery, int $userId): bool
    {
        $assignedTo = $delivery['user_id'] ?? null;
        if ($assignedTo === null) {
            return true;
        }

        return (int) $assignedTo === $userId;
    }
}
