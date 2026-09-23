<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Persistence;

use App\Core\Database\Database;
use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;
use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;

class EmployeeDashboardRepository implements EmployeeDashboardRepositoryInterface
{
    public function __construct(
        private Database $db,
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    public function getSummary(int $userId): array
    {
        $row = $this->db->selectOne(
            "SELECT
                (SELECT COUNT(*) FROM deliveries d INNER JOIN sales s ON d.sale_id = s.sale_id
                 WHERE d.user_id IS NULL AND s.status <> 'cancelled') AS pending,
                (SELECT COUNT(*) FROM deliveries d
                 WHERE d.user_id = :uid1 AND d.status <> 'cancelled') AS assigned,
                (SELECT COUNT(*) FROM deliveries d
                 WHERE d.user_id = :uid2 AND d.status = 'preparing') AS preparing,
                (SELECT COUNT(*) FROM deliveries d
                 WHERE d.user_id = :uid3 AND d.status = 'shipped' AND DATE(d.shipping_date) = CURDATE()) AS shipped_today,
                (SELECT COUNT(*) FROM deliveries d
                 WHERE d.user_id = :uid4 AND d.status = 'delivered' AND DATE(d.delivery_date) = CURDATE()) AS delivered_today",
            [
                'uid1' => $userId,
                'uid2' => $userId,
                'uid3' => $userId,
                'uid4' => $userId,
            ]
        );

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'assigned' => (int) ($row['assigned'] ?? 0),
            'preparing' => (int) ($row['preparing'] ?? 0),
            'shipped_today' => (int) ($row['shipped_today'] ?? 0),
            'delivered_today' => (int) ($row['delivered_today'] ?? 0),
        ];
    }

    public function findMyDeliveries(int $userId, int $page = 1, int $perPage = 10): array
    {
        return $this->paginate(
            'WHERE d.user_id = :user_id',
            ['user_id' => $userId],
            $page,
            $perPage
        );
    }

    public function findUnassigned(int $page = 1, int $perPage = 10): array
    {
        return $this->paginate(
            "WHERE d.user_id IS NULL AND s.status <> 'cancelled'",
            [],
            $page,
            $perPage
        );
    }

    public function findById(int $deliveryId): ?array
    {
        return $this->deliveryRepository->findById($deliveryId);
    }

    public function findProductsBySaleId(int $saleId): array
    {
        return $this->deliveryRepository->findProductsBySaleId($saleId);
    }

    public function employeeExistsAndActive(int $userId): bool
    {
        return $this->deliveryRepository->employeeExistsAndActive($userId);
    }

    public function isAssignedTo(int $deliveryId, int $userId): bool
    {
        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS total FROM deliveries WHERE delivery_id = :id AND user_id = :uid',
            ['id' => $deliveryId, 'uid' => $userId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function assignEmployee(int $deliveryId, int $userId): void
    {
        $this->deliveryRepository->assignEmployee($deliveryId, $userId);
    }

    public function updateStatus(int $deliveryId, string $status): void
    {
        $this->deliveryRepository->updateStatus($deliveryId, $status);
    }

    public function syncSaleStatus(int $saleId, string $status): void
    {
        $this->deliveryRepository->syncSaleStatus($saleId, $status);
    }

    public function registerShippingDate(int $deliveryId, string $date): void
    {
        $this->deliveryRepository->registerShippingDate($deliveryId, $date);
    }

    public function registerDeliveryDate(int $deliveryId, string $date): void
    {
        $this->deliveryRepository->registerDeliveryDate($deliveryId, $date);
    }

    private function baseSelect(): string
    {
        return "SELECT d.delivery_id, d.status AS delivery_status,
                       d.shipping_date, d.delivery_date,
                       s.sale_id, s.sale_code, s.sale_date, s.total, s.status AS sale_status, s.payment_status,
                       c.customer_id, CONCAT(c.first_name, ' ', c.last_name_paternal) AS customer_name, c.email AS customer_email,
                       a.street, a.number, a.neighborhood, a.municipality, a.state, a.zip_code, a.reference, a.alias
                FROM deliveries d
                INNER JOIN sales s ON d.sale_id = s.sale_id
                INNER JOIN customers c ON s.customer_id = c.customer_id
                LEFT JOIN addresses a ON s.address_id = a.address_id";
    }

    private function paginate(string $whereClause, array $params, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS total
             FROM deliveries d
             INNER JOIN sales s ON d.sale_id = s.sale_id
             INNER JOIN customers c ON s.customer_id = c.customer_id
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
