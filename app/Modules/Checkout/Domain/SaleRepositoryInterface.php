<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

interface SaleRepositoryInterface
{
    public function transaction(callable $callback): mixed;

    public function createSale(Sale $sale): Sale;

    public function createDetail(SaleDetail $detail): SaleDetail;

    public function createDelivery(Delivery $delivery): Delivery;

    public function findSaleById(int $saleId): ?Sale;

    public function findBySaleCode(string $saleCode): ?Sale;

    public function findDetailsBySaleId(int $saleId): array;

    /** @return array{data: array, meta: array} */
    public function findByCustomer(int $customerId, int $page = 1, int $perPage = 10): array;

    public function findDeliveryBySaleId(int $saleId): ?Delivery;

    /** @return array{data: array, meta: array} */
    public function findAll(int $page = 1, int $perPage = 10, string $search = '', string $sortBy = 'sale_date', string $sortDir = 'DESC', array $filters = []): array;

    public function updateSaleStatus(int $saleId, string $status): void;

    public function updatePaymentStatus(int $saleId, string $paymentStatus): void;

    public function updateSaleNotes(int $saleId, string $notes): void;

    /** @return array */
    public function getDetailsForCancel(int $saleId): array;

    public function cancelDeliveryForSale(int $saleId): void;
}
