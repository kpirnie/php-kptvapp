<?php

/**
 * Async Cache Traits for I/O-intensive cache backends
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheFileAsync')) {

    /**
     * KPT Cache File Async Trait
     *
     * Provides asynchronous file caching operations for improved performance
     * in I/O-intensive applications using event loops and promises.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheFileAsync
    {
        /**
         * Async get from file cache
         *
         * Asynchronously retrieves an item from the file cache using promises
         * and event loop integration for non-blocking operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @return CachePromise Returns a promise that resolves with the cached data
         */
        public static function getFromFileAsync(string $key): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($key, $resolve, $reject) {

                        // try to get the item from file cache
                        try {
                            // get the result and resolve
                            $result = self::getFromFile($key);
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to get the item synchronously
                    try {
                        // get the result and resolve
                        $result = self::getFromFile($key);
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async set to file cache
         *
         * Asynchronously stores an item in the file cache using promises
         * and event loop integration for non-blocking operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function setToFileAsync(string $key, mixed $data, int $ttl): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key, $data, $ttl) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($key, $data, $ttl, $resolve, $reject) {

                        // try to set the item to file cache
                        try {
                            // set the result and resolve
                            $result = self::setToFile($key, $data, $ttl);
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to set the item synchronously
                    try {
                        // set the result and resolve
                        $result = self::setToFile($key, $data, $ttl);
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async batch file operations
         *
         * Performs multiple file cache operations asynchronously in batch
         * for improved performance when handling multiple cache operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $operations Array of operations to perform
         * @return CachePromise Returns a promise that resolves with operation results
         */
        public static function fileCacheBatchAsync(array $operations): CachePromise
        {

            // return a new promise for the batch operation
            return new CachePromise(function ($resolve, $reject) use ($operations) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // setup promises array
                    $promises = [ ];

                    // loop through each operation and create promises
                    foreach ($operations as $op) {
                        // match the operation type and create appropriate promise
                        $promise = match ($op['type']) {
                            'get' => self::getFromFileAsync($op['key']),
                            'set' => self::setToFileAsync($op['key'], $op['data'], $op['ttl'] ?? 3600),
                            'delete' => self::deleteFromFileAsync($op['key']),
                            default => CachePromise::reject(new \Exception("Unknown operation: {$op['type']}"))
                        };

                        // add to promises array
                        $promises[ ] = $promise;
                    }

                    // wait for all promises to complete
                    CachePromise::all($promises)
                        -> then(function ($results) use ($resolve) {
                            $resolve($results);
                        })
                        -> catch(function ($error) use ($reject) {
                            $reject($error);
                        });

                // Fallback to synchronous batch processing
                } else {
                    // try to process batch synchronously
                    try {
                        // setup results array
                        $results = [ ];

                        // loop through each operation
                        foreach ($operations as $op) {
                            // match the operation type and execute
                            $results[ ] = match ($op['type']) {
                                'get' => self::getFromFile($op['key']),
                                'set' => self::setToFile($op['key'], $op['data'], $op['ttl'] ?? 3600),
                                'delete' => self::deleteFromFile($op['key']),
                                default => false
                            };
                        }

                        // resolve with results
                        $resolve($results);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async delete from file cache
         *
         * Asynchronously deletes an item from the file cache using promises
         * and event loop integration for non-blocking operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to delete
         * @return CachePromise Returns a promise that resolves with deletion status
         */
        public static function deleteFromFileAsync(string $key): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($key, $resolve, $reject) {

                        // try to delete the item from file cache
                        try {
                            // delete the item and resolve
                            $result = self::deleteFromFile($key);
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to delete the item synchronously
                    try {
                        // delete the item and resolve
                        $result = self::deleteFromFile($key);
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async cleanup expired files
         *
         * Asynchronously cleans up expired cache files to maintain optimal
         * performance and free up disk space.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return CachePromise Returns a promise that resolves with cleanup status
         */
        public static function cleanupExpiredFilesAsync(): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($resolve, $reject) {

                        // try to cleanup expired files
                        try {
                            // cleanup expired files and resolve
                            $result = self::cleanupExpiredFiles();
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to cleanup expired files synchronously
                    try {
                        // cleanup expired files and resolve
                        $result = self::cleanupExpiredFiles();
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
    }
}
