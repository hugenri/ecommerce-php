<?php

namespace App\Core\Database;

use PDO;
use PDOException;
use PDOStatement;
use App\Config\AppConfig;
use App\Config\DatabaseConfig;
use App\Core\Exceptions\DatabaseException;

class Database
{
    private PDO $pdo;

    private int $transactionDepth = 0;

    public function __construct(
        private DatabaseConfig $databaseConfig,
        private AppConfig $appConfig
    ) {
        $this->pdo = $this->createConnection();
    }

    private function buildDsn(): string
    {
        $driver = $this->databaseConfig->driver();

        switch ($driver) {
            case 'mysql':
                $dsn = 'mysql:host=' . $this->databaseConfig->host()
                    . ';port=' . $this->databaseConfig->port()
                    . ';dbname=' . $this->databaseConfig->database()
                    . ';charset=' . $this->databaseConfig->charset();
                break;

            case 'pgsql':
                $dsn = 'pgsql:host=' . $this->databaseConfig->host()
                    . ';port=' . $this->databaseConfig->port()
                    . ';dbname=' . $this->databaseConfig->database();
                break;

            case 'sqlite':
                $dsn = 'sqlite:' . $this->databaseConfig->database();
                break;

            case 'sqlsrv':
                $dsn = 'sqlsrv:Server=' . $this->databaseConfig->host()
                    . ',' . $this->databaseConfig->port()
                    . ';Database=' . $this->databaseConfig->database();
                break;

            default:
                throw new DatabaseException('Driver no soportado: ' . $driver, hideSensitiveDetails: $this->appConfig->isProduction());
        }

        return $dsn;
    }

    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_PERSISTENT => false,
        ];
    }

    private function createConnection(): PDO
    {
        $dsn = $this->buildDsn();

        try {
            $pdo = new PDO(
                $dsn,
                $this->databaseConfig->username(),
                $this->databaseConfig->password(),
                $this->pdoOptions()
            );

            $this->configureDriver($pdo);

            return $pdo;
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Error de conexion a la base de datos: ' . $e->getMessage(),
                null,
                [],
                $e,
                hideSensitiveDetails: $this->appConfig->isProduction()
            );
        }
    }

    private function configureDriver(PDO $pdo): void
    {
        if ($this->databaseConfig->driver() !== 'mysql') {
            return;
        }

        $pdo->exec("SET time_zone = '" . date('P') . "'");

        if ($this->appConfig->environment() === 'development') {
            $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        }
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $this->bindValue($stmt, $key, $value);
            }

            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Error en consulta SQL: ' . $e->getMessage(),
                $sql,
                $params,
                $e,
                hideSensitiveDetails: $this->appConfig->isProduction()
            );
        }
    }

    private function bindValue(PDOStatement $stmt, $key, $value): void
    {
        $paramType = PDO::PARAM_STR;

        if (is_int($value)) {
            $paramType = PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            $paramType = PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            $paramType = PDO::PARAM_NULL;
        }

        $paramKey = is_int($key) ? $key + 1 : (strpos($key, ':') === 0 ? $key : ':' . $key);

        $stmt->bindValue($paramKey, $value, $paramType);
    }

    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function insert(string $table, array $data): ?int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $this->query($sql, $data);

        return $this->pdo->lastInsertId() ? (int) $this->pdo->lastInsertId() : null;
    }

    public function update(string $table, array $data, array $conditions): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = $column . ' = :' . $column;
        }

        $whereParts = [];
        $whereParams = [];
        foreach ($conditions as $column => $value) {
            $whereParts[] = $column . ' = :where_' . $column;
            $whereParams['where_' . $column] = $value;
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $setParts);

        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $stmt = $this->query($sql, array_merge($data, $whereParams));
        return $stmt->rowCount();
    }

    public function delete(string $table, array $conditions): int
    {
        $whereParts = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereParts[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }

        $sql = 'DELETE FROM ' . $table;

        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function transaction(callable $callback)
    {
        $this->transactionDepth++;

        if ($this->transactionDepth === 1) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT trans_' . $this->transactionDepth);
        }

        try {
            $result = $callback($this);

            if ($this->transactionDepth === 1) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec('RELEASE SAVEPOINT trans_' . $this->transactionDepth);
            }

            $this->transactionDepth--;

            return $result;
        } catch (\Exception $e) {
            if ($this->transactionDepth === 1) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT trans_' . $this->transactionDepth);
                $this->pdo->exec('RELEASE SAVEPOINT trans_' . $this->transactionDepth);
            }

            $this->transactionDepth--;

            throw $e;
        }
    }

    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    public function table(string $table): string
    {
        return $this->databaseConfig->tablePrefix() . $table;
    }

}
