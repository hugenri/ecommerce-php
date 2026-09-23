<?php

namespace App\Modules\Identity\Persistence;

use App\Core\Database\Database;
use App\Core\Database\PaginationRequest;
use App\Core\Database\QueryPaginator;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    protected string $table = 'users';

    protected string $select = '*';

    protected string $joins = '';

    protected string $defaultSort = 'user_id';

    protected array $searchableColumns = [
        'name',
        'email',
        'phone',
    ];

    protected array $allowedSorts = [
        'user_id',
        'name',
        'email',
        'created_at',
    ];

    protected array $filterRules = [
        'role' => [
            'column' => 'role',
            'operator' => '=',
            'allowed' => [
                'admin',
                'employee',
            ],
        ],
        'is_active' => [
            'column' => 'is_active',
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

    public function findById(int $id): ?User
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT 1";
        $row = $this->db->selectOne($sql, ['user_id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email AND is_active = 1 LIMIT 1";
        $row = $this->db->selectOne($sql, ['email' => $email]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmailIncludingInactive(string $email): ?User
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $row = $this->db->selectOne($sql, ['email' => $email]);
        return $row ? $this->hydrate($row) : null;
    }

    public function save(User $user): User
    {
        if ($user->getId() === null) {
            $data = $this->dehydrate($user);
            $id = $this->db->insert($this->table, $data);
            return $user->withId((int) $id);
        }

        $data = $this->dehydrate($user);
        $this->db->update($this->table, $data, ['user_id' => $user->getId()]);
        return $user;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table, ['user_id' => $id]);
        return $result > 0;
    }

    public function all(): array
    {
        $sql = "SELECT * FROM {$this->table}";
        return $this->db->select($sql);
    }

    public function paginate(
        int $page = 1,
        int $perPage = 5,
        string $search = '',
        string $sortBy = 'user_id',
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

    public function emailExists(string $email, int $exceptId = 0): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE email = :email AND user_id != :user_id";
        $result = $this->db->selectOne($sql, [
            'email' => strtolower(trim($email)),
            'user_id' => $exceptId,
        ]);

        return (int) ($result['total'] ?? 0) > 0;
    }

    public function countAdmins(): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE role = 'admin'";
        $result = $this->db->selectOne($sql);
        return (int) ($result['total'] ?? 0);
    }

    public function search(string $query, int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name LIKE :query 
                OR email LIKE :query 
                ORDER BY name 
                LIMIT :limit";

        $params = [
            'query' => '%' . $this->db->escapeLike($query) . '%',
            'limit' => $limit,
        ];

        return $this->db->select($sql, $params);
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

    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['user_id'],
            name: $row['name'],
            email: $row['email'],
            password: $row['password'],
            role: $row['role'] ?? 'employee',
            isActive: (bool) ($row['is_active'] ?? true),
            avatar: $row['avatar'] ?? null,
            phone: $row['phone'] ?? null,
            lastLogin: isset($row['last_login'])
                ? new \DateTimeImmutable($row['last_login'])
                : null,
            loginAttempts: (int) ($row['login_attempts'] ?? 0),
            lastAttempt: isset($row['last_attempt'])
                ? new \DateTimeImmutable($row['last_attempt'])
                : null,
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable($row['created_at'])
                : null,
            updatedAt: isset($row['updated_at'])
                ? new \DateTimeImmutable($row['updated_at'])
                : null,
        );
    }

    private function dehydrate(User $user): array
    {
        return [
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'role' => $user->getRole(),
            'is_active' => $user->isActive() ? 1 : 0,
            'avatar' => $user->getAvatar(),
            'phone' => $user->getPhone(),
            'last_login' => $user->getLastLogin()?->format('Y-m-d H:i:s'),
            'login_attempts' => $user->getLoginAttempts(),
            'last_attempt' => $user->getLastAttempt()?->format('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

}
