<?php

/**
 * KPT Cache - File Caching Trait
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheFile')) {

    /**
     * KPT Cache File Trait
     *
     * Provides file-based caching functionality with comprehensive management
     * features including directory creation, permissions handling, and cleanup.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheFile
    {
        /**
         * Create cache directory with proper permissions
         * Fixed to handle trailing slashes and is_writable() quirks
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Directory path to create
         * @return bool Returns true if directory was created or already exists and is writable
         */
        private static function createCacheDirectory(string $path): bool
        {

            // Normalize the path - ensure it ends with a slash for consistency
            $path = rtrim($path, '/') . '/';

            // For checking, use the path WITHOUT trailing slash
            // This avoids is_writable() issues on some systems
            $check_path = rtrim($path, '/');

            // Check if directory exists
            if (! is_dir($check_path)) {
                // Try to create the directory
                if (! @mkdir($path, 0755, true)) {
                    $error = error_get_last();
                    Logger::error("Failed to create cache directory", [
                        'path' => $path,
                        'error' => $error['message'] ?? 'Unknown error'
                    ]);
                    return false;
                }
                Logger::debug("Created cache directory", ['path' => $path]);
            }

            // Check if writable - use path WITHOUT trailing slash
            if (! is_writable($check_path)) {
                // Try a actual write test as is_writable() can be unreliable
                $test_file = $path . '.write_test_' . uniqid();
                $write_test = @file_put_contents($test_file, 'test');

                if ($write_test !== false) {
                    // Write succeeded, directory is actually writable
                    @unlink($test_file);
                    Logger::debug("Directory is writable (write test passed)", ['path' => $path]);
                    return true;

                // otherwise, it's really not writable
                } else {
                    Logger::debug("Directory not writable (trying next fallback)", [
                        'path' => $path,
                        'check_path' => $check_path,
                        'is_dir' => is_dir($check_path),
                        'file_exists' => file_exists($check_path),
                        'permissions' => file_exists($check_path) ? substr(sprintf('%o', fileperms($check_path)), -4) : 'N/A',
                        'owner' => file_exists($check_path) ? fileowner($check_path) : 'N/A',
                        'current_user' => get_current_user()
                    ]);
                    return false;
                }
            }

            Logger::debug("Directory verified as writable", ['path' => $path]);
            return true;
        }

        /**
         * Set a custom cache path for file-based caching
         *
         * Configures a custom directory path for file caching operations
         * and ensures proper permissions are set.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_path The custom cache directory path
         * @return bool Returns true if successful, false otherwise
         */
        public static function setCachePath(string $_path): bool
        {

            // Normalize the path (ensure it ends with a slash)
            $_path = rtrim($_path, '/') . '/';

            // Try to create the cache directory with proper permissions
            $config = CacheConfig::get('file');
            $permissions = $config['permissions'] ?? 0755;

            // try to create the directory
            if (self::createCacheDirectory($_path, $permissions)) {
                // Update the configuration
                CacheConfig::set('file', array_merge($config, ['path' => $_path]));

                // set the configurable cache path
                self::$_configurable_cache_path = $_path;

                // If we're already initialized, update the fallback path immediately
                if (self::$_initialized) {
                    self::$_fallback_path = $_path;
                }

                // return success
                return true;
            }

            // failed to create directory
            return false;
        }

        /**
         * Get the current cache path being used
         *
         * Returns the current cache directory path that's being used
         * for file-based caching operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Returns the current cache directory path
         */
        public static function getCachePath(): string
        {

            // First check if we have a specific fallback path set
            if (self::$_fallback_path !== null) {
                return self::$_fallback_path;
            }

            // Then check if there's a global path configured
            $global_path = CacheConfig::getGlobalPath();
            if ($global_path !== null) {
                return $global_path;
            }

            // Finally fall back to system temp directory
            return sys_get_temp_dir() . '/kpt_cache/';
        }

        /**
         * Get item from file cache
         *
         * Retrieves a cached item from the file system with proper
         * file locking and expiration checking.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found/expired
         */
        private static function getFromFile(string $_key): mixed
        {
            // Setup the cache file
            $file = self::getCachePath() . md5($_key);

            // If it exists
            if (file_exists($file)) {
                try {
                    // Read file contents
                    $data = file_get_contents($file);

                    if ($data === false || strlen($data) < 10) {
                        return false;
                    }

                    // Setup its expiry
                    $expires = substr($data, 0, 10);

                    // Is it supposed to expire
                    if (is_numeric($expires) && time() > (int)$expires) {
                        // Delete it and return false
                        @unlink($file);
                        return false;
                    }

                    // Return the unserialized data
                    return unserialize(substr($data, 10));
                } catch (\Exception $e) {
                    self::$_last_error = "File cache read error: " . $e->getMessage();
                    return false;
                }
            }

            // file doesn't exist
            return false;
        }

        /**
         * Set item to file cache
         *
         * Stores an item in the file cache with expiration timestamp
         * and exclusive file locking for data integrity.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to store
         * @param mixed $_data The data to cache
         * @param int $_length Time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToFile(string $_key, mixed $_data, int $_length): bool
        {
            // setup file path and data
            $file = self::getCachePath() . md5($_key);
            $expires = time() + $_length;
            $data = $expires . serialize($_data);

            // try to write the file
            try {
                // Write with exclusive lock
                $result = file_put_contents($file, $data, LOCK_EX);
                return $result !== false;
            } catch (\Exception $e) {
                self::$_last_error = "File cache write error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Delete item from file cache
         *
         * Removes a cached item from the file system by deleting
         * the corresponding cache file.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $_key The cache key to delete
         * @return bool Returns true if deleted or didn't exist, false on error
         */
        private static function deleteFromFile(string $_key): bool
        {

            // setup the file path
            $file = self::getCachePath() . md5($_key);

            // check if file exists and delete it
            if (file_exists($file)) {
                return @unlink($file);
            }

            // File doesn't exist, consider it deleted
            return true;
        }

        /**
         * Clear all file cache
         *
         * Removes all cached files from the cache directory
         * to completely clear the file cache.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if all files cleared, false if some failed
         */
        public static function clearFileCache(): bool
        {

            // get cache path and files
            $cache_path = self::getCachePath();
            $files = glob($cache_path . '*');
            $success = true;

            // loop through each file and delete it
            foreach ($files as $file) {
                // check if it's a file and delete it
                if (is_file($file)) {
                    // try to delete the file
                    if (! @unlink($file)) {
                        $success = false;
                    }
                }
            }

            // return overall success status
            return $success;
        }

        /**
         * Cleans up expires items from the cache
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of items removed
         */
        private static function cleanupFile(): int
        {

            // setup the count to return
            $count = 0;

                        // Clean up file cache
            $files = glob(self::getCachePath() . '*');

            // loop over each file
            foreach ($files as $file) {
                // if it's a real file
                if (is_file($file)) {
                    // get the file contents
                    $content = file_get_contents($file);

                    // if we have content
                    if ($content !== false) {
                        // get the expiry time
                        $expires = substr($content, 0, 10);

                        // if it's numeric and expired
                        if (is_numeric($expires) && time() > (int)$expires) {
                            // if we can unlink it, increment the count
                            if (unlink($file)) {
                                $count++;
                            }
                        }
                    }
                }
            }

            // return the count
            return $count;
        }

        /**
         * Get detailed information about the cache path and permissions
         *
         * Returns comprehensive information about the cache directory
         * including permissions, ownership, and accessibility.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of cache path information
         */
        public static function getCachePathInfo(): array
        {

            // get the cache path
            $path = self::getCachePath();

            // setup the info array
            $info = [
                'path' => $path,
                'exists' => false,
                'is_dir' => false,
                'is_writable' => false,
                'is_readable' => false,
                'permissions' => null,
                'owner' => null,
                'parent_writable' => false,
            ];

            // check if path exists and gather info
            if ($path) {
                // get basic path information
                $info['exists'] = file_exists($path);
                $info['is_dir'] = is_dir($path);
                $info['is_writable'] = is_writable($path);
                $info['is_readable'] = is_readable($path);

                // get detailed info if path exists
                if ($info['exists']) {
                    // get file permissions
                    $info['permissions'] = substr(sprintf('%o', fileperms($path)), -4);

                    // get owner info if functions exist
                    if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
                        $owner_info = posix_getpwuid(fileowner($path));
                        $info['owner'] = $owner_info ? $owner_info['name'] : fileowner($path);
                    }
                }

                // Check if parent directory is writable
                $parent = dirname(rtrim($path, '/'));
                $info['parent_writable'] = is_writable($parent);
                $info['parent_path'] = $parent;
            }

            // return the info array
            return $info;
        }

        /**
         * Attempt to fix cache directory permissions
         *
         * Tries to repair cache directory permissions by attempting
         * different permission levels and recreation if necessary.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if permissions fixed, false otherwise
         */
        public static function fixCachePermissions(): bool
        {

            // get the cache path
            $path = self::getCachePath();

            // check if path exists
            if (! $path || ! file_exists($path)) {
                return false;
            }

            // try to fix permissions
            try {
                // Try different permission levels
                $permission_levels = [ 0755, 0775, 0777 ];

                // loop through each permission level
                foreach ($permission_levels as $perms) {
                    // try to change permissions
                    if (@chmod($path, $perms)) {
                        // check if it's now writable
                        if (is_writable($path)) {
                            return true;
                        }
                    }
                }

                // If chmod failed, try recreating the directory
                if (is_dir($path)) {
                    // Try to remove and recreate (only if empty or only contains cache files)
                    $files = glob($path . '*');
                    $safe_to_recreate = true;

                    // Check if all files look like cache files (md5 hashes)
                    foreach ($files as $file) {
                        // get the basename and check if it's a valid md5 hash
                        $basename = basename($file);
                        if (! preg_match('/^[a-f0-9]{32}$/', $basename)) {
                            $safe_to_recreate = false;
                            break;
                        }
                    }

                    // check if it's safe to recreate
                    if ($safe_to_recreate) {
                        // Remove cache files
                        foreach ($files as $file) {
                            @unlink($file);
                        }

                        // Remove directory and recreate
                        if (@rmdir($path)) {
                            return self::createCacheDirectory($path);
                        }
                    }
                }

            // whoopsie... setup the error
            } catch (\Exception $e) {
                self::$_last_error = "Permission fix failed: " . $e -> getMessage();
            }

            // failed to fix permissions
            return false;
        }

        /**
         * Get suggested alternative cache paths for troubleshooting
         *
         * Provides a list of alternative cache directory paths
         * for troubleshooting when the current path has issues.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of suggested cache paths with status
         */
        public static function getSuggestedCachePaths(): array
        {

            // setup suggestions array
            $suggestions = [
                'current' => self::getCachePath(),
                'alternatives' => [ ]
            ];

            // setup test paths to check
            $test_paths = [
                sys_get_temp_dir() . '/kpt_cache_alt/',
                getcwd() . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/kpt_cache_alt/',
                sys_get_temp_dir() . '/cache/',
            ];

            // test each path
            foreach ($test_paths as $path) {
                // setup status array for this path
                $status = [
                    'path' => $path,
                    'parent_exists' => file_exists(dirname($path)),
                    'parent_writable' => is_writable(dirname($path)),
                    'can_create' => false,
                    'recommended' => false
                ];

                // Test if we can create a test directory
                $test_dir = $path . 'test_' . uniqid();
                if (@mkdir($test_dir, 0755, true)) {
                    $status['can_create'] = true;
                    $status['recommended'] = is_writable($test_dir);
                    @rmdir($test_dir);
                }

                // add to suggestions
                $suggestions['alternatives'][ ] = $status;
            }

            // return the suggestions
            return $suggestions;
        }

        /**
         * Get file cache statistics
         *
         * Returns comprehensive statistics about the file cache
         * including file counts, sizes, and expiration information.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of file cache statistics
         */
        private static function getFileCacheStats(): array
        {

            // get cache path and files
            $cache_path = self::getCachePath();
            $files = glob($cache_path . '*');

            // setup stats array
            $stats = [
                'path' => $cache_path,
                'total_files' => 0,
                'total_size' => 0,
                'total_size_human' => '0 B',
                'expired_files' => 0,
                'valid_files' => 0,
                'oldest_file' => null,
                'newest_file' => null
            ];

            // check if we have files
            if (! is_array($files)) {
                return $stats;
            }

            // setup counters and trackers
            $stats['total_files'] = count($files);
            $now = time();
            $oldest = null;
            $newest = null;

            // loop through each file
            foreach ($files as $file) {
                // skip if not a file
                if (! is_file($file)) {
                    continue;
                }

                // add to total size
                $size = filesize($file);
                $stats['total_size'] += $size;

                // track oldest and newest modification times
                $mtime = filemtime($file);
                if ($oldest === null || $mtime < $oldest) {
                    $oldest = $mtime;
                }
                if ($newest === null || $mtime > $newest) {
                    $newest = $mtime;
                }

                // Check if file is expired by reading expiration timestamp
                try {
                    // open file and read expiration data
                    $handle = fopen($file, 'rb');
                    if ($handle) {
                        // read the expiration timestamp
                        $expires_data = fread($handle, 10);
                        fclose($handle);

                        // check if it's a valid timestamp
                        if (is_numeric($expires_data)) {
                            // check if expired
                            $expires = (int)$expires_data;
                            if ($now > $expires) {
                                $stats['expired_files']++;
                            } else {
                                $stats['valid_files']++;
                            }
                        }
                    }

                // whoopsie... skip files we can't read
                } catch (\Exception $e) {
                    // Skip files we can't read
                }
            }

            // format human readable size and dates
            $stats['total_size_human'] = KPT::format_bytes($stats['total_size']);
            $stats['oldest_file'] = $oldest ? date('Y-m-d H:i:s', $oldest) : null;
            $stats['newest_file'] = $newest ? date('Y-m-d H:i:s', $newest) : null;

            // return the stats
            return $stats;
        }

        /**
         * Clean up expired file cache entries
         *
         * Removes all expired cache files from the cache directory
         * to free up disk space and maintain optimal performance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of expired files removed
         */
        private static function cleanupExpiredFiles(): int
        {

            // get cache path and files
            $cache_path = self::getCachePath();
            $files = glob($cache_path . '*');
            $cleaned = 0;
            $now = time();

            // check if we have files
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
                    // open file and read expiration data
                    $handle = fopen($file, 'rb');
                    if (! $handle) {
                        continue;
                    }

                    // read the expiration timestamp
                    $expires_data = fread($handle, 10);
                    fclose($handle);

                    // check if expired and delete
                    if (is_numeric($expires_data)) {
                        // check if expired
                        $expires = (int)$expires_data;
                        if ($now > $expires) {
                            // delete expired file
                            if (@unlink($file)) {
                                $cleaned++;
                            }
                        }
                    }

                // whoopsie... skip files we can't process
                } catch (\Exception $e) {
                    // Skip files we can't process
                }
            }

            // return number of files cleaned
            return $cleaned;
        }

        /**
         * Get list of cache files with details
         *
         * Returns a detailed list of all cache files including
         * expiration information, sizes, and validity status.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of cache file information
         */
        public static function getFileCacheList(): array
        {

            // get cache path and files
            $cache_path = self::getCachePath();
            $files = glob($cache_path . '*');
            $file_list = [ ];
            $now = time();

            // check if we have files
            if (! is_array($files)) {
                return [ ];
            }

            // loop through each file
            foreach ($files as $file) {
                // skip if not a file
                if (! is_file($file)) {
                    continue;
                }

                // setup file info array
                $file_info = [
                    'filename' => basename($file),
                    'full_path' => $file,
                    'size' => filesize($file),
                    'size_human' => KPT::format_bytes(filesize($file)),
                    'created' => filectime($file),
                    'modified' => filemtime($file),
                    'expires' => null,
                    'expired' => false,
                    'ttl_remaining' => null,
                    'valid' => false
                ];

                // Try to read expiration info
                try {
                    // open file and read expiration data
                    $handle = fopen($file, 'rb');
                    if ($handle) {
                        // read the expiration timestamp
                        $expires_data = fread($handle, 10);
                        fclose($handle);

                        // process expiration data
                        if (is_numeric($expires_data)) {
                            // setup expiration info
                            $expires = (int)$expires_data;
                            $file_info['expires'] = $expires;
                            $file_info['expired'] = $now > $expires;
                            $file_info['ttl_remaining'] = max(0, $expires - $now);
                            $file_info['valid'] = true;
                        }
                    }

                // whoopsie... add error to file info
                } catch (\Exception $e) {
                    $file_info['error'] = $e -> getMessage();
                }

                // add to file list
                $file_list[ ] = $file_info;
            }

            // Sort by modification time (newest first)
            usort($file_list, function ($a, $b) {
                return $b['modified'] - $a['modified'];
            });

            // return the file list
            return $file_list;
        }

        /**
         * Test file cache functionality
         *
         * Performs a basic functionality test to ensure the file cache
         * is working properly by testing store, retrieve, and delete operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if test passes, false otherwise
         */
        private static function testFileCacheConnection(): bool
        {

            // try to test the file cache
            try {
                // setup test data
                $test_key = 'file_test_' . uniqid();
                $test_value = 'test_value_' . time();

                // Try to store and retrieve
                if (self::setToFile($test_key, $test_value, 60)) {
                    // get the stored value
                    $retrieved = self::getFromFile($test_key);

                    // clean up the test file
                    self::deleteFromFile($test_key);

                    // return comparison result
                    return $retrieved === $test_value;
                }

                // failed to store
                return false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "File cache test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Backup cache directory
         *
         * Creates a backup of the entire cache directory by copying
         * all cache files to a specified backup location.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backup_path The path where to create the backup
         * @return bool Returns true if backup successful, false otherwise
         */
        public static function backupCache(string $backup_path): bool
        {

            // get the source path
            $source_path = self::getCachePath();

            // check if source directory exists
            if (! is_dir($source_path)) {
                return false;
            }

            // try to create backup
            try {
                // Create backup directory
                if (! is_dir($backup_path)) {
                    // create the backup directory
                    if (! mkdir($backup_path, 0755, true)) {
                        return false;
                    }
                }

                // get all files to backup
                $files = glob($source_path . '*');
                if (! is_array($files)) {
                    return true; // No files to backup
                }

                // copy each file to backup location
                foreach ($files as $file) {
                    // check if it's a file
                    if (is_file($file)) {
                        // setup destination path
                        $filename = basename($file);
                        $destination = $backup_path . '/' . $filename;

                        // copy the file
                        if (! copy($file, $destination)) {
                            return false;
                        }
                    }
                }

                // backup successful
                return true;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "Cache backup failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Restore cache from backup
         *
         * Restores cache files from a backup directory by copying
         * all backup files to the current cache directory.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backup_path The path where the backup is located
         * @return bool Returns true if restore successful, false otherwise
         */
        public static function restoreCache(string $backup_path): bool
        {

            // get the target path
            $target_path = self::getCachePath();

            // check if backup directory exists
            if (! is_dir($backup_path)) {
                return false;
            }

            // try to restore from backup
            try {
                // Ensure target directory exists
                if (! is_dir($target_path)) {
                    // create the target directory
                    if (! self::createCacheDirectory($target_path)) {
                        return false;
                    }
                }

                // get all files to restore
                $files = glob($backup_path . '/*');
                if (! is_array($files)) {
                    return true; // No files to restore
                }

                // copy each file from backup
                foreach ($files as $file) {
                    // check if it's a file
                    if (is_file($file)) {
                        // setup destination path
                        $filename = basename($file);
                        $destination = $target_path . $filename;

                        // copy the file
                        if (! copy($file, $destination)) {
                            return false;
                        }
                    }
                }

                // restore successful
                return true;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "Cache restore failed: " . $e -> getMessage();
                return false;
            }
        }
    }
}
