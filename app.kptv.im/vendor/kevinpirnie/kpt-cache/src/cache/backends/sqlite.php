<?php

/**
 * KPT Cache - SQLite Database Backend
 *
 * Provides SQLite database backend support for the KPT cache system.
 * Creates and manages a SQLite database file for cache storage.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't exist first
if (! trait_exists('CacheSQLite')) {

    /**
     * KPT Cache SQLite Backend Trait
     *
     * Implements SQLite database backend for the KPT cache system.
     * Creates and manages a SQLite file with automatic table creation.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheSQLite
    {
        /** @var PDO|null SQLite PDO instance */
        private static ?\PDO $_sqlite_db = null;

        /** @var string|null Last SQLite error message */
        private static ?string $_sqlite_last_error = null;

        /** @var bool SQLite cache table initialized flag */
        private static bool $_sqlite_table_initialized = false;

        /**
         * Get SQLite database instance
         *
         * Creates or returns existing SQLite database connection for cache operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return \PDO|null Returns PDO instance or null if unavailable
         */
        private static function getSQLiteDatabase(): ?\PDO
        {

            // return existing connection if available
            if (self::$_sqlite_db !== null) {
                return self::$_sqlite_db;
            }

            // try to create new sqlite database instance
            try {
                // get sqlite configuration
                $config = CacheConfig::get('sqlite');
                $db_path = $config['db_path'] ?? self::getSQLiteDefaultPath();

                // ensure directory exists
                $dir = dirname($db_path);
                if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
                    self::$_sqlite_last_error = "Failed to create SQLite directory: {$dir}";
                    return null;
                }

                // create SQLite connection
                $dsn = "sqlite:{$db_path}";
                self::$_sqlite_db = new \PDO($dsn);

                // set SQLite options
                self::$_sqlite_db -> setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                self::$_sqlite_db -> setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);

                // enable WAL mode for better concurrency
                self::$_sqlite_db -> exec('PRAGMA journal_mode=WAL');
                self::$_sqlite_db -> exec('PRAGMA synchronous=NORMAL');
                self::$_sqlite_db -> exec('PRAGMA cache_size=10000');
                self::$_sqlite_db -> exec('PRAGMA temp_store=MEMORY');

                // ensure cache table exists
                if (! self::$_sqlite_table_initialized) {
                    self::initializeSQLiteTable();
                    self::$_sqlite_table_initialized = true;
                }

                // return the database instance
                return self::$_sqlite_db;

            // whoopsie... setup the error and return null
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "Failed to create SQLite connection: " . $e -> getMessage();
                return null;
            }
        }

        /**
         * Get default SQLite database path
         *
         * Returns the default path for the SQLite cache database file.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Returns the default database path
         */
        private static function getSQLiteDefaultPath(): string
        {

            // try to get from global cache config
            $global_path = CacheConfig::getGlobalPath();
            if ($global_path) {
                return rtrim($global_path, '/') . '/kpt_cache.sqlite';
            }

            // fallback to system temp directory
            return sys_get_temp_dir() . '/kpt_cache/kpt_cache.sqlite';
        }

        /**
         * Initialize SQLite cache table
         *
         * Creates the cache table with proper indexes and structure
         * for optimal cache performance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if table was created/exists, false otherwise
         */
        private static function initializeSQLiteTable(): bool
        {

            // try to create the cache table
            try {
                // get sqlite configuration
                $config = CacheConfig::get('sqlite');
                $table_name = $config['table_name'] ?? 'kpt_cache';

                // create cache table SQL
                $create_sql = "
                    CREATE TABLE IF NOT EXISTS `{$table_name}` (
                        `cache_key` TEXT PRIMARY KEY NOT NULL,
                        `cache_value` TEXT NOT NULL,
                        `expires_at` INTEGER NULL,
                        `created_at` INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
                        `updated_at` INTEGER NOT NULL DEFAULT (strftime('%s', 'now'))
                    )
                ";

                // execute table creation
                self::$_sqlite_db -> exec($create_sql);

                // create indexes
                $index_sql = [
                    "CREATE INDEX IF NOT EXISTS `idx_{$table_name}_expires_at` ON `{$table_name}` (`expires_at`)",
                    "CREATE INDEX IF NOT EXISTS `idx_{$table_name}_created_at` ON `{$table_name}` (`created_at`)"
                ];

                // execute index creation
                foreach ($index_sql as $sql) {
                    self::$_sqlite_db -> exec($sql);
                }

                // create trigger for updated_at
                $trigger_sql = "
                    CREATE TRIGGER IF NOT EXISTS `trigger_{$table_name}_updated_at`
                    AFTER UPDATE ON `{$table_name}`
                    FOR EACH ROW
                    BEGIN
                        UPDATE `{$table_name}` SET updated_at = strftime('%s', 'now') WHERE cache_key = NEW.cache_key;
                    END
                ";

                // execute trigger creation
                self::$_sqlite_db -> exec($trigger_sql);

                // return success
                return true;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "Failed to initialize SQLite cache table: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get item from SQLite cache
         *
         * Retrieves a cached item from the SQLite database, checking
         * expiration and returning the unserialized data.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found/expired
         */
        private static function getFromSQLite(string $key): mixed
        {

            // get the database instance
            $db = self::getSQLiteDatabase();
            if (! $db) {
                return false;
            }

            // try to get item from sqlite cache
            try {
                // get sqlite configuration
                $config = CacheConfig::get('sqlite');
                $table_name = $config['table_name'] ?? 'kpt_cache';

                // setup the query sql
                $sql = "
                    SELECT cache_value, expires_at 
                    FROM `{$table_name}` 
                    WHERE cache_key = ? 
                    AND (expires_at IS NULL OR expires_at > strftime('%s', 'now'))
                ";

                // prepare and execute the query
                $stmt = $db -> prepare($sql);
                $stmt -> execute([$key]);
                $result = $stmt -> fetch();

                // check if we have a result and cache value
                if ($result && $result -> cache_value) {
                    // unserialize the cached data
                    $data = unserialize($result -> cache_value);
                    return $data !== false ? $data : false;
                }

                // no result found
                return false;

            // whoopsie... setup the error
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "SQLite get error: " . $e -> getMessage();
            }

            // return false if not found or error
            return false;
        }

        /**
         * Set item to SQLite cache
         *
         * Stores an item in the SQLite cache with the specified TTL.
         * Uses REPLACE INTO for efficient upsert operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToSQLite(string $key, mixed $data, int $ttl): bool
        {

            // get the database instance
            $db = self::getSQLiteDatabase();
            if (! $db) {
                return false;
            }

            // try to set item to sqlite cache
            try {
                // get sqlite configuration
                $config = CacheConfig::get('sqlite');
                $table_name = $config['table_name'] ?? 'kpt_cache';

                // serialize the data
                $serialized_data = serialize($data);

                // calculate expiration time (Unix timestamp)
                $expires_at = $ttl > 0 ? time() + $ttl : null;

                // setup the insert sql
                $sql = "
                    REPLACE INTO `{$table_name}` 
                    (cache_key, cache_value, expires_at) 
                    VALUES (?, ?, ?)
                ";

                // prepare and execute the query
                $stmt = $db -> prepare($sql);
                $result = $stmt -> execute([$key, $serialized_data, $expires_at]);

                // return success status
                return $result !== false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "SQLite set error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Delete item from SQLite cache
         *
         * Removes a cached item from the SQLite database.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to delete
         * @return bool Returns true if successful, false otherwise
         */
        private static function deleteFromSQLite(string $key): bool
        {

            // get the database instance
            $db = self::getSQLiteDatabase();
            if (! $db) {
                return false;
            }

            // try to delete the item from sqlite cache
            try {
                // get sqlite configuration
                $config = CacheConfig::get('sqlite');
                $table_name = $config['table_name'] ?? 'kpt_cache';

                // setup the delete sql
                $sql = "DELETE FROM `{$table_name}` WHERE cache_key = ?";

                // debug logging
                Logger::debug('Delete from SQLite cache', ['key' => $key]);

                // prepare and execute the delete query
                $stmt = $db -> prepare($sql);
                $result = $stmt -> execute([$key]);

                // return success status
                return $result !== false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "SQLite delete error: " . $e -> getMessage();
                Logger::error("SQLite delete error", ['error' => $e -> getMessage()]);
                return false;
            }
        }

        /**
         * Clear all items from SQLite cache
         *
         * Removes all cached items from the SQLite cache table.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if successful, false otherwise
         */
        public static function clearSQLite(): bool
        {

            // get the database instance
            $db = self::getSQLiteDatabase();
            if (! $db) {
                return false;
            }

            // try to clear all sqlite cache items
            try {
                // get sqlite configuration
                $config = CacheConfig::get('sqlite');
                $table_name = $config['table_name'] ?? 'kpt_cache';

                // setup the delete sql
                $sql = "DELETE FROM `{$table_name}`";

                // debug logging
                Logger::debug('Clearing SQLite cache');

                // execute the delete query
                $result = $db -> exec($sql);

                // vacuum to reclaim space
                $db -> exec('VACUUM');

                // return success status
                return $result !== false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "SQLite clear error: " . $e -> getMessage();
                Logger::error("SQLite clear error", ['error' => $e -> getMessage()]);
                return false;
            }
        }

        /**
         * Clean up expired SQLite cache items
         *
         * Removes all expired items from the SQLite cache table
         * and returns the number of items cleaned.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of expired items removed
         */
        private static function cleanupSQLite(): int
        {

            // get the database instance
            $db = self::getSQLiteDatabase();
            if (! $db) {
                return 0;
            }

            // try to cleanup expired sqlite cache items
            try {
                // get sqlite configuration
                $config = CacheConfig::get('sqlite');
                $table_name = $config['table_name'] ?? 'kpt_cache';

                // setup the cleanup sql
                $sql = "DELETE FROM `{$table_name}` WHERE expires_at IS NOT NULL AND expires_at <= strftime('%s', 'now')";

                // debug logging
                Logger::debug('Cleaning up expired SQLite cache items');

                // execute the cleanup query
                $result = $db -> exec($sql);

                // return the count of cleaned items
                return is_numeric($result) ? (int)$result : 0;

            // whoopsie... setup the error and return zero
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "SQLite cleanup error: " . $e -> getMessage();
                Logger::error("SQLite cleanup error", ['error' => $e -> getMessage()]);
                return 0;
            }
        }

        /**
         * Test SQLite cache availability and functionality
         *
         * Performs a comprehensive test of the SQLite cache including
         * file creation, basic operations, and cleanup.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if SQLite cache is functional, false otherwise
         */
        private static function testSQLiteConnection(): bool
        {

            // try to test sqlite cache functionality
            try {
                // get the database instance
                $db = self::getSQLiteDatabase();
                if (! $db) {
                    return false;
                }

                // setup test data
                $test_key = 'sqlite_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // test set operation
                if (! self::setToSQLite($test_key, $test_value, 60)) {
                    return false;
                }

                // test get operation
                $retrieved = self::getFromSQLite($test_key);

                // cleanup test data
                self::deleteFromSQLite($test_key);

                // verify retrieved data matches
                return $retrieved === $test_value;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "SQLite test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get SQLite cache statistics
         *
         * Returns statistics about the SQLite cache including
         * database size, entry counts, and performance metrics.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns statistics array
         */
        private static function getSQLiteStats(): array
        {

            // setup the stats array
            $stats = [
                'total_entries' => 0,
                'expired_entries' => 0,
                'valid_entries' => 0,
                'database_size_kb' => 0,
                'database_path' => null,
                'oldest_entry' => null,
                'newest_entry' => null
            ];

            // try to get sqlite cache statistics
            try {
                // get the database instance
                $db = self::getSQLiteDatabase();
                if (! $db) {
                    return $stats;
                }

                // get sqlite configuration
                $config = CacheConfig::get('sqlite');
                $table_name = $config['table_name'] ?? 'kpt_cache';
                $db_path = $config['db_path'] ?? self::getSQLiteDefaultPath();

                // set the database path
                $stats['database_path'] = $db_path;

                // get database file size
                if (file_exists($db_path)) {
                    $stats['database_size_kb'] = round(filesize($db_path) / 1024, 2);
                }

                // get basic counts sql
                $count_sql = "
                    SELECT 
                        COUNT(*) as total_entries,
                        COUNT(CASE WHEN expires_at IS NOT NULL AND expires_at <= strftime('%s', 'now') THEN 1 END) as expired_entries,
                        COUNT(CASE WHEN expires_at IS NULL OR expires_at > strftime('%s', 'now') THEN 1 END) as valid_entries,
                        MIN(created_at) as oldest_entry,
                        MAX(created_at) as newest_entry
                    FROM `{$table_name}`
                ";

                // prepare and execute count query
                $stmt = $db -> prepare($count_sql);
                $stmt -> execute();
                $count_data = $stmt -> fetch();

                // process the count data if available
                if ($count_data) {
                    $stats['total_entries'] = (int)$count_data -> total_entries;
                    $stats['expired_entries'] = (int)$count_data -> expired_entries;
                    $stats['valid_entries'] = (int)$count_data -> valid_entries;
                    $stats['oldest_entry'] = $count_data -> oldest_entry ? date('Y-m-d H:i:s', $count_data -> oldest_entry) : null;
                    $stats['newest_entry'] = $count_data -> newest_entry ? date('Y-m-d H:i:s', $count_data -> newest_entry) : null;
                }

            // whoopsie... setup the error in stats
            } catch (\Exception $e) {
                $stats['error'] = $e -> getMessage();
            }

            // return the stats array
            return $stats;
        }

        /**
         * Get the last SQLite error message
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the last error message or null
         */
        private static function getSQLiteLastError(): ?string
        {

            // return the last sqlite error
            return self::$_sqlite_last_error;
        }

        /**
         * Close SQLite connections and cleanup resources
         *
         * Closes the SQLite database connection and cleans up resources.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        private static function closeSQLite(): void
        {

            // try to close sqlite connection and cleanup
            try {
                // close the database connection if available
                if (self::$_sqlite_db) {
                    self::$_sqlite_db = null;
                }

                // reset the table initialized flag
                self::$_sqlite_table_initialized = false;

            // whoopsie... ignore close errors
            } catch (\Exception $e) {
                // ignore close errors
            }
        }

        /**
         * Optimize SQLite database
         *
         * Performs database optimization including vacuum and analyze
         * to improve performance. Should be called periodically.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if optimization was successful
         */
        private static function optimizeSQLiteDatabase(): bool
        {

            // get the database instance
            $db = self::getSQLiteDatabase();
            if (! $db) {
                return false;
            }

            // try to optimize the sqlite database
            try {
                // debug logging
                Logger::debug('Optimizing SQLite database');

                // vacuum to reclaim space and defragment
                $db -> exec('VACUUM');

                // analyze to update statistics
                $db -> exec('ANALYZE');

                // return success
                return true;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_sqlite_last_error = "SQLite optimize error: " . $e -> getMessage();
                Logger::error("SQLite optimize error", ['error' => $e -> getMessage()]);
                return false;
            }
        }
    }
}
