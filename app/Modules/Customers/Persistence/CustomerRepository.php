<?php

declare(strict_types=1);

namespace App\Modules\Customers\Persistence;

use App\Core\Database\Database;
use App\Core\Database\PaginationRequest;
use App\Core\Database\QueryPaginator;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\CustomerRepositoryInterface;

class CustomerRepository implements CustomerRepositoryInterface
{
    protected string $table = 'customers';

    protected string $select = 'customers.*';

    protected string $joins = '';

    protected string $defaultSort = 'customers.first_name';

    protected array $searchableColumns = [
        'customers.first_name',
        'customers.last_name_paternal',
        'customers.email',
    ];

    protected array $allowedSorts = [
        'customers.first_name',
        'customers.email',
        'customers.created_at',
    ];

    protected array $filterRules = [
        'active' => [
            'column' => 'customers.active',
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

    public function findById(int $id): ?Customer
    {
        $sql = "SELECT * FROM {$this->table} WHERE customer_id = :id LIMIT 1";
        $row = $this->db->selectOne($sql, ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?Customer
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $row = $this->db->selectOne($sql, ['email' => $email]);
        return $row ? $this->hydrate($row) : null;
    }

    public function create(Customer $customer): Customer
    {
        $data = $this->dehydrate($customer);
        unset($data['customer_id'], $data['created_at'], $data['updated_at'], $data['last_login']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $id = $this->db->insert($this->table, $data);
        return $this->findById((int) $id);
    }

    public function paginate(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'customers.first_name',
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
        $sql = "SELECT * FROM {$this->table}
                WHERE first_name LIKE :query
                OR last_name_paternal LIKE :query
                OR email LIKE :query
                ORDER BY first_name
                LIMIT :limit";

        $params = [
            'query' => '%' . $this->db->escapeLike($query) . '%',
            'limit' => $limit,
        ];

        $rows = $this->db->select($sql, $params);
        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function activate(int $id): ?Customer
    {
        $customer = $this->findById($id);
        if (!$customer) return null;
        $customer = $customer->activate();
        return $this->update($customer);
    }

    public function deactivate(int $id): ?Customer
    {
        $customer = $this->findById($id);
        if (!$customer) return null;
        $customer = $customer->deactivate();
        return $this->update($customer);
    }

    public function updateLoginTimestamp(int $id): void
    {
        $this->db->update($this->table, [
            'last_login' => date('Y-m-d H:i:s'),
        ], ['customer_id' => $id]);
    }

    public function markEmailVerified(int $customerId): void
    {
        $this->db->update($this->table, [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['customer_id' => $customerId]);
    }

    public function update(Customer $customer): Customer
    {
        $data = $this->dehydrate($customer);
        $this->db->update($this->table, $data, ['customer_id' => $customer->getCustomerId()]);
        return $customer;
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

    private function hydrate(array $row): Customer
    {
        return new Customer(
            customerId: (int) $row['customer_id'],
            firstName: $row['first_name'],
            lastNamePaternal: $row['last_name_paternal'],
            email: $row['email'],
            password: $row['password'],
            lastNameMaternal: $row['last_name_maternal'] ?? null,
            phone: $row['phone'] ?? null,
            active: (bool) ($row['active'] ?? true),
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable($row['created_at'])
                : null,
            updatedAt: isset($row['updated_at'])
                ? new \DateTimeImmutable($row['updated_at'])
                : null,
            lastLogin: isset($row['last_login'])
                ? new \DateTimeImmutable($row['last_login'])
                : null,
            emailVerifiedAt: isset($row['email_verified_at'])
                ? new \DateTimeImmutable($row['email_verified_at'])
                : null,
        );
    }

    private function dehydrate(Customer $customer): array
    {
        return [
            'first_name' => $customer->getFirstName(),
            'last_name_paternal' => $customer->getLastNamePaternal(),
            'last_name_maternal' => $customer->getLastNameMaternal(),
            'email' => $customer->getEmail(),
            'password' => $customer->getPassword(),
            'phone' => $customer->getPhone(),
            'active' => $customer->isActive() ? 1 : 0,
            'email_verified_at' => $customer->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
