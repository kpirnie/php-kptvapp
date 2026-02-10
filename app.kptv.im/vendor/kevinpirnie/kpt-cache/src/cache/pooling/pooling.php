<?php

/**
 * Connection Pool Manager for Database Backends
 * Manages reusable connections for Redis and Memcached
 *
 * Provides efficient connection pooling for database-based cache backends
 * to improve performance and resource management through connection reuse,
 * idle timeout handling, and automatic health monitoring.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the class doesn't exist
if (! class_exists('CacheConnectionPool')) {

    /**
     * KPT Cache Connection Pool Manager
     *
     * Manages connection pools for database-based cache backends like Redis
     * and Memcached. Provides connection reuse, health monitoring, automatic
     * cleanup of idle connections, and configurable pool settings.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class CacheConnectionPool
    {
        /** @var array Active connection pools by backend */
        private static array $pools = [];

        /** @var array Default pool configurations for each backend */
        private static array $pool_configs = [
            'redis' => [
                'min_connections' => 2,
                'max_connections' => 10,
                'idle_timeout' => 300, // 5 minutes
                'connection_timeout' => 5,
                'retry_attempts' => 3
            ],
            'memcached' => [
                'min_connections' => 1,
                'max_connections' => 5,
                'idle_timeout' => 300,
                'connection_timeout' => 5,
                'retry_attempts' => 3
            ]
        ];

        /**
         * Enable/disable connection pooling
         *
         * Toggles connection pooling on or off, automatically closing all
         * connections when disabled and initializing pools when enabled.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param bool $enabled Whether to enable connection pooling, default false
         * @return void Returns nothing
         */
        public static function setConnectionPooling(bool $enabled = false): void
        {

            // set the connection pooling status
            self::$_connection_pooling_enabled = $enabled;

            // if not enabled
            if (! $enabled) {
                // close all connection pools
                CacheConnectionPool::closeAll();

            // otherwise if we're initialized
            } elseif (self::$_initialized) {
                // initialize the connection pools
                self::initializeConnectionPools();
            }

            // debug logging
            Logger::debug('Cache Connection Pool Initialized');
        }

        /**
         * Configure pool settings for a specific backend
         *
         * Updates the pool configuration for a backend, merging new settings
         * with existing defaults.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend name to configure
         * @param array $config The pool configuration settings
         * @return void Returns nothing
         */
        public static function configurePool(string $backend, array $config): void
        {

            // merge the config with existing settings
            self::$pool_configs[$backend] = array_merge(
                self::$pool_configs[$backend] ?? [],
                $config
            );
        }

        /**
         * Get connection from pool
         *
         * Retrieves a healthy connection from the pool, creating new connections
         * as needed and managing the active/idle connection distribution.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend to get a connection for
         * @return mixed Returns a connection object or null if unavailable
         */
        public static function getConnection(string $backend): mixed
        {

            // if the pool doesn't exist, initialize it
            if (! isset(self::$pools[$backend])) {
                // initialize the pool for this backend
                self::initializePool($backend);
            }

            // get a reference to the pool
            $pool = &self::$pools[$backend];

            // Try to get an active connection first
            foreach ($pool['active'] as $id => $conn_data) {
                // if the connection is healthy
                if (self::isConnectionHealthy($backend, $conn_data['connection'])) {
                    // update the last used time
                    $conn_data['last_used'] = time();

                    // debug logging
                    Logger::debug('Cache Connection Pool', [$conn_data['connection']]);

                    // return the connection
                    return $conn_data['connection'];

                // otherwise
                } else {
                    // debug logging
                    Logger::debug('Removed Dead Cache Connection Pool', [$conn_data['connection']]);

                    // Remove dead connection
                    self::closeConnection($backend, $conn_data['connection']);
                    unset($pool['active'][$id]);
                }
            }

            // Try to get from idle pool
            if (! empty($pool['idle'])) {
                // get a connection from the idle pool
                $conn_data = array_pop($pool['idle']);

                // if the connection is healthy
                if (self::isConnectionHealthy($backend, $conn_data['connection'])) {
                    // generate a unique id
                    $id = uniqid();

                    // move to active pool
                    $pool['active'][$id] = [
                        'connection' => $conn_data['connection'],
                        'created' => $conn_data['created'],
                        'last_used' => time()
                    ];

                    // debug logging
                    Logger::debug('Cache Connection Pool', [$conn_data['connection']]);

                    // return the connection
                    return $conn_data['connection'];

                // otherwise
                } else {
                    // debug logging
                    Logger::debug('Removed Dead Cache Connection Pool', [$conn_data['connection']]);

                    // close the dead connection
                    self::closeConnection($backend, $conn_data['connection']);
                }
            }

            // Create new connection if under max limit
            if (count($pool['active']) < $pool['config']['max_connections']) {
                // try to create a new connection
                $connection = self::createConnection($backend);

                // if we got a connection
                if ($connection) {
                    // generate a unique id
                    $id = uniqid();

                    // add to active pool
                    $pool['active'][$id] = [
                        'connection' => $connection,
                        'created' => time(),
                        'last_used' => time()
                    ];

                    // debug logging
                    Logger::debug('Cache Connection', [$connection]);

                    // return the connection
                    return $connection;
                }
            }

            // no connection available
            return null;
        }

        /**
         * Return connection to pool
         *
         * Returns a connection to the idle pool or closes it if the idle pool
         * is full, managing the transition from active to idle state.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend the connection belongs to
         * @param mixed $connection The connection to return
         * @return void Returns nothing
         */
        public static function returnConnection(string $backend, mixed $connection): void
        {

            // if the pool doesn't exist, just return
            if (! isset(self::$pools[$backend])) {
                return;
            }

            // get a reference to the pool
            $pool = &self::$pools[$backend];

            // Find and move from active to idle
            foreach ($pool['active'] as $id => $conn_data) {
                // if this is the connection we're looking for
                if ($conn_data['connection'] === $connection) {
                    // remove from active pool
                    unset($pool['active'][$id]);

                    // Only return to idle pool if under max idle connections
                    if (count($pool['idle']) < floor($pool['config']['max_connections'] / 2)) {
                        // add to idle pool
                        $pool['idle'][] = $conn_data;

                        // debug logging
                        Logger::debug('Cache Return Connection to Pool', [$pool]);

                    // otherwise
                    } else {
                        // debug logging
                        Logger::debug('Cache Connection Closed', [$pool]);

                        // close the connection
                        self::closeConnection($backend, $connection);
                    }

                    // we found it, break out of the loop
                    break;
                }
            }
        }

        /**
         * Clean up idle connections
         *
         * Removes connections that have exceeded their idle timeout and
         * validates the health of active connections, removing dead ones.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function cleanup(): void
        {

            // loop over each pool
            foreach (self::$pools as $backend => &$pool) {
                // get current time and timeout
                $now = time();
                $timeout = $pool['config']['idle_timeout'];

                // Clean up idle connections
                $pool['idle'] = array_filter($pool['idle'], function ($conn_data) use ($now, $timeout, $backend) {

                    // if the connection has timed out
                    if (( $now - $conn_data['created'] ) > $timeout) {
                        // close the connection
                        self::closeConnection($backend, $conn_data['connection']);

                        // remove from pool
                        return false;
                    }

                    // keep the connection
                    return true;
                });

                // Check active connections
                foreach ($pool['active'] as $id => $conn_data) {
                    // if the connection is not healthy
                    if (! self::isConnectionHealthy($backend, $conn_data['connection'])) {
                        // close the connection
                        self::closeConnection($backend, $conn_data['connection']);

                        // remove from active pool
                        unset($pool['active'][$id]);
                    }
                }
            }
        }

        /**
         * Close all connections and clear pools
         *
         * Closes all active and idle connections for all backends and
         * clears all pool data structures.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function closeAll(): void
        {

            // loop over each pool
            foreach (self::$pools as $backend => $pool) {
                // close all active connections
                foreach ($pool['active'] as $conn_data) {
                    // close the connection
                    self::closeConnection($backend, $conn_data['connection']);
                }

                // close all idle connections
                foreach ($pool['idle'] as $conn_data) {
                    // close the connection
                    self::closeConnection($backend, $conn_data['connection']);
                }
            }

            // clear all pools
            self::$pools = [];
        }

        /**
         * Get pool statistics
         *
         * Returns detailed statistics about all connection pools including
         * active/idle connection counts and usage statistics.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns pool statistics for all backends
         */
        public static function getPoolStats(): array
        {

            // default stats array
            $stats = [];

            // loop over each pool
            foreach (self::$pools as $backend => $pool) {
                // build stats for this backend
                $stats[$backend] = [
                    'active_connections' => count($pool['active']),
                    'idle_connections' => count($pool['idle']),
                    'max_connections' => $pool['config']['max_connections'],
                    'total_created' => $pool['stats']['total_created'] ?? 0,
                    'total_reused' => $pool['stats']['total_reused'] ?? 0
                ];
            }

            // return the stats
            return $stats;
        }

        /**
         * Initialize a connection pool for a specific backend
         *
         * Sets up the pool data structure and pre-creates the minimum
         * number of connections as specified in the configuration.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend to initialize a pool for
         * @return void Returns nothing
         */
        private static function initializePool(string $backend): void
        {

            // initialize the pool structure
            self::$pools[$backend] = [
                'active' => [],
                'idle' => [],
                'config' => self::$pool_configs[$backend] ?? [],
                'stats' => ['total_created' => 0, 'total_reused' => 0]
            ];

            // Pre-create minimum connections
            $min_connections = self::$pools[$backend]['config']['min_connections'] ?? 1;

            // create the minimum connections
            for ($i = 0; $i < $min_connections; $i++) {
                // try to create a connection
                $connection = self::createConnection($backend);

                // if we got a connection
                if ($connection) {
                    // add to idle pool
                    self::$pools[$backend]['idle'][] = [
                        'connection' => $connection,
                        'created' => time(),
                        'last_used' => time()
                    ];
                }
            }
        }

        /**
         * Create a new connection for a specific backend
         *
         * Creates and configures a new connection based on the backend type
         * and its configuration settings.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend to create a connection for
         * @return mixed Returns a connection object or null on failure
         */
        private static function createConnection(string $backend): mixed
        {

            // get the backend configuration
            $config = CacheConfig::get($backend);

            // try to create the connection
            try {
                // match the backend type
                switch ($backend) {
                    // redis
                    case 'redis':
                        // create a new redis connection
                        $redis = new \Redis();

                        // try to connect
                        $connected = $redis -> pconnect(
                            $config['host'],
                            $config['port'],
                            $config['connect_timeout']
                        );

                        // if not connected, return null
                        if (! $connected) {
                            return null;
                        }

                        // select the database
                        $redis -> select($config['database']);

                        // if we have a prefix
                        if (! empty($config['prefix'])) {
                            // set the prefix option
                            $redis -> setOption(\Redis::OPT_PREFIX, $config['prefix']);
                        }

                        // increment stats
                        self::$pools[$backend]['stats']['total_created']++;

                        // return the redis connection
                        return $redis;

                    // memcached
                    case 'memcached':
                        // create a new memcached connection
                        $memcached = new \Memcached($config['persistent'] ? 'kpt_pool' : null);

                        // Only add servers if not using persistent connections or if no servers exist
                        if (! $config['persistent'] || count($memcached -> getServerList()) === 0) {
                            // add the server
                            $memcached -> addServer($config['host'], $config['port']);
                        }

                        // set options
                        $memcached -> setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
                        $memcached -> setOption(\Memcached::OPT_BINARY_PROTOCOL, true);

                        // Test connection
                        $stats = $memcached -> getStats();

                        // if no stats, return null
                        if (empty($stats)) {
                            return null;
                        }

                        // increment stats
                        self::$pools[$backend]['stats']['total_created']++;

                        // return the memcached connection
                        return $memcached;
                }

            // whoopsie...
            } catch (\Exception $e) {
                // return null on error
                return null;
            }

            // default return
            return null;
        }

        /**
         * Check if a connection is healthy
         *
         * Tests the health of a connection by performing a simple operation
         * specific to the backend type.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend type
         * @param mixed $connection The connection to test
         * @return bool Returns true if connection is healthy, false otherwise
         */
        private static function isConnectionHealthy(string $backend, mixed $connection): bool
        {

            // try to test the connection
            try {
                // switch on the backend type
                switch ($backend) {
                    // redis
                    case 'redis':
                        // if it's not a redis instance, return false
                        if (! $connection instanceof \Redis) {
                            return false;
                        }

                        // ping the redis server
                        $result = $connection -> ping();

                        // return if ping was successful
                        return $result === true || $result === '+PONG';

                    // memcached
                    case 'memcached':
                        // if it's not a memcached instance, return false
                        if (! $connection instanceof \Memcached) {
                            return false;
                        }

                        // get stats from memcached
                        $stats = $connection -> getStats();

                        // return if we got stats
                        return ! empty($stats);
                }

            // whoopsie...
            } catch (\Exception $e) {
                // return false on error
                return false;
            }

            // default return
            return false;
        }

        /**
         * Close a connection
         *
         * Properly closes a connection based on the backend type, handling
         * any exceptions that may occur during the close operation.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend type
         * @param mixed $connection The connection to close
         * @return void Returns nothing
         */
        private static function closeConnection(string $backend, mixed $connection): void
        {

            // try to close the connection
            try {
                // switch on the backend type
                switch ($backend) {
                    // redis
                    case 'redis':
                        // if it's a redis instance
                        if ($connection instanceof \Redis) {
                            // close the connection
                            $connection -> close();
                        }
                        break;

                    // memcached
                    case 'memcached':
                        // if it's a memcached instance
                        if ($connection instanceof \Memcached) {
                            // quit the connection
                            $connection -> quit();
                        }
                        break;
                }

            // whoopsie...
            } catch (\Exception $e) {
                // Ignore close errors silently
            }
        }
    }
}
