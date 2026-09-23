<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Domain;

interface DeliveryRepositoryInterface
{
    /** @return array{data: array, meta: array} */
    public function findAll(int $page = 1, int $perPage = 10, string $search = '', array $filters = []): array;

    /** @return array|null */
    public function findById(int $deliveryId): ?array;

    /** @return array */
    public function findProductsBySaleId(int $saleId): array;

    /** @return array{data: array, meta: array} */
    public function search(string $term, int $page = 1, int $perPage = 10): array;

    /** @return array{data: array, meta: array} */
    public function findPending(int $page = 1, int $perPage = 10): array;

    /** @return array{data: array, meta: array} */
    public function findByEmployee(int $userId, int $page = 1, int $perPage = 10): array;

    /** @return array */
    public function findActiveEmployees(): array;

    public function employeeExistsAndActive(int $userId): bool;

    public function assignEmployee(int $deliveryId, int $userId): void;

    public function updateStatus(int $deliveryId, string $status): void;

    public function syncSaleStatus(int $saleId, string $status): void;

    public function registerShippingDate(int $deliveryId, string $date): void;

    public function registerDeliveryDate(int $deliveryId, string $date): void;
}
