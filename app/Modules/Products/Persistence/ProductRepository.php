<?php

declare(strict_types=1);

namespace App\Modules\Products\Persistence;

use App\Core\Database\Database;
use App\Core\Database\PaginationRequest;
use App\Core\Database\QueryPaginator;
use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductImage;
use App\Modules\Products\Domain\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    use CatalogVisibilityTrait;

    protected string $table = 'products';

    protected string $select = 'products.*, subcategories.name as subcategory_name, categories.name as category_name, categories.category_id';

    protected string $joins = 'LEFT JOIN subcategories ON products.subcategory_id = subcategories.subcategory_id LEFT JOIN categories ON subcategories.category_id = categories.category_id';

    protected string $defaultSort = 'products.name';

    protected array $searchableColumns = [
        'products.name',
        'products.product_code',
    ];

    protected array $allowedSorts = [
        'products.name',
        'products.price',
        'products.stock',
        'products.created_at',
    ];

    protected array $filterRules = [
        'subcategory_id' => [
            'column' => 'products.subcategory_id',
            'operator' => '=',
            'type' => 'int',
        ],
        'category_id' => [
            'column' => 'categories.category_id',
            'operator' => '=',
            'type' => 'int',
        ],
        'status' => [
            'column' => 'products.status',
            'operator' => '=',
            'allowed' => ['active', 'inactive'],
        ],
        'category_status' => [
            'column' => 'categories.status',
            'operator' => '=',
            'allowed' => ['active', 'inactive'],
        ],
        'subcategory_status' => [
            'column' => 'subcategories.status',
            'operator' => '=',
            'allowed' => ['active', 'inactive'],
        ],
    ];

    protected string $groupBy = '';

    protected string $having = '';

    private QueryPaginator $paginator;

    private Database $db;

    public function __construct(QueryPaginator $paginator, Database $db)
    {
        $this->paginator = $paginator;
        $this->db = $db;
    }

    public function findById(int $id): ?Product
    {
        $sql = "SELECT * FROM {$this->table} WHERE product_id = :id LIMIT 1";
        $row = $this->db->selectOne($sql, ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            $ids,
            fn(mixed $id): bool => is_int($id) && $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM {$this->table} WHERE product_id IN ({$placeholders})";

        $products = [];
        foreach ($this->db->select($sql, $ids) as $row) {
            $product = $this->hydrate($row);
            $products[$product->getProductId()] = $product;
        }

        return $products;
    }

    public function findByCode(string $code): ?Product
    {
        $sql = "SELECT * FROM {$this->table} WHERE product_code = :code LIMIT 1";
        $row = $this->db->selectOne($sql, ['code' => $code]);
        return $row ? $this->hydrate($row) : null;
    }

    public function existsByCode(string $code, int $exceptId = 0): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE product_code = :code AND product_id != :id";
        $result = $this->db->selectOne($sql, [
            'code' => trim($code),
            'id' => $exceptId,
        ]);
        return (int) ($result['total'] ?? 0) > 0;
    }

    public function hasSales(int $productId): bool
    {
        $tables = ['order_items', 'sale_items', 'invoice_items'];
        foreach ($tables as $table) {
            $checkSql = "SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = (SELECT DATABASE()) AND table_name = :table";
            $checkResult = $this->db->selectOne($checkSql, ['table' => $table]);
            if ((int) ($checkResult['total'] ?? 0) > 0) {
                $relSql = "SELECT COUNT(*) as total FROM {$table} WHERE product_id = :id";
                $relResult = $this->db->selectOne($relSql, ['id' => $productId]);
                if ((int) ($relResult['total'] ?? 0) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    public function create(Product $product): Product
    {
        $data = $this->dehydrate($product);
        unset($data['product_id']);
        $id = $this->db->insert($this->table, $data);
        return $product->withProductId((int) $id);
    }

    public function update(Product $product): Product
    {
        $data = $this->dehydrate($product);
        $this->db->update($this->table, $data, ['product_id' => $product->getProductId()]);
        return $product;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table, ['product_id' => $id]);
        return $result > 0;
    }

    public function paginate(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'products.name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array {
        $request = PaginationRequest::fromRepository(
            repository: $this,
            page: $page,
            perPage: $perPage,
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
            filters: $filters
        );

        $result = $this->paginator->paginate($request);
        $result['data'] = array_map(fn(array $row) => $this->hydrate($row), $result['data']);
        return $result;
    }

    public function search(string $query, int $limit = 10): array
    {
        $like = '%' . $this->db->escapeLike($query) . '%';

        $sql = "SELECT * FROM {$this->table}
                WHERE name LIKE :query_name
                OR product_code LIKE :query_code
                ORDER BY name
                LIMIT :limit";

        $params = [
            'query_name' => $like,
            'query_code' => $like,
            'limit' => $limit,
        ];

        $rows = $this->db->select($sql, $params);
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function searchSuggestions(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%' . $this->db->escapeLike($query) . '%';
        $prefix = $this->db->escapeLike($query) . '%';

        $sql = "SELECT {$this->select}
                FROM {$this->table}
                {$this->joins}
                WHERE {$this->catalogProductCondition()}
                  AND products.name LIKE :like
                ORDER BY (CASE WHEN products.name LIKE :prefix THEN 0 ELSE 1 END), products.name
                LIMIT :limit";

        $params = [
            'like' => $like,
            'prefix' => $prefix,
            'limit' => $limit,
        ];

        $rows = $this->db->select($sql, $params);
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function recentProducts(int $limit = 8): array
    {
        return $this->visibleCatalogQuery(
            orderBy: 'ORDER BY products.created_at DESC',
            limit: $limit
        );
    }

    public function saleProducts(int $limit = 8): array
    {
        return $this->visibleCatalogQuery(
            extraWhere: 'AND products.discount > 0',
            orderBy: 'ORDER BY products.created_at DESC',
            limit: $limit
        );
    }

    public function paginateCatalog(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'products.name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array {
        $request = PaginationRequest::fromRepository(
            repository: $this,
            page: $page,
            perPage: $perPage,
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
            filters: $filters,
            baseConditions: [$this->catalogProductCondition()],
            relevanceSearch: true
        );

        $result = $this->paginator->paginate($request);
        $result['data'] = array_map(fn(array $row) => $this->hydrate($row), $result['data']);
        return $result;
    }

    public function findVisible(int $id): ?Product
    {
        $sql = "SELECT {$this->select}
                FROM {$this->table}
                {$this->joins}
                WHERE products.product_id = :id
                  AND {$this->catalogProductCondition()}
                LIMIT 1";

        $row = $this->db->selectOne($sql, ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Consulta base del catálogo público.
     *
     * Única consulta reutilizable que representa los productos visibles para
     * un cliente; contiene TODA la regla de visibilidad. Los métodos públicos
     * del catálogo se construyen a partir de aquí sin repetir condiciones.
     *
     * @param string $extraWhere condición SQL adicional (sin prefijo AND)
     * @param string $orderBy cláusula ORDER BY
     * @param int|null $limit límite de filas (null = sin límite)
     *
     * @return Product[]
     */
    private function visibleCatalogQuery(string $extraWhere = '', string $orderBy = '', ?int $limit = null): array
    {
        $sql = "SELECT {$this->select}
                FROM {$this->table}
                {$this->joins}
                WHERE {$this->catalogProductCondition()}
                {$extraWhere}
                {$orderBy}";

        $params = [];
        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
            $params['limit'] = $limit;
        }

        $rows = $this->db->select($sql, $params);
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function activate(int $id): ?Product
    {
        $product = $this->findById($id);
        if (!$product) return null;
        $product = $product->activate();
        return $this->update($product);
    }

    public function deactivate(int $id): ?Product
    {
        $product = $this->findById($id);
        if (!$product) return null;
        $product = $product->deactivate();
        return $this->update($product);
    }

    public function updatePrice(int $id, float $price): ?Product
    {
        $product = $this->findById($id);
        if (!$product) return null;
        $product = $product->updatePrice($price);
        return $this->update($product);
    }

    public function updateDiscount(int $id, float $discount): ?Product
    {
        $product = $this->findById($id);
        if (!$product) return null;
        $product = $product->updateDiscount($discount);
        return $this->update($product);
    }

    public function updateStock(int $id, int $stock): ?Product
    {
        $product = $this->findById($id);
        if (!$product) return null;
        $product = $product->updateStock($stock);
        return $this->update($product);
    }

    public function changeMainImage(int $id, string $image): ?Product
    {
        $product = $this->findById($id);
        if (!$product) return null;
        $product = $product->changeMainImage($image);
        return $this->update($product);
    }

    // ──── Product images ───────────────────────────────

    public function getImages(int $productId): array
    {
        $sql = "SELECT * FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC";
        $rows = $this->db->select($sql, ['product_id' => $productId]);
        return array_map(fn(array $row) => $this->hydrateImage($row), $rows);
    }

    public function addImage(int $productId, string $image): ProductImage
    {
        $maxSql = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next FROM product_images WHERE product_id = :product_id";
        $maxResult = $this->db->selectOne($maxSql, ['product_id' => $productId]);
        $nextOrder = (int) ($maxResult['next'] ?? 1);

        $data = [
            'product_id' => $productId,
            'image' => $image,
            'sort_order' => $nextOrder,
        ];
        $id = $this->db->insert('product_images', $data);
        return new ProductImage((int) $id, $productId, $image, $nextOrder);
    }

    public function removeImage(int $imageId): bool
    {
        $result = $this->db->delete('product_images', ['image_id' => $imageId]);
        return $result > 0;
    }

    public function reorderImages(int $productId, array $imageIds): void
    {
        foreach ($imageIds as $order => $imageId) {
            $this->db->update('product_images', ['sort_order' => $order + 1], [
                'image_id' => (int) $imageId,
                'product_id' => $productId,
            ]);
        }
    }

    // ──── Getters for PaginationRequest ────────────────

    public function getTable(): string { return $this->table; }
    public function getSelect(): string { return $this->select; }
    public function getJoins(): string { return $this->joins; }
    public function getDefaultSort(): string { return $this->defaultSort; }
    public function getSearchableColumns(): array { return $this->searchableColumns; }
    public function getAllowedSorts(): array { return $this->allowedSorts; }
    public function getFilterRules(): array { return $this->filterRules; }
    public function getGroupBy(): string { return $this->groupBy; }
    public function getHaving(): string { return $this->having; }

    // ──── Hydrate / Dehydrate ──────────────────────────

    private function hydrate(array $row): Product
    {
        return new Product(
            productId: (int) $row['product_id'],
            subcategoryId: (int) $row['subcategory_id'],
            productCode: $row['product_code'],
            name: $row['name'],
            description: $row['description'] ?? null,
            image: $row['image'] ?? null,
            stock: (int) ($row['stock'] ?? 0),
            price: isset($row['price']) ? (float) $row['price'] : null,
            discount: (float) ($row['discount'] ?? 0),
            status: $row['status'] ?? 'active',
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable($row['created_at'])
                : null,
            updatedAt: isset($row['updated_at'])
                ? new \DateTimeImmutable($row['updated_at'])
                : null,
        );
    }

    private function dehydrate(Product $product): array
    {
        return [
            'product_id' => $product->getProductId(),
            'subcategory_id' => $product->getSubcategoryId(),
            'product_code' => $product->getProductCode(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'image' => $product->getImage(),
            'stock' => $product->getStock(),
            'price' => $product->getPrice() ?? 0.00,
            'discount' => $product->getDiscount(),
            'status' => $product->getStatus(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function hydrateImage(array $row): ProductImage
    {
        return new ProductImage(
            imageId: (int) $row['image_id'],
            productId: (int) $row['product_id'],
            image: $row['image'],
            sortOrder: (int) ($row['sort_order'] ?? 0),
        );
    }
}
