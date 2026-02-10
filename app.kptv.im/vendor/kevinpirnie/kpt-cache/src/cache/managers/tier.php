<?php

/**
 * KPT Cache Tier Manager - Cache Tier Discovery and Management
 *
 * Handles discovery, validation, and management of cache tiers including
 * availability testing, health checks, and tier status reporting.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the class doesn't exist
if (! class_exists('CacheTierManager')) {

    /**
     * KPT Cache Tier Manager
     *
     * Responsible for discovering available cache tiers, validating tier names,
     * checking tier availability and health, and providing tier status information.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class CacheTierManager
    {
        /** @var string Array tier - Highest performance tier */
        const TIER_ARRAY = 'array';

        /** @var string OPcache tier - Highest performance, memory-based opcache tier */
        const TIER_OPCACHE = 'opcache';

        /** @var string SHMOP tier - Shared memory operations tier */
        const TIER_SHMOP = 'shmop';

        /** @var string APCu tier - Alternative PHP Cache user data tier */
        const TIER_APCU = 'apcu';

        /** @var string YAC tier - Yet Another Cache tier */
        const TIER_YAC = 'yac';

        /** @var string Redis tier - Redis database tier */
        const TIER_REDIS = 'redis';

        /** @var string Memcached tier - Memcached distributed memory tier */
        const TIER_MEMCACHED = 'memcached';

        /** @var string SQLite tier - SQLite database tier */
        const TIER_SQLITE = 'sqlite';

        /** @var string File tier - File-based caching tier (lowest priority fallback) */
        const TIER_FILE = 'file';

        /** @var array Valid tier names for validation - ordered by priority (highest to lowest) */
        private static array $_valid_tiers = [
            self::TIER_ARRAY, self::TIER_OPCACHE, self::TIER_SHMOP, self::TIER_APCU,
            self::TIER_YAC, self::TIER_REDIS,
            self::TIER_MEMCACHED,
            self::TIER_SQLITE,
            self::TIER_FILE
        ];

        /** @var array Available cache tiers discovered during initialization */
        private static array $_available_tiers = [];

        /** @var bool Discovery completion status flag */
        private static bool $_discovery_complete = false;

        /** @var string|null Last error message from tier operations */
        private static ?string $_last_error = null;

        /** @var array Cache of tier test results to avoid repeated checks */
        private static array $_tier_test_cache = [];

        /** @var int Cache duration for tier test results (seconds) */
        private static int $_test_cache_duration = 300; // 5 minutes

        /**
         * Discover and validate available cache tiers
         *
         * Automatically discovers which cache backends are available on the current
         * system by testing each tier's functionality. Populates the available tiers
         * array in priority order.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param bool $force_rediscovery Force rediscovery even if already completed
         * @return array Returns array of discovered available tiers
         */
        public static function discoverTiers(bool $force_rediscovery = false): array
        {
            // Prevent infinite recursion during discovery
            static $discovering = false;

            if ($discovering) {
                return self::$_available_tiers; // Return what we have so far
            }

            // Skip if already discovered and not forcing rediscovery
            if (self::$_discovery_complete && ! $force_rediscovery) {
                Logger::debug('Cache Tier Discovery', ['tiers' => self::$_available_tiers]);
                return self::$_available_tiers;
            }

            // Set discovery flag to prevent recursion
            $discovering = true;

            // Clear previous results
            self::$_available_tiers = [];
            self::$_last_error = null;

            // Get allowed backends filter
            $allowed_backends = CacheConfig::getAllowedBackends();
            $tiers_to_test = $allowed_backends !== null
                ? array_intersect(self::$_valid_tiers, $allowed_backends)
                : self::$_valid_tiers;

            // Test each tier in priority order - but safely
            foreach ($tiers_to_test as $tier) {
                // Use basic availability check instead of full test to prevent recursion
                if (self::isBasicTierAvailable($tier)) {
                    self::$_available_tiers[] = $tier;
                }
            }

            // Mark discovery as complete
            self::$_discovery_complete = true;

            // Clear discovery flag
            $discovering = false;

            // debug logging
            Logger::debug('Cache Tier Discovery', ['tiers' => self::$_available_tiers]);

            // return the available tiers
            return self::$_available_tiers;
        }

        /**
         * Test availability of a specific cache tier
         *
         * Performs availability testing for the specified tier type by checking
         * if required extensions/functions exist and basic connectivity works.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier name to test
         * @return bool Returns true if tier is available, false otherwise
         */
        public static function testTierAvailability(string $tier): bool
        {

            // Validate tier name first
            if (! self::isTierValid($tier)) {
                self::$_last_error = "Invalid tier specified: {$tier}";
                return false;
            }

            // Check cache first
            $cache_key = $tier . '_availability';

            // is it already set
            if (isset(self::$_tier_test_cache[$cache_key])) {
                // it is, but return it if it's before the internal cache expires
                $cached_result = self::$_tier_test_cache[$cache_key];
                if (time() - $cached_result['timestamp'] < self::$_test_cache_duration) {
                    return $cached_result['available'];
                }
            }

            // Perform actual availability test
            $available = match ($tier) {
                self::TIER_ARRAY => true, // arrays are always available in PHP
                self::TIER_OPCACHE => self::testOPcacheAvailability(),
                self::TIER_SHMOP => self::testShmopAvailability(),
                self::TIER_APCU => self::testAPCuAvailability(),
                self::TIER_YAC => self::testYacAvailability(),
                self::TIER_REDIS => self::testRedisAvailability(),
                self::TIER_MEMCACHED => self::testMemcachedAvailability(),
                self::TIER_SQLITE => self::testSQLiteAvailability(),
                self::TIER_FILE => self::testFileAvailability(),
                default => false
            };

            // Cache the result
            self::$_tier_test_cache[$cache_key] = [
                'available' => $available,
                'timestamp' => time()
            ];

            // debug logging
            Logger::debug('Cache Tier Availability', ['results' => self::$_tier_test_cache[$cache_key]]);

            // return the available tiers
            return $available;
        }

        /**
         * Force refresh of tier discovery and clear test cache
         *
         * Clears all cached test results and forces a complete rediscovery
         * of available cache tiers.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of newly discovered available tiers
         */
        public static function refreshTierDiscovery(): array
        {

            // Clear all cached test results
            self::$_tier_test_cache = [];

            // Reset discovery status
            self::$_discovery_complete = false;

            // Perform fresh discovery
            return self::discoverTiers(true);
        }

        /**
         * Validate if a tier name is valid
         *
         * Checks if the provided tier name exists in the list of valid tier constants.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier name to validate
         * @return bool Returns true if the tier name is valid, false otherwise
         */
        public static function isTierValid(string $tier): bool
        {

            // Check if tier exists in valid tiers array
            return in_array($tier, self::$_valid_tiers, true);
        }

        /**
         * Check if a tier is available for use
         *
         * Determines if the specified tier was discovered during initialization
         * and is available for cache operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier name to check availability for
         * @return bool Returns true if the tier is available, false otherwise
         */
        public static function isTierAvailable(string $tier): bool
        {

            // Ensure discovery has been performed
            if (! self::$_discovery_complete) {
                self::discoverTiers();
            }

            // return if the tier is available
            return in_array($tier, self::$_available_tiers, true);
        }

        /**
         * Check if multiple tiers are available
         *
         * Batch check for multiple tier availability, useful for validation
         * before performing multi-tier operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $tiers Array of tier names to check
         * @return array Returns associative array of tier => availability status
         */
        public static function areTiersAvailable(array $tiers): array
        {

            // Initialize results array
            $results = [];

            // loop over all tiers and set if it's available
            foreach ($tiers as $tier) {
                $results[$tier] = self::isTierAvailable($tier);
            }

            // return the availability
            return $results;
        }

        /**
         * Get list of all valid tier names
         *
         * Returns the complete list of tier names that the cache system recognizes,
         * regardless of their availability on the current system.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of all valid tier names in priority order
         */
        public static function getValidTiers(): array
        {

            // Return the valid tiers array
            return self::$_valid_tiers;
        }

        /**
         * Get list of available (discovered) tiers
         *
         * Returns the list of tiers that were successfully discovered and are
         * available for use on the current system.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of available tier names in priority order
         */
        public static function getAvailableTiers(): array
        {

            // Prevent infinite recursion
            static $getting_tiers = false;

            if ($getting_tiers) {
                return self::$_available_tiers; // Return current state
            }

            // Ensure discovery has been performed
            if (! self::$_discovery_complete) {
                $getting_tiers = true;
                self::discoverTiers();
                $getting_tiers = false;
            }

            // return the available tier array
            return self::$_available_tiers;
        }

        /**
         * Get comprehensive status information for all tiers
         *
         * Provides detailed status information including availability, health,
         * priority, and last test time for all valid tiers.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns associative array with tier status information
         */
        public static function getTierStatus(): array
        {

            // Ensure discovery has been performed
            if (! self::$_discovery_complete) {
                self::discoverTiers();
            }

            // hold the status
            $status = [];

            // loop over the valid tiers
            foreach (self::$_valid_tiers as $index => $tier) {
                // see if the tier is available
                $available = self::isTierAvailable($tier);

                // set it's priority
                $priority_index = array_search($tier, self::$_available_tiers);

                // set the tier's status
                $status[$tier] = [
                    'valid' => true,
                    'available' => $available,
                    'priority_order' => $index, // Order in valid tiers array
                    'availability_priority' => $priority_index !== false ? $priority_index : null,
                    'last_test_time' => self::$_tier_test_cache[$tier . '_availability']['timestamp'] ?? null,
                ];
            }

            // debug logging
            Logger::debug('Cache Tier Status', ['status' => $status]);

            // return the status
            return $status;
        }

        /**
         * Get tier priority information
         *
         * Returns priority information for tiers, useful for understanding
         * the order in which tiers will be attempted.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string|null $tier Optional specific tier to get priority for
         * @return array|int Returns priority array for all tiers or specific tier priority
         */
        public static function getTierPriority(?string $tier = null): array|int
        {

            // if we actually have a tier
            if ($tier !== null) {
                // and it is not valid
                if (! self::isTierValid($tier)) {
                    return -1;
                }

                // set the priority
                $priority = array_search($tier, self::$_available_tiers);

                // return it
                return $priority !== false ? $priority : -1;
            }

            // hold the tiers priorities
            $priorities = [];

            // loop over the tiers and set it's priority
            foreach (self::$_available_tiers as $index => $tier_name) {
                $priorities[$tier_name] = $index;
            }

            // debug logging
            Logger::debug('Cache Tier Priorities', ['priorities' => $priorities]);

            // return them
            return $priorities;
        }

        /**
         * Get the highest priority available tier
         *
         * Returns the tier with the highest priority (lowest index) that is
         * currently available for use.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the highest priority tier name or null if none available
         */
        public static function getHighestPriorityTier(): ?string
        {

            // get the available tiers
            $available = self::getAvailableTiers();

            // return the empty status or it's actual priority
            return ! empty($available) ? $available[0] : null;
        }

        /**
         * Get the lowest priority available tier
         *
         * Returns the tier with the lowest priority (highest index) that is
         * currently available for use. Usually the file tier.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the lowest priority tier name or null if none available
         */
        public static function getLowestPriorityTier(): ?string
        {

            // get the available tiers
            $available = self::getAvailableTiers();

            // return the empty status or it's actual priority
            return ! empty($available) ? end($available) : null;
        }

        /**
         * Get the last error message encountered during tier operations
         *
         * Returns the most recent error message generated by tier operations,
         * useful for debugging and error handling.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the last error message or null if none exists
         */
        public static function getLastError(): ?string
        {
            Logger::error("Cache Tier Error", [ 'error' => self::$_last_error ]);
            return self::$_last_error;
        }

        /**
         * Clear the last error message
         *
         * Resets the last error message to null, typically called after
         * handling an error condition.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        public static function clearLastError(): void
        {
            self::$_last_error = null;
        }

        /**
         * Get discovery status information
         *
         * Provides statistics and status about the tier discovery process including
         * completion status, tier counts, and cache information.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns associative array with discovery metrics
         */
        public static function getDiscoveryInfo(): array
        {

            // return the info
            return [
                'discovery_complete' => self::$_discovery_complete,
                'total_valid_tiers' => count(self::$_valid_tiers),
                'total_available_tiers' => count(self::$_available_tiers),
                'availability_ratio' => count(self::$_valid_tiers) > 0
                    ? round(count(self::$_available_tiers) / count(self::$_valid_tiers) * 100, 2)
                    : 0,
                'cached_tests' => count(self::$_tier_test_cache),
                'last_error' => self::$_last_error
            ];
        }

        /**
         * Reset the tier manager state
         *
         * Clears all cached data and resets the manager to initial state.
         * Useful for testing or when configuration changes.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        public static function reset(): void
        {
            self::resetDiscovery();
            self::$_last_error = null;
        }

        /**
         * Basic tier availability check without full testing
         *
         * Performs minimal checks to see if a tier is potentially available
         * without running full functionality tests that might cause recursion.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier name to check
         * @return bool Returns true if tier appears available
         */
        private static function isBasicTierAvailable(string $tier): bool
        {

            // try to do a basic test
            try {
                // Basic availability checks without calling Cache methods
                return match ($tier) {
                    self::TIER_ARRAY => true,
                    self::TIER_OPCACHE => function_exists('opcache_get_status'),
                    self::TIER_SHMOP => function_exists('shmop_open'),
                    self::TIER_APCU => function_exists('apcu_enabled') && apcu_enabled(),
                    self::TIER_YAC => extension_loaded('yac'),
                    self::TIER_REDIS => class_exists('Redis'),
                    self::TIER_MEMCACHED => class_exists('Memcached'),
                    self::TIER_SQLITE => class_exists('PDO') && in_array('sqlite', \PDO::getAvailableDrivers()),
                    self::TIER_FILE => true,
                    default => false
                };

            // whoopsie... just return false
            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * Test OPcache availability and basic functionality
         *
         * Checks if OPcache extension is loaded and enabled, and verifies basic
         * functionality by checking status.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if OPcache is available and functional
         */
        private static function testOPcacheAvailability(): bool
        {

            // try to test opcache functionality
            try {
                // Check if function exists and OPcache is enabled
                if (! function_exists('opcache_get_status')) {
                    return false;
                }

                // check the opcache status
                $status = opcache_get_status(false);

                // if it's false or not set, return false
                if (! $status || ! isset($status['opcache_enabled'])) {
                    return false;
                }

                // return it it's actually enabled
                return $status['opcache_enabled'] === true;

            // whoopsie...
            } catch (\Exception $e) {
                // set the error and return false
                self::$_last_error = "OPcache test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test SHMOP availability and basic functionality
         *
         * Verifies SHMOP extension is loaded and tests basic shared memory
         * operations including create, write, read and delete.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if SHMOP is available and functional
         */
        private static function testShmopAvailability(): bool
        {

            // try to test shmop functionality
            try {
                // Check if SHMOP functions exist
                if (! function_exists('shmop_open')) {
                    return false;
                }

                // Try to create a small test segment
                $test_key = ftok(__FILE__, 't');
                $test_size = 1024;

                // Attempt to open shared memory segment
                $segment = @shmop_open($test_key, 'c', 0644, $test_size);
                if ($segment === false) {
                    return false;
                }

                // Test write and read
                $test_data = "test";
                $written = @shmop_write($segment, $test_data, 0);

                // Verify write was successful
                if ($written !== strlen($test_data)) {
                    @shmop_close($segment);
                    return false;
                }

                // Read back the data
                $read_data = @shmop_read($segment, 0, strlen($test_data));

                // Cleanup
                @shmop_delete($segment);
                @shmop_close($segment);

                // Verify data matches
                return $read_data === $test_data;

            // whoopsie...
            } catch (\Exception $e) {
                // set the error and return false
                self::$_last_error = "SHMOP test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test APCu availability and basic functionality
         *
         * Checks if APCu extension is loaded and enabled, and verifies basic
         * cache operations including store, fetch and delete.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if APCu is available and functional
         */
        private static function testAPCuAvailability(): bool
        {

            // try to check apcu
            try {
                // Check if APCu is enabled
                if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                    return false;
                }

                // Test basic operations
                $test_key = '__kpt_cache_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // Test store operation
                if (! apcu_store($test_key, $test_value, 60)) {
                    return false;
                }

                // Test fetch operation
                $retrieved = apcu_fetch($test_key);

                // Cleanup test key
                apcu_delete($test_key);

                // Verify retrieved data matches
                return $retrieved === $test_value;

            // whoopsie...
            } catch (\Exception $e) {
                // set the error and return false
                self::$_last_error = "APCu test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Test YAC availability and basic functionality
         *
         * Verifies YAC extension is loaded and tests basic cache operations
         * including set, get and delete.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if YAC is available and functional
         */
        private static function testYacAvailability(): bool
        {

            // try to test YAC
            try {
                // Check if YAC extension is loaded
                if (! extension_loaded('yac')) {
                    return false;
                }

                // Test basic operations
                $test_key = '__kpt_cache_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // Test store operation
                if (! yac_store($test_key, $test_value, 60)) {
                    return false;
                }

                // Test fetch operation
                $retrieved = yac_get($test_key);

                // Cleanup test key
                yac_delete($test_key);

                // Verify retrieved data matches
                return $retrieved === $test_value;

            // whoopsie...
            } catch (\Exception $e) {
                // set the error and return false
                self::$_last_error = "YAC test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Test Redis availability and basic functionality
         *
         * Verifies Redis extension is loaded and tests connection to Redis server
         * along with basic set/get operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if Redis is available and functional
         */
        private static function testRedisAvailability(): bool
        {

            // try the redis availability
            try {
                // Check if Redis class exists
                if (! class_exists('Redis')) {
                    return false;
                }

                // Create new Redis instance
                $redis = new \Redis();
                $config = CacheConfig::get('redis');

                // Test connection with timeout
                $connected = $redis -> pconnect(
                    $config['host'] ?? '127.0.0.1',
                    $config['port'] ?? 6379,
                    2 // 2 second timeout
                );

                // Verify connection succeeded
                if (! $connected) {
                    return false;
                }

                // Test ping command
                $ping_result = $redis -> ping();
                if ($ping_result !== true && $ping_result !== '+PONG') {
                    $redis -> close();
                    return false;
                }

                // Test basic operations
                $test_key = '__kpt_cache_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // Test set with expiration
                $set_result = $redis->setex($test_key, 60, $test_value);
                if (! $set_result) {
                    $redis -> close();
                    return false;
                }

                // Test get operation
                $get_result = $redis->get($test_key);

                // Cleanup
                $redis -> del($test_key);
                $redis -> close();

                // Verify retrieved data matches
                return $get_result === $test_value;

            // whoopsie...
            } catch (\Exception $e) {
                // set the error and return false
                self::$_last_error = "Redis test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Test Memcached availability and basic functionality
         *
         * Verifies Memcached extension is loaded and tests connection to server
         * along with basic set/get operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if Memcached is available and functional
         */
        private static function testMemcachedAvailability(): bool
        {

            // try the memcached availability
            try {
                // Check if Memcached class exists
                if (! class_exists('Memcached')) {
                    return false;
                }

                // Create new Memcached instance
                $memcached = new \Memcached();
                $config = CacheConfig::get('memcached');

                // Add server
                $memcached->addServer(
                    $config['host'] ?? '127.0.0.1',
                    $config['port'] ?? 11211
                );

                // Test connection by getting stats
                $stats = $memcached -> getStats();
                if (empty($stats)) {
                    return false;
                }

                // Test basic operations
                $test_key = '__kpt_cache_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // Test set operation
                $set_result = $memcached -> set($test_key, $test_value, time() + 60);
                if (! $set_result) {
                    return false;
                }

                // Test get operation
                $get_result = $memcached -> get($test_key);

                // Cleanup
                $memcached -> delete($test_key);
                $memcached ->quit();

                // Verify retrieved data matches
                return $get_result === $test_value;

            // whoopsie...
            } catch (\Exception $e) {
                // set the error and return false
                self::$_last_error = "Memcached test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Test File cache availability and basic functionality
         *
         * Verifies file system caching is available by testing directory
         * creation, file write/read operations and cleanup.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if file caching is available and functional
         */
        private static function testFileAvailability(): bool
        {

            // try the file availability
            try {
                // Get cache path from cache config or use temp directory
                $cache_path = CacheConfig::get('file')['path'] ?? sys_get_temp_dir() . '/kpt_cache/';

                // Ensure directory exists and is writable
                if (! is_dir($cache_path)) {
                    if (! @mkdir($cache_path, 0755, true)) {
                        return false;
                    }
                }

                // Verify directory is writable
                if (! is_writable($cache_path)) {
                    return false;
                }

                // Test file operations
                $test_file = $cache_path . 'test_' . uniqid() . '.tmp';
                $test_data = 'test_data_' . time();

                // Test write operation
                if (file_put_contents($test_file, $test_data) === false) {
                    return false;
                }

                // Test read operation
                $read_data = file_get_contents($test_file);

                // Cleanup
                @unlink($test_file);

                // Verify retrieved data matches
                return $read_data === $test_data;

            // whoopsie...
            } catch (\Exception $e) {
                // set the error and return false
                self::$_last_error = "File cache test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Test SQLite availability and basic functionality
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if SQLite is available and functional
         */
        private static function testSQLiteAvailability(): bool
        {

            try {
                // Check if PDO SQLite is available
                if (! class_exists('PDO') || ! in_array('sqlite', \PDO::getAvailableDrivers())) {
                    return false;
                }

                // Try to create a temporary SQLite connection
                $temp_db = ':memory:';
                $pdo = new \PDO("sqlite:{$temp_db}");

                // Test basic query
                $result = $pdo->query('SELECT 1 as test');

                return $result !== false;
            } catch (\Exception $e) {
                self::$_last_error = "SQLite test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Reset discovery state when configuration changes
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        public static function resetDiscovery(): void
        {
            self::$_available_tiers = [];
            self::$_discovery_complete = false;
            self::$_tier_test_cache = [];
        }
    }

}
