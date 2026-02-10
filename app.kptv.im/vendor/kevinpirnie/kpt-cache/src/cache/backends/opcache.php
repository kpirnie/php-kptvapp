<?php

/**
 * KPT Cache - Fixed OPCache Caching Trait
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't exist first
if (! trait_exists('CacheOPCache')) {

    /**
     * KPT Cache OPCache Trait
     *
     * Provides OPCache-based caching functionality using PHP files
     * compiled into OPCache for maximum performance.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheOPCache
    {
        /**
         * Check if OPcache is properly enabled
         *
         * Verifies that OPCache is available and properly enabled
         * for use in caching operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if OPCache is enabled, false otherwise
         */
        private static function isOPcacheEnabled(): bool
        {

            // first check if the opcache functions exist
            if (! function_exists('opcache_get_status')) {
                return false;
            }

            // just try to get the opcache status
            $status = opcache_get_status(false);

            // return the success of the opcache being enabled
            return is_array($status) && isset($status['opcache_enabled']) && $status['opcache_enabled'];
        }

        /**
         * Get OPcache file path for a given key
         *
         * Generates the file path for storing a cache item in OPCache
         * using configured paths and proper key hashing.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to generate path for
         * @return string Returns the full file path for the cache item
         */
        private static function getOPcacheFilePath(string $key): string
        {

            // get opcache configuration
            $config = CacheConfig::get('opcache');
            $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

            // create the opcache key with prefix and hash
            $opcache_key = $prefix . md5($key);

            // Use configured path from global config
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';

            // Ensure cache path exists with proper error handling
            if (! self::ensureOPcacheDirectory($cache_path)) {
                // Fallback to system temp with unique subdirectory
                $cache_path = sys_get_temp_dir() . '/kpt_opcache_' . getmypid() . '/';
                self::ensureOPcacheDirectory($cache_path);
            }

            // return the full file path
            return $cache_path . $opcache_key . '.php';
        }

        /**
         * Ensure OPcache directory exists and is writable
         *
         * Creates and validates the OPCache directory with proper
         * permissions for storing cache files.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path The directory path to ensure
         * @return bool Returns true if directory is ready, false otherwise
         */
        private static function ensureOPcacheDirectory(string $path): bool
        {

            // try to ensure directory exists and is writable
            try {
                // If directory already exists and is writable, we're good
                if (is_dir($path) && is_writable($path)) {
                    return true;
                }

                // Try to create the directory
                if (! is_dir($path)) {
                    // create the directory with proper permissions
                    if (! mkdir($path, 0755, true)) {
                        return false;
                    }
                }

                // Check if it's writable
                if (! is_writable($path)) {
                    // Try to fix permissions
                    @chmod($path, 0755);
                    return is_writable($path);
                }

                // directory is ready
                return true;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "OPcache directory creation failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get item from OPcache
         *
         * Retrieves a cached item from OPCache by including the PHP file
         * and checking expiration status.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found/expired
         */
        private static function getFromOPcache(string $_key): mixed
        {

            // if opcache is not enabled, just return false
            if (! self::isOPcacheEnabled()) {
                return false;
            }

            // setup the cache key file using configured path
            $temp_file = self::getOPcacheFilePath($_key);

            // if the file does not exist, return false
            if (! file_exists($temp_file)) {
                return false;
            }

            // try to retrieve the data
            try {
                // Include the file to get cached data
                $data = include $temp_file;

                // if the data is an array
                if (is_array($data) && isset($data['expires'], $data['value'])) {
                    // if it isn't expired yet
                    if ($data['expires'] > time()) {
                        // return the cached value
                        return $data['value'];

                    // otherwise it's expired
                    } else {
                        // remove file
                        @unlink($temp_file);

                        // if the invalidation functionality exists, then invalidate it
                        if (function_exists('opcache_invalidate')) {
                            @opcache_invalidate($temp_file, true);
                        }
                    }
                }

            // whoopsie... set the last error
            } catch (\Exception $e) {
                self::$_last_error = "OPcache get error: " . $e -> getMessage();
            }

            // return false
            return false;
        }

        /**
         * Set item to OPcache with improved error handling
         *
         * Stores an item in OPCache by creating a PHP file with the data
         * and proper expiration handling.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to store
         * @param mixed $_data The data to cache
         * @param int $_length Time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToOPcache(string $_key, mixed $_data, int $_length): bool
        {

            // check if opcache is enabled
            if (! self::isOPcacheEnabled()) {
                return false;
            }

            // setup file path and expiration
            $temp_file = self::getOPcacheFilePath($_key);
            $expires = time() + $_length;

            // Ensure the directory exists
            $dir = dirname($temp_file);
            if (! self::ensureOPcacheDirectory($dir)) {
                self::$_last_error = "OPcache: Cannot create or write to directory: {$dir}";
                return false;
            }

            // Create the PHP content with proper escaping
            $content = "<?php return " . var_export([ 'expires' => $expires, 'value' => $_data ], true) . ";";

            // try to write the cache file
            try {
                // Try to write with exclusive lock first
                $result = @file_put_contents($temp_file, $content, LOCK_EX);

                // If locking failed, try without lock (some filesystems don't support it)
                if ($result === false) {
                    // try without lock
                    $result = @file_put_contents($temp_file, $content);

                    // last resort - manual locking
                    if ($result === false) {
                        $result = self::writeOPcacheFileManual($temp_file, $content);
                    }
                }

                // check if write was successful
                if ($result !== false) {
                    // Try to compile to OPcache
                    if (function_exists('opcache_compile_file')) {
                        @opcache_compile_file($temp_file);
                    }
                    return true;

                // write failed
                } else {
                    self::$_last_error = "OPcache: Failed to write file: {$temp_file}";
                    return false;
                }

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "OPcache set error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Manual file writing with fopen/fwrite as fallback
         *
         * Provides a fallback method for writing cache files when
         * file_put_contents fails or file locking is not supported.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $filepath The file path to write to
         * @param string $content The content to write
         * @return bool Returns true if successful, false otherwise
         */
        private static function writeOPcacheFileManual(string $filepath, string $content): bool
        {

            // try manual file writing
            try {
                // open file for writing
                $handle = fopen($filepath, 'w');
                if ($handle === false) {
                    return false;
                }

                // Try to get exclusive lock
                $locked = flock($handle, LOCK_EX);

                // write the content
                $result = fwrite($handle, $content);

                // release lock if we had one
                if ($locked) {
                    flock($handle, LOCK_UN);
                }

                // close the file
                fclose($handle);

                // return write success
                return $result !== false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "OPcache manual write error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Delete item from OPcache
         *
         * Removes a cached item from OPCache by invalidating and
         * deleting the corresponding PHP file.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to delete
         * @return bool Returns true if deleted or didn't exist, false on error
         */
        private static function deleteFromOPcache(string $_key): bool
        {

            // get the file path
            $temp_file = self::getOPcacheFilePath($_key);

            // check if file exists
            if (file_exists($temp_file)) {
                // Invalidate from OPcache first
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($temp_file, true);
                }

                // Remove the file
                return @unlink($temp_file);
            }

            // File doesn't exist, consider it deleted
            return true;
        }

        /**
         * Clear all OPcache files for this application
         *
         * Removes all cache files belonging to this application
         * from OPCache and the file system.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if all files cleared, false if some failed
         */
        public static function clearOPcache(): bool
        {
            // Get cache path
            $config = CacheConfig::get('opcache');
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';

            // Use MULTIPLE patterns to catch all possible files
            $patterns = [
                $cache_path . '*.php',              // All PHP files (most inclusive)
                $cache_path . '*.PHP',              // Windows uppercase extension
                $cache_path . '*~*.php',            // Windows short filename format
                $cache_path . '*~*.PHP',            // Windows short filename uppercase
            ];

            $success = true;
            $deleted_count = 0;

            // Collect all unique files from all patterns
            $all_files = [];
            foreach ($patterns as $pattern) {
                $files = glob($pattern);
                if ($files) {
                    $all_files = array_merge($all_files, $files);
                }
            }

            // Remove duplicates
            $all_files = array_unique($all_files);

            // Delete each file
            foreach ($all_files as $file) {
                if (!is_file($file)) {
                    continue;
                }

                // Try to determine if this is a KPT cache file by checking content
                $content = @file_get_contents($file);
                if (
                    $content !== false &&
                    (strpos($content, "<?php return array") === 0 ||
                    strpos($content, "<?php return [") === 0)
                ) {
                    // This looks like our cache file format

                    // Invalidate from OPcache first
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($file, true);
                    }

                    // Delete the file
                    if (@unlink($file)) {
                        $deleted_count++;
                    } else {
                        $success = false;
                    }
                }
            }

            // Also try global OPcache reset
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            return $success;
        }

        /**
         * Get OPcache statistics with error handling
         *
         * Retrieves comprehensive OPCache statistics including
         * system status and application-specific file information.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of OPCache statistics
         */
        private static function getOPcacheStats(): array
        {

            // check if opcache functions exist
            if (! function_exists('opcache_get_status')) {
                return [ 'error' => 'OPcache not available' ];
            }

            // try to get opcache statistics
            try {
                // get opcache status with script data
                $stats = opcache_get_status(true);

                // check if we got valid stats
                if (! $stats) {
                    return [ 'error' => 'OPcache not enabled' ];
                }

                // Add our specific file count
                $config = CacheConfig::get('opcache');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();
                $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';
                $pattern = $cache_path . $prefix . '*.php';
                $our_files = glob($pattern);

                // add our custom stats
                $stats['kpt_CacheFiles'] = [
                    'count' => is_array($our_files) ? count($our_files) : 0,
                    'total_size' => is_array($our_files) ? array_sum(array_map('filesize', array_filter($our_files, 'is_file'))) : 0,
                    'prefix' => $prefix,
                    'path' => $cache_path,
                    'path_writable' => is_writable($cache_path),
                    'path_exists' => is_dir($cache_path)
                ];

                // return the stats
                return $stats;

            // whoopsie... return error
            } catch (\Exception $e) {
                return [ 'error' => 'Failed to get OPcache stats: ' . $e -> getMessage() ];
            }
        }

        /**
         * Test OPcache functionality with improved diagnostics
         *
         * Performs a comprehensive test of OPCache functionality
         * by storing, retrieving, and cleaning up test data.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if test passes, false otherwise
         */
        private static function testOPcacheConnection(): bool
        {

            // check if opcache is enabled
            if (! self::isOPcacheEnabled()) {
                return false;
            }

            // try to test opcache functionality
            try {
                // setup test data
                $test_key = 'opcache_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // Try to store and retrieve
                if (self::setToOPcache($test_key, $test_value, 60)) {
                    // get the stored value
                    $retrieved = self::getFromOPcache($test_key);

                    // clean up the test data
                    self::deleteFromOPcache($test_key);

                    // return comparison result
                    return $retrieved === $test_value;
                }

                // failed to store
                return false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "OPcache test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Clean up expired OPcache files with better error handling
         *
         * Removes all expired cache files from OPCache to free up
         * disk space and maintain optimal performance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of expired files removed
         */
        private static function cleanupOPcache(): int
        {

            // get opcache configuration
            $config = CacheConfig::get('opcache');
            $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';

            // find all our cache files
            $pattern = $cache_path . $prefix . '*.php';
            $files = glob($pattern);
            $cleaned = 0;

            // check if we found files
            if (! is_array($files)) {
                return 0;
            }

            // loop through each file
            foreach ($files as $file) {
                // skip if not a file
                if (! is_file($file)) {
                    continue;
                }

                // try to check expiration
                try {
                    // Include the file to check expiration
                    $data = @include $file;

                    // check if we have valid data with expiration
                    if (is_array($data) && isset($data['expires'])) {
                        // check if expired
                        if ($data['expires'] <= time()) {
                            // Expired - invalidate and remove
                            if (function_exists('opcache_invalidate')) {
                                @opcache_invalidate($file, true);
                            }

                            // delete the file and increment counter
                            if (@unlink($file)) {
                                $cleaned++;
                            }
                        }
                    }

                // whoopsie... file might be corrupted, remove it
                } catch (\Exception $e) {
                    // If we can't read the file, it might be corrupted - remove it
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($file, true);
                    }

                    // delete the corrupted file and increment counter
                    if (@unlink($file)) {
                        $cleaned++;
                    }
                }
            }

            // return number of files cleaned
            return $cleaned;
        }

        /**
         * Get OPcache file list with details and error handling
         *
         * Returns a detailed list of all OPCache files including
         * expiration information, sizes, and validity status.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of OPCache file information
         */
        private static function getOPcacheFileList(): array
        {

            // get opcache configuration
            $config = CacheConfig::get('opcache');
            $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';

            // find all our cache files
            $pattern = $cache_path . $prefix . '*.php';
            $files = glob($pattern);
            $file_details = [ ];

            // check if we found files
            if (! is_array($files)) {
                return $file_details;
            }

            // loop through each file
            foreach ($files as $file) {
                // skip if not a file
                if (! is_file($file)) {
                    continue;
                }

                // try to get file details
                try {
                    // include the file to get data
                    $data = @include $file;

                    // setup file info array
                    $file_info = [
                        'file' => basename($file),
                        'full_path' => $file,
                        'size' => filesize($file),
                        'created' => filectime($file),
                        'modified' => filemtime($file),
                        'expires' => null,
                        'expired' => false,
                        'valid' => false,
                        'readable' => is_readable($file),
                        'writable' => is_writable($file)
                    ];

                    // check if we have valid cached data
                    if (is_array($data) && isset($data['expires'])) {
                        $file_info['expires'] = $data['expires'];
                        $file_info['expired'] = $data['expires'] <= time();
                        $file_info['valid'] = true;
                    }

                    // add to file details
                    $file_details[ ] = $file_info;

                // whoopsie... add error info to file details
                } catch (\Exception $e) {
                    $file_details[ ] = [
                        'file' => basename($file),
                        'full_path' => $file,
                        'size' => filesize($file),
                        'created' => filectime($file),
                        'modified' => filemtime($file),
                        'error' => $e -> getMessage(),
                        'valid' => false,
                        'readable' => is_readable($file),
                        'writable' => is_writable($file)
                    ];
                }
            }

            // return the file details
            return $file_details;
        }

        /**
         * Diagnostic method for OPcache issues
         *
         * Provides comprehensive diagnostic information about OPCache
         * configuration and potential issues for troubleshooting.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of diagnostic information
         */
        private static function diagnoseOPcache(): array
        {

            // get opcache configuration
            $config = CacheConfig::get('opcache');
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';

            // setup diagnosis array
            $diagnosis = [
                'opcache_available' => function_exists('opcache_get_status'),
                'opcache_enabled' => self::isOPcacheEnabled(),
                'cache_path' => $cache_path,
                'path_exists' => false,
                'path_writable' => false,
                'path_readable' => false,
                'php_version' => PHP_VERSION,
                'issues' => [ ],
                'recommendations' => [ ]
            ];

            // Check path status
            $diagnosis['path_exists'] = is_dir($cache_path);
            $diagnosis['path_writable'] = is_writable($cache_path);
            $diagnosis['path_readable'] = is_readable($cache_path);

            // Identify issues
            if (! $diagnosis['opcache_available']) {
                $diagnosis['issues'][ ] = 'OPcache extension not loaded';
                $diagnosis['recommendations'][ ] = 'Install and enable PHP OPcache extension';
            }

            if (! $diagnosis['opcache_enabled']) {
                $diagnosis['issues'][ ] = 'OPcache not enabled';
                $diagnosis['recommendations'][ ] = 'Enable OPcache in php.ini with opcache.enable=1';
            }

            if (! $diagnosis['path_exists']) {
                $diagnosis['issues'][ ] = 'Cache directory does not exist';
                $diagnosis['recommendations'][ ] = "Create directory: {$cache_path}";
            }

            if (! $diagnosis['path_writable']) {
                $diagnosis['issues'][ ] = 'Cache directory not writable';
                $diagnosis['recommendations'][ ] = "Fix permissions: chmod 755 {$cache_path}";
            }

            // return the diagnosis
            return $diagnosis;
        }
    }
}
