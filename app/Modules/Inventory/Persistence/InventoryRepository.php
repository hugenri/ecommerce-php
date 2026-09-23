<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Persistence;

use App\Core\Database\Database;
use App\Modules\Inventory\Domain\InventoryMovement;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;

class InventoryRepository implements InventoryRepositoryInterface
{
    public function __construct(
        private Database $db,
    ) {}

    public function stockLevels(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'name',
        string $sortDir = 'ASC'
    ): array {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(p.name LIKE :search OR p.product_code LIKE :search2)';
            $like = '%' . $this->db->escapeLike($search) . '%';
            $params['search'] = $like;
            $params['search2'] = $like;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $allowedSorts = ['name', 'product_code', 'stock'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        return $this->paginate(
            "SELECT p.product_id, p.product_code, p.name, p.stock, p.status,
                    p.image
             FROM products p",
            "FROM products p",
            $whereClause,
            $params,
            $page,
            $perPage,
            "ORDER BY p.{$sortBy} {$sortDir}, p.product_id ASC"
        );
    }

    public function movements(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['product_id'])) {
            $where[] = 'm.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }

        if (!empty($filters['movement_type'])) {
            $where[] = 'm.movement_type = :movement_type';
            $params['movement_type'] = $filters['movement_type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'm.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'm.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'm.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->paginate(
            "SELECT m.movement_id, m.product_id, m.movement_type, m.quantity,
                    m.previous_stock, m.current_stock, m.reason, m.user_id,
                    m.origin_type, m.origin_id, m.created_at,
                    p.product_code, p.name AS product_name,
                    u.name AS user_name,
                    s.sale_code
             FROM inventory_movements m
             INNER JOIN products p ON m.product_id = p.product_id
             LEFT JOIN users u ON m.user_id = u.user_id
             LEFT JOIN sales s ON m.origin_type = 'sale' AND m.origin_id = s.sale_id",
            "FROM inventory_movements m
             INNER JOIN products p ON m.product_id = p.product_id
             LEFT JOIN users u ON m.user_id = u.user_id
             LEFT JOIN sales s ON m.origin_type = 'sale' AND m.origin_id = s.sale_id",
            $whereClause,
            $params,
            $page,
            $perPage,
            'ORDER BY m.created_at DESC, m.movement_id DESC'
        );
    }

    public function lowStock(int $threshold, int $page = 1, int $perPage = 10): array
    {
        $select = "SELECT p.product_id, p.product_code, p.name, p.stock, p.status,
                          p.image, c.name AS category_name,
                          (SELECT MAX(m.created_at) FROM inventory_movements m
                           WHERE m.product_id = p.product_id) AS last_movement_at
                   FROM products p
                   LEFT JOIN subcategories s ON p.subcategory_id = s.subcategory_id
                   LEFT JOIN categories c ON s.category_id = c.category_id";

        $from = "FROM products p
                 LEFT JOIN subcategories s ON p.subcategory_id = s.subcategory_id
                 LEFT JOIN categories c ON s.category_id = c.category_id";

        return $this->paginate(
            $select,
            $from,
            "WHERE p.stock <= :threshold AND p.status = 'active'",
            ['threshold' => $threshold],
            $page,
            $perPage,
            'ORDER BY p.stock ASC, p.name ASC'
        );
    }

    public function currentStock(int $productId): ?int
    {
        $row = $this->db->selectOne(
            'SELECT stock FROM products WHERE product_id = :product_id LIMIT 1',
            ['product_id' => $productId]
        );

        return $row ? (int) $row['stock'] : null;
    }

    public function hasSaleMovement(int $saleId): bool
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS total FROM inventory_movements
             WHERE origin_type = 'sale' AND origin_id = :sale_id",
            ['sale_id' => $saleId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function updateProductStock(int $productId, int $stock): void
    {
        $this->db->update('products', ['stock' => $stock], ['product_id' => $productId]);
    }

    public function recordMovement(InventoryMovement $movement): InventoryMovement
    {
        $id = $this->db->insert('inventory_movements', [
            'product_id' => $movement->getProductId(),
            'movement_type' => $movement->getMovementType(),
            'quantity' => $movement->getQuantity(),
            'previous_stock' => $movement->getPreviousStock(),
            'current_stock' => $movement->getCurrentStock(),
            'reason' => $movement->getReason(),
            'user_id' => $movement->getUserId(),
            'origin_type' => $movement->getOriginType(),
            'origin_id' => $movement->getOriginId(),
        ]);

        return $movement->withMovementId((int) $id);
    }

    public function transaction(callable $callback): mixed
    {
        return $this->db->transaction($callback);
    }

    private function paginate(
        string $selectSql,
        string $fromSql,
        string $whereClause,
        array $params,
        int $page,
        int $perPage,
        string $orderBy
    ): array {
        $offset = ($page - 1) * $perPage;

        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS total {$fromSql} {$whereClause}",
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $rows = $this->db->select(
            "{$selectSql} {$whereClause} {$orderBy} LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $perPage, 'offset' => $offset])
        );

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total_pages' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }
}
