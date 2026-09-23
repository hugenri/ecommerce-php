<?php

declare(strict_types=1);

namespace App\Modules\Reports\Persistence;

use App\Core\Database\Database;
use App\Modules\Reports\Domain\ReportRepositoryInterface;

class ReportRepository implements ReportRepositoryInterface
{
    public function __construct(
        private Database $db,
    ) {}

    public function salesSummary(array $filters = []): array
    {
        [$whereClause, $params] = $this->salesWhere($filters);

        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS total_sales, COALESCE(SUM(s.total), 0) AS total_sold
             FROM sales s
             {$whereClause}",
            $params
        );

        return [
            'total_sales' => (int) ($row['total_sales'] ?? 0),
            'total_sold' => (float) ($row['total_sold'] ?? 0),
        ];
    }

    public function topProducts(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $where = ["s.status <> 'cancelled'"];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 's.sale_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.sale_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $countRow = $this->db->selectOne(
            "SELECT COUNT(DISTINCT p.product_id) AS total
             FROM sale_details sd
             INNER JOIN sales s ON sd.sale_id = s.sale_id
             INNER JOIN products p ON sd.product_id = p.product_id
             {$whereClause}",
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT p.product_code, p.name AS product_name,
                    SUM(sd.quantity) AS quantity_sold,
                    COALESCE(SUM(sd.subtotal), 0) AS total_sold
             FROM sale_details sd
             INNER JOIN sales s ON sd.sale_id = s.sale_id
             INNER JOIN products p ON sd.product_id = p.product_id
             {$whereClause}
             GROUP BY p.product_id, p.product_code, p.name
             ORDER BY quantity_sold DESC, p.name ASC
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $perPage, 'offset' => $offset])
        );

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    public function deliveriesSummary(array $filters = []): array
    {
        [$whereClause, $params] = $this->deliveriesWhere($filters);

        $row = $this->db->selectOne(
            "SELECT
                SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN d.status = 'preparing' THEN 1 ELSE 0 END) AS preparing,
                SUM(CASE WHEN d.status = 'shipped' THEN 1 ELSE 0 END) AS shipped,
                SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN d.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
             FROM deliveries d
             INNER JOIN sales s ON d.sale_id = s.sale_id
             {$whereClause}",
            $params
        );

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'preparing' => (int) ($row['preparing'] ?? 0),
            'shipped' => (int) ($row['shipped'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
        ];
    }

    private function salesWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 's.sale_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.sale_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['payment_status'])) {
            $where[] = 's.payment_status = :payment_status';
            $params['payment_status'] = $filters['payment_status'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereClause, $params];
    }

    private function deliveriesWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 's.sale_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.sale_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereClause, $params];
    }
}
