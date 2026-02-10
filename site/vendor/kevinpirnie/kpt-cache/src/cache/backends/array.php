<?php

/**
 * KPT Cache - PHP Array Caching Trait
 *
 * Provides the fastest possible cache tier using PHP's native arrays.
 * This tier exists only for the duration of the current request but offers
 * zero-latency access to cached data, making it perfect as a top-tier cache.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't exist first
if (! trait_exists('CacheArray')) {

    /**
     * KPT Cache Array Trait
     *
     * Implements ultra-fast request-level caching using PHP's native arrays.
     * This tier provides zero-latency cache operations but data only persists
     * for the current request lifecycle.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheArray
    {
        // trait properties
        private static array $_array_cache = [ ];
        private static int $_array_max_items = 1024;
        private static int $_array_hits = 0;
        private static int $_array_misses = 0;
        private static int $_array_sets = 0;
        private static int $_array_deletes = 0;

        /**
         * Get item from array cache
         *
         * Retrieves an item from the array cache, checking expiration
         * and automatically cleaning up expired entries.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found/expired
         */
        private static function getFromArray(string $_key): mixed
        {

            // check if the key exists
            if (! isset(self::$_array_cache[$_key])) {
                self::$_array_misses++;
                return false;
            }

            // get the cached item
            $cached_item = self::$_array_cache[$_key];

            // check if expired
            if (isset($cached_item['expires']) && $cached_item['expires'] <= time()) {
                // remove expired item
                unset(self::$_array_cache[$_key]);
                self::$_array_misses++;
                return false;
            }

            // cache hit
            self::$_array_hits++;
            return $cached_item['data'];
        }

        /**
         * Set item to array cache
         *
         * Stores an item in the array cache with TTL support and automatic
         * cache size management using LRU eviction when needed.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to store
         * @param mixed $_data The data to cache
         * @param int $_length Time to live in seconds
         * @return bool Returns true on success, false on failure
         */
        private static function setToArray(string $_key, mixed $_data, int $_length): bool
        {

            // try to set the item
            try {
                // calculate expiration time
                $expires = $_length > 0 ? time() + $_length : 0;

                // check if we need to make room (LRU eviction)
                if (count(self::$_array_cache) >= self::$_array_max_items) {
                    self::evictOldestArrayItems(100); // Remove 100 oldest items
                }

                // store the item
                self::$_array_cache[$_key] = [
                    'data' => $_data,
                    'expires' => $expires,
                    'created' => time(),
                    'accessed' => time()
                ];

                // increment the sets counter
                self::$_array_sets++;
                return true;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "Array cache set error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Delete item from array cache
         *
         * Removes a specific item from the array cache.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to delete
         * @return bool Returns true if deleted or didn't exist, false on error
         */
        private static function deleteFromArray(string $_key): bool
        {

            // try to delete the item
            try {
                // if the key exists, remove it and increment counter
                if (isset(self::$_array_cache[$_key])) {
                    unset(self::$_array_cache[$_key]);
                    self::$_array_deletes++;
                }

                // return success
                return true;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "Array cache delete error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Clear all items from array cache
         *
         * Empties the entire array cache and resets all statistics.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true on success, false on failure
         */
        public static function clearArray(): bool
        {

            // try to clear the cache
            try {
                // clear the cache and reset stats
                self::$_array_cache = [ ];
                self::$_array_hits = 0;
                self::$_array_misses = 0;
                self::$_array_sets = 0;
                self::$_array_deletes = 0;

                // return success
                return true;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "Array cache clear error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get array cache statistics
         *
         * Returns comprehensive statistics about the array cache including
         * hit rates, memory usage, and item counts.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array cache statistics
         */
        public static function getArrayStats(): array
        {

            // calculate memory usage
            $memory_usage = 0;
            $expired_count = 0;
            $current_time = time();

            // loop through cache to calculate stats
            foreach (self::$_array_cache as $item) {
                // add to memory usage
                $memory_usage += strlen(serialize($item));

                // check if expired
                if (isset($item['expires']) && $item['expires'] > 0 && $item['expires'] <= $current_time) {
                    $expired_count++;
                }
            }

            // calculate hit rate
            $total_requests = self::$_array_hits + self::$_array_misses;
            $hit_rate = $total_requests > 0 ? round(( self::$_array_hits / $total_requests ) * 100, 2) : 0;

            // return the stats array
            return [
                'enabled' => true,
                'items_cached' => count(self::$_array_cache),
                'max_items' => self::$_array_max_items,
                'memory_usage_bytes' => $memory_usage,
                'memory_usage_mb' => round($memory_usage / 1024 / 1024, 2),
                'cache_hits' => self::$_array_hits,
                'cache_misses' => self::$_array_misses,
                'cache_sets' => self::$_array_sets,
                'cache_deletes' => self::$_array_deletes,
                'hit_rate_percent' => $hit_rate,
                'expired_items' => $expired_count,
                'utilization_percent' => round(( count(self::$_array_cache) / self::$_array_max_items ) * 100, 2)
            ];
        }

        /**
         * Test array cache connectivity and functionality
         *
         * Performs a basic functionality test to ensure the array cache
         * is working properly.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if test passes, false otherwise
         */
        private static function testArrayConnection(): bool
        {

            // try to test the connection
            try {
                // test basic set/get operations
                $test_key = '__array_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // test set
                if (! self::setToArray($test_key, $test_value, 60)) {
                    return false;
                }

                // test get
                $retrieved = self::getFromArray($test_key);

                // test delete
                self::deleteFromArray($test_key);

                // verify data integrity
                return $retrieved === $test_value;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "Array cache test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Clean up expired items from array cache
         *
         * Removes all expired items from the cache to free up memory
         * and maintain optimal performance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of expired items removed
         */
        private static function cleanupArray(): int
        {

            // setup counters
            $cleaned = 0;
            $current_time = time();

            // loop through cache and remove expired items
            foreach (self::$_array_cache as $key => $item) {
                // check if this item is expired
                if (isset($item['expires']) && $item['expires'] > 0 && $item['expires'] <= $current_time) {
                    unset(self::$_array_cache[$key]);
                    $cleaned++;
                }
            }

            // return the number cleaned
            return $cleaned;
        }

        /**
         * Evict oldest items using LRU strategy
         *
         * Removes the oldest items from the cache when the cache reaches
         * its maximum capacity.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param int $count Number of items to evict
         * @return int Returns the number of items actually evicted
         */
        private static function evictOldestArrayItems(int $count): int
        {

            // first try to remove expired items
            $expired_removed = self::cleanupArrayExpired();

            // if we removed enough expired items, we're done
            if ($expired_removed >= $count) {
                return $expired_removed;
            }

            // sort by creation time (oldest first)
            uasort(self::$_array_cache, function ($a, $b) {
                return $a['created'] <=> $b['created'];
            });

            // remove oldest items
            $evicted = 0;
            $remaining_to_evict = $count - $expired_removed;

            // loop through and evict the oldest items
            foreach (self::$_array_cache as $key => $item) {
                // check if we've evicted enough
                if ($evicted >= $remaining_to_evict) {
                    break;
                }

                // remove this item and increment counter
                unset(self::$_array_cache[$key]);
                $evicted++;
            }

            // return total evicted
            return $expired_removed + $evicted;
        }

        /**
         * Set maximum items limit for array cache
         *
         * Configures the maximum number of items that can be stored
         * in the array cache before LRU eviction occurs.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param int $max_items Maximum number of items to cache
         * @return void Returns nothing
         */
        public static function setArrayCacheMaxItems(int $max_items): void
        {

            // Ensure at least 1
            self::$_array_max_items = max(1, $max_items);
        }

        /**
         * Get current array cache contents for debugging
         *
         * Returns the current cache contents for debugging purposes.
         * WARNING: This can return large amounts of data.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param bool $include_data Whether to include actual cached data
         * @return array Returns cache contents information
         */
        public static function getArrayCacheContents(bool $include_data = false): array
        {

            // setup the contents array and current time
            $contents = [ ];
            $current_time = time();

            // loop through each cached item
            foreach (self::$_array_cache as $key => $item) {
                // setup the entry data
                $entry = [
                    'key' => $key,
                    'created' => $item['created'],
                    'expires' => $item['expires'],
                    'is_expired' => ( $item['expires'] > 0 && $item['expires'] <= $current_time ),
                    'size_bytes' => strlen(serialize($item['data'])),
                    'ttl_remaining' => $item['expires'] > 0 ? max(0, $item['expires'] - $current_time) : -1
                ];

                // include data if requested
                if ($include_data) {
                    $entry['data'] = $item['data'];
                }

                // add to contents array
                $contents[ ] = $entry;
            }

            // return the contents
            return $contents;
        }
    }
}
