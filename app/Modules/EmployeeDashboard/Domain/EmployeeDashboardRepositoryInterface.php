<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Domain;

interface EmployeeDashboardRepositoryInterface
{
    /** @return array{ pending: int, assigned: int, preparing: int, shipped_today: int, delivered_today: int } */
    public function getSummary(int $userId): array;

    /** @return array{data: array, meta: array} */
    public function findMyDeliveries(int $userId, int $page = 1, int $perPage = 10): array;

    /** @return array{data: array, meta: array} */
    public function findUnassigned(int $page = 1, int $perPage = 10): array;

    /** @return array|null */
    public function findById(int $deliveryId): ?array;

    /** @return array */
    public function findProductsBySaleId(int $saleId): array;

    public function employeeExistsAndActive(int $userId): bool;

    public function isAssignedTo(int $deliveryId, int $userId): bool;

    public function assignEmployee(int $deliveryId, int $userId): void;

    public function updateStatus(int $deliveryId, string $status): void;

    public function syncSaleStatus(int $saleId, string $status): void;

    public function registerShippingDate(int $deliveryId, string $date): void;

    public function registerDeliveryDate(int $deliveryId, string $date): void;
}
