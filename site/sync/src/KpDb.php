<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use KPT\Database;
use InvalidArgumentException;

/**
 * Wrapper class for KPT\Database to provide the interface needed by sync engine
 */
class KpDb
{
    private Database $db;
    private string $tablePrefix;
    private int $chunkSize;

    public function __construct(
        string $host,
        int $port,
        string $database,
        string $user,
        string $password,
        string $table_prefix = '',
        int $pool_size = 10,
        int $chunk_size = 1000
    ) {
        $this->tablePrefix = $table_prefix;
        $this->chunkSize = $chunk_size;

        $settings = (object) [
            'driver' => 'mysql',
            'server' => $host,
            'port' => $port,
            'schema' => $database,
            'username' => $user,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];

        $this->db = new Database($settings);
    }

    /**
     * Create instance from main app's database configuration
     */
    public static function fromAppConfig(): self
    {
        // Ensure KPTV_PATH is defined and autoloader is loaded
        $appPath = dirname(__DIR__, 2);
        
        if (!defined('KPTV_PATH')) {
            define('KPTV_PATH', $appPath . '/');
        }

        require_once $appPath . '/vendor/autoload.php';

        $dbConfig = \KPT\KPTV::get_setting('database');

        if (!$dbConfig) {
            throw new \RuntimeException('Database configuration not found');
        }

        return new self(
            host: $dbConfig->server ?? 'localhost',
            port: (int) ($dbConfig->port ?? 3306),
            database: $dbConfig->schema ?? '',
            user: $dbConfig->username ?? '',
            password: $dbConfig->password ?? '',
            table_prefix: $dbConfig->tbl_prefix ?? 'kptv_',
            pool_size: 10,
            chunk_size: 1000
        );
    }

    private function buildWhereClause(array $where): array
    {
        if (empty($where)) {
            return ['', []];
        }

        $whereParts = [];
        $whereParams = [];

        foreach ($where as $i => $clause) {
            $field = $clause->field;
            $value = $clause->value;
            $operator = $clause->operator->value;
            $connector = $clause->connector;

            if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                $whereStr = "{$field} {$operator}";
            } elseif ($operator === 'BETWEEN') {
                $whereStr = "{$field} BETWEEN ? AND ?";
                $whereParams = [...$whereParams, ...$value];
            } elseif ($operator === 'IN' || $operator === 'NOT IN') {
                $placeholders = implode(', ', array_fill(0, count($value), '?'));
                $whereStr = "{$field} {$operator} ({$placeholders})";
                $whereParams = [...$whereParams, ...$value];
            } else {
                $whereStr = "{$field} {$operator} ?";
                $whereParams[] = $value;
            }

            $whereParts[] = $i === 0 ? $whereStr : "{$connector} {$whereStr}";
        }

