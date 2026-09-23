<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Persistence;

use App\Core\Database\Database;
use App\Modules\Dashboard\Domain\DashboardRepositoryInterface;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(
        private Database $db,
    ) {}

    public function getSummary(): array
    {
        $salesRow = $this->db->selectOne(
            "SELECT COALESCE(SUM(total), 0) AS sales_month,
                    COALESCE(SUM(CASE WHEN sale_date >= CURDATE() THEN total END), 0) AS sales_today
             FROM sales
             WHERE status <> 'cancelled' AND sale_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );

        $ordersRow = $this->db->selectOne(
            "SELECT SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) AS shipped
             FROM sales"
        );

        $customers = $this->db->selectOne(
            'SELECT COUNT(*) AS total FROM customers'
        );

        $products = $this->db->selectOne(
            "SELECT
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN discount > 0 THEN 1 ELSE 0 END) AS with_discount
             FROM products"
        );

        return [
            'sales_today' => (float) ($salesRow['sales_today'] ?? 0),
            'sales_month' => (float) ($salesRow['sales_month'] ?? 0),
            'orders_pending' => (int) ($ordersRow['pending'] ?? 0),
            'orders_shipped' => (int) ($ordersRow['shipped'] ?? 0),
            'customers_registered' => (int) ($customers['total'] ?? 0),
            'products_active' => (int) ($products['active'] ?? 0),
            'products_out_of_stock' => (int) ($products['out_of_stock'] ?? 0),
            'products_with_discount' => (int) ($products['with_discount'] ?? 0),
        ];
    }

    public function getSalesLast7Days(): array
    {
        $rows = $this->db->select(
            "SELECT DATE(sale_date) AS sale_date,
                    COUNT(*) AS orders,
                    COALESCE(SUM(total), 0) AS total
             FROM sales
             WHERE status <> 'cancelled' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(sale_date)
             ORDER BY sale_date ASC"
        );

        return array_map(function (array $row): array {
            return [
                'sale_date' => $row['sale_date'],
                'orders' => (int) $row['orders'],
                'total' => (float) $row['total'],
            ];
        }, $rows);
    }

    public function getTopSellingProducts(int $limit = 10): array
    {
        $rows = $this->db->select(
            "SELECT p.product_id, p.name AS product_name, SUM(sd.quantity) AS quantity_sold
             FROM sale_details sd
             INNER JOIN sales s ON sd.sale_id = s.sale_id
             INNER JOIN products p ON sd.product_id = p.product_id
             WHERE s.status <> 'cancelled'
             GROUP BY p.product_id, p.name
             ORDER BY quantity_sold DESC, p.name ASC
             LIMIT :limit",
            ['limit' => $limit]
        );

        return array_map(function (array $row): array {
            return [
                'product_id' => (int) $row['product_id'],
                'product_name' => $row['product_name'],
                'quantity_sold' => (int) $row['quantity_sold'],
            ];
        }, $rows);
    }

    public function getSalesByCategory(): array
    {
        $rows = $this->db->select(
            "SELECT c.name AS category_name,
                    SUM(sd.quantity) AS quantity_sold,
                    COALESCE(SUM(sd.subtotal), 0) AS amount_sold
             FROM sale_details sd
             INNER JOIN sales s ON sd.sale_id = s.sale_id
             INNER JOIN products p ON sd.product_id = p.product_id
             INNER JOIN subcategories sc ON p.subcategory_id = sc.subcategory_id
             INNER JOIN categories c ON sc.category_id = c.category_id
             WHERE s.status <> 'cancelled'
             GROUP BY c.category_id, c.name
             ORDER BY amount_sold DESC, c.name ASC"
        );

        return array_map(function (array $row): array {
            return [
                'category_name' => $row['category_name'],
                'quantity_sold' => (int) $row['quantity_sold'],
                'amount_sold' => (float) $row['amount_sold'],
            ];
        }, $rows);
    }
}
