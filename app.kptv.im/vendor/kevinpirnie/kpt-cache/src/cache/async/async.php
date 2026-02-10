<?php

/**
 * Async Cache Operations
 * Provides promise-based versions of cache methods
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't exist
if (! trait_exists('CacheAsync')) {

    /**
     * KPT Cache Async Operations Trait
     *
     * Provides asynchronous promise-based versions of all cache methods
     * for improved performance in concurrent operations and event-driven applications.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheAsync
    {
        // class properties
        private static ?object $event_loop = null;
        private static bool $async_enabled = false;

        /**
         * Enable async support with optional event loop
         *
         * Enables asynchronous cache operations with support for event loops
         * like ReactPHP or other async frameworks.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param object|null $eventLoop Optional event loop instance
         * @return void Returns nothing
         */
        public static function enableAsync(?object $eventLoop = null): void
        {

            // enable async operations
            self::$_async_enabled = true;

            // set the event loop
            self::$_event_loop = $eventLoop;
        }

        /**
         * Check if async is enabled
         *
         * Returns the current status of asynchronous operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if async is enabled, false otherwise
         */
        public static function isAsyncEnabled(): bool
        {
            return self::$_async_enabled;
        }

        /**
         * Get event loop instance
         *
         * Returns the currently configured event loop for async operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return object|null Returns the event loop instance or null if none set
         */
        public static function getEventLoop(): ?object
        {
            return self::$_event_loop;
        }

        /**
         * Disable async support
         *
         * Disables asynchronous operations and clears the event loop reference.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function disableAsync(): void
        {

            // disable async operations
            self::$_async_enabled = false;

            // clear the event loop
            self::$_event_loop = null;
        }

        /**
         * Asynchronous get operation
         *
         * Retrieves a cached item asynchronously, returning a promise that resolves
         * with the cached data or rejects with an error.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @return CachePromise Returns a promise that resolves with the cached data
         */
        public static function getAsync(string $key): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $resolve, $reject) {

                        // try to get the cached item
                        try {
                            // get the result
                            $result = self::get($key);

                            // resolve the promise
                            $resolve($result);

                            // debug log
                            Logger::debug("Cache getAsync (Event Loop)", [
                                'key' => $key,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache getAsync Error (Event Loop)", [
                                'key' => $key,
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // get the result
                        $result = self::get($key);

                        // resolve the promise
                        $resolve($result);

                        // debug log
                        Logger::debug("Cache getAsync (Fallback)", [
                            'key' => $key,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache getAsync Error (Fallback)", [
                            'key' => $key,
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous set operation
         *
         * Stores data in the cache asynchronously, returning a promise that resolves
         * with the success status or rejects with an error.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to store
         * @param int $ttl Time to live in seconds (default: 1 hour)
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function setAsync(string $key, mixed $data, int $ttl = 3600): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key, $data, $ttl) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $data, $ttl, $resolve, $reject) {

                        // try to set the cached item
                        try {
                            // set the result
                            $result = self::set($key, $data, $ttl);

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache setAsync (Event Loop)", [
                                'key' => $key,
                                'ttl' => $ttl,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache setAsync Error (Event Loop)", [
                                'key' => $key,
                                'ttl' => $ttl,
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // set the result
                        $result = self::set($key, $data, $ttl);

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache setAsync (Fallback)", [
                            'key' => $key,
                            'ttl' => $ttl,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache setAsync Error (Fallback)", [
                            'key' => $key,
                            'ttl' => $ttl,
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous delete operation
         *
         * Deletes a cached item asynchronously, returning a promise that resolves
         * with the success status or rejects with an error.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to delete
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function deleteAsync(string $key): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $resolve, $reject) {

                        // try to delete the cached item
                        try {
                            // delete the item
                            $result = self::delete($key);

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache deleteAsync (Event Loop)", [
                                'key' => $key,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache deleteAsync Error (Event Loop)", [
                                'key' => $key,
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // delete the item
                        $result = self::delete($key);

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache deleteAsync (Fallback)", [
                            'key' => $key,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache deleteAsync Error (Fallback)", [
                            'key' => $key,
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous clear operation
         *
         * Clears all cached data asynchronously, returning a promise that resolves
         * with the success status or rejects with an error.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function clearAsync(): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($resolve, $reject) {

                        // try to clear the cache
                        try {
                            // clear all cached data
                            $result = self::clear();

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache clearAsync (Event Loop)", [
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache clearAsync Error (Event Loop)", [
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // clear all cached data
                        $result = self::clear();

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache clearAsync (Fallback)", [
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache clearAsync Error (Fallback)", [
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous batch get operation
         *
         * Retrieves multiple cached items concurrently, returning a promise that
         * resolves with an associative array of key => value pairs.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to retrieve
         * @return CachePromise Returns a promise that resolves with key => value array
         */
        public static function getBatchAsync(array $keys): CachePromise
        {

            // create promises for each key
            $promises = array_map(function ($key) {

                // return the async get for this key
                return self::getAsync($key);
            }, $keys);

            // return all promises combined
            return CachePromise::all($promises)
                -> then(function ($results) use ($keys) {

                    // combine keys with results and return
                    $combined = array_combine($keys, $results);

                    // debug logging
                    Logger::debug("Cache getBatchAsync Completed", [
                        'keys' => $keys,
                        'key_count' => count($keys),
                        'success' => true
                    ]);

                    return $combined;
                })
                -> catch(function ($error) use ($keys) {

                    // error logging
                    Logger::error("Cache getBatchAsync Error", [
                        'keys' => $keys,
                        'key_count' => count($keys),
                        'message' => $error -> getMessage()
                    ]);

                    throw $error;
                });
        }

        /**
         * Asynchronous batch set operation
         *
         * Stores multiple items in the cache concurrently, returning a promise that
         * resolves when all items have been stored successfully.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $items Associative array of key => data pairs to store
         * @param int $ttl Time to live in seconds (default: 1 hour)
         * @return CachePromise Returns a promise that resolves with success status array
         */
        public static function setBatchAsync(array $items, int $ttl = 3600): CachePromise
        {

            // default promises array
            $promises = [];

            // loop over each item
            foreach ($items as $key => $data) {
                // add the set promise to the array
                $promises[] = self::setAsync($key, $data, $ttl);
            }

            // return all promises combined
            return CachePromise::all($promises)
                -> then(function ($results) use ($items, $ttl) {

                    // debug logging
                    Logger::debug("Cache setBatchAsync Completed", [
                        'items' => array_keys($items),
                        'item_count' => count($items),
                        'ttl' => $ttl,
                        'success' => true
                    ]);

                    return $results;
                })
                -> catch(function ($error) use ($items, $ttl) {

                    // error logging
                    Logger::error("Cache setBatchAsync Error", [
                        'items' => array_keys($items),
                        'item_count' => count($items),
                        'ttl' => $ttl,
                        'message' => $error -> getMessage()
                    ]);

                    throw $error;
                });
        }

        /**
         * Asynchronous batch delete operation
         *
         * Deletes multiple cached items concurrently, returning a promise that
         * resolves when all items have been deleted.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to delete
         * @return CachePromise Returns a promise that resolves with success status array
         */
        public static function deleteBatchAsync(array $keys): CachePromise
        {

            // create promises for each key
            $promises = array_map(function ($key) {

                // return the async delete for this key
                return self::deleteAsync($key);
            }, $keys);

            // return all promises combined
            return CachePromise::all($promises)
                -> then(function ($results) use ($keys) {

                    // debug logging
                    Logger::debug("Cache deleteBatchAsync Completed", [
                        'keys' => $keys,
                        'key_count' => count($keys),
                        'success' => true
                    ]);

                    return $results;
                })
                -> catch(function ($error) use ($keys) {

                    // error logging
                    Logger::error("Cache deleteBatchAsync Error", [
                        'keys' => $keys,
                        'key_count' => count($keys),
                        'message' => $error -> getMessage()
                    ]);

                    throw $error;
                });
        }

        /**
         * Asynchronous tier-specific get operation
         *
         * Retrieves a cached item from a specific tier asynchronously.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @param string $tier The specific tier to retrieve from
         * @return CachePromise Returns a promise that resolves with the cached data
         */
        public static function getFromTierAsync(string $key, string $tier): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key, $tier) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $tier, $resolve, $reject) {

                        // try to get the cached item from the tier
                        try {
                            // get the result from the tier
                            $result = self::getFromTier($key, $tier);

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache getFromTierAsync (Event Loop)", [
                                'key' => $key,
                                'tier' => $tier,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache getFromTierAsync Error (Event Loop)", [
                                'key' => $key,
                                'tier' => $tier,
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // get the result from the tier
                        $result = self::getFromTier($key, $tier);

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache getFromTierAsync (Fallback)", [
                            'key' => $key,
                            'tier' => $tier,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache getFromTierAsync Error (Fallback)", [
                            'key' => $key,
                            'tier' => $tier,
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous tier-specific set operation
         *
         * Stores data in a specific cache tier asynchronously.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to store
         * @param int $ttl Time to live in seconds
         * @param string $tier The specific tier to store to
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function setToTierAsync(string $key, mixed $data, int $ttl, string $tier): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key, $data, $ttl, $tier) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $data, $ttl, $tier, $resolve, $reject) {

                        // try to set the cached item to the tier
                        try {
                            // set the result to the tier
                            $result = self::setToTier($key, $data, $ttl, $tier);

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache setToTierAsync (Event Loop)", [
                                'key' => $key,
                                'tier' => $tier,
                                'ttl' => $ttl,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache setToTierAsync Error (Event Loop)", [
                                'key' => $key,
                                'tier' => $tier,
                                'ttl' => $ttl,
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // set the result to the tier
                        $result = self::setToTier($key, $data, $ttl, $tier);

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache setToTierAsync (Fallback)", [
                            'key' => $key,
                            'tier' => $tier,
                            'ttl' => $ttl,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache setToTierAsync Error (Fallback)", [
                            'key' => $key,
                            'tier' => $tier,
                            'ttl' => $ttl,
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous tier-specific delete operation
         *
         * Deletes a cached item from a specific tier asynchronously.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to delete
         * @param string $tier The specific tier to delete from
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function deleteFromTierAsync(string $key, string $tier): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key, $tier) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $tier, $resolve, $reject) {

                        // try to delete the cached item from the tier
                        try {
                            // delete the item from the tier
                            $result = self::deleteFromTier($key, $tier);

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache deleteFromTierAsync (Event Loop)", [
                                'key' => $key,
                                'tier' => $tier,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache deleteFromTierAsync Error (Event Loop)", [
                                'key' => $key,
                                'tier' => $tier,
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // delete the item from the tier
                        $result = self::deleteFromTier($key, $tier);

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache deleteFromTierAsync (Fallback)", [
                            'key' => $key,
                            'tier' => $tier,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache deleteFromTierAsync Error (Fallback)", [
                            'key' => $key,
                            'tier' => $tier,
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous multi-tier set operation
         *
         * Stores data in multiple specific tiers asynchronously, providing detailed
         * results for each tier attempted.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to store
         * @param int $ttl Time to live in seconds
         * @param array $tiers Array of tier names to store the item in
         * @return CachePromise Returns a promise that resolves with detailed results
         */
        public static function setToTiersAsync(string $key, mixed $data, int $ttl, array $tiers): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key, $data, $ttl, $tiers) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $data, $ttl, $tiers, $resolve, $reject) {

                        // try to set the cached item to the tiers
                        try {
                            // set the result to the tiers
                            $result = self::setToTiers($key, $data, $ttl, $tiers);

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache setToTiersAsync (Event Loop)", [
                                'key' => $key,
                                'tiers' => $tiers,
                                'tier_count' => count($tiers),
                                'ttl' => $ttl,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache setToTiersAsync Error (Event Loop)", [
                                'key' => $key,
                                'tiers' => $tiers,
                                'tier_count' => count($tiers),
                                'ttl' => $ttl,
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // set the result to the tiers
                        $result = self::setToTiers($key, $data, $ttl, $tiers);

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache setToTiersAsync (Fallback)", [
                            'key' => $key,
                            'tiers' => $tiers,
                            'tier_count' => count($tiers),
                            'ttl' => $ttl,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache setToTiersAsync Error (Fallback)", [
                            'key' => $key,
                            'tiers' => $tiers,
                            'tier_count' => count($tiers),
                            'ttl' => $ttl,
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous multi-tier delete operation
         *
         * Deletes a cached item from multiple specific tiers asynchronously,
         * providing detailed results for each tier attempted.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to delete
         * @param array $tiers Array of tier names to delete the item from
         * @return CachePromise Returns a promise that resolves with detailed results
         */
        public static function deleteFromTiersAsync(string $key, array $tiers): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key, $tiers) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $tiers, $resolve, $reject) {

                        // try to delete the cached item from the tiers
                        try {
                            // delete the item from the tiers
                            $result = self::deleteFromTiers($key, $tiers);

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache deleteFromTiersAsync (Event Loop)", [
                                'key' => $key,
                                'tiers' => $tiers,
                                'tier_count' => count($tiers),
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache deleteFromTiersAsync Error (Event Loop)", [
                                'key' => $key,
                                'tiers' => $tiers,
                                'tier_count' => count($tiers),
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // delete the item from the tiers
                        $result = self::deleteFromTiers($key, $tiers);

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache deleteFromTiersAsync (Fallback)", [
                            'key' => $key,
                            'tiers' => $tiers,
                            'tier_count' => count($tiers),
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache deleteFromTiersAsync Error (Fallback)", [
                            'key' => $key,
                            'tiers' => $tiers,
                            'tier_count' => count($tiers),
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous tier preference operation
         *
         * Attempts to retrieve data from a preferred tier first, with optional
         * fallback to the standard tier hierarchy.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @param string $preferred_tier The preferred tier to try first
         * @param bool $fallback_on_failure Whether to fallback on failure
         * @return CachePromise Returns a promise that resolves with the cached data
         */
        public static function getWithTierPreferenceAsync(string $key, string $preferred_tier, bool $fallback_on_failure = true): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($key, $preferred_tier, $fallback_on_failure) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($key, $preferred_tier, $fallback_on_failure, $resolve, $reject) {

                        // try to get the cached item with preference
                        try {
                            // get the result with tier preference
                            $result = self::getWithTierPreference($key, $preferred_tier, $fallback_on_failure);

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache getWithTierPreferenceAsync (Event Loop)", [
                                'key' => $key,
                                'preferred_tier' => $preferred_tier,
                                'fallback_on_failure' => $fallback_on_failure,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache getWithTierPreferenceAsync Error (Event Loop)", [
                                'key' => $key,
                                'preferred_tier' => $preferred_tier,
                                'fallback_on_failure' => $fallback_on_failure,
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // get the result with tier preference
                        $result = self::getWithTierPreference($key, $preferred_tier, $fallback_on_failure);

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache getWithTierPreferenceAsync (Fallback)", [
                            'key' => $key,
                            'preferred_tier' => $preferred_tier,
                            'fallback_on_failure' => $fallback_on_failure,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache getWithTierPreferenceAsync Error (Fallback)", [
                            'key' => $key,
                            'preferred_tier' => $preferred_tier,
                            'fallback_on_failure' => $fallback_on_failure,
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }

        /**
         * Asynchronous pipeline operations for better performance
         *
         * Executes a series of cache operations in a pipeline for improved
         * performance when dealing with multiple operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $operations Array of operations to execute
         * @return CachePromise Returns a promise that resolves with operation results
         */
        public static function pipelineAsync(array $operations): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) use ($operations) {

                // default promises array
                $promises = [];

                // loop over each operation
                foreach ($operations as $index => $operation) {
                    // get the method and args
                    $method = $operation['method'];
                    $args = $operation['args'] ?? [];

                    // match the method and create the appropriate promise
                    $promise = match ($method) {
                        'get' => self::getAsync($args[0]),
                        'set' => self::setAsync($args[0], $args[1], $args[2] ?? 3600),
                        'delete' => self::deleteAsync($args[0]),
                        'getFromTier' => self::getFromTierAsync($args[0], $args[1]),
                        'setToTier' => self::setToTierAsync($args[0], $args[1], $args[2] ?? 3600, $args[3]),
                        'deleteFromTier' => self::deleteFromTierAsync($args[0], $args[1]),
                        default => CachePromise::reject(new Exception("Unknown method: {$method}"))
                    };

                    // add the promise to the array
                    $promises[] = $promise;
                }

                // execute all promises and handle results
                CachePromise::all($promises)
                    -> then(function ($results) use ($resolve, $operations) {

                        // debug logging
                        Logger::debug("Cache pipelineAsync Completed", [
                            'operation_count' => count($operations),
                            'operations' => array_column($operations, 'method'),
                            'success' => true
                        ]);

                        // resolve with the results
                        $resolve($results);
                    })
                    -> catch(function ($error) use ($reject, $operations) {

                        // error logging
                        Logger::error("Cache pipelineAsync Error", [
                            'operation_count' => count($operations),
                            'operations' => array_column($operations, 'method'),
                            'message' => $error -> getMessage()
                        ]);

                        // reject with the error
                        $reject($error);
                    });
            });
        }

        /**
         * Asynchronous cleanup operation
         *
         * Performs cache maintenance and cleanup asynchronously, including
         * expired entry removal and connection pool maintenance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return CachePromise Returns a promise that resolves with cleanup count
         */
        public static function cleanupAsync(): CachePromise
        {

            // return a new promise
            return new CachePromise(function ($resolve, $reject) {

                // if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // Use event loop for true async
                    self::$_event_loop -> futureTick(function () use ($resolve, $reject) {

                        // try to cleanup the cache
                        try {
                            // cleanup expired items
                            $result = self::cleanup();

                            // resolve the promise
                            $resolve($result);

                            // debug logging
                            Logger::debug("Cache cleanupAsync (Event Loop)", [
                                'cleanup_count' => $result,
                                'success' => true,
                                'mode' => 'async'
                            ]);

                        // whoopsie...
                        } catch (\Exception $e) {
                            // reject the promise and log the error
                            $reject($e);
                            Logger::error("Cache cleanupAsync Error (Event Loop)", [
                                'message' => $e -> getMessage()
                            ]);
                        }
                    });

                // otherwise
                } else {
                    // Fallback to immediate execution
                    try {
                        // cleanup expired items
                        $result = self::cleanup();

                        // resolve the promise
                        $resolve($result);

                        // debug logging
                        Logger::debug("Cache cleanupAsync (Fallback)", [
                            'cleanup_count' => $result,
                            'success' => true,
                            'mode' => 'fallback'
                        ]);

                    // whoopsie...
                    } catch (\Exception $e) {
                        // reject the promise and log the error
                        $reject($e);
                        Logger::error("Cache cleanupAsync Error (Fallback)", [
                            'message' => $e -> getMessage()
                        ]);
                    }
                }
            });
        }
    }
}
