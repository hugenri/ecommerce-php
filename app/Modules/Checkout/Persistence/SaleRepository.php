<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Persistence;

use App\Core\Database\Database;
use App\Modules\Checkout\Domain\Sale;
use App\Modules\Checkout\Domain\SaleDetail;
use App\Modules\Checkout\Domain\Delivery;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;

class SaleRepository implements SaleRepositoryInterface
{
    public function __construct(
        private Database $db,
    ) {}

    public function transaction(callable $callback): mixed
    {
        return $this->db->transaction($callback);
    }

    public function createSale(Sale $sale): Sale
    {
        $data = [
            'sale_code' => $sale->getSaleCode(),
            'customer_id' => $sale->getCustomerId(),
            'address_id' => $sale->getAddressId(),
            'payment_method' => $sale->getPaymentMethod(),
            'payment_status' => $sale->getPaymentStatus(),
            'sale_date' => $sale->getSaleDate()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            'subtotal' => $sale->getSubtotal(),
            'tax' => $sale->getTax(),
            'total' => $sale->getTotal(),
            'status' => $sale->getStatus(),
            'notes' => $sale->getNotes(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $id = $this->db->insert('sales', $data);
        return $sale->withSaleId((int) $id);
    }

    public function createDetail(SaleDetail $detail): SaleDetail
    {
        $data = [
            'sale_id' => $detail->getSaleId(),
            'product_id' => $detail->getProductId(),
            'quantity' => $detail->getQuantity(),
            'unit_price' => $detail->getUnitPrice(),
            'discount_percentage' => $detail->getDiscountPercentage(),
            'discounted_unit_price' => $detail->getDiscountedUnitPrice(),
        ];

        $this->db->insert('sale_details', $data);
        return $detail;
    }

    public function createDelivery(Delivery $delivery): Delivery
    {
        $data = [
            'sale_id' => $delivery->getSaleId(),
            'status' => $delivery->getStatus(),
        ];

        $this->db->insert('deliveries', $data);
        return $delivery;
    }

    public function findAll(int $page = 1, int $perPage = 10, string $search = '', string $sortBy = 'sale_date', string $sortDir = 'DESC', array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];

        $allowedSorts = ['sale_date', 'total', 'status', 'sale_code'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'sale_date';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        if ($search !== '') {
            $where[] = '(s.sale_code LIKE :search OR c.first_name LIKE :search2 OR c.last_name_paternal LIKE :search3 OR c.email LIKE :search4)';
            $params['search'] = '%' . $this->db->escapeLike($search) . '%';
            $params['search2'] = '%' . $this->db->escapeLike($search) . '%';
            $params['search3'] = '%' . $this->db->escapeLike($search) . '%';
            $params['search4'] = '%' . $this->db->escapeLike($search) . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = 's.payment_status = :payment_status';
            $params['payment_status'] = $filters['payment_status'];
        }
        if (!empty($filters['payment_method'])) {
            $where[] = 's.payment_method = :payment_method';
            $params['payment_method'] = $filters['payment_method'];
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

        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) as total FROM sales s LEFT JOIN customers c ON s.customer_id = c.customer_id {$whereClause}",
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $rows = $this->db->select(
            "SELECT s.*, CONCAT(c.first_name, ' ', c.last_name_paternal) as customer_name, c.email as customer_email
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.customer_id
             {$whereClause}
             ORDER BY s.{$sortBy} {$sortDir}
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

    public function updateSaleStatus(int $saleId, string $status): void
    {
        $this->db->update('sales', ['status' => $status], ['sale_id' => $saleId]);
    }

    public function updatePaymentStatus(int $saleId, string $paymentStatus): void
    {
        $this->db->update('sales', ['payment_status' => $paymentStatus], ['sale_id' => $saleId]);
    }

    public function updateSaleNotes(int $saleId, string $notes): void
    {
        $this->db->update('sales', ['notes' => $notes], ['sale_id' => $saleId]);
    }

    public function getDetailsForCancel(int $saleId): array
    {
        return $this->db->select(
            'SELECT product_id, quantity FROM sale_details WHERE sale_id = :sale_id',
            ['sale_id' => $saleId]
        );
    }

    public function cancelDeliveryForSale(int $saleId): void
    {
        $this->db->update('deliveries', ['status' => 'cancelled'], ['sale_id' => $saleId]);
    }

    public function findByCustomer(int $customerId, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        $countRow = $this->db->selectOne(
            'SELECT COUNT(*) as total FROM sales WHERE customer_id = :customer_id',
            ['customer_id' => $customerId]
        );
        $total = (int) ($countRow['total'] ?? 0);

        $rows = $this->db->select(
            'SELECT * FROM sales WHERE customer_id = :customer_id ORDER BY sale_date DESC LIMIT :limit OFFSET :offset',
            ['customer_id' => $customerId, 'limit' => $perPage, 'offset' => $offset]
        );

        $data = array_map(fn(array $row) => $this->hydrateSale($row), $rows);

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    public function findDeliveryBySaleId(int $saleId): ?Delivery
    {
        $row = $this->db->selectOne(
            'SELECT * FROM deliveries WHERE sale_id = :sale_id LIMIT 1',
            ['sale_id' => $saleId]
        );
        return $row ? $this->hydrateDelivery($row) : null;
    }

    public function findSaleById(int $saleId): ?Sale
    {
        $row = $this->db->selectOne(
            'SELECT * FROM sales WHERE sale_id = :id LIMIT 1',
            ['id' => $saleId]
        );
        return $row ? $this->hydrateSale($row) : null;
    }

    public function findBySaleCode(string $saleCode): ?Sale
    {
        $row = $this->db->selectOne(
            'SELECT * FROM sales WHERE sale_code = :sale_code LIMIT 1',
            ['sale_code' => $saleCode]
        );
        return $row ? $this->hydrateSale($row) : null;
    }

    public function findDetailsBySaleId(int $saleId): array
    {
        $rows = $this->db->select(
            'SELECT sd.*, p.name as product_name FROM sale_details sd
             LEFT JOIN products p ON sd.product_id = p.product_id
             WHERE sd.sale_id = :sale_id',
            ['sale_id' => $saleId]
        );
        return $rows;
    }

    private function hydrateSale(array $row): Sale
    {
        return new Sale(
            saleId: (int) $row['sale_id'],
            saleCode: $row['sale_code'],
            customerId: (int) $row['customer_id'],
            addressId: (int) $row['address_id'],
            paymentMethod: $row['payment_method'],
            paymentStatus: $row['payment_status'] ?? 'paid',
            saleDate: isset($row['sale_date']) ? new \DateTimeImmutable($row['sale_date']) : null,
            subtotal: (float) ($row['subtotal'] ?? 0),
            tax: (float) ($row['tax'] ?? 0),
            total: (float) ($row['total'] ?? 0),
            status: $row['status'] ?? 'pending',
            notes: $row['notes'] ?? null,
            createdAt: isset($row['created_at']) ? new \DateTimeImmutable($row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable($row['updated_at']) : null,
        );
    }

    private function hydrateDelivery(array $row): Delivery
    {
        return new Delivery(
            deliveryId: (int) $row['delivery_id'],
            saleId: (int) $row['sale_id'],
            userId: isset($row['user_id']) ? (int) $row['user_id'] : null,
            shippingDate: isset($row['shipping_date']) ? new \DateTimeImmutable($row['shipping_date']) : null,
            deliveryDate: isset($row['delivery_date']) ? new \DateTimeImmutable($row['delivery_date']) : null,
            status: $row['status'] ?? 'pending',
        );
    }
}
