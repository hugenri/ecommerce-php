<?php

declare(strict_types=1);

namespace App\Modules\Reports\Domain;

interface ReportRepositoryInterface
{
    /**
     * Resumen de ventas para los filtros dados.
     * Filtros soportados: date_from, date_to, status, payment_status.
     *
     * @return array{total_sales: int, total_sold: float}
     */
    public function salesSummary(array $filters = []): array;

    /**
     * Productos más vendidos con total vendido.
     * Filtros soportados: date_from, date_to.
     *
     * @return array{data: array, meta: array}
     */
    public function topProducts(array $filters = [], int $page = 1, int $perPage = 10): array;

    /**
     * Conteo de entregas por estado para los filtros dados.
     * Filtros soportados: status, date_from, date_to.
     *
     * @return array{pending: int, preparing: int, shipped: int, delivered: int, cancelled: int}
     */
    public function deliveriesSummary(array $filters = []): array;
}
