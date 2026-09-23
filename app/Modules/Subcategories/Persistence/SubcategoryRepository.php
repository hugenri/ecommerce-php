<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Persistence;

use App\Core\Database\Database;
use App\Core\Database\PaginationRequest;
use App\Core\Database\QueryPaginator;
use App\Modules\Subcategories\Domain\Subcategory;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;

class SubcategoryRepository implements SubcategoryRepositoryInterface
{
    protected string $table = 'subcategories';

    protected string $select = 'subcategories.*, categories.name as category_name';

    protected string $joins = 'LEFT JOIN categories ON subcategories.category_id = categories.category_id';

    protected string $defaultSort = 'subcategories.name';

    protected array $searchableColumns = [
        'subcategories.name',
    ];

    protected array $allowedSorts = [
        'subcategories.name',
        'subcategories.category_id',
    ];

    protected array $filterRules = [
        'category_id' => [
            'column' => 'subcategories.category_id',
            'operator' => '=',
            'type' => 'int',
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

    public function findById(int $id): ?Subcategory
    {
        $sql = "SELECT * FROM {$this->table} WHERE subcategory_id = :id LIMIT 1";
        $row = $this->db->selectOne($sql, ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByName(string $name, int $categoryId): ?Subcategory
    {
        $sql = "SELECT * FROM {$this->table} WHERE name = :name AND category_id = :category_id LIMIT 1";
        $row = $this->db->selectOne($sql, ['name' => $name, 'category_id' => $categoryId]);
        return $row ? $this->hydrate($row) : null;
    }

    public function existsByName(string $name, int $categoryId, int $exceptId = 0): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE name = :name AND category_id = :category_id AND subcategory_id != :id";
        $result = $this->db->selectOne($sql, [
            'name' => trim($name),
            'category_id' => $categoryId,
            'id' => $exceptId,
        ]);
        return (int) ($result['total'] ?? 0) > 0;
    }

    public function existsInCategory(int $categoryId): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE category_id = :category_id";
        $result = $this->db->selectOne($sql, ['category_id' => $categoryId]);
        return (int) ($result['total'] ?? 0) > 0;
    }

    public function hasProducts(int $subcategoryId): bool
    {
        $sql = "SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = (SELECT DATABASE()) AND table_name IN ('products', 'product_subcategory')";
        $result = $this->db->selectOne($sql);
        if ((int) ($result['total'] ?? 0) === 0) {
            return false;
        }
        $tables = ['products', 'product_subcategory'];
        foreach ($tables as $table) {
            $checkSql = "SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = (SELECT DATABASE()) AND table_name = :table";
            $checkResult = $this->db->selectOne($checkSql, ['table' => $table]);
            if ((int) ($checkResult['total'] ?? 0) > 0) {
                $relSql = "SELECT COUNT(*) as total FROM {$table} WHERE subcategory_id = :id";
                $relResult = $this->db->selectOne($relSql, ['id' => $subcategoryId]);
                if ((int) ($relResult['total'] ?? 0) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    public function create(Subcategory $subcategory): Subcategory
    {
        $data = $this->dehydrate($subcategory);
        unset($data['subcategory_id']);
        $id = $this->db->insert($this->table, $data);
        return $subcategory->withSubcategoryId((int) $id);
    }

    public function update(Subcategory $subcategory): Subcategory
    {
        $data = $this->dehydrate($subcategory);
        $this->db->update($this->table, $data, ['subcategory_id' => $subcategory->getSubcategoryId()]);
        return $subcategory;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table, ['subcategory_id' => $id]);
        return $result > 0;
    }

    public function paginate(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'subcategories.name',
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

    public function findByCategory(int $categoryId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE category_id = :category_id ORDER BY name";
        $rows = $this->db->select($sql, ['category_id' => $categoryId]);
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function catalogSubcategories(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY category_id, name";
        $rows = $this->db->select($sql);
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function activate(int $id): ?Subcategory
    {
        $subcategory = $this->findById($id);
        if (!$subcategory) {
            return null;
        }
        $subcategory = $subcategory->activate();
        return $this->update($subcategory);
    }

    public function deactivate(int $id): ?Subcategory
    {
        $subcategory = $this->findById($id);
        if (!$subcategory) {
            return null;
        }
        $subcategory = $subcategory->deactivate();
        return $this->update($subcategory);
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

    private function hydrate(array $row): Subcategory
    {
        return new Subcategory(
            subcategoryId: (int) $row['subcategory_id'],
            categoryId: (int) $row['category_id'],
            name: $row['name'],
            description: $row['description'] ?? null,
            status: $row['status'] ?? 'active',
        );
    }

    private function dehydrate(Subcategory $subcategory): array
    {
        return [
            'subcategory_id' => $subcategory->getSubcategoryId(),
            'category_id' => $subcategory->getCategoryId(),
            'name' => $subcategory->getName(),
            'description' => $subcategory->getDescription(),
            'status' => $subcategory->getStatus(),
        ];
    }
}
