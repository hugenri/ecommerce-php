<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Persistence;

use App\Core\Database\Database;
use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class DeliveryRepository implements DeliveryRepositoryInterface
{
    public function __construct(
        private Database $db,
    ) {}

    public function findAll(int $page = 1, int $perPage = 10, string $search = '', array $filters = []): array
    {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(s.sale_code LIKE :search OR c.first_name LIKE :search2 OR c.last_name_paternal LIKE :search3 OR c.email LIKE :search4)';
            $like = '%' . $this->db->escapeLike($search) . '%';
            $params['search'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
        }

        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['employee_id'])) {
            $where[] = 'd.user_id = :employee_id';
            $params['employee_id'] = (int) $filters['employee_id'];
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

        return $this->paginate($whereClause, $params, $page, $perPage);
    }

    public function findById(int $deliveryId): ?array
    {
        $row = $this->db->selectOne(
            "SELECT d.delivery_id, d.sale_id, d.user_id, d.shipping_date, d.delivery_date, d.status,
                    s.sale_code, s.sale_date, s.subtotal, s.tax, s.total, s.payment_method, s.payment_status, s.status AS sale_status, s.notes,
                    c.customer_id, c.first_name, c.last_name_paternal, c.last_name_maternal, c.email, c.phone,
                    u.user_id AS employee_id, u.name AS employee_name, u.email AS employee_email,
                    a.street, a.number, a.neighborhood, a.municipality, a.state, a.zip_code, a.reference, a.alias
             FROM deliveries d
             INNER JOIN sales s ON d.sale_id = s.sale_id
             INNER JOIN customers c ON s.customer_id = c.customer_id
             LEFT JOIN users u ON d.user_id = u.user_id
             LEFT JOIN addresses a ON s.address_id = a.address_id
             WHERE d.delivery_id = :id
             LIMIT 1",
            ['id' => $deliveryId]
        );

        return $row ?: null;
    }

    public function findProductsBySaleId(int $saleId): array
    {
        return $this->db->select(
            "SELECT sd.detail_id, sd.product_id, sd.quantity, sd.unit_price, sd.discount_percentage, sd.discounted_unit_price, sd.subtotal,
                    p.name AS product_name, p.product_code, p.image AS product_image
             FROM sale_details sd
             INNER JOIN products p ON sd.product_id = p.product_id
             WHERE sd.sale_id = :sale_id
             ORDER BY sd.detail_id ASC",
            ['sale_id' => $saleId]
        );
    }

    public function search(string $term, int $page = 1, int $perPage = 10): array
    {
        $like = '%' . $this->db->escapeLike($term) . '%';

        $whereClause = 'WHERE s.sale_code LIKE :term
            OR c.first_name LIKE :term2
            OR c.last_name_paternal LIKE :term3
            OR c.email LIKE :term4
            OR u.name LIKE :term5';

        return $this->paginate($whereClause, [
            'term' => $like,
            'term2' => $like,
            'term3' => $like,
            'term4' => $like,
            'term5' => $like,
        ], $page, $perPage);
    }

    public function findPending(int $page = 1, int $perPage = 10): array
    {
        return $this->paginate("WHERE d.status = 'pending'", [], $page, $perPage);
    }

    public function findByEmployee(int $userId, int $page = 1, int $perPage = 10): array
    {
        return $this->paginate('WHERE d.user_id = :user_id', ['user_id' => $userId], $page, $perPage);
    }

    public function findActiveEmployees(): array
    {
        return $this->db->select('SELECT user_id, name, email FROM users WHERE is_active = 1 ORDER BY name ASC');
    }

    public function employeeExistsAndActive(int $userId): bool
    {
        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS total FROM users WHERE user_id = :user_id AND is_active = 1',
            ['user_id' => $userId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function assignEmployee(int $deliveryId, int $userId): void
    {
        $this->db->update('deliveries', ['user_id' => $userId], ['delivery_id' => $deliveryId]);
    }

    public function updateStatus(int $deliveryId, string $status): void
    {
        $this->db->update('deliveries', ['status' => $status], ['delivery_id' => $deliveryId]);
    }

    public function syncSaleStatus(int $saleId, string $status): void
    {
        $this->db->update('sales', ['status' => $status], ['sale_id' => $saleId]);
    }

    public function registerShippingDate(int $deliveryId, string $date): void
    {
        $this->db->update('deliveries', ['shipping_date' => $date], ['delivery_id' => $deliveryId]);
    }

    public function registerDeliveryDate(int $deliveryId, string $date): void
    {
        $this->db->update('deliveries', ['delivery_date' => $date], ['delivery_id' => $deliveryId]);
    }

    private function baseSelect(): string
    {
        return "SELECT d.delivery_id, d.status AS delivery_status,
                       d.shipping_date, d.delivery_date,
                       s.sale_id, s.sale_code, s.sale_date, s.total, s.status AS sale_status, s.payment_status,
                       c.customer_id, CONCAT(c.first_name, ' ', c.last_name_paternal) AS customer_name, c.email AS customer_email,
                       u.user_id AS employee_id, u.name AS employee_name
                FROM deliveries d
                INNER JOIN sales s ON d.sale_id = s.sale_id
                INNER JOIN customers c ON s.customer_id = c.customer_id
                LEFT JOIN users u ON d.user_id = u.user_id";
    }

    private function paginate(string $whereClause, array $params, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS total
             FROM deliveries d
             INNER JOIN sales s ON d.sale_id = s.sale_id
             INNER JOIN customers c ON s.customer_id = c.customer_id
             LEFT JOIN users u ON d.user_id = u.user_id
             {$whereClause}",
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $rows = $this->db->select(
            $this->baseSelect() . " {$whereClause} ORDER BY d.delivery_id DESC LIMIT :limit OFFSET :offset",
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
}
