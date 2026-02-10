<?php

/**
 * KPT Cache Health Monitor - Comprehensive Health Monitoring and Alerting
 *
 * Provides comprehensive health monitoring for all cache tiers including
 * connection health, performance monitoring, resource usage tracking,
 * automated health checks, and alerting capabilities.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the class doesn't exist
if (! class_exists('CacheHealthMonitor')) {

    /**
     * KPT Cache Health Monitor
     *
     * Centralized health monitoring system that tracks the health status of all
     * cache tiers, monitors performance metrics, provides alerting capabilities,
     * and maintains health history for trend analysis.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class CacheHealthMonitor
    {
        // class constants
        const STATUS_HEALTHY = 'healthy';
        const STATUS_WARNING = 'warning';
        const STATUS_CRITICAL = 'critical';
        const STATUS_UNAVAILABLE = 'unavailable';
        const STATUS_UNKNOWN = 'unknown';
        const TIER_ARRAY = 'array';
        const TIER_OPCACHE = 'opcache';
        const TIER_SHMOP = 'shmop';
        const TIER_APCU = 'apcu';
        const TIER_YAC = 'yac';
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_FILE = 'file';
        const TIER_SQLITE = 'sqlite';
        const CHECK_CONNECTIVITY = 'connectivity';
        const CHECK_PERFORMANCE = 'performance';
        const CHECK_RESOURCES = 'resources';
        const CHECK_INTEGRITY = 'integrity';
        const CHECK_CONFIG = 'config';

        // class properties
        private static array $_tier_health_status = [];
        private static array $_health_history = [];
        private static int $_max_history_entries = 1000;
        private static array $_last_alerts = [];
        private static bool $_monitoring_enabled = true;
        private static int $_check_interval = 60;
        private static array $_cached_results = [];
        private static int $_cache_duration = 30;
        private static array $_system_resources = [];
        private static array $_connection_health = [];
        private static $_custom_health_callback = null;

        // performance thresholds
        private static array $_performance_thresholds = [
            self::TIER_ARRAY => ['response_time' => 0.001, 'memory_usage' => 50],
            self::TIER_OPCACHE => ['response_time' => 0.01, 'memory_usage' => 80],
            self::TIER_SHMOP => ['response_time' => 0.01, 'memory_usage' => 80],
            self::TIER_APCU => ['response_time' => 0.01, 'memory_usage' => 80],
            self::TIER_YAC => ['response_time' => 0.01, 'memory_usage' => 80],
            self::TIER_REDIS => ['response_time' => 0.05, 'memory_usage' => 90, 'connections' => 80],
            self::TIER_MEMCACHED => ['response_time' => 0.05, 'memory_usage' => 90, 'connections' => 80],
            self::TIER_SQLITE => ['response_time' => 0.05, 'database_size' => 100],
            self::TIER_FILE => ['response_time' => 0.01, 'disk_usage' => 95]
        ];

        // alert config
        private static array $_alert_config = [
            'enabled' => true,
            'email_alerts' => false,
            'log_alerts' => true,
            'callback_alerts' => null,
            'alert_cooldown' => 300 // 5 minutes
        ];

        /**
         * Initialize the health monitor
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $config Configuration options
         * @return void
         */
        public static function initialize(array $config = []): void
        {

            // if the monitor is enabled
            if (isset($config['monitoring_enabled'])) {
                self::$_monitoring_enabled = (bool) $config['monitoring_enabled'];
            }

            // if the interval is set
            if (isset($config['check_interval'])) {
                self::$_check_interval = (int) $config['check_interval'];
            }

            // if the duration is set
            if (isset($config['cache_duration'])) {
                self::$_cache_duration = (int) $config['cache_duration'];
            }

            // if the max history entries is set
            if (isset($config['max_history_entries'])) {
                self::$_max_history_entries = (int) $config['max_history_entries'];
            }

            // if performance thresholds are set
            if (isset($config['performance_thresholds']) && is_array($config['performance_thresholds'])) {
                self::$_performance_thresholds = array_merge(self::$_performance_thresholds, $config['performance_thresholds']);
            }

            // if alerts are set
            if (isset($config['alert_config']) && is_array($config['alert_config'])) {
                self::$_alert_config = array_merge(self::$_alert_config, $config['alert_config']);
            }

            // if the custom callback is set
            if (isset($config['custom_health_callback']) && is_callable($config['custom_health_callback'])) {
                self::$_custom_health_callback = $config['custom_health_callback'];
            }

            // Initialize tier health status
            $available_tiers = CacheTierManager::getAvailableTiers();

            // Apply allowed backends filter
            $allowed_backends = CacheConfig::getAllowedBackends();
            if ($allowed_backends !== null) {
                $available_tiers = array_intersect($available_tiers, $allowed_backends);
            }

            // for each available tier
            foreach ($available_tiers as $tier) {
                // setup the default status
                self::$_tier_health_status[$tier] = [
                    'status' => self::STATUS_UNKNOWN,
                    'last_check' => null,
                    'last_healthy' => null,
                    'consecutive_failures' => 0,
                    'total_checks' => 0,
                    'total_failures' => 0
                ];
            }
        }

        /**
         * Perform comprehensive health check for all tiers
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param bool $force_check Force fresh check ignoring cache
         * @return array Returns comprehensive health status for all tiers
         */
        public static function checkAllTiers(bool $force_check = false): array
        {

            // if monitoring is not enabled, just return an empty array
            if (! self::$_monitoring_enabled) {
                return [];
            }

            // hold the results
            $results = [];

            // get all available tiers
            $available_tiers = CacheTierManager::getAvailableTiers();

            // Apply allowed backends filter
            $allowed_backends = CacheConfig::getAllowedBackends();
            if ($allowed_backends !== null) {
                $available_tiers = array_intersect($available_tiers, $allowed_backends);
            }

            // loop over the available tiers
            foreach ($available_tiers as $tier) {
                // set the results for the tier
                $results[$tier] = self::checkTierHealth($tier, $force_check);
            }

            // Update system resources
            self::updateSystemResources();

            // return the results
            return $results;
        }

        /**
         * Check health status of a specific tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to check
         * @param bool $force_check Force fresh check ignoring cache
         * @return array Returns detailed health status for the tier
         */
        public static function checkTierHealth(string $tier, bool $force_check = false): array
        {

            // if monitoring is not enabled, return unknown status
            if (! self::$_monitoring_enabled) {
                return ['status' => self::STATUS_UNKNOWN, 'message' => 'Monitoring disabled'];
            }

            // Check cache first unless forced
            $cache_key = 'health_' . $tier;
            if (! $force_check && isset(self::$_cached_results[$cache_key])) {
                $cached = self::$_cached_results[$cache_key];
                if (time() - $cached['timestamp'] < self::$_cache_duration) {
                    return $cached['result'];
                }
            }

            // Perform actual health check
            $start_time = microtime(true);
            $health_result = self::performTierHealthCheck($tier);
            $check_duration = microtime(true) - $start_time;

            // Add timing and metadata
            $health_result['check_duration'] = $check_duration;
            $health_result['timestamp'] = time();
            $health_result['tier'] = $tier;

            // Update tier health status
            self::updateTierHealthStatus($tier, $health_result);

            // Cache the result
            self::$_cached_results[$cache_key] = [
                'result' => $health_result,
                'timestamp' => time()
            ];

            // Add to history
            self::addToHealthHistory($tier, $health_result);

            // Check for alerts
            self::checkAlerts($tier, $health_result);

            // return the health result
            return $health_result;
        }

        /**
         * Perform detailed health check for a specific tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to check
         * @return array Returns detailed health check results
         */
        private static function performTierHealthCheck(string $tier): array
        {

            // setup the initial result
            $result = [
                'status' => self::STATUS_UNKNOWN,
                'checks' => [],
                'metrics' => [],
                'errors' => [],
                'warnings' => []
            ];

            try {
                // Connectivity check
                $connectivity = self::checkConnectivity($tier);
                $result['checks'][self::CHECK_CONNECTIVITY] = $connectivity;

                // if connectivity failed, return early
                if (! $connectivity['success']) {
                    $result['status'] = self::STATUS_UNAVAILABLE;
                    $result['errors'][] = $connectivity['message'];
                    return $result;
                }

                // Performance check
                $performance = self::checkPerformance($tier);
                $result['checks'][self::CHECK_PERFORMANCE] = $performance;
                $result['metrics'] = array_merge($result['metrics'], $performance['metrics']);

                // Resource usage check
                $resources = self::checkResources($tier);
                $result['checks'][self::CHECK_RESOURCES] = $resources;
                $result['metrics'] = array_merge($result['metrics'], $resources['metrics']);

                // Data integrity check
                $integrity = self::checkDataIntegrity($tier);
                $result['checks'][self::CHECK_INTEGRITY] = $integrity;

                // Configuration check
                $config = self::checkConfiguration($tier);
                $result['checks'][self::CHECK_CONFIG] = $config;

                // Determine overall status
                $result['status'] = self::determineOverallStatus($result['checks']);

                // Collect warnings
                foreach ($result['checks'] as $check_type => $check_result) {
                    if (isset($check_result['warnings'])) {
                        $result['warnings'] = array_merge($result['warnings'], $check_result['warnings']);
                    }
                    if (isset($check_result['errors'])) {
                        $result['errors'] = array_merge($result['errors'], $check_result['errors']);
                    }
                }

                // Custom health check if configured
                if (self::$_custom_health_callback) {
                    $custom_result = call_user_func(self::$_custom_health_callback, $tier, $result);
                    if (is_array($custom_result)) {
                        $result['checks']['custom'] = $custom_result;
                    }
                }
            } catch (\Exception $e) {
                $result['status'] = self::STATUS_CRITICAL;
                $result['errors'][] = 'Health check failed: ' . $e->getMessage();
            }

            // return the result
            return $result;
        }

        /**
         * Check connectivity for a specific tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to check connectivity for
         * @return array Returns connectivity check results
         */
        private static function checkConnectivity(string $tier): array
        {

            // capture the start time
            $start_time = microtime(true);

            // setup the initial result
            $result = ['success' => false, 'message' => '', 'response_time' => 0];

            // try to check each tier
            try {
                // match on the tier
                $result = match ($tier) {
                    self::TIER_ARRAY => [
                        'success' => true,
                        'message' => 'Array cache is always available'
                    ],
                    self::TIER_OPCACHE => [
                        'success' => function_exists('opcache_get_status') && self::isOPcacheEnabled(),
                        'message' => (function_exists('opcache_get_status') && self::isOPcacheEnabled())
                            ? 'OPcache is enabled and functional'
                            : 'OPcache not available or disabled'
                    ],
                    self::TIER_SHMOP => [
                        'success' => function_exists('shmop_open'),
                        'message' => function_exists('shmop_open')
                            ? 'SHMOP functions available'
                            : 'SHMOP extension not available'
                    ],
                    self::TIER_APCU => [
                        'success' => function_exists('apcu_enabled') && apcu_enabled(),
                        'message' => (function_exists('apcu_enabled') && apcu_enabled())
                            ? 'APCu is enabled and functional'
                            : 'APCu not available or disabled'
                    ],
                    self::TIER_YAC => [
                        'success' => extension_loaded('yac'),
                        'message' => extension_loaded('yac')
                            ? 'YAC extension loaded'
                            : 'YAC extension not available'
                    ],
                    self::TIER_REDIS => self::checkRedisConnectivity(),
                    self::TIER_MEMCACHED => self::checkMemcachedConnectivity(),
                    self::TIER_SQLITE => self::checkSQLiteConnectivity(),
                    self::TIER_FILE => self::checkFileSystemConnectivity(),
                    default => ['message' => 'Unknown tier type']
                };

            // whoopsie... nfg
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['message'] = 'Connectivity check failed: ' . $e->getMessage();
            }

            // capture the response time
            $result['response_time'] = microtime(true) - $start_time;

            // return the result
            return $result;
        }

        /**
         * Check Redis connectivity and basic functionality
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns Redis connectivity results
         */
        private static function checkRedisConnectivity(): array
        {

            // setup the initial result
            $result = ['success' => false, 'message' => ''];

            // check if redis class exists
            if (! class_exists('Redis')) {
                $result['message'] = 'Redis extension not available';
                return $result;
            }

            try {
                // create the redis instance
                $redis = new \Redis();

                // get the configuration
                $config = CacheConfig::get('redis');

                // connect to redis
                $connected = $redis->pconnect(
                    $config['host'] ?? '127.0.0.1',
                    $config['port'] ?? 6379,
                    2
                );

                // if connection failed
                if (! $connected) {
                    $result['message'] = 'Failed to connect to Redis server';
                    return $result;
                }

                // Test ping
                $ping_result = $redis->ping();
                if ($ping_result !== true && $ping_result !== '+PONG') {
                    $result['message'] = 'Redis ping failed';
                    $redis->close();
                    return $result;
                }

                // Test basic operations
                $test_key = '__health_check_' . uniqid();
                $test_value = 'health_test_' . time();

                // test write operation
                if (! $redis->setex($test_key, 10, $test_value)) {
                    $result['message'] = 'Redis write operation failed';
                    $redis->close();
                    return $result;
                }

                // test read operation
                $retrieved = $redis->get($test_key);
                $redis->del($test_key);
                $redis->close();

                // check if read was successful
                if ($retrieved !== $test_value) {
                    $result['message'] = 'Redis read operation failed';
                    return $result;
                }

                // all tests passed
                $result['success'] = true;
                $result['message'] = 'Redis connectivity and operations successful';
            } catch (\Exception $e) {
                $result['message'] = 'Redis connectivity error: ' . $e->getMessage();
            }

            // return the result
            return $result;
        }

        /**
         * Check Memcached connectivity and basic functionality
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns Memcached connectivity results
         */
        private static function checkMemcachedConnectivity(): array
        {

            // setup the initial result
            $result = ['success' => false, 'message' => ''];

            // check if memcached class exists
            if (! class_exists('Memcached')) {
                $result['message'] = 'Memcached extension not available';
                return $result;
            }

            try {
                // create the memcached instance
                $memcached = new \Memcached();

                // get the configuration
                $config = CacheConfig::get('memcached');

                // add server
                $memcached->addServer(
                    $config['host'] ?? '127.0.0.1',
                    $config['port'] ?? 11211
                );

                // Test connection with stats
                $stats = $memcached->getStats();
                if (empty($stats)) {
                    $result['message'] = 'Failed to connect to Memcached server';
                    return $result;
                }

                // Test basic operations
                $test_key = '__health_check_' . uniqid();
                $test_value = 'health_test_' . time();

                // test write operation
                if (! $memcached->set($test_key, $test_value, time() + 10)) {
                    $result['message'] = 'Memcached write operation failed';
                    $memcached->quit();
                    return $result;
                }

                // test read operation
                $retrieved = $memcached->get($test_key);
                $memcached->delete($test_key);
                $memcached->quit();

                // check if read was successful
                if ($retrieved !== $test_value) {
                    $result['message'] = 'Memcached read operation failed';
                    return $result;
                }

                // all tests passed
                $result['success'] = true;
                $result['message'] = 'Memcached connectivity and operations successful';
            } catch (\Exception $e) {
                $result['message'] = 'Memcached connectivity error: ' . $e->getMessage();
            }

            // return the result
            return $result;
        }

        /**
         * Check file system connectivity and permissions
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns file system connectivity results
         */
        private static function checkFileSystemConnectivity(): array
        {

            // setup the initial result
            $result = ['success' => false, 'message' => ''];

            try {
                // get the cache path
                $cache_path = Cache::getCachePath();

                // check if directory exists
                if (! is_dir($cache_path)) {
                    $result['message'] = 'Cache directory does not exist: ' . $cache_path;
                    return $result;
                }

                // check if directory is writable
                if (! is_writable($cache_path)) {
                    $result['message'] = 'Cache directory is not writable: ' . $cache_path;
                    return $result;
                }

                // Test file operations
                $test_file = $cache_path . 'health_check_' . uniqid() . '.tmp';
                $test_data = 'health_test_' . time();

                // test write operation
                if (file_put_contents($test_file, $test_data) === false) {
                    $result['message'] = 'Failed to write test file';
                    return $result;
                }

                // test read operation
                $read_data = file_get_contents($test_file);
                @unlink($test_file);

                // check if read was successful
                if ($read_data !== $test_data) {
                    $result['message'] = 'File read/write test failed';
                    return $result;
                }

                // all tests passed
                $result['success'] = true;
                $result['message'] = 'File system connectivity and operations successful';
            } catch (\Exception $e) {
                $result['message'] = 'File system connectivity error: ' . $e->getMessage();
            }

            // return the result
            return $result;
        }

        /**
         * Check SQLite connectivity and basic functionality
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns SQLite connectivity results
         */
        private static function checkSQLiteConnectivity(): array
        {

            $result = ['success' => false, 'message' => ''];

            try {
                if (! class_exists('PDO') || ! in_array('sqlite', \PDO::getAvailableDrivers())) {
                    $result['message'] = 'SQLite PDO driver not available';
                    return $result;
                }

                // Test with memory database
                $pdo = new \PDO('sqlite::memory:');
                $test_result = $pdo->query('SELECT 1 as test');

                if ($test_result !== false) {
                    $result['success'] = true;
                    $result['message'] = 'SQLite connectivity and operations successful';
                } else {
                    $result['message'] = 'SQLite query test failed';
                }
            } catch (\Exception $e) {
                $result['message'] = 'SQLite connectivity error: ' . $e->getMessage();
            }

            return $result;
        }

        /**
         * Check performance metrics for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to check performance for
         * @return array Returns performance check results
         */
        private static function checkPerformance(string $tier): array
        {

            // setup the initial result
            $result = [
                'success' => true,
                'metrics' => [],
                'warnings' => [],
                'errors' => []
            ];

            try {
                // Perform benchmark operations
                $benchmark_key = '__perf_test_' . uniqid();
                $benchmark_data = str_repeat('A', 1024); // 1KB test data

                // Measure write performance
                $write_start = microtime(true);
                $write_success = self::performBenchmarkWrite($tier, $benchmark_key, $benchmark_data);
                $write_time = microtime(true) - $write_start;

                // Measure read performance
                $read_start = microtime(true);
                $read_success = self::performBenchmarkRead($tier, $benchmark_key);
                $read_time = microtime(true) - $read_start;

                // Cleanup
                self::performBenchmarkDelete($tier, $benchmark_key);

                // store metrics
                $result['metrics']['write_time'] = $write_time;
                $result['metrics']['read_time'] = $read_time;
                $result['metrics']['total_time'] = $write_time + $read_time;

                // Check against thresholds
                $thresholds = self::$_performance_thresholds[$tier] ?? [];
                if (isset($thresholds['response_time'])) {
                    if ($result['metrics']['total_time'] > $thresholds['response_time']) {
                        $result['warnings'][] = "Response time ({$result['metrics']['total_time']}s) exceeds threshold ({$thresholds['response_time']}s)";
                    }
                }

                // check if operations failed
                if (! $write_success || ! $read_success) {
                    $result['success'] = false;
                    $result['errors'][] = 'Performance benchmark operations failed';
                }
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['errors'][] = 'Performance check failed: ' . $e->getMessage();
            }

            // return the result
            return $result;
        }

        /**
         * Check resource usage for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to check resources for
         * @return array Returns resource usage check results
         */
        private static function checkResources(string $tier): array
        {

            // setup the initial result
            $result = [
                'success' => true,
                'metrics' => [],
                'warnings' => [],
                'errors' => []
            ];

            try {
                // match on the tier type
                $result['metrics'] = match ($tier) {
                    self::TIER_ARRAY => self::getArrayResourceMetrics(),
                    self::TIER_OPCACHE => self::getOPcacheResourceMetrics(),
                    self::TIER_APCU => self::getAPCuResourceMetrics(),
                    self::TIER_REDIS => self::getRedisResourceMetrics(),
                    self::TIER_MEMCACHED => self::getMemcachedResourceMetrics(),
                    self::TIER_FILE => self::getFileSystemResourceMetrics(),
                    default => ['resource_check' => 'not_applicable']
                };

                // Check thresholds
                $thresholds = self::$_performance_thresholds[$tier] ?? [];
                foreach ($thresholds as $metric => $threshold) {
                    if (isset($result['metrics'][$metric]) && $result['metrics'][$metric] > $threshold) {
                        $result['warnings'][] = "{$metric} ({$result['metrics'][$metric]}%) exceeds threshold ({$threshold}%)";
                    }
                }
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['errors'][] = 'Resource check failed: ' . $e->getMessage();
            }

            // return the result
            return $result;
        }

        /**
         * Check data integrity for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to check data integrity for
         * @return array Returns data integrity check results
         */
        private static function checkDataIntegrity(string $tier): array
        {

            // setup the initial result
            $result = [
                'success' => true,
                'tests_passed' => 0,
                'tests_failed' => 0,
                'errors' => []
            ];

            try {
                // Test data integrity with various data types
                $test_cases = [
                    'string' => 'test_string_' . uniqid(),
                    'integer' => 12345,
                    'float' => 123.45,
                    'array' => ['key1' => 'value1', 'key2' => 'value2'],
                    'object' => (object) ['prop1' => 'value1', 'prop2' => 'value2'],
                    'binary' => "\x00\x01\x02\x03\xFF"
                ];

                // loop through each test case
                foreach ($test_cases as $type => $data) {
                    $test_key = "__integrity_test_{$type}_" . uniqid();

                    // Store and retrieve data
                    if (self::performBenchmarkWrite($tier, $test_key, $data)) {
                        $retrieved = self::performBenchmarkRead($tier, $test_key);

                        if ($retrieved === $data) {
                            $result['tests_passed']++;
                        } else {
                            $result['tests_failed']++;
                            $result['errors'][] = "Data integrity test failed for type: {$type}";
                        }

                        self::performBenchmarkDelete($tier, $test_key);
                    } else {
                        $result['tests_failed']++;
                        $result['errors'][] = "Failed to store data for integrity test: {$type}";
                    }
                }

                // check if any tests failed
                if ($result['tests_failed'] > 0) {
                    $result['success'] = false;
                }
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['errors'][] = 'Data integrity check failed: ' . $e->getMessage();
            }

            // return the result
            return $result;
        }

        /**
         * Check configuration validity for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to check configuration for
         * @return array Returns configuration check results
         */
        private static function checkConfiguration(string $tier): array
        {

            // setup the initial result
            $result = [
                'success' => true,
                'config_valid' => true,
                'warnings' => [],
                'errors' => []
            ];

            try {
                // get the tier configuration
                $config = CacheConfig::get($tier);

                // Validate tier-specific configuration
                switch ($tier) {
                    // redis
                    case self::TIER_REDIS:
                        if (empty($config['host'])) {
                            $result['errors'][] = 'Redis host not configured';
                            $result['config_valid'] = false;
                        }
                        if (empty($config['port']) || ! is_numeric($config['port'])) {
                            $result['errors'][] = 'Redis port not properly configured';
                            $result['config_valid'] = false;
                        }
                        break;
                    // memcached
                    case self::TIER_MEMCACHED:
                        if (empty($config['host'])) {
                            $result['errors'][] = 'Memcached host not configured';
                            $result['config_valid'] = false;
                        }
                        if (empty($config['port']) || ! is_numeric($config['port'])) {
                            $result['errors'][] = 'Memcached port not properly configured';
                            $result['config_valid'] = false;
                        }
                        break;
                    // file
                    case self::TIER_FILE:
                        if (! empty($config['path']) && ! is_dir($config['path'])) {
                            $result['warnings'][] = 'Configured file cache path does not exist';
                        }
                        break;
                }

                // if config is not valid, set success to false
                if (! $result['config_valid']) {
                    $result['success'] = false;
                }
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['errors'][] = 'Configuration check failed: ' . $e->getMessage();
            }

            // return the result
            return $result;
        }

        /**
         * Determine overall health status from individual check results
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $checks Individual check results
         * @return string Returns overall status
         */
        private static function determineOverallStatus(array $checks): string
        {

            // setup flags
            $has_errors = false;
            $has_warnings = false;

            // loop through each check
            foreach ($checks as $check) {
                if (! $check['success']) {
                    $has_errors = true;
                }
                if (! empty($check['warnings'])) {
                    $has_warnings = true;
                }
            }

            // determine status based on flags
            if ($has_errors) {
                return self::STATUS_CRITICAL;
            } elseif ($has_warnings) {
                return self::STATUS_WARNING;
            } else {
                return self::STATUS_HEALTHY;
            }
        }

        /**
         * Update tier health status tracking
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier being updated
         * @param array $health_result Health check results
         * @return void
         */
        private static function updateTierHealthStatus(string $tier, array $health_result): void
        {

            // if tier status doesn't exist, initialize it
            if (! isset(self::$_tier_health_status[$tier])) {
                self::$_tier_health_status[$tier] = [
                    'status' => self::STATUS_UNKNOWN,
                    'last_check' => null,
                    'last_healthy' => null,
                    'consecutive_failures' => 0,
                    'total_checks' => 0,
                    'total_failures' => 0
                ];
            }

            // get reference to status
            $status = &self::$_tier_health_status[$tier];
            $status['last_check'] = time();
            $status['total_checks']++;

            // update based on health result
            if ($health_result['status'] === self::STATUS_HEALTHY) {
                $status['last_healthy'] = time();
                $status['consecutive_failures'] = 0;
            } else {
                $status['consecutive_failures']++;
                $status['total_failures']++;
            }

            // update the current status
            $status['status'] = $health_result['status'];
        }

        /**
         * Add health check result to history
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier being checked
         * @param array $health_result Health check results
         * @return void
         */
        private static function addToHealthHistory(string $tier, array $health_result): void
        {

            // if tier history doesn't exist, initialize it
            if (! isset(self::$_health_history[$tier])) {
                self::$_health_history[$tier] = [];
            }

            // create history entry
            $entry = [
                'timestamp' => time(),
                'status' => $health_result['status'],
                'check_duration' => $health_result['check_duration'],
                'errors' => count($health_result['errors']),
                'warnings' => count($health_result['warnings'])
            ];

            // add to history
            self::$_health_history[$tier][] = $entry;

            // Maintain history size limit
            if (count(self::$_health_history[$tier]) > self::$_max_history_entries) {
                self::$_health_history[$tier] = array_slice(self::$_health_history[$tier], -self::$_max_history_entries);
            }
        }

        /**
         * Check for alert conditions and trigger alerts
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier being checked
         * @param array $health_result Health check results
         * @return void
         */
        private static function checkAlerts(string $tier, array $health_result): void
        {

            // if alerts are not enabled, return early
            if (! self::$_alert_config['enabled']) {
                return;
            }

            // setup alert flags
            $should_alert = false;
            $alert_message = '';

            // Check for alert conditions
            if ($health_result['status'] === self::STATUS_CRITICAL) {
                $should_alert = true;
                $alert_message = "CRITICAL: {$tier} tier is in critical state";
            } elseif ($health_result['status'] === self::STATUS_UNAVAILABLE) {
                $should_alert = true;
                $alert_message = "UNAVAILABLE: {$tier} tier is unavailable";
            } elseif (isset(self::$_tier_health_status[$tier])) {
                $status = self::$_tier_health_status[$tier];
                if ($status['consecutive_failures'] >= 3) {
                    $should_alert = true;
                    $alert_message = "WARNING: {$tier} tier has {$status['consecutive_failures']} consecutive failures";
                }
            }

            // if we should alert
            if ($should_alert) {
                // Check cooldown period
                $last_alert = self::$_last_alerts[$tier] ?? 0;
                if (time() - $last_alert >= self::$_alert_config['alert_cooldown']) {
                    self::triggerAlert($tier, $alert_message, $health_result);
                    self::$_last_alerts[$tier] = time();
                }
            }
        }

        /**
         * Trigger an alert for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier triggering the alert
         * @param string $message Alert message
         * @param array $health_result Full health check results
         * @return void
         */
        private static function triggerAlert(string $tier, string $message, array $health_result): void
        {

            // Log alert
            if (self::$_alert_config['log_alerts']) {
                Logger::error($message, [
                    'tier' => $tier,
                    'health_result' => $health_result
                ]);
            }

            // Callback alert
            if (self::$_alert_config['callback_alerts'] && is_callable(self::$_alert_config['callback_alerts'])) {
                call_user_func(self::$_alert_config['callback_alerts'], $tier, $message, $health_result);
            }
        }

        /**
         * Perform benchmark write operation for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to test
         * @param string $key Test key
         * @param mixed $data Test data
         * @return bool Returns true if write was successful
         */
        private static function performBenchmarkWrite(string $tier, string $key, mixed $data): bool
        {

            try {
                return Cache::setToTier($key, $data, 60, $tier);
            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * Perform benchmark read operation for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to test
         * @param string $key Test key
         * @return mixed Returns retrieved data or false
         */
        private static function performBenchmarkRead(string $tier, string $key): mixed
        {

            try {
                return Cache::getFromTier($key, $tier);
            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * Perform benchmark delete operation for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to test
         * @param string $key Test key
         * @return bool Returns true if delete was successful
         */
        private static function performBenchmarkDelete(string $tier, string $key): bool
        {

            try {
                return Cache::deleteFromTier($key, $tier);
            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * Get Array cache resource metrics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns Array cache resource metrics
         */
        private static function getArrayResourceMetrics(): array
        {

            try {
                // Get array cache stats
                $stats = Cache::getArrayStats();

                return [
                    'memory_usage' => $stats['utilization_percent'],
                    'cached_items' => $stats['items_cached'],
                    'hit_rate' => $stats['hit_rate_percent'],
                    'memory_usage_mb' => $stats['memory_usage_mb']
                ];
            } catch (\Exception $e) {
                return ['error' => 'Failed to get Array cache metrics: ' . $e->getMessage()];
            }
        }

        /**
         * Get OPcache resource metrics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns OPcache resource metrics
         */
        private static function getOPcacheResourceMetrics(): array
        {

            // check if opcache is available
            if (! function_exists('opcache_get_status')) {
                return ['error' => 'OPcache not available'];
            }

            // get opcache status
            $status = opcache_get_status(true);
            if (! $status) {
                return ['error' => 'Failed to get OPcache status'];
            }

            // calculate memory usage
            $memory = $status['memory_usage'];
            $memory_usage = round(( $memory['used_memory'] / $memory['free_memory'] + $memory['used_memory'] ) * 100, 2);

            // return the metrics
            return [
                'memory_usage' => $memory_usage,
                'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'],
                'cache_hits' => $status['opcache_statistics']['hits'],
                'cache_misses' => $status['opcache_statistics']['misses'],
                'hit_rate' => round($status['opcache_statistics']['opcache_hit_rate'], 2)
            ];
        }

        /**
         * Get APCu resource metrics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns APCu resource metrics
         */
        private static function getAPCuResourceMetrics(): array
        {

            // check if apcu is available
            if (! function_exists('apcu_cache_info')) {
                return ['error' => 'APCu not available'];
            }

            // get apcu info
            $info = apcu_cache_info();
            if (! $info) {
                return ['error' => 'Failed to get APCu info'];
            }

            // calculate memory usage
            $memory_usage = round(( $info['mem_size'] - $info['avail_mem'] ) / $info['mem_size'] * 100, 2);

            // return the metrics
            return [
                'memory_usage' => $memory_usage,
                'cached_entries' => $info['num_entries'],
                'cache_hits' => $info['num_hits'],
                'cache_misses' => $info['num_misses'],
                'hit_rate' => $info['num_hits'] > 0 ? round($info['num_hits'] / ( $info['num_hits'] + $info['num_misses'] ) * 100, 2) : 0
            ];
        }

        /**
         * Get Redis resource metrics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns Redis resource metrics
         */
        private static function getRedisResourceMetrics(): array
        {

            try {
                // create redis connection
                $redis = new \Redis();
                $config = CacheConfig::get('redis');

                // connect to redis
                if (! $redis->pconnect($config['host'] ?? '127.0.0.1', $config['port'] ?? 6379, 2)) {
                    return ['error' => 'Failed to connect to Redis'];
                }

                // get info and close connection
                $info = $redis->info();
                $redis->close();

                // return the metrics
                return [
                    'memory_usage' => round(( $info['used_memory'] / $info['maxmemory'] ) * 100, 2),
                    'connected_clients' => $info['connected_clients'],
                    'operations_per_sec' => $info['instantaneous_ops_per_sec'],
                    'keyspace_hits' => $info['keyspace_hits'],
                    'keyspace_misses' => $info['keyspace_misses'],
                    'hit_rate' => ( $info['keyspace_hits'] + $info['keyspace_misses'] ) > 0
                        ? round($info['keyspace_hits'] / ( $info['keyspace_hits'] + $info['keyspace_misses'] ) * 100, 2)
                        : 0
                ];
            } catch (\Exception $e) {
                return ['error' => 'Failed to get Redis metrics: ' . $e->getMessage()];
            }
        }

        /**
         * Get Memcached resource metrics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns Memcached resource metrics
         */
        private static function getMemcachedResourceMetrics(): array
        {

            try {
                // create memcached connection
                $memcached = new \Memcached();
                $config = CacheConfig::get('memcached');

                // add server
                $memcached->addServer($config['host'] ?? '127.0.0.1', $config['port'] ?? 11211);
                $stats = $memcached->getStats();
                $memcached->quit();

                // check if stats were retrieved
                if (empty($stats)) {
                    return ['error' => 'Failed to get Memcached stats'];
                }

                // get first server stats
                $server_stats = reset($stats);
                $memory_usage = round(( $server_stats['bytes'] / $server_stats['limit_maxbytes'] ) * 100, 2);

                // return the metrics
                return [
                    'memory_usage' => $memory_usage,
                    'current_connections' => $server_stats['curr_connections'],
                    'cache_hits' => $server_stats['get_hits'],
                    'cache_misses' => $server_stats['get_misses'],
                    'hit_rate' => ( $server_stats['get_hits'] + $server_stats['get_misses'] ) > 0
                        ? round($server_stats['get_hits'] / ( $server_stats['get_hits'] + $server_stats['get_misses'] ) * 100, 2)
                        : 0
                ];
            } catch (\Exception $e) {
                return ['error' => 'Failed to get Memcached metrics: ' . $e->getMessage()];
            }
        }

        /**
         * Get file system resource metrics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns file system resource metrics
         */
        private static function getFileSystemResourceMetrics(): array
        {

            try {
                // get cache path
                $cache_path = Cache::getCachePath();

                // get disk space info
                $total_space = disk_total_space($cache_path);
                $free_space = disk_free_space($cache_path);
                $used_space = $total_space - $free_space;
                $disk_usage = round(( $used_space / $total_space ) * 100, 2);

                // get cache file info
                $files = glob($cache_path . '*');
                $CacheFiles = count($files);
                $cache_size = array_sum(array_map('filesize', $files));

                // return the metrics
                return [
                    'disk_usage' => $disk_usage,
                    'CacheFiles' => $CacheFiles,
                    'cache_size' => $cache_size,
                    'free_space' => $free_space,
                    'total_space' => $total_space
                ];
            } catch (\Exception $e) {
                return ['error' => 'Failed to get file system metrics: ' . $e->getMessage()];
            }
        }

        /**
         * Update system resource metrics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        private static function updateSystemResources(): void
        {

            // capture system resource metrics
            self::$_system_resources = [
                'timestamp' => time(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'process_id' => getmypid(),
                'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null
            ];
        }

        /**
         * Check if OPcache is enabled
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if OPcache is enabled
         */
        private static function isOPcacheEnabled(): bool
        {

            // check if opcache function exists
            if (! function_exists('opcache_get_status')) {
                return false;
            }

            // get status and check if enabled
            $status = opcache_get_status(false);
            return $status && isset($status['opcache_enabled']) && $status['opcache_enabled'];
        }

        /**
         * Get current health status for all tiers
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns current health status for all tiers
         */
        public static function getHealthStatus(): array
        {

            return self::$_tier_health_status;
        }

        /**
         * Get health history for a tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to get history for
         * @param int|null $limit Optional limit on number of entries
         * @return array Returns health history for the tier
         */
        public static function getHealthHistory(string $tier, ?int $limit = null): array
        {

            // get history for tier
            $history = self::$_health_history[$tier] ?? [];

            // apply limit if specified
            if ($limit !== null) {
                return array_slice($history, -$limit);
            }

            // return full history
            return $history;
        }

        /**
         * Get health monitoring statistics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns monitoring statistics
         */
        public static function getMonitoringStats(): array
        {

            // initialize counters
            $total_checks = 0;
            $total_failures = 0;
            $healthy_tiers = 0;

            // loop through tier statuses
            foreach (self::$_tier_health_status as $tier => $status) {
                $total_checks += $status['total_checks'];
                $total_failures += $status['total_failures'];

                if ($status['status'] === self::STATUS_HEALTHY) {
                    $healthy_tiers++;
                }
            }

            // return monitoring statistics
            return [
                'monitoring_enabled' => self::$_monitoring_enabled,
                'total_tiers' => count(self::$_tier_health_status),
                'healthy_tiers' => $healthy_tiers,
                'total_checks' => $total_checks,
                'total_failures' => $total_failures,
                'success_rate' => $total_checks > 0 ? round(( $total_checks - $total_failures ) / $total_checks * 100, 2) : 0,
                'check_interval' => self::$_check_interval,
                'cache_duration' => self::$_cache_duration,
                'system_resources' => self::$_system_resources
            ];
        }

        /**
         * Enable or disable health monitoring
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param bool $enabled Whether to enable monitoring
         * @return void
         */
        public static function setMonitoringEnabled(bool $enabled): void
        {

            self::$_monitoring_enabled = $enabled;
        }

        /**
         * Clear health history for all tiers
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        public static function clearHealthHistory(): void
        {

            self::$_health_history = [];
        }

        /**
         * Clear cached health check results
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        public static function clearCache(): void
        {

            self::$_cached_results = [];
        }

        /**
         * Reset all health monitoring data
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        public static function reset(): void
        {

            self::$_tier_health_status = [];
            self::$_health_history = [];
            self::$_cached_results = [];
            self::$_last_alerts = [];
            self::$_system_resources = [];
            self::$_connection_health = [];
        }
    }
}
