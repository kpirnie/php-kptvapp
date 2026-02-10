<?php

/**
 * KPT Cache - Memcached Caching Trait
 * Enhanced Memcached support with connection pooling
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheMemcached')) {

    /**
     * KPT Cache Memcached Trait
     *
     * Provides comprehensive Memcached caching functionality with connection pooling,
     * atomic operations, and enhanced batch processing capabilities.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheMemcached
    {
        // Keep direct connection for non-pooled usage
        private static ?Memcached $_memcached = null;

        /**
         * Test Memcached connection
         *
         * Performs a connectivity test to ensure Memcached is available
         * and functioning properly with basic operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if connection test passes, false otherwise
         */
        private static function testMemcachedConnection(): bool
        {

            // try to test the memcached connection
            try {
                // get memcached configuration
                $config = CacheConfig::get('memcached');

                // create new memcached instance
                $memcached = new \Memcached();
                $memcached -> addServer($config['host'], $config['port']);

                // Set basic options for testing
                $memcached -> setOption(\Memcached::OPT_CONNECT_TIMEOUT, 1000);
                $memcached -> setOption(\Memcached::OPT_POLL_TIMEOUT, 1000);

                // Test with a simple operation
                $test_key = 'kpt_memcached_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // try to set test value
                $success = $memcached -> set($test_key, $test_value, 60);
                if ($success) {
                    // retrieve the test value
                    $retrieved = $memcached -> get($test_key);

                    // clean up the test key
                    $memcached -> delete($test_key);
                    $memcached -> quit();

                    // return comparison result
                    return $retrieved === $test_value;
                }

                // close connection and return false
                $memcached -> quit();
                return false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "Memcached test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get Memcached connection (backward compatibility)
         *
         * Uses connection pool if available, falls back to direct connection
         * for backward compatibility and optimal resource management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return Memcached|null Returns Memcached connection or null on failure
         */
        private static function getMemcached(): ?Memcached
        {

            // Try connection pool first
            if (self::$_connection_pooling_enabled ?? true) {
                // get connection from pool
                $connection = CacheConnectionPool::getConnection('memcached');
                if ($connection) {
                    return $connection;
                }
            }

            // Fallback to direct connection
            if (self::$_memcached === null || ! self::isMemcachedConnected()) {
                self::$_memcached = self::createDirectMemcachedConnection();
            }

            // return the direct connection
            return self::$_memcached;
        }

        /**
         * Create direct Memcached connection (non-pooled)
         *
         * Creates a direct Memcached connection with retry logic
         * and proper configuration for non-pooled usage.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return Memcached|null Returns Memcached connection or null on failure
         */
        private static function createDirectMemcachedConnection(): ?Memcached
        {

            // get configuration and setup retry logic
            $config = CacheConfig::get('memcached');
            $attempts = 0;
            $max_attempts = $config['retry_attempts'] ?? 2;

            // try up to max attempts
            while ($attempts <= $max_attempts) {
                // try to create connection
                try {
                    // create memcached instance with optional persistence
                    $memcached = new \Memcached($config['persistent'] ? 'kpt_pool' : null);

                    // Only add servers if not using persistent connections or if no servers exist
                    if (! $config['persistent'] || count($memcached -> getServerList()) === 0) {
                        $memcached -> addServer($config['host'], $config['port']);
                    }

                    // Set options
                    $memcached -> setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
                    $memcached -> setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                    $memcached -> setOption(\Memcached::OPT_CONNECT_TIMEOUT, ( $config['connection_timeout'] ?? 5 ) * 1000);
                    $memcached -> setOption(\Memcached::OPT_POLL_TIMEOUT, 1000);

                    // Test connection
                    $stats = $memcached -> getStats();
                    if (empty($stats)) {
                        throw new Exception("Memcached connection test failed");
                    }

                    // return successful connection
                    return $memcached;

                // whoopsie... setup error and retry
                } catch (\Exception $e) {
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
         * Check if Memcached connection is alive
         *
         * Tests if the current Memcached connection is still active
         * and responsive to basic operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if connection is alive, false otherwise
         */
        private static function isMemcachedConnected(): bool
        {

            // check if connection exists
            if (self::$_memcached === null) {
                return false;
            }

            // try to test connection with stats
            try {
                // get server stats to test connection
                $stats = self::$_memcached -> getStats();
                return ! empty($stats);

            // whoopsie... connection failed
            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * Get from Memcached with pool-aware connection handling
         *
         * Retrieves an item from Memcached using connection pooling
         * when available for optimal resource management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found
         */
        private static function getFromMemcached(string $_key): mixed
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to get the item from memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // get the result from memcached
                $result = $connection -> get($prefixed_key);

                // check if the operation was successful
                if ($connection -> getResultCode() === \Memcached::RES_SUCCESS) {
                    return $result;
                }

                // operation failed
                return false;

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                if (! $use_pool) {
                    self::$_memcached = null; // Reset direct connection on error
                }
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Set to Memcached with pool-aware connection handling
         *
         * Stores an item in Memcached using connection pooling
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
        private static function setToMemcached(string $_key, mixed $_data, int $_length): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to set the item to memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // set the item with expiration
                return $connection -> set($prefixed_key, $_data, time() + $_length);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                if (! $use_pool) {
                    self::$_memcached = null;
                }
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Delete from Memcached with pool-aware connection handling
         *
         * Deletes an item from Memcached using connection pooling
         * when available for optimal resource management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to delete
         * @return bool Returns true if successful, false otherwise
         */
        private static function deleteFromMemcached(string $_key): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to delete the item from memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // delete the item
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;
                $result = $connection -> delete($prefixed_key);

                // Consider it successful if key was deleted OR if key didn't exist
                if ($result || $connection -> getResultCode() === \Memcached::RES_NOTFOUND) {
                    return true;
                }

                // return false
                return false;

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                if (! $use_pool) {
                    self::$_memcached = null;
                }
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Enhanced Memcached batch get operations for pooled connections
         *
         * Retrieves multiple items from Memcached in a single operation
         * for improved performance when fetching multiple keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to retrieve
         * @return array Returns array of key-value pairs
         */
        public static function memcachedMultiGet(array $keys): array
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to get multiple items from memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return [ ];
                }

                // setup config and prefix
                $config = CacheConfig::get('memcached');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Prefix all keys
                $prefixed_keys = array_map(function ($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);

                // get multiple items at once
                $results = $connection -> getMulti($prefixed_keys);

                // Remove prefix from results
                if ($prefix && $results) {
                    // setup unprefixed results array
                    $unprefixed_results = [ ];
                    foreach ($results as $prefixed_key => $value) {
                        $original_key = substr($prefixed_key, strlen($prefix));
                        $unprefixed_results[$original_key] = $value;
                    }
                    return $unprefixed_results;
                }

                // return results or empty array
                return $results ?: [ ];

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return [ ];

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Enhanced Memcached batch set operations
         *
         * Stores multiple items in Memcached in a single operation
         * for improved performance when setting multiple keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $items Array of key-value pairs to store
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if all successful, false otherwise
         */
        public static function memcachedMultiSet(array $items, int $ttl = 3600): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to set multiple items to memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefix
                $config = CacheConfig::get('memcached');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Prefix all keys
                $prefixed_items = [ ];
                foreach ($items as $key => $value) {
                    $prefixed_items[$prefix . $key] = $value;
                }

                // set multiple items at once
                return $connection -> setMulti($prefixed_items, time() + $ttl);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Enhanced Memcached batch delete operations
         *
         * Deletes multiple items from Memcached in a single operation
         * for improved performance when removing multiple keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to delete
         * @return array Returns array with deletion statistics
         */
        public static function memcachedMultiDelete(array $keys): array
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to delete multiple items from memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return [ ];
                }

                // setup config and prefix
                $config = CacheConfig::get('memcached');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Prefix all keys
                $prefixed_keys = array_map(function ($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);

                // delete multiple items at once
                $results = $connection -> deleteMulti($prefixed_keys);

                // Process results - deleteMulti returns array of result codes
                $failed_keys = [ ];
                if (is_array($results)) {
                    // check each result
                    foreach ($results as $prefixed_key => $result) {
                        // add failed keys to array
                        if ($result !== true) {
                            $original_key = substr($prefixed_key, strlen($prefix));
                            $failed_keys[ ] = $original_key;
                        }
                    }
                }

                // return deletion statistics
                return [
                    'total' => count($keys),
                    'successful' => count($keys) - count($failed_keys),
                    'failed' => count($failed_keys),
                    'failed_keys' => $failed_keys
                ];

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return [
                    'total' => count($keys),
                    'successful' => 0,
                    'failed' => count($keys),
                    'failed_keys' => $keys,
                    'error' => $e -> getMessage()
                ];

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Increment Memcached value (atomic operation)
         *
         * Atomically increments a numeric value stored in Memcached
         * with support for initial value and expiration settings.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to increment
         * @param int $offset The amount to increment by
         * @param int $initial_value Initial value if key doesn't exist
         * @param int $expiry Expiration time in seconds
         * @return int|bool Returns new value on success, false on failure
         */
        public static function memcachedIncrement(string $_key, int $offset = 1, int $initial_value = 0, int $expiry = 0): int|bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to increment the value in memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // increment the value atomically
                return $connection -> increment($prefixed_key, $offset, $initial_value, $expiry);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Decrement Memcached value (atomic operation)
         *
         * Atomically decrements a numeric value stored in Memcached
         * with support for initial value and expiration settings.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to decrement
         * @param int $offset The amount to decrement by
         * @param int $initial_value Initial value if key doesn't exist
         * @param int $expiry Expiration time in seconds
         * @return int|bool Returns new value on success, false on failure
         */
        public static function memcachedDecrement(string $_key, int $offset = 1, int $initial_value = 0, int $expiry = 0): int|bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to decrement the value in memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // decrement the value atomically
                return $connection -> decrement($prefixed_key, $offset, $initial_value, $expiry);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Add item to Memcached (only if it doesn't exist)
         *
         * Adds an item to Memcached only if the key doesn't already exist,
         * providing atomic add-if-not-exists functionality.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to add
         * @param mixed $_data The data to cache
         * @param int $_length Time to live in seconds
         * @return bool Returns true if added, false if key exists or on error
         */
        public static function memcachedAdd(string $_key, mixed $_data, int $_length): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to add the item to memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // add the item only if it doesn't exist
                return $connection -> add($prefixed_key, $_data, time() + $_length);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Replace item in Memcached (only if it exists)
         *
         * Replaces an item in Memcached only if the key already exists,
         * providing atomic replace-if-exists functionality.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to replace
         * @param mixed $_data The data to cache
         * @param int $_length Time to live in seconds
         * @return bool Returns true if replaced, false if key doesn't exist or on error
         */
        public static function memcachedReplace(string $_key, mixed $_data, int $_length): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to replace the item in memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // replace the item only if it exists
                return $connection -> replace($prefixed_key, $_data, time() + $_length);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Append data to existing Memcached item
         *
         * Appends data to an existing item in Memcached without
         * affecting expiration time or other metadata.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to append to
         * @param string $_data The data to append
         * @return bool Returns true if successful, false otherwise
         */
        public static function memcachedAppend(string $_key, string $_data): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to append data to memcached item
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // append the data to existing item
                return $connection -> append($prefixed_key, $_data);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Prepend data to existing Memcached item
         *
         * Prepends data to an existing item in Memcached without
         * affecting expiration time or other metadata.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to prepend to
         * @param string $_data The data to prepend
         * @return bool Returns true if successful, false otherwise
         */
        public static function memcachedPrepend(string $_key, string $_data): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to prepend data to memcached item
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // prepend the data to existing item
                return $connection -> prepend($prefixed_key, $_data);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Touch Memcached item (update expiration time)
         *
         * Updates the expiration time of an existing item in Memcached
         * without modifying the stored data.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to touch
         * @param int $_length New time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        public static function memcachedTouch(string $_key, int $_length): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to touch the memcached item
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // touch the item to update expiration
                return $connection -> touch($prefixed_key, time() + $_length);

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Get Memcached statistics
         *
         * Retrieves comprehensive statistics from Memcached including
         * server stats, connection pool info, and version information.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of Memcached statistics
         */
        private static function getMemcachedStats(): array
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to get memcached statistics
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return [ 'error' => 'No connection' ];
                }

                // get server statistics
                $stats = $connection -> getStats();

                // Add connection pool stats if using pooled connections
                if ($use_pool) {
                    $pool_stats = CacheConnectionPool::getPoolStats();
                    $stats['pool_stats'] = $pool_stats['memcached'] ?? [ ];
                }

                // Add server list
                $stats['servers'] = $connection -> getServerList();

                // Add version information
                $versions = $connection -> getVersion();
                if ($versions) {
                    $stats['versions'] = $versions;
                }

                // return the statistics
                return $stats;

            // whoopsie... return error
            } catch (\Exception $e) {
                return [ 'error' => $e -> getMessage() ];

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Clear Memcached cache (flush all)
         *
         * Flushes all items from the Memcached server,
         * effectively clearing the entire cache.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if successful, false otherwise
         */
        public static function clearMemcached(): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to clear memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // flush all items from memcached
                return $connection -> flush();

            // whoopsie... handle errors
            } catch (\Exception $e) {
                self::$_last_error = $e -> getMessage();
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Get last result code from Memcached
         *
         * Retrieves the result code from the last Memcached operation
         * for debugging and error handling purposes.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the result code or -1 on connection failure
         */
        public static function getMemcachedResultCode(): int
        {

            // get memcached connection
            $connection = self::getMemcached();
            if (! $connection) {
                return -1;
            }

            // return the result code
            return $connection -> getResultCode();
        }

        /**
         * Get last result message from Memcached
         *
         * Retrieves the result message from the last Memcached operation
         * for debugging and error handling purposes.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Returns the result message or error string
         */
        public static function getMemcachedResultMessage(): string
        {

            // get memcached connection
            $connection = self::getMemcached();
            if (! $connection) {
                return 'No connection';
            }

            // return the result message
            return $connection -> getResultMessage();
        }

        /**
         * Check if Memcached key exists
         *
         * Checks if a specific key exists in Memcached without
         * retrieving the actual data.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to check
         * @return bool Returns true if key exists, false otherwise
         */
        public static function memcachedKeyExists(string $_key): bool
        {

            // setup connection variables
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;

            // try to check if key exists in memcached
            try {
                // get connection based on pooling preference
                if ($use_pool) {
                    $connection = CacheConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }

                // check if we got a connection
                if (! $connection) {
                    return false;
                }

                // setup config and prefixed key
                $config = CacheConfig::get('memcached');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // Try to get the key
                $connection -> get($prefixed_key);

                // Check if the result code indicates success
                return $connection -> getResultCode() === \Memcached::RES_SUCCESS;

            // whoopsie... return false
            } catch (\Exception $e) {
                return false;

            // always return connection to pool if using pooling
            } finally {
                if ($use_pool && $connection) {
                    CacheConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }

        /**
         * Memcached does this automatically
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of items removed
         */
        private static function cleanupMemcached(): int
        {

            // setup the count
            $count = 0;

            // Memcached handles expiration automatically
            // There's no way to iterate keys or force cleanup
            // Just return 0 as Memcached manages this internally

            // return the count
            return $count;
        }
    }
}
