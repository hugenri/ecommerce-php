<?php

declare(strict_types=1);

namespace App\Modules\Categories\Persistence;

use App\Core\Database\Database;
use App\Core\Database\PaginationRequest;
use App\Core\Database\QueryPaginator;
use App\Modules\Categories\Domain\Category;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;
use App\Modules\Products\Persistence\CatalogVisibilityTrait;

class CategoryRepository implements CategoryRepositoryInterface
{
    use CatalogVisibilityTrait;
    protected string $table = 'categories';

    protected string $select = '*';

    protected string $joins = '';

    protected string $defaultSort = 'name';

    protected array $searchableColumns = [
        'name',
    ];

    protected array $allowedSorts = [
        'name',
        'created_at',
    ];

    protected array $filterRules = [
        'status' => [
            'column' => 'status',
            'operator' => '=',
            'allowed' => [
                'active',
                'inactive',
            ],
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

    public function findById(int $id): ?Category
    {
        $sql = "SELECT * FROM {$this->table} WHERE category_id = :id LIMIT 1";
        $row = $this->db->selectOne($sql, ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByName(string $name): ?Category
    {
        $sql = "SELECT * FROM {$this->table} WHERE name = :name LIMIT 1";
        $row = $this->db->selectOne($sql, ['name' => $name]);
        return $row ? $this->hydrate($row) : null;
    }

    public function existsByName(string $name, int $exceptId = 0): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE name = :name AND category_id != :id";
        $result = $this->db->selectOne($sql, [
            'name' => trim($name),
            'id' => $exceptId,
        ]);
        return (int) ($result['total'] ?? 0) > 0;
    }

    public function hasSubcategories(int $categoryId): bool
    {
        $sql = "SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = (SELECT DATABASE()) AND table_name = 'subcategories'";
        $result = $this->db->selectOne($sql);
        if ((int) ($result['total'] ?? 0) === 0) {
            return false;
        }
        $sql = "SELECT COUNT(*) as total FROM subcategories WHERE category_id = :id";
        $result = $this->db->selectOne($sql, ['id' => $categoryId]);
        return (int) ($result['total'] ?? 0) > 0;
    }

    public function catalogCategories(): array
    {
        $sql = "SELECT c.*
                FROM {$this->table} c
                WHERE c.status = 'active'
                  AND EXISTS (
                      SELECT 1
                      FROM subcategories sc
                      JOIN products ON products.subcategory_id = sc.subcategory_id
                      WHERE sc.category_id = c.category_id
                        AND sc.status = 'active'
                        AND (
                            {$this->catalogProductCondition()}
                        )
                  )
                ORDER BY c.name ASC";

        $rows = $this->db->select($sql);
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function create(Category $category): Category
    {
        $data = $this->dehydrate($category);
        unset($data['category_id']);
        $id = $this->db->insert($this->table, $data);
        return $category->withCategoryId((int) $id);
    }

    public function update(Category $category): Category
    {
        $data = $this->dehydrate($category);
        $this->db->update($this->table, $data, ['category_id' => $category->getCategoryId()]);
        return $category;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table, ['category_id' => $id]);
        return $result > 0;
    }

    public function paginate(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'name',
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

    public function activate(int $id): ?Category
    {
        $category = $this->findById($id);
        if (!$category) {
            return null;
        }
        $category = $category->activate();
        return $this->update($category);
    }

    public function deactivate(int $id): ?Category
    {
        $category = $this->findById($id);
        if (!$category) {
            return null;
        }
        $category = $category->deactivate();
        return $this->update($category);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getSelect(): string
    {
        return $this->select;
    }

    public function getJoins(): string
    {
        return $this->joins;
    }

    public function getDefaultSort(): string
    {
        return $this->defaultSort;
    }

    public function getSearchableColumns(): array
    {
        return $this->searchableColumns;
    }

    public function getAllowedSorts(): array
    {
        return $this->allowedSorts;
    }

    public function getFilterRules(): array
    {
        return $this->filterRules;
    }

    public function getGroupBy(): string
    {
        return $this->groupBy;
    }

    public function getHaving(): string
    {
        return $this->having;
    }

    private function hydrate(array $row): Category
    {
        return new Category(
            categoryId: (int) $row['category_id'],
            name: $row['name'],
            description: $row['description'] ?? null,
            image: $row['image'] ?? null,
            status: $row['status'] ?? 'active',
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable($row['created_at'])
                : null,
            updatedAt: isset($row['updated_at'])
                ? new \DateTimeImmutable($row['updated_at'])
                : null,
        );
    }

    private function dehydrate(Category $category): array
    {
        return [
            'category_id' => $category->getCategoryId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'image' => $category->getImage(),
            'status' => $category->getStatus(),
            'created_at' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
