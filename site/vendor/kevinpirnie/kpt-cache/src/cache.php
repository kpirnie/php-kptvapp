<?php

/**
 * KPT Cache - Modern Multi-tier Caching System (Refactored)
 *
 * A comprehensive caching solution that provides multiple tiers of caching
 * including OPcache, SHMOP, APCu, YAC, Redis, Memcached, and File-based
 * caching with automatic tier discovery, connection pooling, and failover support.
 *
 * This refactored version delegates specialized functionality to dedicated managers
 * while maintaining the same public API for backward compatibility.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the class doesn't exist
if (! class_exists('Cache')) {

    /**
     * KPT Cache - Modern Multi-tier Caching System (Refactored)
     *
     * This refactored version maintains the same public API while delegating
     * specialized functionality to dedicated manager classes for better
     * maintainability and single responsibility adherence.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class Cache
    {
        // Import all cache backend traits
        use CacheArray;
        use CacheAPCU;
        use CacheFile;
        use CacheMemcached;
        use CacheOPCache;
        use CacheRedis;
        use CacheSHMOP;
        use CacheYAC;
        use CacheAsync;
        use CacheRedisAsync;
        use CacheFileAsync;
        use CacheMemcachedAsync;
        use CacheMixedAsync;
        use CacheOPCacheAsync;
        use CacheSQLite;

        // tier contstants
        const TIER_ARRAY = 'array';
        const TIER_OPCACHE = 'opcache';
        const TIER_SHMOP = 'shmop';
        const TIER_APCU = 'apcu';
        const TIER_YAC = 'yac';
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_SQLITE = 'sqlite';
        const TIER_FILE = 'file';

        // internal configs
        private static ?string $_fallback_path = null;
        private static bool $_initialized = false;
        private static ?string $_configurable_cache_path = null;
        private static array $_shmop_segments = [];
        private static ?string $_last_used_tier = null;
        private static bool $_connection_pooling_enabled = true;
        private static bool $_async_enabled = false;
        private static ?object $_event_loop = null;
        private static ?string $_last_error = null;

        /**
         * Initialize the cache system
         *
         * Performs complete initialization of the cache system including all
         * manager classes and subsystems.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $config Optional configuration array
         * @return void Returns nothing
         */
        private static function init(array $config = []): void
        {

            // if we're already initialized we don't need to do it again
            if (self::$_initialized) {
                return;
            }

            // Initialize core configuration
            CacheConfig::initialize();

            // Apply allowed backends early if provided in config
            if (isset($config['allowed_backends'])) {
                CacheConfig::setAllowedBackends($config['allowed_backends']);
            }

            // Initialize all manager classes
            self::initializeManagers($config);

            // Initialize the fallback path from global config if available
            if (self::$_fallback_path === null) {
                $global_path = CacheConfig::getGlobalPath();
                if ($global_path !== null) {
                    self::$_fallback_path = $global_path;
                } else {
                    self::$_fallback_path = sys_get_temp_dir() . '/kpt_cache/';
                }
            }

            // Initialize file fallback
            self::initFallback();

            // Initialize connection pools for database backends
            if (self::$_connection_pooling_enabled) {
                self::initializeConnectionPools();
            }

            // mark us as initialized
            self::$_initialized = true;

            // Log initialization if debug is set
            Logger::debug('KPT Cache system initialized', [
                'available_tiers' => self::getAvailableTiers(),
                'connection_pooling' => self::$_connection_pooling_enabled,
                'async_enabled' => self::$_async_enabled
            ]);
        }

        /**
         * Reinitialize the cache system with new configuration
         * Allows changing configuration after initialization
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function reinitialize(): void
        {

            // Force reinitialization
            self::$_initialized = false;

            // Clear the current fallback path
            self::$_fallback_path = null;
            self::$_configurable_cache_path = null;

            // Reinitialize
            self::init();

            Logger::info("Cache system reinitialized", []);
        }

        /**
         * Initialize all manager classes
         *
         * Sets up the tier manager, key manager, logger, and health monitor
         * with appropriate configuration.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $config Configuration array
         * @return void Returns nothing
         */
        private static function initializeManagers(array $config): void
        {

            // hold the key manager
            $km_config = [];

            // Initialize Key Manager
            if (isset($config['key_manager'])) {
                $km_config = $config['key_manager'];

                // see if we need/want to config the global namespace
                if (isset($km_config['global_namespace'])) {
                    CacheKeyManager::setGlobalNamespace($km_config['global_namespace']);
                }

                // see if we need to set the key separator
                if (isset($km_config['key_separator'])) {
                    CacheKeyManager::setKeySeparator($km_config['key_separator']);
                }

                // see if we need to automagically hash long keys
                if (isset($km_config['auto_hash_long_keys'])) {
                    CacheKeyManager::setAutoHashLongKeys($km_config['auto_hash_long_keys']);
                }

                // see what hashing algo we'll be using
                if (isset($km_config['hash_algorithm'])) {
                    CacheKeyManager::setHashAlgorithm($km_config['hash_algorithm']);
                }
            }

            // Initialize Health Monitor
            self::ensureHealthMonitorInitialized();

            // debug
            Logger::debug("Cache Managers Initialized", ['key_manager' => $km_config,]);
        }

        /**
         * Ensure the cache system is properly initialized
         *
         * Lazy initialization check - calls init() if the system hasn't been initialized yet.
         * This method is called by all public methods to ensure the cache system is ready.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $config Optional configuration for initialization
         * @return void Returns nothing
         */
        private static function ensureInitialized(array $config = []): void
        {

            // if we aren't currently, do it!
            if (! self::$_initialized) {
                // Get allowed_backends from global config if available
                $allowed_backends = CacheConfig::getAllowedBackends();
                if ($allowed_backends !== null && !isset($config['allowed_backends'])) {
                    $config['allowed_backends'] = $allowed_backends;
                }
                self::init($config);
            }
        }

        /**
         * Initialize connection pools for database-based cache tiers
         *
         * Sets up connection pooling for Redis and Memcached tiers to improve
         * performance and resource management. Configures minimum/maximum connections
         * and idle timeout settings.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        private static function initializeConnectionPools(): void
        {
            // hold the available tiers
            $available_tiers = self::getAvailableTiers();

            // Get allowed backends filter
            $allowed_backends = CacheConfig::getAllowedBackends();
            if ($allowed_backends !== null) {
                $available_tiers = array_intersect($available_tiers, $allowed_backends);
            }

            // Configure Redis pool if it's available as a tier
            if (in_array(self::TIER_REDIS, $available_tiers)) {
                // configure the pool
                CacheConnectionPool::configurePool('redis', [
                    'min_connections' => 1,
                    'max_connections' => 16,
                    'idle_timeout' => 300
                ]);
            }

            // Configure Memcached pool
            if (in_array(self::TIER_MEMCACHED, $available_tiers)) {
                // configure the pool
                CacheConnectionPool::configurePool('memcached', [
                    'min_connections' => 1,
                    'max_connections' => 16,
                    'idle_timeout' => 300
                ]);
            }

            // debug logging
            Logger::debug("Redis/Memcached Connection Pools Initialized", []);
        }

        /**
         * Initialize fallback caching directory
         *
         * Creates and validates the cache directory for file-based caching. Tries multiple
         * fallback paths if the preferred path fails, ensuring at least one working
         * cache directory is available.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        private static function initFallback(): void
        {

            // Check for configurable path first, then global config, then fallback
            $cache_path = self::$_configurable_cache_path;

            if ($cache_path === null) {
                $global_path = CacheConfig::getGlobalPath();

                if ($global_path !== null) {
                    $cache_path = $global_path;
                    Logger::debug("Using global cache path from config", ['path' => $cache_path]);
                } else {
                    $cache_path = self::$_fallback_path;
                    Logger::debug("No global path set, using default fallback", ['path' => $cache_path]);
                }
            }

            // Try to create and setup the cache directory
            if ($cache_path !== null && self::createCacheDirectory($cache_path)) {
                self::$_fallback_path = $cache_path;
                Logger::info("Cache directory initialized", ['path' => self::$_fallback_path]);
                return;
            }

            Logger::warning("Preferred cache path failed, trying fallbacks", ['preferred' => $cache_path]);

            // Rest of the fallback logic...
            $fallback_paths = [
                sys_get_temp_dir() . '/kpt_cache_' . getmypid() . '_' . get_current_user() . '/',
                sys_get_temp_dir() . '/kpt_cache_' . uniqid() . '/',
                getcwd() . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/kpt_cache_' . getmypid() . '_' . get_current_user() . '/',
                '/tmp/kpt_cache_' . uniqid() . '/',
            ];

            foreach ($fallback_paths as $alt_path) {
                Logger::debug("Trying fallback path", ['path' => $alt_path]);

                if (self::createCacheDirectory($alt_path)) {
                    self::$_fallback_path = $alt_path;
                    Logger::info("Using fallback cache path", ['path' => $alt_path]);
                    return;
                }
            }

            // Last resort
            $temp_path = sys_get_temp_dir() . '/kpt_' . uniqid() . '_' . getmypid() . '/';

            if (self::createCacheDirectory($temp_path)) {
                self::$_fallback_path = $temp_path;
                Logger::warning("Using last resort cache path", ['path' => $temp_path]);
            } else {
                Logger::error("Unable to create any writable cache directory - all fallback paths failed");

                // Try one more unique path in /tmp with different approach
                $final_attempt = '/tmp/kpt_emergency_' . uniqid() . '_' . time() . '/';
                if (self::createCacheDirectory($final_attempt)) {
                    self::$_fallback_path = $final_attempt;
                    Logger::warning("Emergency cache path created", ['path' => $final_attempt]);
                } else {
                    $available_tiers = self::getAvailableTiers();
                    $key = array_search(self::TIER_FILE, $available_tiers);
                    if ($key !== false) {
                        Logger::warning("File tier disabled due to directory creation failure");
                    }
                }
            }
        }

        /**
         * Initialize health monitor lazily when first needed
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        private static function ensureHealthMonitorInitialized(): void
        {

            // set the initial flag to false
            static $health_monitor_initialized = false;

            // check if we aren't initialized and the class isn't in userspace
            if (! $health_monitor_initialized && class_exists('KPT\\CacheHealthMonitor')) {
                // try to initialize the health monitor and set the flag to true
                try {
                    CacheHealthMonitor::initialize();
                    $health_monitor_initialized = true;

                // whoopsie... log the error
                } catch (\Exception $e) {
                    Logger::error("Health Monitor initialization failed", ['error' => $e -> getMessage()]);
                }
            }
        }

        /**
         * Configure and initialize the cache system
         * This should be called BEFORE any cache operations
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $config Configuration array with 'path' and other settings
         * @return void Returns nothing
         */
        public static function configure(array $config = []): void
        {

            // Set global path if provided
            if (isset($config['path'])) {
                CacheConfig::setGlobalPath($config['path']);
                Logger::debug("Global cache path configured", ['path' => $config['path']]);
            }

            // Set global prefix if provided
            if (isset($config['prefix'])) {
                CacheConfig::setGlobalPrefix($config['prefix']);
                Logger::debug("Global cache prefix configured", ['prefix' => $config['prefix']]);
            }

            // Configure specific backends if provided
            if (isset($config['backends']) && is_array($config['backends'])) {
                // loop the backends provided and set their configurations
                foreach ($config['backends'] as $backend => $backend_config) {
                    CacheConfig::set($backend, $backend_config);
                    Logger::debug("Backend configured", ['backend' => $backend]);
                }
            }

            // Configure allowed backends if provided
            if (isset($config['allowed_backends'])) {
                CacheConfig::setAllowedBackends($config['allowed_backends']);
                Logger::debug("Allowed backends configured", ['backends' => $config['allowed_backends']]);
            }

            // If already initialized, reinitialize with new config
            if (self::$_initialized) {
                self::reinitialize();
            }
        }

        /**
         * Update the cache path and reinitialize if necessary
         * Ensures the new path is actually used
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path The new cache path
         * @return bool Returns true if successful
         */
        public static function updateCachePath(string $path): bool
        {

            // Set the global path
            if (! CacheConfig::setGlobalPath($path)) {
                return false;
            }

            // If we're already initialized, we need to reinitialize
            if (self::$_initialized) {
                self::reinitialize();
            }

            return true;
        }

        /**
         * Refresh the cache path
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function refreshCachePathFromGlobal(): bool
        {

            // get the set global path
            $global_path = CacheConfig::getGlobalPath();

            // if it's not null, return the success of setting the cache path
            if ($global_path !== null) {
                return self::setCachePath($global_path);
            }

            // default return
            return false;
        }

        /**
         * Retrieve an item from cache using tier hierarchy
         *
         * Searches through available cache tiers in priority order to find the requested
         * item. If found in a lower tier, automatically promotes it to higher tiers for
         * faster future access.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The unique identifier for the cached item
         * @return mixed Returns the cached data if found, false otherwise
         */
        public static function get(string $key): mixed
        {

            // make sure we're initialized
            self::ensureInitialized();

            // get the available tiers
            $available_tiers = self::getAvailableTiers();

            // loop through the tiers
            foreach ($available_tiers as $tier) {
                // get the cached item from the tier
                $result = self::getFromTierInternal($key, $tier);

                // if it was found
                if ($result !== false) {
                    // Log cache hit
                    Logger::debug("Cache Hit", ['tier' => $tier, 'key' => $key]);

                    // Promote to higher tiers for faster future access
                    self::promoteToHigherTiers($key, $result, $tier);
                    self::$_last_used_tier = $tier;

                    // return the item
                    return $result;
                }
            }

            // Log cache miss
            Logger::debug("Cache Miss", [$key]);

            // default return
            return false;
        }

        /**
         * Store an item in cache across all available tiers
         *
         * Attempts to store the provided data in all available cache tiers
         * for maximum redundancy and performance. Uses the highest priority
         * tier that succeeds as the primary tier.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The unique identifier for the cache item
         * @param mixed $data The data to store in cache
         * @param int $ttl Time to live in seconds (default: 1 hour)
         * @return bool Returns true if stored in at least one tier, false otherwise
         */
        public static function set(string $key, mixed $data, int $ttl = KPT::HOUR_IN_SECONDS): bool
        {

            // make sure we're initialized
            self::ensureInitialized();

            // if there's no data, then there's nothing to do here... just return
            if (empty($data)) {
                Logger::error("Attempted to cache empty data", ['key' => $key]);
                return false;
            }

            // default success
            $success = false;

            // hold the primary tier used
            $primary_tier_used = null;

            // get the available tiers
            $available_tiers = self::getAvailableTiers();

            // loop through each available caching tier
            foreach ($available_tiers as $tier) {
                // if we can successfully set it
                if (self::setToTierInternal($key, $data, $ttl, $tier)) {
                    // set our success to true
                    $success = true;

                    // Track the first (highest priority) successful tier as the primary
                    if ($primary_tier_used === null) {
                        $primary_tier_used = $tier;
                    }

                    // Log successful set operation
                    Logger::debug("Cache item set", ['key' => $key, 'ttl' => $ttl, 'tier' => $tier]);
                } else {
                    // Log failed set operation
                    Logger::error("Failed to set cache item", ['key' => $key, 'ttl' => $ttl, 'tier' => $tier]);
                }
            }

            // Set the last used tier to the primary (first successful) tier
            if ($primary_tier_used !== null) {
                self::$_last_used_tier = $primary_tier_used;
            }

            // return if we're successful
            return $success;
        }

        /**
         * Delete an item from all cache tiers
         *
         * Removes the specified cache item from all available tiers to ensure
         * complete removal and prevent stale data from being served.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The unique identifier for the cache item to delete
         * @return bool Returns true if deleted from all tiers successfully, false if any failed
         */
        public static function delete(string $key): bool
        {

            // make sure we're initialized
            self::ensureInitialized();

            // default success
            $success = true;

            // get the available tiers
            $available_tiers = self::getAvailableTiers();

            // loop through each tier
            foreach ($available_tiers as $tier) {
                // if the delete from the tier was not successful
                if (! self::deleteFromTierInternal($key, $tier)) {
                    $success = false;
                    Logger::error("Failed to delete cache item", ['key' => $key, 'tier' => $tier]);

                // otherwise, debug log it
                } else {
                    Logger::debug("Cache item deleted", ['key' => $key, 'tier' => $tier]);
                }
            }

            // return if we're successful or not
            return $success;
        }

        /**
         * Clear all cached data from all tiers (IMPROVED with error isolation)
         *
         * Performs a complete cache flush across all available tiers. This is a
         * destructive operation that removes all cached data. Each tier is cleared
         * independently so failures in one tier don't prevent others from being cleared.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if ALL tiers cleared successfully, false if ANY failed
         */
        public static function clear(): bool
        {
            // make sure we're initialized
            self::ensureInitialized();

            // Track overall success and individual results
            $overall_success = true;
            $results = [];

            // grab all available tiers
            $available_tiers = self::getAvailableTiers();

            Logger::info("Starting cache clear operation", ['tiers' => $available_tiers]);

            // loop through each tier with error isolation
            foreach ($available_tiers as $tier) {
                try {
                    // Clear this tier independently
                    $tier_success = self::clearTier($tier);
                    $results[$tier] = $tier_success;

                    if ($tier_success) {
                        Logger::debug("Successfully cleared tier", ['tier' => $tier]);
                    } else {
                        Logger::error("Failed to clear tier", ['tier' => $tier]);
                        $overall_success = false;
                    }
                } catch (\Exception $e) {
                    // Log the exception but continue with other tiers
                    Logger::error("Exception while clearing tier", [
                        'tier' => $tier,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $results[$tier] = false;
                    $overall_success = false;
                }
            }

            // Log final results
            $successful_tiers = array_keys(array_filter($results));
            $failed_tiers = array_keys(array_filter($results, function ($success) {
                return !$success;
            }));

            Logger::info("Cache clear operation completed", [
                'overall_success' => $overall_success,
                'successful_tiers' => $successful_tiers,
                'failed_tiers' => $failed_tiers,
                'total_attempted' => count($available_tiers)
            ]);

            return $overall_success;
        }

        /**
         * Clear a specific cache tier (public wrapper)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to clear
         * @return bool Returns true if tier was cleared successfully
         */
        public static function clearSpecificTier(string $tier): bool
        {
            // make sure we're initialized
            self::ensureInitialized();

            // validate tier
            if (!CacheTierManager::isTierValid($tier)) {
                Logger::error("Invalid tier specified for clearing", ['tier' => $tier]);
                return false;
            }

            // check if tier is available
            if (!CacheTierManager::isTierAvailable($tier)) {
                Logger::warning("Tier not available for clearing", ['tier' => $tier]);
                return true; // Consider unavailable tiers as "cleared"
            }

            // try to clear the tier with error isolation
            try {
                $result = self::clearTier($tier);

                if ($result) {
                    Logger::info("Successfully cleared tier", ['tier' => $tier]);
                } else {
                    Logger::error("Failed to clear tier", ['tier' => $tier]);
                }

                return $result;
            } catch (\Exception $e) {
                Logger::error("Exception while clearing tier", [
                    'tier' => $tier,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return false;
            }
        }

        /**
         * Retrieve an item from a specific cache tier
         *
         * Attempts to get data from the specified tier only. If the tier fails,
         * falls back to the standard hierarchy search as a safety measure.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The unique identifier for the cached item
         * @param string $tier The specific cache tier to retrieve from
         * @return mixed Returns the cached data if found, false otherwise
         */
        public static function getFromTier(string $key, string $tier): mixed
        {

            // make sure we're initialized
            self::ensureInitialized();

            // do we have a valid tier?
            if (! CacheTierManager::isTierValid($tier)) {
                Logger::error("Invalid tier specified", ['tier' => $tier,'key' => $key]);
                return false;
            }

            // now... is the tier actually available?
            if (! CacheTierManager::isTierAvailable($tier)) {
                Logger::error("Tier not available", ['tier' => $tier,'key' => $key]);
                return false;
            }

            // get the item from the tier
            $result = self::getFromTierInternal($key, $tier);

            // if we have a result
            if ($result !== false) {
                // set the last used and return the item
                self::$_last_used_tier = $tier;
                Logger::debug("Cache Hit", ['tier' => $tier,'key' => $key]);
                return $result;
            }

            // Fallback to default hierarchy if enabled and tier failed
            Logger::debug("Cache Miss", ['tier' => $tier,'key' => $key]);
            return self::get($key);
        }

        /**
         * Store an item in a specific cache tier only
         *
         * Stores data in the specified tier exclusively, without attempting
         * to replicate to other tiers.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The unique identifier for the cache item
         * @param mixed $data The data to store in cache
         * @param int $ttl Time to live in seconds
         * @param string $tier The specific cache tier to store in
         * @return bool Returns true if successfully stored, false otherwise
         */
        public static function setToTier(string $key, mixed $data, int $ttl, string $tier): bool
        {

            // make sure we're initialized
            self::ensureInitialized();

            // if it's a not valid tier
            if (! CacheTierManager::isTierValid($tier)) {
                Logger::error("Invalid tier specified", ['tier' => $tier,'key' => $key]);
                return false;
            }

            // if the tier is not available
            if (! CacheTierManager::isTierAvailable($tier)) {
                Logger::error("Tier not available", ['tier' => $tier,'key' => $key]);
                return false;
            }

            // if we have no data
            if (empty($data)) {
                Logger::warning("Attempted to cache empty data", ['tier' => $tier,'key' => $key]);
                return false;
            }

            // set the data to the tier
            $success = self::setToTierInternal($key, $data, $ttl, $tier);

            // if it was successfully set
            if ($success) {
                self::$_last_used_tier = $tier;
                Logger::debug("Cache Set", ['tier' => $tier,'key' => $key]);

            // otherwise, log the error
            } else {
                Logger::error("Failed to set cache item", ['tier' => $tier,'key' => $key]);
            }

            // return if it was true or not
            return $success;
        }

        /**
         * Delete an item from a specific cache tier only
         *
         * Removes data from the specified tier exclusively, leaving other
         * tiers unchanged.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The unique identifier for the cache item to delete
         * @param string $tier The specific cache tier to delete from
         * @return bool Returns true if successfully deleted, false otherwise
         */
        public static function deleteFromTier(string $key, string $tier): bool
        {

            // make sure we're initialized
            self::ensureInitialized();

            // if the tier is valid
            if (! CacheTierManager::isTierValid($tier)) {
                Logger::error("Invalid tier specified", ['tier' => $tier,'key' => $key]);
                return false;
            }

            // is the tier available
            if (! CacheTierManager::isTierAvailable($tier)) {
                Logger::error("Tier not available", 'tier_availability', ['tier' => $tier,'key' => $key]);
                return false;
            }

            // delete from the tier
            $success = self::deleteFromTierInternal($key, $tier);

            // if it was successful
            if ($success) {
                self::$_last_used_tier = $tier;
                Logger::debug("Cache Deleted", ['tier' => $tier,'key' => $key]);
            } else {
                Logger::error("Failed to delete cache item", ['tier' => $tier,'key' => $key]);
            }

            // return if it was successful or not
            return $success;
        }

        /**
         * Store an item in multiple specific tiers
         *
         * Attempts to store data in the specified tiers only, providing detailed
         * results for each tier attempted. Useful for selective tier management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The unique identifier for the cache item
         * @param mixed $data The data to store in cache
         * @param int $ttl Time to live in seconds
         * @param array $tiers Array of tier names to store the item in
         * @return array Returns detailed results for each tier plus summary statistics
         */
        public static function setToTiers(string $key, mixed $data, int $ttl, array $tiers): array
        {

            // make sure we're initialized
            self::ensureInitialized();

            // if we have no data, return an empty array
            if (empty($data)) {
                Logger::warning("Attempted to cache empty data to multiple tiers", ['tiers' => $tiers,'key' => $key]);
                return [];
            }

            // setup the results and success count
            $results = [];
            $success_count = 0;

            // loop over the tiers specified
            foreach ($tiers as $tier) {
                // if the tier is valid
                if (! CacheTierManager::isTierValid($tier)) {
                    $results[$tier] = ['success' => false, 'error' => 'Invalid tier'];
                    continue;
                }

                // if the tier available?
                if (! CacheTierManager::isTierAvailable($tier)) {
                    $results[$tier] = ['success' => false, 'error' => 'Tier not available'];
                    continue;
                }

                // set the item to the tier
                $success = self::setToTierInternal($key, $data, $ttl, $tier);

                // setup the results
                $error_msg = $success ? null : Logger::getLastError();
                $results[$tier] = ['success' => $success, 'error' => $error_msg];

                // if it was successful
                if ($success) {
                    // increment the count
                    $success_count++;

                    // setup the last tier used
                    if (self::$_last_used_tier === null) {
                        self::$_last_used_tier = $tier;
                    }

                    Logger::debug("Cache Set", ['tier' => $tier,'key' => $key]);
                } else {
                    Logger::error("Failed to set cache item to tier in multi-tier operation", ['tier' => $tier,'key' => $key]);
                }
            }

            // setup the results
            $results['_summary'] = [
                'total_tiers' => count($tiers),
                'successful' => $success_count,
                'failed' => count($tiers) - $success_count
            ];

            // return the results
            return $results;
        }

        /**
         * Delete an item from multiple specific tiers
         *
         * Attempts to delete data from the specified tiers only, providing detailed
         * results for each tier attempted. Useful for selective cache invalidation.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The unique identifier for the cache item to delete
         * @param array $tiers Array of tier names to delete the item from
         * @return array Returns detailed results for each tier plus summary statistics
         */
        public static function deleteFromTiers(string $key, array $tiers): array
        {

            // make sure we're initialized
            self::ensureInitialized();

            // setup the results and count
            $results = [];
            $success_count = 0;

            // loop over each tier
            foreach ($tiers as $tier) {
                // is the tier valid?
                if (! CacheTierManager::isTierValid($tier)) {
                    $results[$tier] = ['success' => false, 'error' => 'Invalid tier'];
                    continue;
                }

                // is the tier available?
                if (! CacheTierManager::isTierAvailable($tier)) {
                    $results[$tier] = ['success' => false, 'error' => 'Tier not available'];
                    continue;
                }

                // delete from the tier
                $success = self::deleteFromTierInternal($key, $tier);

                // throw the results in the return array
                $error_msg = $success ? null : Logger::getLastError();
                $results[$tier] = ['success' => $success, 'error' => $error_msg];

                // if it was sucessful, increment the count
                if ($success) {
                    $success_count++;
                    Logger::debug("Cache Deleted", ['tier' => $tier,'key' => $key]);
                } else {
                    Logger::error("Failed to delete cache item from tier in multi-tier operation", ['tier' => $tier,'key' => $key]);
                }
            }

            // setup the results
            $results['_summary'] = [
                'total_tiers' => count($tiers),
                'successful' => $success_count,
                'failed' => count($tiers) - $success_count
            ];

            // return the results
            return $results;
        }

        /**
         * Validate if a tier name is valid
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier name to validate
         * @return bool Returns true if the tier name is valid, false otherwise
         */
        public static function isTierValid(string $tier): bool
        {
            return CacheTierManager::isTierValid($tier);
        }

        /**
         * Check if a tier is available for use
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier name to check availability for
         * @return bool Returns true if the tier is available, false otherwise
         */
        public static function isTierAvailable(string $tier): bool
        {
            return CacheTierManager::isTierAvailable($tier);
        }

        /**
         * Check if a specific tier is healthy and functioning
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier name to check health for
         * @return bool Returns true if the tier is healthy, false otherwise
         */
        public static function isTierHealthy(string $tier): bool
        {

            // First check if tier is available
            if (! self::isTierAvailable($tier)) {
                return false;
            }

            // Try a simple test operation
            try {
                $test_key = '__health_check_' . $tier . '_' . uniqid();
                $test_value = 'healthy';

                // Try to set and get a test value
                if (self::setToTier($test_key, $test_value, 60, $tier)) {
                    $retrieved = self::getFromTier($test_key, $tier);
                    self::deleteFromTier($test_key, $tier);
                    return $retrieved === $test_value;
                }

                return false;
            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * Get list of all valid tier names
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of all valid tier names
         */
        public static function getValidTiers(): array
        {
            return CacheTierManager::getValidTiers();
        }

        /**
         * Get list of available (discovered) tiers
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of available tier names in priority order
         */
        public static function getAvailableTiers(): array
        {
            return CacheTierManager::getAvailableTiers();
        }

        /**
         * Get comprehensive status information for all tiers
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns associative array with tier status information
         */
        public static function getTierStatus(): array
        {
            return CacheTierManager::getTierStatus();
        }

        /**
         * Get the tier used for the last cache operation
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the last used tier name or null if none
         */
        public static function getLastUsedTier(): ?string
        {
            return self::$_last_used_tier;
        }

        /**
         * Get the last error message encountered
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the last error message or null if none
         */
        public static function getLastError(): ?string
        {
            return Logger::getLastError();
        }

        /**
         * Perform comprehensive cache system cleanup
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of expired items removed
         */
        public static function cleanup(): int
        {

            // cleanup expired items
            $count = self::cleanupExpired();

            // if connection pooling is enabled
            if (self::$_connection_pooling_enabled) {
                // cleanup the connection pools
                CacheConnectionPool::cleanup();
            }

            // return the count
            return $count;
        }

        /**
         * Close all connections and clean up system resources
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function close(): void
        {

            // if connection pooling is enabled
            if (self::$_connection_pooling_enabled) {
                // close all connection pools
                CacheConnectionPool::closeAll();
            }

            // Clean up tracking arrays
            self::$_shmop_segments = [];

            // log the close
            Logger::info("Cache system closed", ['system']);
        }

        /**
         * Get comprehensive cache system statistics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns comprehensive statistics array
         */
        public static function getStats(): array
        {

            // make sure we're initialized
            self::ensureInitialized();

            // default stats array
            $stats = [];

            // Get tier-specific stats using traits
            if (function_exists('opcache_get_status')) {
                // Get full OPcache stats
                $opcache_stats = opcache_get_status(true);

                if ($opcache_stats) {
                    // Get the base path of the current application
                    $app_base_path = defined('KPT_PATH') ? dirname(KPT_PATH) : getcwd();

                    // Filter scripts to only include those from current application
                    $filtered_scripts = [];
                    $app_memory_usage = 0;
                    $app_hits = 0;
                    $app_misses = 0;

                    if (isset($opcache_stats['scripts']) && is_array($opcache_stats['scripts'])) {
                        foreach ($opcache_stats['scripts'] as $script_path => $script_info) {
                            // Check if script belongs to current application
                            if (strpos($script_path, $app_base_path) === 0) {
                                $filtered_scripts[$script_path] = $script_info;

                                // Calculate app-specific stats
                                $app_memory_usage += $script_info['memory_consumption'] ?? 0;
                                $app_hits += $script_info['hits'] ?? 0;
                            }
                        }
                    }

                    // Build filtered OPcache stats
                    $stats[self::TIER_OPCACHE] = [
                        'opcache_enabled' => $opcache_stats['opcache_enabled'] ?? false,
                        'cache_full' => $opcache_stats['cache_full'] ?? false,
                        'restart_pending' => $opcache_stats['restart_pending'] ?? false,
                        'restart_in_progress' => $opcache_stats['restart_in_progress'] ?? false,
                        'app_scripts' => [
                            'count' => count($filtered_scripts),
                            'scripts' => $filtered_scripts,
                            'memory_usage' => $app_memory_usage,
                            'memory_usage_human' => KPT::format_bytes($app_memory_usage),
                            'total_hits' => $app_hits,
                            'base_path' => $app_base_path
                        ],
                        'memory_usage' => [
                            'used_memory' => $opcache_stats['memory_usage']['used_memory'] ?? 0,
                            'free_memory' => $opcache_stats['memory_usage']['free_memory'] ?? 0,
                            'wasted_memory' => $opcache_stats['memory_usage']['wasted_memory'] ?? 0,
                            'current_wasted_percentage' => $opcache_stats['memory_usage']['current_wasted_percentage'] ?? 0,
                        ],
                        'statistics' => [
                            'num_cached_scripts' => $opcache_stats['opcache_statistics']['num_cached_scripts'] ?? 0,
                            'num_cached_keys' => $opcache_stats['opcache_statistics']['num_cached_keys'] ?? 0,
                            'max_cached_keys' => $opcache_stats['opcache_statistics']['max_cached_keys'] ?? 0,
                            'hits' => $opcache_stats['opcache_statistics']['hits'] ?? 0,
                            'misses' => $opcache_stats['opcache_statistics']['misses'] ?? 0,
                            'opcache_hit_rate' => $opcache_stats['opcache_statistics']['opcache_hit_rate'] ?? 0,
                        ]
                    ];
                }
            }

            // get the shmop stats
            $stats[self::TIER_SHMOP] = [
                'segments_tracked' => count(self::$_shmop_segments)
            ];

            // if we have the apcu cache info function
            if (function_exists('apcu_cache_info')) {
                // Get basic APCu info without the full cache list
                $apcu_info = apcu_cache_info(false); // false parameter excludes cache list

                // Get our prefix for filtering
                $config = CacheConfig::get('apcu');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Count our prefixed entries if cache list is available
                $our_entries = 0;
                $our_size = 0;

                // Get full info with cache list to count our entries
                $full_info = apcu_cache_info(true);
                if (isset($full_info['cache_list'])) {
                    foreach ($full_info['cache_list'] as $entry) {
                        $key = $entry['info'] ?? $entry['key'] ?? '';
                        if (strpos($key, $prefix) === 0) {
                            $our_entries++;
                            $our_size += $entry['mem_size'] ?? 0;
                        }
                    }
                }

                $stats[self::TIER_APCU] = [
                    'num_slots' => $apcu_info['num_slots'] ?? 0,
                    'ttl' => $apcu_info['ttl'] ?? 0,
                    'num_hits' => $apcu_info['num_hits'] ?? 0,
                    'num_misses' => $apcu_info['num_misses'] ?? 0,
                    'num_inserts' => $apcu_info['num_inserts'] ?? 0,
                    'num_entries' => $apcu_info['num_entries'] ?? 0,
                    'expunges' => $apcu_info['expunges'] ?? 0,
                    'start_time' => $apcu_info['start_time'] ?? 0,
                    'mem_size' => $apcu_info['mem_size'] ?? 0,
                    'memory_type' => $apcu_info['memory_type'] ?? 'unknown',
                    'our_prefix' => $prefix,
                    'our_entries' => $our_entries,
                    'our_memory_usage' => $our_size,
                    'our_memory_usage_human' => KPT::format_bytes($our_size)
                ];
            }

            // if yac is loaded and has the info function
            if (extension_loaded('yac') && function_exists('yac_info')) {
                // get the yac stats
                $stats[self::TIER_YAC] = yac_info();

            // otherwise if yac is loaded
            } elseif (extension_loaded('yac')) {
                // just note that the extension is loaded
                $stats[self::TIER_YAC] = ['extension_loaded' => true];
            }

            // Add connection pool stats
            if (self::$_connection_pooling_enabled) {
                // get the pool stats
                $stats['connection_pools'] = CacheConnectionPool::getPoolStats();
            }

            // File cache stats
            $files = glob(self::$_fallback_path . '*');

            // set the file tier stats
            $stats[self::TIER_FILE] = [
                'file_count' => count($files),
                'total_size' => array_sum(array_map('filesize', $files)),
                'path' => self::$_fallback_path
            ];

            // Add manager statistics
            $stats['tier_manager'] = CacheTierManager::getDiscoveryInfo();
            $stats['key_manager'] = CacheKeyManager::getCacheStats();
            $stats['health_monitor'] = CacheHealthMonitor::getMonitoringStats();

            // return the stats
            return $stats;
        }

        /**
         * Check overall cache system health
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns health status for all tiers
         */
        public static function isHealthy(): array
        {
            return CacheHealthMonitor::checkAllTiers();
        }

        /**
         * Get current configuration settings for all cache tiers
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns complete configuration settings
         */
        public static function getSettings(): array
        {
            return CacheConfig::getAll();
        }

        /**
         * Set a custom cache path for file-based caching
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path The custom cache directory path
         * @return bool Returns true if path was set successfully, false otherwise
         */
        public static function setCachePath(string $path): bool
        {

            // Normalize the path (ensure it ends with a slash)
            $path = rtrim($path, '/') . '/';

            // Try to create the cache directory with proper permissions
            if (self::createCacheDirectory($path)) {
                self::$_configurable_cache_path = $path;

                // If we're already initialized, update the fallback path immediately
                if (self::$_initialized) {
                    self::$_fallback_path = $path;
                }

                // Update the global config so other tiers can access it
                CacheConfig::setGlobalPath($path);

                // Also update the file backend config to match
                CacheConfig::setBackendPath('file', $path);

                Logger::debug("Cache path updated", ['new_path' => $path]);
                return true;
            }

            Logger::error("Failed to set cache path", ['attempted_path' => $path]);
            return false;
        }

        /**
         * Get the current cache path being used
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Returns the current cache directory path
         */
        public static function getCachePath(): string
        {
            return self::$_fallback_path ?? sys_get_temp_dir() . '/kpt_cache/';
        }

        /**
         * Get comprehensive debug information
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns comprehensive debug information array
         */
        public static function debug(): array
        {

            // make sure we're initialized
            self::ensureInitialized();

            // build the debug info
            $debug_info = [
                'cache_system' => [
                    'initialized' => self::$_initialized,
                    'last_used_tier' => self::$_last_used_tier,
                    'connection_pooling_enabled' => self::$_connection_pooling_enabled,
                    'async_enabled' => self::$_async_enabled,
                    'cache_path' => self::$_fallback_path,
                ],
                'tier_manager' => CacheTierManager::getDiscoveryInfo(),
                'key_manager' => [
                    'cache_stats' => CacheKeyManager::getCacheStats(),
                    'global_namespace' => CacheKeyManager::getGlobalNamespace(),
                    'key_separator' => CacheKeyManager::getKeySeparator(),
                    'tier_limitations' => CacheKeyManager::getTierLimitations()
                ],
                'health_monitor' => [
                    'monitoring_stats' => CacheHealthMonitor::getMonitoringStats(),
                    'health_status' => CacheHealthMonitor::getHealthStatus()
                ],
                'system_info' => [
                    'temp_dir' => sys_get_temp_dir(),
                    'current_user' => get_current_user(),
                    'process_id' => getmypid(),
                    'umask' => sprintf('%04o', umask()),
                ]
            ];

            // return the debug info
            return $debug_info;
        }

        /**
         * Internal method to get data from a specific tier with connection pooling
         *
         * Handles the actual retrieval of data from cache tiers with support for
         * connection pooling on database-based tiers (Redis, Memcached).
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @param string $tier The tier to retrieve from
         * @return mixed Returns the cached data or false if not found
         */
        private static function getFromTierInternal(string $key, string $tier): mixed
        {

            // Generate the appropriate key for this tier
            $tier_key = CacheKeyManager::generateKey($key, $tier);

            // default results
            $result = false;

            // get the allowed backends and check if this one is indeed allowed
            $allowed_backends = CacheConfig::getAllowedBackends() ?? [];
            if (! in_array($tier, $allowed_backends)) {
                return false;
            }

            // try to get a result from a tier
            try {
                // match the tier
                $result = match ($tier) {
                    self::TIER_ARRAY => self::getFromArray($tier_key),
                    self::TIER_SHMOP => self::getFromShmop($key),
                    self::TIER_REDIS => self::getFromRedis($tier_key),
                    self::TIER_MEMCACHED => self::getFromMemcached($tier_key),
                    self::TIER_OPCACHE => self::getFromOPcache($tier_key),
                    self::TIER_APCU => self::getFromAPCu($tier_key),
                    self::TIER_YAC => self::getFromYac($tier_key),
                    self::TIER_SQLITE => self::getFromSQLite($tier_key),
                    self::TIER_FILE => self::getFromFile($tier_key),
                    default => false
                };

                // debug log
                Logger::debug('Cache Hit', ['tier' => $tier, 'key' => $key, 'tier_key' => $tier_key]);

            // whoopsie... log the error and return set the result to false
            } catch (\Exception $e) {
                Logger::error("Error getting from tier", [
                    'error' => $e -> getMessage(),
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key
                ]);
                $result = false;
            }

            // return the result
            return $result;
        }

        /**
         * Internal method to set data to a specific tier with connection pooling
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to store
         * @param int $ttl Time to live in seconds
         * @param string $tier The tier to store to
         * @return bool Returns true if successfully stored, false otherwise
         */
        private static function setToTierInternal(string $key, mixed $data, int $ttl, string $tier): bool
        {

            // Generate the appropriate key for this tier
            $tier_key = CacheKeyManager::generateKey($key, $tier);

            // default results
            $result = false;

            // get the allowed backends and check if this one is indeed allowed
            $allowed_backends = CacheConfig::getAllowedBackends() ?? [];
            if (! in_array($tier, $allowed_backends)) {
                return false;
            }

            // try to match the tier to the internal method
            try {
                // match the tier
                $result = match ($tier) {
                    self::TIER_ARRAY => self::setToArray($tier_key, $data, $ttl),
                    self::TIER_SHMOP => self::setToShmop($key, $data, $ttl),
                    self::TIER_REDIS => self::setToRedis($tier_key, $data, $ttl),
                    self::TIER_MEMCACHED => self::setToMemcached($tier_key, $data, $ttl),
                    self::TIER_OPCACHE => self::setToOPcache($tier_key, $data, $ttl),
                    self::TIER_APCU => self::setToAPCu($tier_key, $data, $ttl),
                    self::TIER_YAC => self::setToYac($tier_key, $data, $ttl),
                    self::TIER_SQLITE => self::setToSQLite($tier_key, $data, $ttl),
                    self::TIER_FILE => self::setToFile($tier_key, $data, $ttl),
                    default => false
                };

                // debug logging
                Logger::debug('Set to Tier', [
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key,
                    'ttl' => $ttl
                ]);

            // whoopsie... log the error set false
            } catch (Exception $e) {
                Logger::error("Error setting to tier {$tier}: " . $e -> getMessage(), [
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key,
                    'ttl' => $ttl
                ]);
                $result = false;
            }

            // return the result
            return $result;
        }

        /**
         * Internal method to delete data from a specific tier with connection pooling
         *
         * Handles the actual deletion of data from cache tiers with support for
         * connection pooling on database-based tiers (Redis, Memcached).
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to delete
         * @param string $tier The tier to delete from
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromTierInternal(string $key, string $tier): bool
        {

            // Generate the appropriate key for this tier
            $tier_key = CacheKeyManager::generateKey($key, $tier);

            // default results
            $result = false;

            // get the allowed backends and check if this one is indeed allowed
            $allowed_backends = CacheConfig::getAllowedBackends() ?? [];
            if (! in_array($tier, $allowed_backends)) {
                return false;
            }

            // try to match the tier to the internal method
            try {
                $result = match ($tier) {
                    self::TIER_ARRAY => self::deleteFromArray($tier_key),
                    self::TIER_REDIS => self::deleteFromRedis($tier_key),
                    self::TIER_MEMCACHED => self::deleteFromMemcached($tier_key),
                    self::TIER_OPCACHE => self::deleteFromOPcache($tier_key),
                    self::TIER_SHMOP => self::deleteFromShmop($key),
                    self::TIER_APCU => self::deleteFromAPCu($tier_key),
                    self::TIER_YAC => self::deleteFromYac($tier_key),
                    self::TIER_SQLITE => self::deleteFromSQLite($tier_key),
                    self::TIER_FILE => self::deleteFromFile($tier_key),
                    default => false
                };

            // debug log
                Logger::debug('Delete From Tier', ['tier' => $tier, 'key' => $key, 'tier_key' => $tier_key]);

            // whoopsie... log the error and set the result
            } catch (Exception $e) {
                Logger::error("Error deleting from tier", [
                    'error' => $e -> getMessage(),
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key
                ]);
                $result = false;
            }

            // return the result
            return $result;
        }

        /**
         * Promote cache item to higher priority tiers
         *
         * When an item is found in a lower-priority tier, this method automatically
         * copies it to all higher-priority tiers for faster future access.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to promote
         * @param mixed $data The cached data to promote
         * @param string $current_tier The tier where the data was found
         * @return void Returns nothing
         */
        private static function promoteToHigherTiers(string $key, mixed $data, string $current_tier): void
        {

            // get the available tiers
            $available_tiers = self::getAvailableTiers();

            // get the index of the current tier
            $current_index = array_search($current_tier, $available_tiers);

            // if we couldn't find the tier in the available list, just return
            if ($current_index === false) {
                return;
            }

            // Promote to all higher tiers (lower index = higher priority)
            for ($i = 0; $i < $current_index; $i++) {
                // try to set the item to the higher priority tier
                $promote_success = self::setToTierInternal($key, $data, 3600, $available_tiers[$i]);

                // if it was successful
                if ($promote_success) {
                    // log the promotion
                    Logger::debug("Cache Promoted", [
                        'from_tier' => $current_tier,
                        'to_tier' => $available_tiers[$i]
                    ]);
                }
            }
        }

        /**
         * Clear all data from a specific cache tier
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to clear
         * @return bool Returns true if successfully cleared, false otherwise
         */
        private static function clearTier(string $tier): bool
        {

            // default results
            $result = false;

            // get the allowed backends and check if this one is indeed allowed
            $allowed_backends = CacheConfig::getAllowedBackends();
            if ($allowed_backends !== null && ! in_array($tier, $allowed_backends)) {
                return false;
            }

            // try to match the tier to the internal method
            try {
                $result = match ($tier) {
                    self::TIER_ARRAY => self::clearArray(),
                    self::TIER_REDIS => self::clearRedis(),
                    self::TIER_MEMCACHED => self::clearMemcached(),
                    self::TIER_OPCACHE => self::clearOPcache(),
                    self::TIER_SHMOP => self::clearShmop(),
                    self::TIER_APCU => self::clearAPCu(),
                    self::TIER_YAC => self::clearYac(),
                    self::TIER_SQLITE => self::clearSQLite(),
                    self::TIER_FILE => self::clearFileCache(),
                    default => false
                };

            // debug log
                Logger::debug('Clear Tier', ['tier' => $tier,]);

            // whoopsie... log the error and set the result
            } catch (Exception $e) {
                Logger::error("Error deleting from tier", [
                    'error' => $e -> getMessage(),
                    'tier' => $tier,
                ]);
                $result = false;
            }

            // return the result
            return $result;
        }

        /**
         * Remove expired cache entries from all tiers
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of expired items removed
         */
        private static function cleanupExpired(): int
        {

            // default count
            $result = 0;

            // get all available tiers
            $available_tiers = self::getAvailableTiers();

            // loop over them
            foreach ($available_tiers as $tier) {
                // get the allowed backends and check if this one is indeed allowed
                $allowed_backends = CacheConfig::getAllowedBackends() ?? [];
                if (! in_array($tier, $allowed_backends)) {
                    continue;
                }

                // try to match the tier to the internal method
                try {
                    $result += match ($tier) {
                        self::TIER_ARRAY => self::cleanupArray(),
                        self::TIER_REDIS => self::cleanupRedis(),
                        self::TIER_MEMCACHED => self::cleanupMemcached(),
                        self::TIER_OPCACHE => self::cleanupOPcache(),
                        self::TIER_SHMOP => self::cleanupSHMOP(),
                        self::TIER_APCU => self::cleanupAPCu(),
                        self::TIER_YAC => self::cleanupYac(),
                        self::TIER_SQLITE => self::cleanupSQLite(),
                        self::TIER_FILE => self::cleanupFile(),
                        default => 0
                    };

                // debug log
                    Logger::debug('Cleanup Expired', ['tier' => $tier,]);

                // whoopsie... log the error and set the result
                } catch (Exception $e) {
                    Logger::error("Error cleaning from tier", [
                        'error' => $e -> getMessage(),
                        'tier' => $tier,
                    ]);
                    $result = 0;
                }
            }

            // log the completion
            Logger::info("Cleanup completed", ['expired_items_removed' => $result]);

            // return the count
            return $result;
        }
    }
}
