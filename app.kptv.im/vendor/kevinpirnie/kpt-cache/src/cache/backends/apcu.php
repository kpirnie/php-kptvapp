<?php

/**
 * KPT Cache - APCu Caching Trait
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheAPCU')) {

    /**
     * KPT Cache - APCu Caching Trait
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheAPCU
    {
        /**
         * Test the APCu connection
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns if the connection was successful or not
         */
        private static function testAPCuConnection(): bool
        {

            // get the allowed backends, if this one is NOT allowed, dump out of this functions
            $allowed_backends = CacheConfig::getAllowedBackends();
            if (! in_array('apcu', $allowed_backends)) {
                return false;
            }

            // try to check if its enabled
            try {
                // Check if APCu is not enabled and return false
                if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                    return false;
                }

                // Test with a simple store/fetch operation
                $test_key = 'kpt_apcu_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // Try to store and retrieve
                if (apcu_store($test_key, $test_value, 60)) {
                    // get the item
                    $retrieved = apcu_fetch($test_key);

                    // delete it
                    apcu_delete($test_key);

                    // return the retreival test
                    return $retrieved === $test_value;
                }

                // default return
                return false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get item from APCu
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to retrieve
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromAPCu(string $_key): mixed
        {

            // If APCu is not enabled, just return false
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return false;
            }

            // try to get the item
            try {
                // Setup the prefixed key
                $config = CacheConfig::get('apcu');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // Fetch the value
                $success = false;
                $value = apcu_fetch($prefixed_key, $success);

                // If successful, return the value
                if ($success) {
                    return $value;
                }

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu get error: " . $e -> getMessage();
            }

            // default return
            return false;
        }

        /**
         * Set item to APCu
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to set
         * @param mixed $_data The data to cache
         * @param int $_length The cache duration in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToAPCu(string $_key, mixed $_data, int $_length): bool
        {

            // If APCu is not enabled, just return false
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return false;
            }

            // try to set the item
            try {
                // setup the config and prefixed key
                $config = CacheConfig::get('apcu');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // store and return the result
                return apcu_store($prefixed_key, $_data, $_length);

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu set error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Delete item from APCu
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to delete
         * @return bool Returns true if successful, false otherwise
         */
        private static function deleteFromAPCu(string $_key): bool
        {

            // If APCu is not enabled, consider it deleted
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return true;
            }

            // try to delete the item
            try {
                // setup the config and prefixed key
                $config = CacheConfig::get('apcu');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // delete and return the result
                return apcu_delete($prefixed_key);

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu delete error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Clear APCu cache (with prefix filtering)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if successful, false otherwise
         */
        public static function clearAPCu(): bool
        {

            // If APCu is not enabled, consider it cleared
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return true;
            }

            // try to clear the cache
            try {
                // get the config and prefix
                $config = CacheConfig::get('apcu');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Get cache info to iterate through keys
                if (function_exists('apcu_cache_info')) {
                    // get the cache info
                    $cache_info = apcu_cache_info();

                    // check if we have a cache list
                    if (isset($cache_info['cache_list'])) {
                        // setup the deleted counter
                        $deleted = 0;

                        // loop through each entry
                        foreach ($cache_info['cache_list'] as $entry) {
                            // get the key
                            $key = $entry['info'] ?? $entry['key'] ?? '';

                            // Only delete keys with our prefix
                            if (strpos($key, $prefix) === 0) {
                                // delete the key and increment the counter
                                if (apcu_delete($key)) {
                                    $deleted++;
                                }
                            }
                        }

                        // return if we deleted anything
                        return $deleted > 0;
                    }
                }

                // Fallback to clearing entire cache if we can't filter by prefix
                return function_exists('apcu_clear_cache') ? apcu_clear_cache() : false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu clear error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get APCu statistics
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of APCu statistics or error information
         */
        private static function getAPCuStats(): array
        {

            // If APCu is not enabled, return error
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return [ 'error' => 'APCu not available' ];
            }

            // try to get the stats
            try {
                // setup the stats array
                $stats = [ ];

                // Get basic cache info
                if (function_exists('apcu_cache_info')) {
                    $cache_info = apcu_cache_info();
                    $stats['cache_info'] = $cache_info;
                }

                // Get SMA (Shared Memory Allocation) info
                if (function_exists('apcu_sma_info')) {
                    $sma_info = apcu_sma_info();
                    $stats['sma_info'] = $sma_info;
                }

                // Add our prefix-specific stats
                $config = CacheConfig::get('apcu');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // setup counters
                $our_keys = 0;
                $our_size = 0;

                // check if we have cache list
                if (isset($cache_info['cache_list'])) {
                    // loop through each entry
                    foreach ($cache_info['cache_list'] as $entry) {
                        // get the key
                        $key = $entry['info'] ?? $entry['key'] ?? '';

                        // check if it starts with our prefix
                        if (strpos($key, $prefix) === 0) {
                            $our_keys++;
                            $our_size += $entry['mem_size'] ?? 0;
                        }
                    }
                }

                // add our custom stats
                $stats['kpt_cache_stats'] = [
                    'prefix' => $prefix,
                    'our_keys' => $our_keys,
                    'our_memory_usage' => $our_size,
                    'our_memory_usage_human' => KPT::format_bytes($our_size)
                ];

                // return the stats
                return $stats;

            // whoopsie... return the error
            } catch (\Exception $e) {
                return [ 'error' => $e -> getMessage() ];
            }
        }

        /**
         * Check if specific key exists in APCu
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to check
         * @return bool Returns true if key exists, false otherwise
         */
        private static function apcuKeyExists(string $_key): bool
        {

            // If APCu is not enabled, return false
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return false;
            }

            // try to check if the key exists
            try {
                // setup the config and prefixed key
                $config = CacheConfig::get('apcu');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // check and return the result
                return apcu_exists($prefixed_key);

            // whoopsie... return false
            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * Get APCu key TTL (time to live)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to check TTL for
         * @return int Returns remaining TTL in seconds, -1 for permanent, -2 if not found
         */
        private static function getAPCuTTL(string $_key): int
        {

            // If APCu is not enabled, return -1
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return -1;
            }

            // try to get the TTL
            try {
                // setup the config and prefixed key
                $config = CacheConfig::get('apcu');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // APCu doesn't have a direct TTL function, so we need to check cache info
                if (function_exists('apcu_cache_info')) {
                    // get the cache info
                    $cache_info = apcu_cache_info();

                    // check if we have a cache list
                    if (isset($cache_info['cache_list'])) {
                        // loop through each entry
                        foreach ($cache_info['cache_list'] as $entry) {
                            // get the key
                            $key = $entry['info'] ?? $entry['key'] ?? '';

                            // check if this is our key
                            if ($key === $prefixed_key) {
                                // get the creation time and ttl
                                $creation_time = $entry['creation_time'] ?? 0;
                                $ttl = $entry['ttl'] ?? 0;

                                // if we have a ttl, calculate remaining time
                                if ($ttl > 0) {
                                    $expires_at = $creation_time + $ttl;
                                    $remaining = $expires_at - time();
                                    return max(0, $remaining);
                                }

                                // No TTL (permanent)
                                return -1;
                            }
                        }
                    }
                }

                // Key not found
                return -2;

            // whoopsie... return -1
            } catch (\Exception $e) {
                return -1;
            }
        }

        /**
         * Increment APCu value (atomic operation)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to increment
         * @param int $step The step to increment by
         * @return int|bool Returns new value on success, false on failure
         */
        public static function apcuIncrement(string $_key, int $step = 1): int|bool
        {

            // If APCu is not enabled, return false
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return false;
            }

            // try to increment the value
            try {
                // setup the config and prefixed key
                $config = CacheConfig::get('apcu');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // increment and return the result
                return apcu_inc($prefixed_key, $step);

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu increment error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Decrement APCu value (atomic operation)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to decrement
         * @param int $step The step to decrement by
         * @return int|bool Returns new value on success, false on failure
         */
        public static function apcuDecrement(string $_key, int $step = 1): int|bool
        {

            // If APCu is not enabled, return false
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return false;
            }

            // try to decrement the value
            try {
                // setup the config and prefixed key
                $config = CacheConfig::get('apcu');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // decrement and return the result
                return apcu_dec($prefixed_key, $step);

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu decrement error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * APCu compare and swap (atomic operation)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to perform CAS on
         * @param mixed $old_value The expected old value
         * @param mixed $new_value The new value to set
         * @return bool Returns true if successful, false otherwise
         */
        public static function apcuCAS(string $_key, mixed $old_value, mixed $new_value): bool
        {

            // If APCu is not enabled, return false
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return false;
            }

            // try to perform the compare and swap
            try {
                // setup the config and prefixed key
                $config = CacheConfig::get('apcu');
                $prefixed_key = ( $config['prefix'] ?? CacheConfig::getGlobalPrefix() ) . $_key;

                // perform CAS and return the result
                return apcu_cas($prefixed_key, $old_value, $new_value);

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu CAS error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get multiple keys from APCu at once
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to retrieve
         * @return array Returns array of key-value pairs
         */
        public static function apcuMultiGet(array $keys): array
        {

            // If APCu is not enabled, return empty array
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return [ ];
            }

            // try to get multiple keys
            try {
                // setup the config and prefix
                $config = CacheConfig::get('apcu');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Prefix all keys
                $prefixed_keys = array_map(function ($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);

                // Fetch all at once
                $results = apcu_fetch($prefixed_keys);

                // check if we got results
                if (! is_array($results)) {
                    return [ ];
                }

                // Remove prefix from results
                $clean_results = [ ];
                foreach ($results as $prefixed_key => $value) {
                    $original_key = substr($prefixed_key, strlen($prefix));
                    $clean_results[$original_key] = $value;
                }

                // return the clean results
                return $clean_results;

            // whoopsie... setup the error and return empty array
            } catch (\Exception $e) {
                self::$_last_error = "APCu multi-get error: " . $e -> getMessage();
                return [ ];
            }
        }

        /**
         * Set multiple keys in APCu at once
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $items Array of key-value pairs to set
         * @param int $ttl Cache duration in seconds
         * @return bool Returns true if all successful, false otherwise
         */
        public static function apcuMultiSet(array $items, int $ttl = 3600): bool
        {

            // If APCu is not enabled, return false
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return false;
            }

            // try to set multiple keys
            try {
                // setup the config and prefix
                $config = CacheConfig::get('apcu');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Prefix all keys
                $prefixed_items = [ ];
                foreach ($items as $key => $value) {
                    $prefixed_items[$prefix . $key] = $value;
                }

                // Store all at once
                $failed_keys = apcu_store($prefixed_items, null, $ttl);

                // Return true if no keys failed
                return empty($failed_keys);

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "APCu multi-set error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Delete multiple keys from APCu at once
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to delete
         * @return array Returns array of keys that failed to delete
         */
        public static function apcuMultiDelete(array $keys): array
        {

            // If APCu is not enabled, return empty array
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return [ ];
            }

            // try to delete multiple keys
            try {
                // setup the config and prefix
                $config = CacheConfig::get('apcu');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Prefix all keys
                $prefixed_keys = array_map(function ($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);

                // Delete all at once
                $result = apcu_delete($prefixed_keys);

                // check if we got an array result
                if (is_array($result)) {
                    // Remove prefix from failed keys
                    $failed_keys = [ ];
                    foreach ($result as $prefixed_key) {
                        $failed_keys[ ] = substr($prefixed_key, strlen($prefix));
                    }
                    return $failed_keys;
                }

                // If result is boolean, return empty array on success
                return $result ? [ ] : $keys;

            // whoopsie... setup the error and return all keys as failed
            } catch (\Exception $e) {
                self::$_last_error = "APCu multi-delete error: " . $e -> getMessage();
                return $keys;
            }
        }

        /**
         * Get list of APCu keys with our prefix
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of key information for our prefix
         */
        public static function getAPCuKeys(): array
        {

            // If APCu is not enabled, return empty array
            if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
                return [ ];
            }

            // try to get our keys
            try {
                // setup the config and prefix
                $config = CacheConfig::get('apcu');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();
                $our_keys = [ ];

                // check if we have cache info function
                if (function_exists('apcu_cache_info')) {
                    // get the cache info
                    $cache_info = apcu_cache_info();

                    // check if we have a cache list
                    if (isset($cache_info['cache_list'])) {
                        // loop through each entry
                        foreach ($cache_info['cache_list'] as $entry) {
                            // get the key
                            $key = $entry['info'] ?? $entry['key'] ?? '';

                            // check if it starts with our prefix
                            if (strpos($key, $prefix) === 0) {
                                // add to our keys array
                                $our_keys[ ] = [
                                    'key' => substr($key, strlen($prefix)),
                                    'full_key' => $key,
                                    'creation_time' => $entry['creation_time'] ?? 0,
                                    'ttl' => $entry['ttl'] ?? 0,
                                    'access_time' => $entry['access_time'] ?? 0,
                                    'ref_count' => $entry['ref_count'] ?? 0,
                                    'mem_size' => $entry['mem_size'] ?? 0
                                ];
                            }
                        }
                    }
                }

                // return our keys
                return $our_keys;

            // whoopsie... return empty array
            } catch (\Exception $e) {
                return [ ];
            }
        }

        /**
         * Cleans up expires items from the cache
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of items removed
         */
        private static function cleanupAPCu(): int
        {

            // setup the count
            $count = 0;

            // If APCu is not enabled, return 0
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return $count;
            }

            try {
                // APCu handles expiration automatically on access
                // We can iterate through cache entries to force cleanup
                if (function_exists('apcu_cache_info')) {
                    $cache_info = apcu_cache_info();

                    if (isset($cache_info['cache_list'])) {
                        $current_time = time();
                        $config = CacheConfig::get('apcu');
                        $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                        foreach ($cache_info['cache_list'] as $entry) {
                            $key = $entry['info'] ?? $entry['key'] ?? '';

                            // Only process our prefixed keys
                            if (strpos($key, $prefix) === 0) {
                                $creation_time = $entry['creation_time'] ?? 0;
                                $ttl = $entry['ttl'] ?? 0;

                                // Check if expired
                                if ($ttl > 0 && ($creation_time + $ttl) < $current_time) {
                                    if (apcu_delete($key)) {
                                        $count++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Silent fail
            }

            // return the count
            return $count;
        }
    }
}
