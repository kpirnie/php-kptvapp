<?php

/**
 * Async Cache Traits for I/O-intensive cache backends
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheRedisAsync')) {

    /**
     * KPT Cache Redis Async Trait
     *
     * Provides asynchronous Redis caching operations with connection pooling
     * for improved performance in I/O-intensive applications using promises.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheRedisAsync
    {
        /**
         * Async Redis get with connection pooling
         *
         * Asynchronously retrieves an item from Redis using connection pooling
         * for optimal resource management and performance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @return CachePromise Returns a promise that resolves with the cached data
         */
        public static function getRedisAsync(string $key): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // try to get the item from Redis
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // setup the config and prefixed key
                    $config = CacheConfig::get('redis');
                    $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $key;
                    $value = $connection -> get($prefixed_key);

                    // return the connection to the pool
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // unserialize the result and resolve
                    $result = $value !== false ? unserialize($value) : false;
                    $resolve($result);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }

        /**
         * Async Redis set with connection pooling
         *
         * Asynchronously stores an item in Redis using connection pooling
         * for optimal resource management and performance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function setRedisAsync(string $key, mixed $data, int $ttl): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key, $data, $ttl) {

                // try to set the item to Redis
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // setup the config and prefixed key
                    $config = CacheConfig::get('redis');
                    $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $key;
                    $success = $connection -> setex($prefixed_key, $ttl, serialize($data));

                    // return the connection to the pool
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // resolve with success status
                    $resolve($success);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }

        /**
         * Async Redis delete with connection pooling
         *
         * Asynchronously deletes an item from Redis using connection pooling
         * for optimal resource management and performance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to delete
         * @return CachePromise Returns a promise that resolves with deletion status
         */
        public static function deleteRedisAsync(string $key): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // try to delete the item from Redis
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // setup the config and prefixed key
                    $config = CacheConfig::get('redis');
                    $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $key;
                    $result = $connection -> del($prefixed_key);

                    // return the connection to the pool
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // resolve with deletion status
                    $resolve($result > 0);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }

        /**
         * Async Redis pipeline operations
         *
         * Executes multiple Redis commands in a pipeline asynchronously
         * for improved performance when executing multiple operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $commands Array of Redis commands to execute
         * @return CachePromise Returns a promise that resolves with command results
         */
        public static function redisPipelineAsync(array $commands): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($commands) {

                // try to execute the pipeline
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // create the pipeline
                    $pipeline = $connection -> pipeline();

                    // add each command to the pipeline
                    foreach ($commands as $command) {
                        $method = $command['method'];
                        $args = $command['args'] ?? [ ];
                        $pipeline -> $method(...$args);
                    }

                    // execute the pipeline and return connection
                    $results = $pipeline -> exec();
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // resolve with results
                    $resolve($results ?: [ ]);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }

        /**
         * Async Redis transaction
         *
         * Executes multiple Redis commands in a transaction asynchronously
         * for atomic operations across multiple commands.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $commands Array of Redis commands to execute in transaction
         * @return CachePromise Returns a promise that resolves with transaction results
         */
        public static function redisTransactionAsync(array $commands): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($commands) {

                // try to execute the transaction
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // start the multi transaction
                    $multi = $connection -> multi();

                    // add each command to the transaction
                    foreach ($commands as $command) {
                        $method = $command['method'];
                        $args = $command['args'] ?? [ ];
                        $multi -> $method(...$args);
                    }

                    // execute the transaction and return connection
                    $results = $multi -> exec();
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // resolve with results
                    $resolve($results ?: [ ]);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }

        /**
         * Async Redis multi-get
         *
         * Asynchronously retrieves multiple items from Redis in a single operation
         * for improved performance when fetching multiple cache keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to retrieve
         * @return CachePromise Returns a promise that resolves with key-value pairs
         */
        public static function redisMultiGetAsync(array $keys): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($keys) {

                // try to get multiple items from Redis
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // setup the config and prefix
                    $config = CacheConfig::get('redis');
                    $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                    // Prefix all keys
                    $prefixed_keys = array_map(function ($key) use ($prefix) {
                        return $prefix . $key;
                    }, $keys);

                    // get all values and return connection
                    $values = $connection -> mget($prefixed_keys);
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // check if we got values
                    if (! $values) {
                        $resolve([ ]);
                        return;
                    }

                    // Unserialize values and combine with original keys
                    $results = [ ];
                    foreach ($keys as $i => $key) {
                        $value = $values[$i] ?? false;
                        $results[$key] = $value !== false ? unserialize($value) : false;
                    }

                    // resolve with results
                    $resolve($results);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }

        /**
         * Async Redis multi-set
         *
         * Asynchronously stores multiple items in Redis in a single operation
         * for improved performance when setting multiple cache keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $items Array of key-value pairs to store
         * @param int $ttl Time to live in seconds
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function redisMultiSetAsync(array $items, int $ttl = 3600): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($items, $ttl) {

                // try to set multiple items to Redis
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // setup the config and prefix
                    $config = CacheConfig::get('redis');
                    $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                    // Use pipeline for batch operations
                    $pipeline = $connection -> pipeline();

                    // add each item to the pipeline
                    foreach ($items as $key => $value) {
                        $prefixed_key = $prefix . $key;
                        $pipeline -> setex($prefixed_key, $ttl, serialize($value));
                    }

                    // execute the pipeline and return connection
                    $results = $pipeline -> exec();
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // Check if all operations succeeded
                    $success = ! in_array(false, $results ?: [ ]);
                    $resolve($success);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }

        /**
         * Async Redis exists check
         *
         * Asynchronously checks if multiple keys exist in Redis
         * for efficient key existence validation.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to check
         * @return CachePromise Returns a promise that resolves with count of existing keys
         */
        public static function redisExistsAsync(array $keys): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($keys) {

                // try to check if keys exist in Redis
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // setup the config and prefix
                    $config = CacheConfig::get('redis');
                    $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                    // Prefix all keys
                    $prefixed_keys = array_map(function ($key) use ($prefix) {
                        return $prefix . $key;
                    }, $keys);

                    // check existence and return connection
                    $count = $connection -> exists(...$prefixed_keys);
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // resolve with count
                    $resolve($count);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }

        /**
         * Async Redis TTL check
         *
         * Asynchronously checks the time-to-live for a Redis key
         * for monitoring cache expiration times.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to check TTL for
         * @return CachePromise Returns a promise that resolves with TTL in seconds
         */
        public static function redisTtlAsync(string $key): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // try to get the TTL from Redis
                try {
                    // get a connection from the pool
                    $connection = CacheConnectionPool::getConnection('redis');
                    if (! $connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }

                    // setup the config and prefixed key
                    $config = CacheConfig::get('redis');
                    $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $key;
                    $ttl = $connection -> ttl($prefixed_key);

                    // return the connection to the pool
                    CacheConnectionPool::returnConnection('redis', $connection);

                    // resolve with TTL
                    $resolve($ttl);

                // whoopsie... reject the promise with the error
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
    }
}
