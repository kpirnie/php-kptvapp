<?php

/**
 * KPT Cache - Redis Caching Traits
 * Enhanced Redis support with connection pooling and async operations
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheRedis')) {

    /**
     * KPT Cache Redis Trait
     *
     * Provides comprehensive Redis caching functionality with connection pooling,
     * transaction support, and enhanced batch processing capabilities.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheRedis
    {
        // Keep direct connection for non-pooled usage
        private static ?Redis $_redis = null;

        /**
         * Test Redis connection
         *
         * Performs a connectivity test to ensure Redis is available
         * and functioning properly with basic operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if connection test passes, false otherwise
         */
        private static function testRedisConnection(): bool
        {

            // try to test the redis connection
            try {
                // get redis configuration
                $config = CacheConfig::get('redis');

                // create new redis instance
                $redis = new \Redis();
                $connected = $redis -> pconnect(
                    $config['host'],
                    $config['port'],
                    $config['connect_timeout'] ?? 2
                );

                // check if connection failed
                if (! $connected) {
                    return false;
                }

                // select the database and test ping
                $redis -> select($config['database'] ?? 0);
                $result = $redis -> ping();
                $redis -> close();

                // return ping result
                return $result === true || $result === '+PONG';

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "Redis test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get Redis connection (backward compatibility)
         *
         * Uses connection pool if available, falls back to direct connection
         * for backward compatibility and optimal resource management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return Redis|null Returns Redis connection or null on failure
         */
        private static function getRedis(): ?Redis
        {

            // Try connection pool first
            if (self::$_connection_pooling_enabled ?? true) {
                // get connection from pool
                $connection = CacheConnectionPool::getConnection('redis');
                if ($connection) {
                    return $connection;
                }
            }

            // Fallback to direct connection
            if (self::$_redis === null || ! self::isRedisConnected()) {
                self::$_redis = self::createDirectRedisConnection();
            }

            // return the direct connection
            return self::$_redis;
        }

        /**
         * Create direct Redis connection (non-pooled)
         *
         * Creates a direct Redis connection with retry logic
         * and proper configuration for non-pooled usage.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return Redis|null Returns Redis connection or null on failure
         */
        private static function createDirectRedisConnection(): ?Redis
        {

            // get configuration and setup retry logic
            $config = CacheConfig::get('redis');
            $attempts = 0;
            $max_attempts = $config['retry_attempts'] ?? 2;

            // try up to max attempts
            while ($attempts <= $max_attempts) {
                // try to create connection
                try {
                    // create redis instance
                    $redis = new \Redis();

                    // attempt persistent connection
                    $connected = $redis -> pconnect(
                        $config['host'],
                        $config['port'],
                        $config['connect_timeout'] ?? 2
                    );

                    // check if connection failed
                    if (! $connected) {
                        throw new \RedisException("Connection failed");
                    }

                    // select the database
                    $redis -> select($config['database'] ?? 0);

                    // set prefix if configured
                    if (! empty($config['prefix'])) {
                        $redis -> setOption(\Redis::OPT_PREFIX, $config['prefix'] ?? CacheConfig::getGlobalPrefix());
                    }

                    // test connection with ping
                    $ping_result = $redis -> ping();
                    if ($ping_result !== true && $ping_result !== '+PONG') {
                        throw new \RedisException("Ping test failed");
                    }

                    // return successful connection
                    return $redis;

                // whoopsie... setup error and retry
                } catch (\RedisException $e) {
                    self::$_last_error = $e -> getMessage();

                    // add delay between retries
                    if ($attempts < $max_attempts) {
                        usleep(( $config['retry_delay'] ?? 100 ) * 1000);
                    }
                    $attempts++;
                }
            }

            // failed after all attempts
            return null;
        }

        /**
         * Check if Redis connection is alive
         *
         * Tests if the current Redis connection is still active
         * and responsive to basic operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if connection is alive, false otherwise
         */
        private static function isRedisConnected(): bool
        {

            // check if connection exists
            if (self::$_redis === null) {
                return false;
            }

            // try to test connection with ping
            try {
                // ping the server
                $result = self::$_redis -> ping();
                return $result === true || $result === '+PONG';

            // whoopsie... connection failed
            } catch (\RedisException $e) {
                return false;
            }
        }

        /**
         * Get from Redis with pool-aware connection handling
         *
         * Retrieves an item from Redis using connection pooling
         * when available for optimal resource management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found
         */
        private static function getFromRedis(string $_key): mixed
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to get the item from redis
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('redis');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;
                $value = $connection -> get($prefixed_key);

                // unserialize and return the value
                return $value !== false ? unserialize($value) : false;

            // whoopsie... handle errors
            } catch (\RedisException $e) {
                self::$_last_error = $e -> getMessage();
                if (! $use_pool) {
                    self::$_redis = null; // Reset direct connection on error
                }
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }
        }

        /**
         * Set to Redis with pool-aware connection handling
         *
         * Stores an item in Redis using connection pooling
         * when available for optimal resource management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to store
         * @param mixed $_data The data to cache
         * @param int $_length Time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToRedis(string $_key, mixed $_data, int $_length): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to set the item to redis
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('redis');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // set the item with expiration
                return $connection -> setex($prefixed_key, $_length, serialize($_data));

            // whoopsie... handle errors
            } catch (\RedisException $e) {
                self::$_last_error = $e -> getMessage();
                if (! $use_pool) {
                    self::$_redis = null;
                }
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }
        }

        /**
         * Delete from Redis with pool-aware connection handling
         *
         * Deletes an item from Redis using connection pooling
         * when available for optimal resource management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to delete
         * @return bool Returns true if successful, false otherwise
         */
        private static function deleteFromRedis(string $_key): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to delete the item from redis
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // delete the item
                $config = CacheConfig::get('redis');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;
                $deleted_count = $connection -> del($prefixed_key);

                // Consider it successful if key was deleted OR if key didn't exist
                // Both scenarios mean the key is no longer in Redis, which is the desired outcome
                return $deleted_count >= 0;

            // whoopsie... handle errors
            } catch (\RedisException $e) {
                self::$_last_error = $e -> getMessage();
                if (! $use_pool) {
                    self::$_redis = null;
                }
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }
        }

        /**
         * Enhanced Redis transaction operations for pooled connections
         *
         * Executes multiple Redis commands in a transaction for atomic
         * operations across multiple commands.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $commands Array of Redis commands to execute in transaction
         * @return array Returns array of transaction results
         */
        public static function redisTransaction(array $commands): array
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to execute the transaction
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                // check if we got a connection
                if (! $connection) {
                    return [ ];
                }

                // start the multi transaction
                $multi = $connection -> multi();

                // add each command to the transaction
                foreach ($commands as $command) {
                    $method = $command['method'];
                    $args = $command['args'] ?? [ ];
                    $multi -> $method(...$args);
                }

                // execute the transaction
                return $multi -> exec() ?: [ ];

            // whoopsie... handle errors
            } catch (\RedisException $e) {
                self::$_last_error = $e -> getMessage();
                return [ ];

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }
        }

        /**
         * Enhanced Redis pipeline operations
         *
         * Executes multiple Redis commands in a pipeline for improved
         * performance when executing multiple operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $commands Array of Redis commands to execute in pipeline
         * @return array Returns array of pipeline results
         */
        public static function redisPipeline(array $commands): array
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to execute the pipeline
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                // check if we got a connection
                if (! $connection) {
                    return [ ];
                }

                // create the pipeline
                $pipeline = $connection -> pipeline();

                // add each command to the pipeline
                foreach ($commands as $command) {
                    $method = $command['method'];
                    $args = $command['args'] ?? [ ];
                    $pipeline -> $method(...$args);
                }

                // execute the pipeline
                return $pipeline -> exec() ?: [ ];

            // whoopsie... handle errors
            } catch (\RedisException $e) {
                self::$_last_error = $e -> getMessage();
                return [ ];

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }
        }

        /**
         * Enhanced Redis batch operations
         *
         * Retrieves multiple items from Redis in a single operation
         * for improved performance when fetching multiple keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to retrieve
         * @return array Returns array of key-value pairs
         */
        public static function redisMultiGet(array $keys): array
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to get multiple items from redis
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                // check if we got a connection
                if (! $connection) {
                    return [ ];
                }

                // setup config and prefix
                $config = CacheConfig::get('redis');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Prefix all keys
                $prefixed_keys = array_map(function ($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);

                // get all values at once
                $values = $connection -> mget($prefixed_keys);

                // check if we got values
                if (! $values) {
                    return [ ];
                }

                // Unserialize values and combine with original keys
                $results = [ ];
                foreach ($keys as $i => $key) {
                    $value = $values[$i] ?? false;
                    $results[$key] = $value !== false ? unserialize($value) : false;
                }

                // return the results
                return $results;

            // whoopsie... handle errors
            } catch (\RedisException $e) {
                self::$_last_error = $e -> getMessage();
                return [ ];

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }
        }

        /**
         * Enhanced Redis batch set operations
         *
         * Stores multiple items in Redis in a single operation
         * for improved performance when setting multiple keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $items Array of key-value pairs to store
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if all successful, false otherwise
         */
        public static function redisMultiSet(array $items, int $ttl = 3600): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to set multiple items to redis
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefix
                $config = CacheConfig::get('redis');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Use pipeline for batch operations
                $pipeline = $connection -> pipeline();

                // add each item to the pipeline
                foreach ($items as $key => $value) {
                    $prefixed_key = $prefix . $key;
                    $pipeline -> setex($prefixed_key, $ttl, serialize($value));
                }

                // execute the pipeline
                $results = $pipeline -> exec();

                // Check if all operations succeeded
                return ! in_array(false, $results ?: [ ]);

            // whoopsie... handle errors
            } catch (\RedisException $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }
        }

        /**
         * Get Redis statistics
         *
         * Retrieves comprehensive statistics from Redis including
         * server info and connection pool information.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of Redis statistics
         */
        private static function getRedisStats(): array
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to get redis statistics
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                // check if we got a connection
                if (! $connection) {
                    return [ 'error' => 'No connection' ];
                }

                // get server info
                $info = $connection -> info();

                // Add connection pool stats if using pooled connections
                if ($use_pool) {
                    $pool_stats = CacheConnectionPool::getPoolStats();
                    $info['pool_stats'] = $pool_stats['redis'] ?? [ ];
                }

                // return the info
                return $info;

            // whoopsie... return error
            } catch (\RedisException $e) {
                return [ 'error' => $e -> getMessage() ];

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }
        }

        /**
         * Clear all items from redis cache
         *
         * Empties the entire redis cache.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true on success, false on failure
         */
        public static function clearRedis(): bool
        {

            // see if we are utilizing connection pooling
            if (self::$_connection_pooling_enabled) {
                // get the connection
                $connection = CacheConnectionPool::getConnection('redis');

                // if we have a connection
                if ($connection) {
                    // try to flush the db
                    try {
                        return $connection -> flushDB();

                    // finally... return the connection
                    } finally {
                        CacheConnectionPool::returnConnection('redis', $connection);
                    }
                }

            // otherwise
            } else {
                // try to flush the redis db directly
                try {
                    // create a redis connection
                    $redis = new \Redis();
                    $config = CacheConfig::get('redis');

                    // connect to redis
                    $redis -> pconnect($config['host'], $config['port']);

                    // select the database
                    $redis -> select($config['database']);

                    // return flushing the db
                    return $redis -> flushDB();

                // whoopsie...
                } catch (\Exception $e) {
                    // log the error and return false
                    Logger::error("Redis clear error: " . $e -> getMessage(), 'redis_operation');
                    return false;
                }
            }

            // default return
            return true;
        }

        /**
         * Cleans up expires items from the cache
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of items removed
         */
        private static function cleanupRedis(): int
        {

            // setup the count
            $count = 0;

            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            try {
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }

                if (!$connection) {
                    return $count;
                }

                $config = CacheConfig::get('redis');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Scan for keys with our prefix
                $iterator = null;
                $pattern = $prefix . '*';

                while ($keys = $connection->scan($iterator, $pattern, 100)) {
                    foreach ($keys as $key) {
                        // Check TTL
                        $ttl = $connection->ttl($key);

                        // If TTL is 0 or about to expire (less than 1 second)
                        if ($ttl !== false && $ttl >= 0 && $ttl < 1) {
                            if ($connection->del($key) > 0) {
                                $count++;
                            }
                        }
                    }

                    if ($iterator === 0) {
                        break;
                    }
                }
            } catch (\RedisException $e) {
                // Silent fail
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('redis', $connection);
                }
            }

            // return the count
            return $count;
        }
    }
}
