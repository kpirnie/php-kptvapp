<?php

/**
 * This is our database class
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 *
 */

// throw it under my namespace
namespace KPT;

// if the class is not already in userspace
if (! class_exists('Database')) {

    /**
     * Class Database
     *
     * Database Class
     *
     * @since 8.4
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     *
     * @property protected $db_handle: The database handle used throughout the class
     * @property protected $current_query: The current query being built
     * @property protected $query_params: Parameters for the current query
     * @property protected $fetch_mode: The fetch mode for the current query
     *
     */
    class Database
    {
        // hold the database handle object
        protected ?\PDO $db_handle = null;

        // query builder properties
        protected string $current_query = '';
        protected array $query_params = [];
        protected int $fetch_mode = \PDO::FETCH_OBJ;
        protected bool $fetch_single = false;

        // optimization properties
        protected object $db_settings;
        protected bool $is_connected = false;
        protected string $driver = 'mysql';

        // Singleton/connection pool storage
        protected static array $instances = [];
        protected ?string $connection_name = null;

        // Query profiling
        protected bool $profiling_enabled = false;
        protected array $query_log = [];

        /**
         * __construct
         *
         * Initialize the database connection
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param object $db_settings Database configuration settings (required)
         * @return void
         * @throws \InvalidArgumentException When $db_settings is null or invalid
         */
        public function __construct(object $db_settings)
        {
            // validate settings first
            self::validateSettings($db_settings);

            $this->db_settings = $db_settings;
            $this->driver = $db_settings->driver ?? 'mysql';

            // Apply driver defaults
            $this->applyDriverDefaults();

            // Lazy connection - only connect when needed for performance
            Logger::debug("Database Constructor Completed Successfully");
        }

        /**
         * applyDriverDefaults - Apply database-specific defaults
         *
         * @return void
         */
        protected function applyDriverDefaults(): void
        {
            $defaults = match ($this->driver) {
                'mysql' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'port' => 3306],
                'pgsql' => ['charset' => 'UTF8', 'port' => 5432],
                'sqlsrv' => ['charset' => 'UTF-8', 'port' => 1433],
                'oci' => ['charset' => 'AL32UTF8', 'port' => 1521],
                'sqlite' => [],
                default => []
            };

            foreach ($defaults as $key => $value) {
                if (!isset($this->db_settings->$key)) {
                    $this->db_settings->$key = $value;
                }
            }
        }

        /**
         * connect - Lazy connection with optimized setup
         *
         * @return void
         * @throws \Exception
         */
        protected function connect(): void
        {
            if ($this->is_connected) {
                return;
            }

            try {
                // Build DSN based on driver
                $dsn = $this->buildDsn();

                // Create PDO with optimized attributes
                $this->db_handle = new \PDO(
                    $dsn,
                    $this->db_settings->username ?? '',
                    $this->db_settings->password ?? '',
                    $this->getOptimizedAttributes()
                );

                // Driver-specific configuration
                $this->configureDriver();

                $this->is_connected = true;

                Logger::debug("Database Connection Established");
            } catch (\Exception $e) {
                Logger::error("Database Connection Failed", ['message' => $e->getMessage()]);
                throw $e;
            }
        }

        /**
         * buildDsn - Build DSN string for different database drivers
         *
         * @return string
         */
        protected function buildDsn(): string
        {
            $s = $this->db_settings;

            return match ($this->driver) {
                'mysql' => sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $s->server ?? $s->host ?? 'localhost',
                    $s->port ?? 3306,
                    $s->schema ?? $s->database ?? '',
                    $s->charset ?? 'utf8mb4'
                ),

                'pgsql' => sprintf(
                    "pgsql:host=%s;port=%d;dbname=%s",
                    $s->server ?? $s->host ?? 'localhost',
                    $s->port ?? 5432,
                    $s->schema ?? $s->database ?? ''
                ),

                'sqlsrv' => sprintf(
                    "sqlsrv:Server=%s,%d;Database=%s",
                    $s->server ?? $s->host ?? 'localhost',
                    $s->port ?? 1433,
                    $s->schema ?? $s->database ?? ''
                ),

                'sqlite' => sprintf(
                    "sqlite:%s",
                    $s->path ?? $s->database ?? ':memory:'
                ),

                'oci' => sprintf(
                    "oci:dbname=//%s:%d/%s;charset=%s",
                    $s->server ?? $s->host ?? 'localhost',
                    $s->port ?? 1521,
                    $s->schema ?? $s->database ?? '',
                    $s->charset ?? 'AL32UTF8'
                ),

                default => throw new \RuntimeException("Unsupported database driver: {$this->driver}")
            };
        }

        /**
         * getOptimizedAttributes - Get optimized PDO attributes
         *
         * @return array
         */
        protected function getOptimizedAttributes(): array
        {
            // Check if persistent connections are enabled (default: false for safety)
            $persistent = $this->db_settings->persistent ?? false;

            $base = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_CASE => \PDO::CASE_NATURAL,
                \PDO::ATTR_PERSISTENT => $persistent,
            ];

            $driver_attrs = match ($this->driver) {
                'mysql' => [
                    \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->db_settings->charset}",
                ],
                'sqlsrv' => [\PDO::SQLSRV_ATTR_ENCODING => \PDO::SQLSRV_ENCODING_UTF8],
                'sqlite' => [\PDO::ATTR_TIMEOUT => 5],
                default => []
            };

            return array_merge($base, $driver_attrs);
        }

        /**
         * configureDriver - Post-connection driver configuration
         *
         * @return void
         */
        protected function configureDriver(): void
        {
            if (!$this->db_handle) {
                return;
            }

            switch ($this->driver) {
                case 'mysql':
                    $charset = $this->db_settings->charset ?? 'utf8mb4';
                    $collation = $this->db_settings->collation ?? 'utf8mb4_unicode_ci';
                    $this->db_handle->exec(
                        "SET NAMES $charset COLLATE $collation, 
                        CHARACTER SET $charset, 
                        collation_connection = $collation"
                    );
                    break;

                case 'pgsql':
                    $charset = $this->db_settings->charset ?? 'UTF8';
                    $this->db_handle->exec("SET NAMES '$charset'");
                    break;

                case 'sqlite':
                    $this->db_handle->exec("PRAGMA synchronous = NORMAL");
                    $this->db_handle->exec("PRAGMA journal_mode = WAL");
                    $this->db_handle->exec("PRAGMA temp_store = MEMORY");
                    break;
            }
        }

        /**
         * configure
         *
         * Static method to create a configured Database instance
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param array|object $config Database configuration (array or object)
         * @return self Returns configured Database instance
         * @throws \InvalidArgumentException When configuration is invalid
         */
        public static function configure(array|object $config): self
        {
            // if array is passed, convert to object
            if (is_array($config)) {
                $config = (object) $config;
            }

            // validate settings before attempting to create instance
            self::validateSettings($config);

            // debug logging
            Logger::debug("Database Configure Settings Validated Successfully");

            // create and return new instance (validation already done)
            return new self($config);
        }

        /**
         * getInstance
         *
         * Get or create a named database instance (connection pooling)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $name Connection name identifier
         * @param object|null $db_settings Database settings (required on first call)
         * @return self Returns Database instance
         * @throws \InvalidArgumentException When settings missing on first call
         */
        public static function getInstance(string $name = 'default', ?object $db_settings = null): self
        {
            if (!isset(self::$instances[$name])) {
                if ($db_settings === null) {
                    throw new \InvalidArgumentException("Database settings required for new connection: {$name}");
                }
                self::$instances[$name] = new self($db_settings);
                self::$instances[$name]->connection_name = $name;

                Logger::debug("Database Instance Created", ['name' => $name]);
            }

            return self::$instances[$name];
        }

        /**
         * closeInstance
         *
         * Close and remove a named instance
         *
         * @param string $name Connection name identifier
         * @return void
         */
        public static function closeInstance(string $name = 'default'): void
        {
            if (isset(self::$instances[$name])) {
                self::$instances[$name] = null;
                unset(self::$instances[$name]);

                Logger::debug("Database Instance Closed", ['name' => $name]);
            }
        }

        /**
         * enableProfiling
         *
         * Enable query profiling/logging
         *
         * @return self
         */
        public function enableProfiling(): self
        {
            $this->profiling_enabled = true;
            Logger::debug("Database Profiling Enabled");
            return $this;
        }

        /**
         * disableProfiling
         *
         * Disable query profiling/logging
         *
         * @return self
         */
        public function disableProfiling(): self
        {
            $this->profiling_enabled = false;
            Logger::debug("Database Profiling Disabled");
            return $this;
        }

        /**
         * getQueryLog
         *
         * Get the query log
         *
         * @return array
         */
        public function getQueryLog(): array
        {
            return $this->query_log;
        }

        /**
         * clearQueryLog
         *
         * Clear the query log
         *
         * @return self
         */
        public function clearQueryLog(): self
        {
            $this->query_log = [];
            return $this;
        }

        /**
         * logQuery
         *
         * Log a query execution for profiling
         *
         * @param string $query The SQL query
         * @param array $params Query parameters
         * @param float $start_time Microtime when query started
         * @return void
         */
        protected function logQuery(string $query, array $params, float $start_time): void
        {
            if (!$this->profiling_enabled) {
                return;
            }

            $duration = microtime(true) - $start_time;

            $this->query_log[] = [
                'query' => $query,
                'params' => $params,
                'duration_ms' => round($duration * 1000, 2),
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            Logger::debug("Database Query Profiled", [
                'duration_ms' => round($duration * 1000, 2),
            ]);
        }

        /**
         * validateSettings
         *
         * Validate database configuration settings
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param object $db_settings Database configuration settings to validate
         * @return void
         * @throws \InvalidArgumentException When settings are invalid
         */
        private static function validateSettings(object $db_settings): void
        {
            // validate that db_settings is provided
            if ($db_settings === null) {
                Logger::error("Database Validation Failed - No database settings provided");
                throw new \InvalidArgumentException('Database settings are required.');
            }

            // Get driver
            $driver = $db_settings->driver ?? 'mysql';

            // Driver-specific validation
            $required_properties = match ($driver) {
                'sqlite' => [],
                default => ['server', 'schema', 'username', 'password']
            };

            // validate required properties exist
            foreach ($required_properties as $property) {
                if (!property_exists($db_settings, $property)) {
                    Logger::error("Database Validation Failed - Missing required property", [
                        'missing_property' => $property,
                        'provided_properties' => array_keys(get_object_vars($db_settings))
                    ]);

                    throw new \InvalidArgumentException("Database settings missing required property: {$property}");
                }
            }
        }

        /**
         * __destruct
         *
         * Clean up the database connection
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return void
         */
        public function __destruct()
        {

            // try to clean up
            try {
                // reset
                $this->reset();

                // close the connection
                $this->db_handle = null;

                // clear em our
                unset($this->db_handle);

                // debug logging
                Logger::debug("Database Destructor Completed Successfully");

                // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Destructor Error", [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        /**
         * query
         *
         * Set the query to be executed
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $query The SQL query to prepare
         * @return self Returns self for method chaining
         */
        public function query(string $query): self
        {

            // reset the query builder state inline for performance
            $this->current_query = $query;
            $this->query_params = [];
            $this->fetch_mode = \PDO::FETCH_OBJ;
            $this->fetch_single = false;

            // debug logging
            Logger::debug("Database Query Stored Successfully", []);

            // return self for chaining
            return $this;
        }

        /**
         * bind
         *
         * Bind parameters for the current query
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param array|mixed $params Parameters to bind (array or single value)
         * @return self Returns self for method chaining
         */
        public function bind(mixed $params): self
        {

            // if single value passed, wrap in array
            if (! is_array($params)) {
                $params = [$params];
            }

            // store the parameters
            $this->query_params = $params;

            // debug logging
            Logger::debug("Database Parameters Bound Successfully", [
                'param_count' => count($this->query_params),
                'param_types' => array_map('gettype', $this->query_params)
            ]);

            // return self for chaining
            return $this;
        }

        /**
         * single
         *
         * Set fetch mode to return single record
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function single(): self
        {

            // set fetch single flag
            $this->fetch_single = true;

            // return self for chaining
            return $this;
        }

        /**
         * many
         *
         * Set fetch mode to return multiple records (default)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function many(): self
        {

            // set fetch single flag
            $this->fetch_single = false;

            // return self for chaining
            return $this;
        }

        /**
         * asArray
         *
         * Set fetch mode to return arrays instead of objects
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function asArray(): self
        {

            // set fetch mode to array
            $this->fetch_mode = \PDO::FETCH_ASSOC;

            // return self for chaining
            return $this;
        }

        /**
         * asObject
         *
         * Set fetch mode to return objects (default)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function asObject(): self
        {

            // set fetch mode to object
            $this->fetch_mode = \PDO::FETCH_OBJ;

            // return self for chaining
            return $this;
        }

        /**
         * fetch
         *
         * Execute SELECT query and fetch results
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param ?int $limit Optional limit for number of records
         * @return mixed Returns query results (object/array/bool)
         */
        public function fetch(?int $limit = null): mixed
        {

            // validate we have a query
            if (empty($this->current_query)) {
                // error logging
                Logger::error("Database Fetch Failed - No Query Set");

                throw new \RuntimeException('No query has been set. Call query() first.');
            }

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            // if limit is provided, determine fetch mode
            if ($limit === 1) {
                // set the single property
                $this->fetch_single = true;

                // debug logging
                Logger::debug("Database Fetch Mode Auto-Set to Single (limit=1)");

                // otherwise
            } elseif ($limit > 1) {
                // set it false
                $this->fetch_single = false;

                // debug logging
                Logger::debug("Database Fetch Mode Auto-Set to Many", [
                    'limit' => $limit
                ]);
            }

            // try to execute the query
            try {
                // capture start time for profiling
                $start_time = microtime(true);

                // prepare the statement
                $stmt = $this->db_handle->prepare($this->current_query);

                // bind parameters if we have any
                $this->bindParams($stmt, $this->query_params);

                // execute the query
                if (! $stmt->execute()) {
                    // error logging
                    Logger::error("Database Query Execution Failed");

                    return false;
                }

                // fetch based on mode - optimized
                $result = $this->fetch_single
                    ? $stmt->fetch($this->fetch_mode)
                    : $stmt->fetchAll($this->fetch_mode);

                // close the cursor
                $stmt->closeCursor();

                // log query for profiling
                $this->logQuery($this->current_query, $this->query_params, $start_time);

                // debug logging
                Logger::debug($this->fetch_single ? "Database Single Record Fetched" :
                    "Database Multiple Records Fetched", [
                    'has_result' => !empty($result),
                    'result_count' => is_array($result) ? count($result) : 0
                ]);

                // return the result
                return !empty($result) ? $result : false;

                // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Fetch Error", [
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        /**
         * count
         *
         * Execute a COUNT query and return the result
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $table The table name
         * @param string $column The column to count (default: *)
         * @param string|null $where Optional WHERE clause (without 'WHERE' keyword)
         * @param array $params Optional parameters for WHERE clause
         * @return int|false Returns count or false on failure
         */
        public function count(string $table, string $column = '*', ?string $where = null, array $params = []): int|false
        {
            // build the query
            $query = "SELECT COUNT({$column}) as cnt FROM {$table}";

            // add where clause if provided
            if ($where !== null) {
                $query .= " WHERE {$where}";
            }

            // execute and get result
            $result = $this->query($query)->bind($params)->single()->fetch();

            // return the count
            if ($result === false) {
                return false;
            }

            return (int) ($this->fetch_mode === \PDO::FETCH_ASSOC ? $result['cnt'] : $result->cnt);
        }

        /**
         * exists
         *
         * Check if records exist matching the criteria
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $table The table name
         * @param string $where WHERE clause (without 'WHERE' keyword)
         * @param array $params Parameters for WHERE clause
         * @return bool Returns true if records exist, false otherwise
         */
        public function exists(string $table, string $where, array $params = []): bool
        {
            // build an efficient EXISTS query
            $query = "SELECT EXISTS(SELECT 1 FROM {$table} WHERE {$where} LIMIT 1) as record_exists";

            // execute and get result
            $result = $this->query($query)->bind($params)->single()->fetch();

            // return boolean
            if ($result === false) {
                return false;
            }

            return (bool) ($this->fetch_mode === \PDO::FETCH_ASSOC ? $result['record_exists'] : $result->record_exists);
        }

        /**
         * first
         *
         * Fetch the first record from the query result
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return mixed Returns single record or false if not found
         */
        public function first(): mixed
        {
            return $this->single()->fetch();
        }

        /**
         * insertBatch
         *
         * Insert multiple rows efficiently in a single query
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $table The table name
         * @param array $columns Array of column names
         * @param array $rows Array of rows, each row is an array of values
         * @return int|false Returns number of inserted rows or false on failure
         */
        public function insertBatch(string $table, array $columns, array $rows): int|false
        {
            // validate inputs
            if (empty($columns) || empty($rows)) {
                Logger::error("Database Insert Batch Failed - Empty columns or rows");
                return false;
            }

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            try {
                // build column list
                $column_list = implode(', ', $columns);
                $column_count = count($columns);

                // build placeholders for a single row
                $row_placeholder = '(' . implode(', ', array_fill(0, $column_count, '?')) . ')';

                // build all row placeholders
                $placeholders = implode(', ', array_fill(0, count($rows), $row_placeholder));

                // build the query
                $query = "INSERT INTO {$table} ({$column_list}) VALUES {$placeholders}";

                // flatten all row values into single params array
                $params = [];
                foreach ($rows as $row) {
                    if (count($row) !== $column_count) {
                        Logger::error("Database Insert Batch Failed - Row column count mismatch");
                        return false;
                    }
                    foreach ($row as $value) {
                        $params[] = $value;
                    }
                }

                // execute the query
                $result = $this->query($query)->bind($params)->execute();

                Logger::debug("Database Insert Batch Completed", [
                    'table' => $table,
                    'rows_inserted' => count($rows)
                ]);

                return $result !== false ? count($rows) : false;
            } catch (\Exception $e) {
                Logger::error("Database Insert Batch Error", [
                    'message' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        /**
         * upsert
         *
         * Insert or update a record (MySQL ON DUPLICATE KEY UPDATE)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $table The table name
         * @param array $data Associative array of column => value pairs to insert
         * @param array $update Associative array of column => value pairs to update on duplicate
         * @return int|false Returns last insert ID, affected rows, or false on failure
         */
        public function upsert(string $table, array $data, array $update): int|false
        {
            // validate inputs
            if (empty($data) || empty($update)) {
                Logger::error("Database Upsert Failed - Empty data or update arrays");
                return false;
            }

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            try {
                // build column and placeholder lists for INSERT
                $columns = array_keys($data);
                $column_list = implode(', ', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));

                // build UPDATE clause
                $update_parts = [];
                foreach (array_keys($update) as $col) {
                    $update_parts[] = "{$col} = ?";
                }
                $update_clause = implode(', ', $update_parts);

                // build driver-specific query
                $query = match ($this->driver) {
                    'mysql' => "INSERT INTO {$table} ({$column_list}) VALUES ({$placeholders}) " .
                        "ON DUPLICATE KEY UPDATE {$update_clause}",
                    'sqlite', 'pgsql' => "INSERT INTO {$table} ({$column_list}) VALUES ({$placeholders}) " .
                        "ON CONFLICT DO UPDATE SET {$update_clause}",
                    default => throw new \RuntimeException("Upsert not supported for driver: {$this->driver}")
                };

                // combine params: insert values + update values
                $params = array_merge(array_values($data), array_values($update));

                // execute the query
                $result = $this->query($query)->bind($params)->execute();

                Logger::debug("Database Upsert Completed", [
                    'table' => $table
                ]);

                return $result;
            } catch (\Exception $e) {
                Logger::error("Database Upsert Error", [
                    'message' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        /**
         * replace
         *
         * Replace a record (MySQL REPLACE INTO)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $table The table name
         * @param array $data Associative array of column => value pairs
         * @return int|false Returns last insert ID or false on failure
         */
        public function replace(string $table, array $data): int|false
        {
            // validate inputs
            if (empty($data)) {
                Logger::error("Database Replace Failed - Empty data array");
                return false;
            }

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            try {
                // build column and placeholder lists
                $columns = array_keys($data);
                $column_list = implode(', ', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));

                // build driver-specific query
                $query = match ($this->driver) {
                    'mysql' => "REPLACE INTO {$table} ({$column_list}) VALUES ({$placeholders})",
                    'sqlite' => "INSERT OR REPLACE INTO {$table} ({$column_list}) VALUES ({$placeholders})",
                    default => throw new \RuntimeException("Replace not supported for driver: {$this->driver}")
                };

                // execute the query
                $result = $this->query($query)->bind(array_values($data))->execute();

                Logger::debug("Database Replace Completed", [
                    'table' => $table
                ]);

                return $result;
            } catch (\Exception $e) {
                Logger::error("Database Replace Error", [
                    'message' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        /**
         * quote
         *
         * Quote a string for safe use in a query (for edge cases)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $value The value to quote
         * @param int $type PDO parameter type (default: PDO::PARAM_STR)
         * @return string|false Returns quoted string or false on failure
         */
        public function quote(string $value, int $type = \PDO::PARAM_STR): string|false
        {
            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            try {
                return $this->db_handle->quote($value, $type);
            } catch (\Exception $e) {
                Logger::error("Database Quote Error", [
                    'message' => $e->getMessage()
                ]);
                return false;
            }
        }

        /**
         * execute
         *
         * Execute non-SELECT queries (INSERT, UPDATE, DELETE)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return mixed Returns last insert ID for INSERT, affected rows for UPDATE/DELETE, or false on failure
         */
        public function execute(): mixed
        {

            // validate we have a query
            if (empty($this->current_query)) {
                // error logging
                Logger::error("Database Execute Failed - No Query Set");

                // throw an exception
                throw new \RuntimeException('No query has been set. Call query() first.');
            }

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            // try to execute the query
            try {
                // capture start time for profiling
                $start_time = microtime(true);

                // prepare the statement
                $stmt = $this->db_handle->prepare($this->current_query);

                // debug logging
                Logger::debug("Database Statement Prepared for Execute");

                // bind parameters if we have any
                $this->bindParams($stmt, $this->query_params);

                // execute the query
                $success = $stmt->execute();

                // it was not successful, log an error and return false
                if (! $success) {
                    // error logging
                    Logger::error("Database Execute Failed", []);
                    return false;
                }

                // debug logging
                Logger::debug("Database Query Executed Successfully");

                // determine return value based on query type
                $query_type = strtoupper(substr(trim($this->current_query), 0, 6));

                // figure out what kind of query are we running for the return value
                switch ($query_type) {
                    case 'INSERT':
                        // return last insert ID for inserts
                        $id = $this->db_handle->lastInsertId();
                        $result = $id ?: true;

                        // debug logging
                        Logger::debug("Database INSERT Executed", []);

                        // log query for profiling
                        $this->logQuery($this->current_query, $this->query_params, $start_time);

                        return $result;

                    case 'UPDATE':
                    case 'DELETE':
                        // return affected rows for updates/deletes
                        $affected_rows = $stmt->rowCount();

                        // debug logging
                        Logger::debug("Database {$query_type} Executed", []);

                        // log query for profiling
                        $this->logQuery($this->current_query, $this->query_params, $start_time);

                        return $affected_rows;

                    default:
                        // debug logging
                        Logger::debug("Database {$query_type} Executed", []);

                        // log query for profiling
                        $this->logQuery($this->current_query, $this->query_params, $start_time);

                        // return success for other queries
                        return $success;
                }

                // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Execute Error", [
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        /**
         * getLastId
         *
         * Get the last inserted ID
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return string|false Returns the last insert ID or false
         */
        public function getLastId(): string|false
        {

            // try to get the last insert ID
            try {
                // ensure connection exists
                if (!$this->is_connected || !$this->db_handle) {
                    return false;
                }

                // return the last id
                return $this->db_handle->lastInsertId() ?? false;

                // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Get Last ID Error", [
                    'message' => $e->getMessage()
                ]);

                return false;
            }
        }

        /**
         * transaction
         *
         * Begin a database transaction
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return bool Returns true if transaction started successfully
         */
        public function transaction(): bool
        {

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            // try to begin transaction
            try {
                // begin the transaction
                return $this->db_handle->beginTransaction();

                // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Transaction Start Error", [
                    'message' => $e->getMessage()
                ]);

                return false;
            }
        }

        /**
         * commit
         *
         * Commit the current transaction
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return bool Returns true if transaction committed successfully
         */
        public function commit(): bool
        {

            // try to commit transaction
            try {
                // commit the transaction
                return $this->db_handle->commit();

                // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Transaction Commit Error", [
                    'message' => $e->getMessage()
                ]);

                return false;
            }
        }

        /**
         * rollback
         *
         * Roll back the current transaction
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return bool Returns true if transaction rolled back successfully
         */
        public function rollback(): bool
        {

            // try to rollback transaction
            try {
                // rollback the transaction
                return $this->db_handle->rollBack();

                // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Transaction Rollback Error", [
                    'message' => $e->getMessage()
                ]);

                return false;
            }
        }

        /**
         * Check if currently in a transaction
         *
         * @return bool
         */
        public function inTransaction(): bool
        {
            // make sure we have a connection as well
            if (!$this->is_connected || !$this->db_handle) {
                return false;
            }

            // now try to see if we're in a transaction
            try {
                return $this->db_handle->inTransaction();
            } catch (\Exception $e) {
                Logger::error("Database inTransaction Check Error", [
                    'message' => $e->getMessage()
                ]);
                return false;
            }
        }

        /**
         * reset
         *
         * Reset the query builder state
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function reset(): self
        {

            // reset all query builder properties
            $this->current_query = '';
            $this->query_params = [];
            $this->fetch_mode = \PDO::FETCH_OBJ;
            $this->fetch_single = false;

            // debug logging
            Logger::debug("Database Reset Completed");

            // return self for chaining
            return $this;
        }

        /**
         * bindParams
         *
         * Bind parameters to a prepared statement with appropriate data types
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param PDOStatement $stmt The prepared statement to bind parameters to
         * @param array $params The parameters to bind
         * @return void
         */
        private function bindParams(\PDOStatement $stmt, array $params = []): void
        {
            // if we don't have any parameters just return
            if (empty($params)) {
                Logger::debug("Database Bind Params - No Parameters to Bind");
                return;
            }

            // try to bind parameters
            try {
                // check if using named parameters (associative array) or positional (numeric array)
                $is_named = array_keys($params) !== range(0, count($params) - 1);

                // loop over the parameters
                foreach ($params as $key => $param) {
                    // Optimized type detection using match
                    $paramType = match (true) {
                        is_bool($param) => \PDO::PARAM_BOOL,
                        is_int($param) => \PDO::PARAM_INT,
                        is_null($param) => \PDO::PARAM_NULL,
                        default => \PDO::PARAM_STR
                    };

                    // determine the bind key
                    if ($is_named) {
                        // ensure named param has colon prefix
                        $bind_key = str_starts_with($key, ':') ? $key : ':' . $key;
                    } else {
                        // positional params are 1-indexed
                        $bind_key = $key + 1;
                    }

                    // bind the parameter and value
                    $stmt->bindValue($bind_key, $param, $paramType);

                    // debug logging
                    Logger::debug("Database Parameter Bound", [
                        'key' => $bind_key,
                        'param_type' => gettype($param),
                        'pdo_type' => $paramType,
                    ]);
                }

                // debug logging
                Logger::debug("Database Bind Params Completed Successfully", [
                    'total_bound' => count($params),
                    'binding_type' => $is_named ? 'named' : 'positional'
                ]);

                // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Bind Params Error", [
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        /**
         * raw
         *
         * Execute a raw query without the query builder
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $query The SQL query to execute
         * @param array $params Optional parameters to bind
         * @return mixed Returns query results or false on failure
         */
        public function raw(string $query, array $params = []): mixed
        {
            // determine query type
            $query_type = strtoupper(substr(trim($query), 0, 6));

            // set up the query and params
            $this->query($query)->bind($params);

            // handle SELECT queries via fetch()
            if ($query_type === 'SELECT') {
                return $this->asObject()->many()->fetch();
            }

            // handle all other queries via execute()
            return $this->execute();
        }
    }
}
