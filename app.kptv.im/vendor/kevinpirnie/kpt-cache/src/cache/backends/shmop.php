<?php

/**
 * KPT Cache - SHMOP Caching Trait
 * Shared memory segment caching implementation
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheSHMOP')) {

    /**
     * KPT Cache SHMOP Trait
     *
     * Provides shared memory segment caching functionality using SHMOP
     * for ultra-fast in-memory caching with inter-process communication.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheSHMOP
    {
        /**
         * Test if shmop shared memory operations are working
         *
         * Performs a comprehensive test of SHMOP shared memory operations
         * to ensure the system supports and can perform shared memory caching.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if SHMOP operations work, false otherwise
         */
        private static function testShmopConnection(): bool
        {

            // try to test shmop functionality
            try {
                // get shmop configuration
                $config = CacheConfig::get('shmop');

                // Generate a test key
                $test_key = CacheKeyManager::generateSpecialKey('test_' . time(), 'shmop');
                $test_data = 'test_' . time();
                $serialized_data = serialize([
                    'expires' => time() + 60,
                    'data' => $test_data
                ]);
                $data_size = strlen($serialized_data);

                // Try to create a shared memory segment
                $segment = @shmop_open($test_key, 'c', 0644, max($data_size, 1024));

                // check if segment creation failed
                if ($segment === false) {
                    return false;
                }

                // Test write operation
                $written = @shmop_write($segment, str_pad($serialized_data, 1024, "\0"), 0);

                // check if write failed
                if ($written === false) {
                    @shmop_close($segment);
                    @shmop_delete($segment);
                    return false;
                }

                // Test read operation
                $read_data = @shmop_read($segment, 0, 1024);

                // Clean up
                @shmop_close($segment);
                @shmop_delete($segment);

                // check if read failed
                if ($read_data === false) {
                    return false;
                }

                // Verify data integrity
                $unserialized = @unserialize(trim($read_data, "\0"));
                return is_array($unserialized)
                    && isset($unserialized['data'])
                    && $unserialized['data'] === $test_data;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "SHMOP test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get item from shmop shared memory
         *
         * Retrieves a cached item from shared memory using SHMOP
         * with proper expiration checking and cleanup.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found/expired
         */
        private static function getFromShmop(string $key): mixed
        {

            // If shmop functions don't exist, just return false
            if (! function_exists('shmop_open')) {
                return false;
            }

            // try to get item from shmop
            try {
                // Generate the shmop key
                $shmop_key = CacheKeyManager::generateSpecialKey($key, 'shmop');

                // Try to open the shared memory segment
                $segment = @shmop_open($shmop_key, 'a', 0, 0);

                // check if segment doesn't exist
                if ($segment === false) {
                    return false;
                }

                // Get the size of the segment
                $size = shmop_size($segment);

                // check if segment is empty
                if ($size === 0) {
                    @shmop_close($segment);
                    return false;
                }

                // Read the data
                $data = shmop_read($segment, 0, $size);
                @shmop_close($segment);

                // check if read failed
                if ($data === false) {
                    return false;
                }

                // Unserialize and check expiration
                $unserialized = @unserialize(trim($data, "\0"));

                // check if we have valid cached data
                if (is_array($unserialized) && isset($unserialized['expires'], $unserialized['data'])) {
                    // Check if expired
                    if ($unserialized['expires'] > time()) {
                        return $unserialized['data'];
                    } else {
                        // Expired, delete the segment
                        self::deleteFromTierInternal($key, self::TIER_SHMOP);
                    }
                }

            // whoopsie... setup the error
            } catch (\Exception $e) {
                self::$_last_error = "SHMOP get error: " . $e -> getMessage();
            }

            // return false if not found or error
            return false;
        }

        /**
         * Set item to shmop shared memory
         *
         * Stores an item in shared memory using SHMOP with expiration
         * timestamp and proper segment management.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToShmop(string $key, mixed $data, int $ttl): bool
        {

            // check if shmop functions exist
            if (! function_exists('shmop_open')) {
                return false;
            }

            // try to set item to shmop
            try {
                // get shmop configuration
                $config = CacheConfig::get('shmop');

                // Generate the shmop key
                $shmop_key = CacheKeyManager::generateSpecialKey($key, 'shmop');

                // Prepare data with expiration
                $cache_data = [
                    'expires' => time() + $ttl,
                    'data' => $data
                ];

                // serialize the cache data
                $serialized_data = serialize($cache_data);
                $data_size = strlen($serialized_data);

                // Use configured segment size or data size, whichever is larger
                $segment_size = max($data_size + 100, $config['segment_size'] ?? 1048576);

                // Try to open existing segment first
                $segment = @shmop_open($shmop_key, 'w', 0, 0);

                // If doesn't exist, create new segment
                if ($segment === false) {
                    $segment = @shmop_open($shmop_key, 'c', 0644, $segment_size);
                }

                // check if segment creation/opening failed
                if ($segment === false) {
                    return false;
                }

                // Pad data to prevent issues with reading
                $padded_data = str_pad($serialized_data, $segment_size, "\0");

                // Write data
                $written = @shmop_write($segment, $padded_data, 0);
                @shmop_close($segment);

                // check if write was successful
                if ($written !== false) {
                    // Keep track of this segment for cleanup
                    self::$_shmop_segments[$key] = $shmop_key;
                    return true;
                }

            // whoopsie... setup the error
            } catch (\Exception $e) {
                self::$_last_error = "SHMOP set error: " . $e -> getMessage();
            }

            // return false on failure
            return false;
        }

        /**
         * Clears out the entire cache
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns success or not
         */
        public static function clearShmop(): bool
        {
            $success = true;

            // Method 1: Clear tracked segments
            foreach (self::$_shmop_segments as $cache_key => $shmop_key) {
                if (! self::deleteFromShmopInternal($shmop_key)) {
                    $success = false;
                    Logger::error("Failed to delete tracked SHMOP segment", [
                        'cache_key' => $cache_key,
                        'shmop_key' => $shmop_key
                    ]);
                } else {
                    Logger::debug("Successfully deleted tracked SHMOP segment", [
                        'cache_key' => $cache_key,
                        'shmop_key' => $shmop_key
                    ]);
                }
            }

            // Method 2: Try to clear segments based on prefix pattern
            // This attempts to clear any segments that might exist but aren't tracked
            $config = CacheConfig::get('shmop');
            $base_key = $config['base_key'] ?? 0x12345000;

            // Try to clear a reasonable range of possible keys
            for ($i = 0; $i < 1000; $i++) {
                $test_key = $base_key + $i;

                // Skip keys we already processed in the tracking loop
                if (! in_array($test_key, self::$_shmop_segments)) {
                    $segment = @shmop_open($test_key, 'w', 0, 0);
                    if ($segment !== false) {
                        @shmop_delete($segment);
                        @shmop_close($segment);
                        Logger::debug("Cleaned up untracked SHMOP segment", ['shmop_key' => $test_key]);
                    }
                }
            }

            // Clear the tracking array
            self::$_shmop_segments = [];

            return $success;
        }

        /**
         * Internal SHMOP delete operation
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param int $shmop_key The SHMOP key to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromShmop(string $key): bool
        {

            // if we don't have the function, just return true
            if (! function_exists('shmop_open')) {
                return true;
            }

            try {
                // Generate the shmop key from the cache key
                $shmop_key = CacheKeyManager::generateSpecialKey($key, 'shmop');

                // Call the internal delete method with the numeric key
                $result = self::deleteFromShmopInternal($shmop_key);

                // Remove from tracking array
                if (isset(self::$_shmop_segments[$key])) {
                    unset(self::$_shmop_segments[$key]);
                }

                return $result;
            } catch (\Exception $e) {
                Logger::error("SHMOP delete error", ['error' => $e->getMessage(), 'key' => $key]);
                return false;
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
        private static function cleanupSHMOP(): int
        {
            $count = 0;

            // Clean up tracked segments
            foreach (self::$_shmop_segments as $cache_key => $shmop_key) {
                try {
                    $segment = @shmop_open($shmop_key, 'a', 0, 0);
                    if ($segment !== false) {
                        $size = shmop_size($segment);
                        if ($size > 0) {
                            $data = shmop_read($segment, 0, $size);
                            $unserialized = @unserialize(trim($data, "\0"));

                            if (is_array($unserialized) && isset($unserialized['expires'])) {
                                if ($unserialized['expires'] <= time()) {
                                    @shmop_delete($segment);
                                    unset(self::$_shmop_segments[$cache_key]);
                                    $count++;
                                }
                            }
                        }
                        @shmop_close($segment);
                    } else {
                        // Segment doesn't exist, remove from tracking
                        unset(self::$_shmop_segments[$cache_key]);
                    }
                } catch (\Exception $e) {
                    unset(self::$_shmop_segments[$cache_key]);
                }
            }

            return $count;
        }

        /**
         * Internal SHMOP delete operation (using numeric key)
         * Called internally by clearShmop and deleteFromShmop
         */
        private static function deleteFromShmopInternal(int $shmop_key): bool
        {

            // if we don't have the function, just return true
            if (! function_exists('shmop_open')) {
                return true;
            }

            // try to delete the segment
            try {
                // open the segment
                $segment = @shmop_open($shmop_key, 'w', 0, 0);

                // if we have a segment
                if ($segment !== false) {
                    // delete it
                    $result = @shmop_delete($segment);
                    // close it
                    @shmop_close($segment);
                    // debug logging
                    Logger::debug('Delete from SHMOP', ['key' => $shmop_key]);
                    // return the result
                    return $result;
                }

            // whoopsie...
            } catch (\Exception $e) {
                // log the error
                Logger::error("SHMOP delete error", ['error' => $e->getMessage()]);
            }

            // default return
            return true;
        }
    }
}