        return [' WHERE ' . implode(' ', $whereParts), $whereParams];
    }

    public function get_all(
        string $table,
        ?array $columns = null,
        ?array $joins = null,
        ?array $where = null,
        ?string $group_by = null,
        ?string $having = null,
        ?array $order_by = null,
        ?int $limit = null,
        ?int $offset = null
    ): ?array {
        $cols = $columns === null || empty($columns) ? '*' : implode(', ', $columns);
        $fullTable = $this->tablePrefix ? "{$this->tablePrefix}{$table}" : $table;

        $query = "SELECT {$cols} FROM {$fullTable}";
        $params = [];

        if ($joins !== null) {
            foreach ($joins as $join) {
                $query .= " {$join}";
            }
        }

        if ($where !== null) {
            [$whereClause, $whereParams] = $this->buildWhereClause($where);
            $query .= $whereClause;
            $params = [...$params, ...$whereParams];
        }

        if ($group_by !== null) {
            $query .= " GROUP BY {$group_by}";
        }

        if ($having !== null) {
            $query .= " HAVING {$having}";
        }

        if ($order_by !== null) {
            $orderClauses = array_map(fn($ob) => (string) $ob, $order_by);
            $query .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        if ($limit !== null) {
            $query .= " LIMIT {$limit}";
            if ($offset !== null) {
                $query .= " OFFSET {$offset}";
            }
        }

        $this->db->query($query);
        if (!empty($params)) {
            $this->db->bind($params);
        }
        $results = $this->db->asArray()->many()->fetch();

        return empty($results) ? null : $results;
    }

    public function get_one(
        string $table,
        ?array $columns = null,
        ?array $joins = null,
        ?array $where = null,
        ?string $group_by = null,
        ?string $having = null,
        ?array $order_by = null
    ): ?array {
        $result = $this->get_all(
            table: $table,
            columns: $columns,
            joins: $joins,
            where: $where,
            group_by: $group_by,
            having: $having,
            order_by: $order_by,
            limit: 1
        );

        return $result !== null && count($result) > 0 ? $result[0] : null;
    }

    public function insert(string $table, array $data): ?int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('No data provided for insert');
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $fullTable = $this->tablePrefix ? "{$this->tablePrefix}{$table}" : $table;

        $query = "INSERT INTO {$fullTable} ({$columns}) VALUES ({$placeholders})";

        $this->db->query($query)->bind(array_values($data))->execute();
        $lastId = $this->db->getLastId();

        return $lastId !== false ? (int) $lastId : null;
    }

    public function insert_many(
        string $table,
        array $data,
        bool $ignore_duplicates = true,
        int $batch_size = 1000
    ): void {
        if (empty($data)) {
            throw new InvalidArgumentException('No data provided for insert');
        }

        $columns = implode(', ', array_keys($data[0]));
        $placeholders = implode(', ', array_fill(0, count($data[0]), '?'));
        $fullTable = $this->tablePrefix ? "{$this->tablePrefix}{$table}" : $table;

        $ignoreKeyword = $ignore_duplicates ? 'IGNORE' : '';
        $baseQuery = "INSERT {$ignoreKeyword} INTO {$fullTable} ({$columns}) VALUES ({$placeholders})";

        $batches = array_chunk($data, $batch_size);

        foreach ($batches as $batch) {
            $this->db->transaction();
            try {
                foreach ($batch as $row) {
                    try {
                        $this->db->query($baseQuery)->bind(array_values($row))->execute();
                    } catch (\PDOException $e) {
                        if (!$ignore_duplicates || !str_contains($e->getMessage(), 'Duplicate entry')) {
                            echo "Database error in insert_many: {$e->getMessage()}\n";
                            echo "Query: {$baseQuery}\n";
                            throw $e;
                        }
                    }
                }
                $this->db->commit();
            } catch (\Exception $e) {
                $this->db->rollback();
                throw $e;
            }
        }
    }

    public function update(string $table, array $where, array $data): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('No data provided for update');
        }

        $setClause = implode(', ', array_map(fn($key) => "{$key} = ?", array_keys($data)));
        $fullTable = $this->tablePrefix ? "{$this->tablePrefix}{$table}" : $table;

        [$whereClause, $whereParams] = $this->buildWhereClause($where);

        $query = "UPDATE {$fullTable} SET {$setClause}{$whereClause}";
        $params = [...array_values($data), ...$whereParams];

        $this->db->query($query)->bind($params)->execute();

        return 1;
    }

    public function delete(string $table, array $where): int
    {
        $fullTable = $this->tablePrefix ? "{$this->tablePrefix}{$table}" : $table;
        [$whereClause, $whereParams] = $this->buildWhereClause($where);

        $query = "DELETE FROM {$fullTable}{$whereClause}";

        $this->db->query($query)->bind($whereParams)->execute();

        return 1;
    }

    public function call_proc(string $procedure_name, array $args = [], bool $fetch = false): mixed
    {
        try {
            $placeholders = implode(', ', array_fill(0, count($args), '?'));
            $query = "CALL {$procedure_name}({$placeholders})";

            $this->db->query($query);
            if (!empty($args)) {
                $this->db->bind($args);
            }

            if (!$fetch) {
                $this->db->execute();
                return 0;
            }

            $results = $this->db->asArray()->many()->fetch();
            return empty($results) ? null : $results;
        } catch (\Exception $e) {
            if (!$fetch) {
                return 0;
            }
            return null;
        }
    }

    public function execute_raw(string $query, array $params = [], bool $fetch = false): mixed
    {
        try {
            $this->db->query($query);
            if (!empty($params)) {
                $this->db->bind($params);
            }

            if (!$fetch) {
                $this->db->execute();
                return 0;
            }

            $results = $this->db->asArray()->many()->fetch();
            return empty($results) ? null : $results;
        } catch (\Exception $e) {
            if ($fetch) {
                return null;
            }
            throw $e;
        }
    }

    public function transaction(): void
    {
        $this->db->transaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        $this->db->rollback();
    }

    public function query(string $sql): Database
    {
        return $this->db->query($sql);
    }
}