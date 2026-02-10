<?php

/**
 * KPT Cache Key Manager - Cache Key Generation and Management
 *
 * Centralized key management system that handles key generation, validation,
 * prefixing, namespacing, and tier-specific key formatting for all cache tiers.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the class doesn't exist
if (! class_exists('CacheKeyManager')) {

    /**
     * KPT Cache Key Manager
     *
     * Handles all aspects of cache key management including generation, validation,
     * prefixing, namespacing, and tier-specific formatting. Ensures consistent
     * key handling across all cache tiers while respecting tier-specific limitations.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class CacheKeyManager
    {
        // class constants
        const TIER_ARRAY = 'array';
        const TIER_OPCACHE = 'opcache';
        const TIER_SHMOP = 'shmop';
        const TIER_APCU = 'apcu';
        const TIER_YAC = 'yac';
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_FILE = 'file';
        const TIER_SQLITE = 'sqlite';

        // tier key limits
        private static array $_tier_key_limits = [
            self::TIER_ARRAY => 1024,       // Reasonable limit for array keys
            self::TIER_OPCACHE => 255,      // File path limitations
            self::TIER_SHMOP => 32,         // System-dependent, conservative limit
            self::TIER_APCU => 255,         // APCu limitation
            self::TIER_YAC => 48,           // YAC limitation
            self::TIER_REDIS => 512000000,  // Very large, practically unlimited
            self::TIER_MEMCACHED => 250,    // Memcached limitation
            self::TIER_SQLITE => 1024,      // SQLite TEXT field, generous limit
            self::TIER_FILE => 255          // File system limitations
        ];

        // tier key allowed characters
        private static array $_tier_forbidden_chars = [
            self::TIER_ARRAY => [],
            self::TIER_OPCACHE => ['/', '\\', ':', '*', '?', '"', '<', '>', '|'],
            self::TIER_SHMOP => [],         // Binary safe
            self::TIER_APCU => ["\0"],      // Null bytes not allowed
            self::TIER_YAC => ["\0"],       // Null bytes not allowed
            self::TIER_REDIS => [],         // Binary safe
            self::TIER_MEMCACHED => [' ', "\t", "\r", "\n", "\0"],
            self::TIER_SQLITE => ["\0"],    // Null bytes not allowed
            self::TIER_FILE => ['/', '\\', ':', '*', '?', '"', '<', '>', '|']
        ];

        // class properties
        private static ?string $_global_namespace = null;
        private static string $_key_separator = ':';
        private static bool $_auto_hash_long_keys = true;
        private static string $_hash_algorithm = 'md5';
        private static array $_key_cache = [];
        private static int $_max_cached_keys = 1000;
        private static ?string $_last_error = null;

        /**
         * Generate a cache key for a specific tier
         *
         * Creates a properly formatted cache key for the specified tier, taking into
         * account tier-specific limitations, prefixes, and formatting requirements.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $raw_key The raw key provided by the user
         * @param string $tier The cache tier the key will be used for
         * @param string|null $namespace Optional namespace for the key
         * @return string Returns the formatted cache key
         */
        public static function generateKey(string $raw_key, string $tier, ?string $namespace = null): string
        {

            // Clear any previous errors
            self::$_last_error = null;

            // Check cache first
            $cache_key = md5($raw_key . $tier . ( $namespace ?? '' ) . ( self::$_global_namespace ?? '' ));
            if (isset(self::$_key_cache[$cache_key])) {
                return self::$_key_cache[$cache_key];
            }

            // Build the key components
            $key_parts = [];

            // Add global namespace if set
            if (self::$_global_namespace !== null) {
                $key_parts[] = self::$_global_namespace;
            }

            // Add specific namespace if provided
            if ($namespace !== null) {
                $key_parts[] = $namespace;
            }

            // Add tier-specific prefix if configured
            $config = CacheConfig::get($tier);
            if (isset($config['prefix']) && $config['prefix'] !== '') {
                $key_parts[] = rtrim($config['prefix'], self::$_key_separator);
            }

            // Add the raw key
            $key_parts[] = $raw_key;

            // Join parts with separator
            $full_key = implode(self::$_key_separator, array_filter($key_parts));

            // Sanitize for the specific tier
            $sanitized_key = self::sanitizeKeyForTier($full_key, $tier);

            // Handle length limitations
            $final_key = self::handleKeyLength($sanitized_key, $tier);

            // Cache the result
            self::cacheKey($cache_key, $final_key);

            // debug logging
            Logger::debug('Cache Generated Key', []);

            // return the final key
            return $final_key;
        }

        /**
         * Generate tier-specific keys for special requirements
         *
         * Handles special key generation for tiers that have unique requirements
         * like SHMOP numeric keys
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $raw_key The raw key provided by the user
         * @param string $tier The cache tier requiring special key format
         * @return string|int Returns the specially formatted key
         */
        public static function generateSpecialKey(string $raw_key, string $tier): string|int
        {

            // use match to generate special keys based on tier
            return match ($tier) {
                self::TIER_SHMOP => self::generateShmopKey($raw_key),
                self::TIER_OPCACHE => self::generateOPcacheKey($raw_key),
                self::TIER_FILE => self::generateFileKey($raw_key),
                default => self::generateKey($raw_key, $tier)
            };
        }

        /**
         * Generate a batch of keys for multiple tiers
         *
         * Efficiently generates keys for multiple tiers at once, useful for
         * multi-tier cache operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $raw_key The raw key provided by the user
         * @param array $tiers Array of tier names to generate keys for
         * @param string|null $namespace Optional namespace for all keys
         * @return array Returns associative array of tier => generated_key
         */
        public static function generateKeysForTiers(string $raw_key, array $tiers, ?string $namespace = null): array
        {

            // initialize the keys array
            $keys = [];

            // loop through each tier and generate a key
            foreach ($tiers as $tier) {
                $keys[$tier] = self::generateKey($raw_key, $tier, $namespace);
            }

            // debug logging
            Logger::debug('Cache Generated Tier Keys', ['tiers' => $tiers]);

            // return the keys
            return $keys;
        }

        /**
         * Generate SHMOP-compatible numeric key
         *
         * Creates a numeric key suitable for SHMOP shared memory operations
         * using ftok() or hash-based numeric generation.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $raw_key The raw key to convert
         * @return int Returns numeric SHMOP key
         */
        private static function generateShmopKey(string $raw_key): int
        {

            // Get shmop configuration
            $config = CacheConfig::get('shmop');
            $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();
            $base_key = $config['base_key'] ?? 0x12345000;

            // Create deterministic key using consistent hashing
            $full_key = $prefix . $raw_key;

            // Use CRC32 for consistent numeric generation
            $hash = crc32($full_key);

            // Ensure it's positive and within reasonable range
            $shmop_key = $base_key + abs($hash % 100000);

            return $shmop_key;
        }

        /**
         * Generate OPcache-compatible key
         *
         * Creates a key suitable for OPcache operations, typically a file path.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $raw_key The raw key to convert
         * @return string Returns OPcache-compatible key
         */
        private static function generateOPcacheKey(string $raw_key): string
        {

            // get the opcache configuration
            $config = CacheConfig::get(self::TIER_OPCACHE);
            $prefix = $config['prefix'] ?? 'KPT_OPCACHE_';

            // return the prefixed key
            return $prefix . md5($raw_key);
        }

        /**
         * Generate file cache key (filename)
         *
         * Creates a safe filename for file-based cache operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $raw_key The raw key to convert
         * @return string Returns safe filename
         */
        private static function generateFileKey(string $raw_key): string
        {

            // Always use MD5 hash for file names to ensure filesystem compatibility
            return md5($raw_key);
        }

        /**
         * Validate a key for a specific tier
         *
         * Checks if a key meets the requirements for the specified cache tier
         * including length limits and character restrictions.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The key to validate
         * @param string $tier The target cache tier
         * @return bool Returns true if key is valid for the tier
         */
        public static function validateKey(string $key, string $tier): bool
        {

            // Check if tier is supported
            if (! isset(self::$_tier_key_limits[$tier])) {
                self::$_last_error = "Unsupported tier: {$tier}";
                return false;
            }

            // Check length
            $max_length = self::$_tier_key_limits[$tier];
            if (strlen($key) > $max_length) {
                self::$_last_error = "Key too long for tier {$tier}. Max: {$max_length}, Got: " . strlen($key);
                return false;
            }

            // Check forbidden characters
            $forbidden_chars = self::$_tier_forbidden_chars[$tier] ?? [];
            foreach ($forbidden_chars as $char) {
                if (strpos($key, $char) !== false) {
                    self::$_last_error = "Key contains forbidden character '{$char}' for tier {$tier}";
                    return false;
                }
            }

            // Additional tier-specific validation
            switch ($tier) {
                case self::TIER_SHMOP:
                    // SHMOP keys should be handled as numeric in generateSpecialKey
                    break;

                case self::TIER_MEMCACHED:
                    // Memcached has additional restrictions
                    if (strlen($key) == 0) {
                        self::$_last_error = "Empty keys not allowed for Memcached";
                        return false;
                    }
                    break;

                case self::TIER_FILE:
                    // Additional file system checks could go here
                    break;
            }

            // validation passed
            return true;
        }

        /**
         * Sanitize a key for a specific tier
         *
         * Removes or replaces characters that are not allowed in the specified
         * cache tier while preserving key uniqueness.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The key to sanitize
         * @param string $tier The target cache tier
         * @return string Returns sanitized key
         */
        private static function sanitizeKeyForTier(string $key, string $tier): string
        {

            // get forbidden characters for the tier
            $forbidden_chars = self::$_tier_forbidden_chars[$tier] ?? [];

            // if no forbidden characters, return key as-is
            if (empty($forbidden_chars)) {
                return $key; // Tier allows all characters
            }

            // Replace forbidden characters with safe alternatives
            $replacements = [
                '/' => '_slash_',
                '\\' => '_bslash_',
                ':' => '_colon_',
                '*' => '_star_',
                '?' => '_question_',
                '"' => '_quote_',
                '<' => '_lt_',
                '>' => '_gt_',
                '|' => '_pipe_',
                ' ' => '_space_',
                "\t" => '_tab_',
                "\r" => '_cr_',
                "\n" => '_nl_',
                "\0" => '_null_'
            ];

            // start with the original key
            $sanitized = $key;

            // replace forbidden characters
            foreach ($forbidden_chars as $char) {
                if (isset($replacements[$char])) {
                    $sanitized = str_replace($char, $replacements[$char], $sanitized);
                } else {
                    // Remove character if no replacement defined
                    $sanitized = str_replace($char, '', $sanitized);
                }
            }

            // return the sanitized key
            return $sanitized;
        }

        /**
         * Handle key length limitations for a tier
         *
         * Automatically shortens keys that exceed tier limits using hashing
         * while preserving uniqueness.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The key to check and potentially shorten
         * @param string $tier The target cache tier
         * @return string Returns key within tier length limits
         */
        private static function handleKeyLength(string $key, string $tier): string
        {

            // get the maximum length for the tier
            $max_length = self::$_tier_key_limits[$tier] ?? 255;

            // if key is within limits, return as-is
            if (strlen($key) <= $max_length) {
                return $key;
            }

            // if auto-hashing is disabled, truncate
            if (! self::$_auto_hash_long_keys) {
                // Truncate if auto-hashing is disabled
                return substr($key, 0, $max_length);
            }

            // Use hashing to shorten while preserving uniqueness
            $hash = hash(self::$_hash_algorithm, $key);
            $hash_length = strlen($hash);

            // if hash fits within limits, use it
            if ($hash_length <= $max_length) {
                return $hash;
            }

            // If even the hash is too long, truncate it
            return substr($hash, 0, $max_length);
        }

        /**
         * Set global namespace for all cache keys
         *
         * Sets a global namespace that will be prepended to all generated keys.
         * Useful for multi-tenant applications or environment separation.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string|null $namespace The global namespace to set (null to clear)
         * @return void
         */
        public static function setGlobalNamespace(?string $namespace): void
        {

            self::$_global_namespace = $namespace;
            self::clearKeyCache(); // Clear cache when namespace changes
        }

        /**
         * Get the current global namespace
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the current global namespace or null if none set
         */
        public static function getGlobalNamespace(): ?string
        {

            return self::$_global_namespace;
        }

        /**
         * Create a namespaced key without changing global namespace
         *
         * Generates a key with a specific namespace without affecting the global
         * namespace setting.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $raw_key The raw key
         * @param string $namespace The namespace for this key
         * @param string $tier The target tier
         * @return string Returns the namespaced key
         */
        public static function createNamespacedKey(string $raw_key, string $namespace, string $tier): string
        {

            return self::generateKey($raw_key, $tier, $namespace);
        }

        /**
         * Set the key separator character
         *
         * Changes the character used to separate key components.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $separator The separator character to use
         * @return void
         */
        public static function setKeySeparator(string $separator): void
        {

            self::$_key_separator = $separator;
            self::clearKeyCache();
        }

        /**
         * Get the current key separator
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Returns the current key separator
         */
        public static function getKeySeparator(): string
        {

            return self::$_key_separator;
        }

        /**
         * Set whether to automatically hash long keys
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param bool $enabled Whether to enable auto-hashing
         * @return void
         */
        public static function setAutoHashLongKeys(bool $enabled): void
        {

            self::$_auto_hash_long_keys = $enabled;
        }

        /**
         * Set the hash algorithm for key hashing
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $algorithm The hash algorithm to use
         * @return bool Returns true if algorithm is supported, false otherwise
         */
        public static function setHashAlgorithm(string $algorithm): bool
        {

            // check if algorithm is supported
            if (! in_array($algorithm, hash_algos())) {
                self::$_last_error = "Unsupported hash algorithm: {$algorithm}";
                return false;
            }

            // set the algorithm and clear cache
            self::$_hash_algorithm = $algorithm;
            self::clearKeyCache();

            // return success
            return true;
        }

        /**
         * Get information about a generated key
         *
         * Provides detailed information about how a key was generated and
         * its properties for debugging purposes.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $raw_key The original raw key
         * @param string $tier The target tier
         * @param string|null $namespace Optional namespace
         * @return array Returns detailed key information
         */
        public static function analyzeKey(string $raw_key, string $tier, ?string $namespace = null): array
        {

            // generate the key
            $generated_key = self::generateKey($raw_key, $tier, $namespace);

            // return detailed analysis
            return [
                'raw_key' => $raw_key,
                'generated_key' => $generated_key,
                'tier' => $tier,
                'namespace' => $namespace,
                'global_namespace' => self::$_global_namespace,
                'key_length' => strlen($generated_key),
                'max_length_for_tier' => self::$_tier_key_limits[$tier] ?? 'unlimited',
                'is_valid' => self::validateKey($generated_key, $tier),
                'was_hashed' => strlen($generated_key) !== strlen($raw_key),
                'separator_used' => self::$_key_separator,
                'forbidden_chars_for_tier' => self::$_tier_forbidden_chars[$tier] ?? []
            ];
        }

        /**
         * Get tier limitations information
         *
         * Returns information about key limitations for all tiers.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns tier limitations data
         */
        public static function getTierLimitations(): array
        {

            return [
                'key_limits' => self::$_tier_key_limits,
                'forbidden_chars' => self::$_tier_forbidden_chars
            ];
        }

        /**
         * Get key cache statistics
         *
         * Returns information about the internal key cache.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns cache statistics
         */
        public static function getCacheStats(): array
        {

            return [
                'cached_keys' => count(self::$_key_cache),
                'max_cached_keys' => self::$_max_cached_keys,
                'cache_utilization' => round(count(self::$_key_cache) / self::$_max_cached_keys * 100, 2)
            ];
        }

        /**
         * Clear the internal key cache
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        public static function clearKeyCache(): void
        {

            self::$_key_cache = [];
        }

        /**
         * Get the last error message
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the last error message or null if none
         */
        public static function getLastError(): ?string
        {
            Logger::error("Cache Key Error", [ 'error' => self::$_last_error ]);
            return self::$_last_error;
        }

        /**
         * Cache a generated key to avoid regeneration
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $cache_key The cache key identifier
         * @param string $generated_key The generated key to cache
         * @return void
         */
        private static function cacheKey(string $cache_key, string $generated_key): void
        {

            // Implement LRU-style cache management
            if (count(self::$_key_cache) >= self::$_max_cached_keys) {
                // Remove oldest entries (simple FIFO for now)
                $keys_to_remove = array_slice(array_keys(self::$_key_cache), 0, 100);
                foreach ($keys_to_remove as $old_key) {
                    unset(self::$_key_cache[$old_key]);
                }
            }

            // cache the key
            self::$_key_cache[$cache_key] = $generated_key;
        }
    }
}
