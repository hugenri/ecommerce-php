<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain;

interface DashboardRepositoryInterface
{
    /**
     * @return array{
     *     sales_today: float,
     *     sales_month: float,
     *     orders_pending: int,
     *     orders_shipped: int,
     *     customers_registered: int,
     *     products_active: int,
     *     products_out_of_stock: int,
     *     products_with_discount: int
     * }
     */
    public function getSummary(): array;

    /** @return array<int, array{sale_date: string, orders: int, total: float}> */
    public function getSalesLast7Days(): array;

    /** @return array<int, array{product_id: int, product_name: string, quantity_sold: int}> */
    public function getTopSellingProducts(int $limit = 10): array;

    /** @return array<int, array{category_name: string, quantity_sold: int, amount_sold: float}> */
    public function getSalesByCategory(): array;
}
