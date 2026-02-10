<?php

/**
 * Enhanced Cache Configuration Manager
 * Centralizes all cache configuration and settings management
 * Supports global path and prefix settings that act as defaults
 *
 * Provides centralized configuration management for all cache tiers with
 * support for global defaults, backend-specific settings, and validation.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the class doesn't exist
if (! class_exists('CacheConfig')) {

    /**
     * KPT Cache Configuration Manager
     *
     * Manages all cache system configuration including global defaults and
     * tier-specific settings. Provides validation, path management, and
     * configuration export/import capabilities.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class CacheConfig
    {
        // global config across all caching tiers
        private static array $global_config = [
            'path' => null,
            'prefix' => '',
            'allowed_backends' => null, // null means all backends allowed
        ];

        // default configs for the caching tiers
        private static array $default_configs = [
            'array' => [
                'max_items' => 1024,
                'prefix' => null,
            ],
            'redis' => [
                'host' => 'localhost',
                'port' => 6379,
                'database' => 0,
                'prefix' => null,
                'read_timeout' => 0,
                'connect_timeout' => 2,
                'persistent' => true,
                'retry_attempts' => 2,
                'retry_delay' => 100,
            ],
            'memcached' => [
                'host' => 'localhost',
                'port' => 11211,
                'prefix' => null,
                'persistent' => true,
                'retry_attempts' => 2,
                'retry_delay' => 100,
            ],
            'apcu' => [
                'prefix' => null,
                'ttl_default' => 3600,
            ],
            'yac' => [
                'prefix' => null,
                'ttl_default' => 3600,
            ],
            'opcache' => [
                'prefix' => null,
                'cleanup_interval' => 3600,
                'path' => null,
            ],
            'shmop' => [
                'prefix' => null,
                'segment_size' => 1048576,
                'base_key' => 0x12345000,
            ],
            'sqlite' => [
                'db_path' => null,  // will use default path if null
                'table_name' => 'kptv_cache',
                'prefix' => null,
            ],
            'file' => [
                'path' => null,
                'permissions' => 0755,
                'prefix' => null,
            ]
        ];

        // class properties
        private static array $current_configs = [];
        private static bool $initialized = false;

        /**
         * Initialize configuration with defaults
         *
         * Sets up the configuration manager with default values and ensures
         * all backend configurations are properly initialized.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function initialize(): void
        {

            // if we're already initialized, just return
            if (self::$initialized) {
                return;
            }

            // copy default configs to current configs
            self::$current_configs = self::$default_configs;

            // Set default global path if not already set
            if (self::$global_config['path'] === null) {
                // set to system temp directory
                self::$global_config['path'] = sys_get_temp_dir() . '/kpt_cache/';
            }

            // debug logging
            Logger::debug("Cache Config Initialized", []);

            // mark as initialized
            self::$initialized = true;
        }

        /**
         * Set global cache path used as default for all backends
         *
         * Sets the global cache directory path that will be used as a default
         * for all cache backends that don't have specific paths configured.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path The global cache directory path
         * @return bool Returns true if path was set successfully, false otherwise
         */
        public static function setGlobalPath(string $path): bool
        {
            // Normalize the path (ensure it ends with a slash)
            $normalized_path = rtrim($path, '/') . '/';

            // Validate path is accessible
            if (! is_dir(dirname($normalized_path)) && ! mkdir(dirname($normalized_path), 0755, true)) {
                Logger::error("Error Accessing Path", ['path' => $normalized_path]);
                return false;
            }

            // Set the global path
            self::$global_config['path'] = $normalized_path;

            Logger::debug("Cache Global Path Set", ['path' => $normalized_path]);
            return true;
        }

        /**
         * Set global prefix used as default for all backends
         *
         * Sets the global key prefix that will be used as a default for all
         * cache backends that don't have specific prefixes configured.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $prefix The global key prefix
         * @return void Returns nothing
         */
        public static function setGlobalPrefix(string $prefix): void
        {

            // Ensure prefix ends with appropriate separator if not empty
            if (! empty($prefix) && ! str_ends_with($prefix, ':') && ! str_ends_with($prefix, '_')) {
                // add colon separator
                $prefix .= ':';
            }

            // debug logging
            Logger::debug("Cache Global Prefix Set", ['prefix' => $prefix]);

            // set the global prefix
            self::$global_config['prefix'] = $prefix;
        }

        /**
         * Get global cache path
         *
         * Returns the currently configured global cache directory path.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string|null Returns the global path or null if not set
         */
        public static function getGlobalPath(): ?string
        {

            // make sure we're initialized
            self::initialize();

            // return the global path
            return self::$global_config['path'];
        }

        /**
         * Get global prefix
         *
         * Returns the currently configured global key prefix.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Returns the global prefix
         */
        public static function getGlobalPrefix(): string
        {

            // make sure we're initialized
            self::initialize();

            // return the global prefix
            return self::$global_config['prefix'];
        }

        /**
         * Reset global settings only
         *
         * Resets only the global configuration settings to their defaults
         * while leaving backend-specific configurations unchanged.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function resetGlobal(): void
        {

            // reset global config to defaults
            self::$global_config = [
                'path' => sys_get_temp_dir() . '/kpt_cache/',
                'prefix' => '',
                'allowed_backends' => null,
            ];
        }

        /**
         * Set allowed backends
         *
         * @param array|null $backends Array of backend names to allow, null for all
         * @return void
         */
        public static function setAllowedBackends(?array $backends): void
        {
            self::$global_config['allowed_backends'] = $backends;

            // Reset tier discovery when allowed backends change
            if (class_exists('CacheTierManager')) {
                CacheTierManager::reset();
            }
        }

        /**
         * Get allowed backends
         *
         * @return array|null Returns allowed backends or null if all are allowed
         */
        public static function getAllowedBackends(): ?array
        {
            self::initialize();
            return self::$global_config['allowed_backends'];
        }

        /**
         * Get configuration for specific backend with global defaults applied
         *
         * Returns the complete configuration for a backend with global defaults
         * applied where backend-specific values are not set.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend name to get configuration for
         * @return array Returns the backend configuration array
         */
        public static function get(string $backend): array
        {

            // make sure we're initialized
            self::initialize();

            // if the backend doesn't exist, return empty array
            if (! isset(self::$current_configs[$backend])) {
                return [];
            }

            // get the backend config
            $config = self::$current_configs[$backend];

            // Apply global defaults where backend-specific values are null
            $config = self::applyGlobalDefaults($config, $backend);

            // debug logging
            Logger::debug("Cache Config Get", ['config' => $config]);

            // return the config
            return $config;
        }

        /**
         * Set configuration for specific backend
         *
         * Updates the configuration for a specific cache backend with validation
         * and merging with default values.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend name to configure
         * @param array $config The configuration array
         * @return bool Returns true if configuration was set successfully
         */
        public static function set(string $backend, array $config): bool
        {

            // make sure we're initialized
            self::initialize();

            // if the backend doesn't exist in defaults, return false
            if (! isset(self::$default_configs[$backend])) {
                return false;
            }

            // Validate required fields (but allow null values for global fallback)
            if (! self::validateConfig($backend, $config)) {
                return false;
            }

            // Merge with defaults but preserve null values for global fallback
            self::$current_configs[$backend] = array_merge(
                self::$default_configs[$backend],
                $config
            );

            // debug logging
            Logger::debug("Cache Config Set", ['config' => $config]);

            // return success
            return true;
        }

        /**
         * Get all configurations with global defaults applied
         *
         * Returns all backend configurations along with global settings,
         * useful for debugging and system overview.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns complete configuration data
         */
        public static function getAll(): array
        {

            // make sure we're initialized
            self::initialize();

            // default all configs array
            $all_configs = [];

            // loop over each backend
            foreach (array_keys(self::$current_configs) as $backend) {
                // get the backend config with globals applied
                $all_configs[$backend] = self::get($backend);
            }

            // debug logging
            Logger::debug('Cache Get Full Config', ['config' => [
                'global' => self::$global_config,
                'backends' => $all_configs
            ]]);

            // return global and backend configs
            return [
                'global' => self::$global_config,
                'backends' => $all_configs
            ];
        }

        /**
         * Get backend-specific path (considering different path field names)
         *
         * Returns the appropriate path for a backend, accounting for the fact
         * that different backends use different field names for paths.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend name to get path for
         * @return string|null Returns the backend path or null if not applicable
         */
        public static function getBackendPath(string $backend): ?string
        {

            // get the backend config
            $config = self::get($backend);

            // return the appropriate path based on backend type
            return match ($backend) {
                'file', 'opcache' => $config['path'] ?? null,
                default => null
            };
        }

        /**
         * Set backend-specific path
         *
         * Sets the path for a specific backend, using the appropriate field
         * name for that backend type.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend name to set path for
         * @param string $path The path to set
         * @return bool Returns true if path was set successfully
         */
        public static function setBackendPath(string $backend, string $path): bool
        {

            // make sure we're initialized
            self::initialize();

            // if the backend doesn't exist, return false
            if (! isset(self::$current_configs[$backend])) {
                return false;
            }

            // normalize the path
            $normalized_path = rtrim($path, '/') . '/';

            // Update the appropriate path field based on backend
            $path_field = match ($backend) {
                'file', 'opcache' => 'path',
                default => null
            };

            // if no valid path field, return false
            if ($path_field === null) {
                return false;
            }

            // set the path field
            self::$current_configs[$backend][$path_field] = $normalized_path;

            // return success
            return true;
        }

        /**
         * Validate backend configuration
         *
         * Validates a backend configuration array to ensure all required
         * fields are present and valid.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $backend The backend name to validate
         * @param array $config The configuration to validate
         * @return bool Returns true if configuration is valid
         */
        private static function validateConfig(string $backend, array $config): bool
        {

            // get required fields based on backend type
            $required_fields = match ($backend) {
                'redis' => ['host', 'port'],
                'memcached' => ['host', 'port'],
                'apcu', 'yac', 'opcache', 'shmop', 'file' => [],
                default => []
            };

            // check each required field
            foreach ($required_fields as $field) {
                // if field is missing and no default exists
                if (
                    ! isset($config[$field]) &&
                    ! isset(self::$default_configs[$backend][$field]) &&
                    self::$default_configs[$backend][$field] !== null
                ) {
                    return false;
                }
            }

            // Validate path if provided
            if (isset($config['path']) && $config['path'] !== null) {
                // get the directory
                $dir = dirname($config['path']);

                // if directory doesn't exist and parent isn't writable, return false
                if (! is_dir($dir) && ! is_writable(dirname($dir))) {
                    return false;
                }
            }

            // validate base_path if provided
            if (isset($config['base_path']) && $config['base_path'] !== null) {
                // get the directory
                $dir = dirname($config['base_path']);

                // if directory doesn't exist and parent isn't writable, return false
                if (! is_dir($dir) && ! is_writable(dirname($dir))) {
                    return false;
                }
            }

            // validation passed
            return true;
        }

        /**
         * Validate global configuration
         *
         * Validates the global configuration settings and returns any issues
         * found along with validation status.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns validation results with issues and status
         */
        public static function validateGlobal(): array
        {

            // default issues array
            $issues = [];

            // Check global path
            if (self::$global_config['path']) {
                // get the path and parent directory
                $path = self::$global_config['path'];
                $parent_dir = dirname(rtrim($path, '/'));

                // check if parent directory exists
                if (! is_dir($parent_dir)) {
                    // add issue
                    $issues[] = "Global path parent directory does not exist: {$parent_dir}";

                // check if parent directory is writable
                } elseif (! is_writable($parent_dir)) {
                    // add issue
                    $issues[] = "Global path parent directory is not writable: {$parent_dir}";
                }

                // if path exists but isn't writable
                if (is_dir($path) && ! is_writable($path)) {
                    // add issue
                    $issues[] = "Global path exists but is not writable: {$path}";
                }

            // otherwise
            } else {
                // add issue
                $issues[] = "Global path is not set";
            }

            // Check global prefix
            if (empty(self::$global_config['prefix'])) {
                // add issue
                $issues[] = "Global prefix is empty (this may cause key collisions)";
            }

            // return validation results
            return [
                'valid' => empty($issues),
                'issues' => $issues,
                'global_config' => self::$global_config
            ];
        }

        /**
         * Reset to defaults
         *
         * Resets all configuration (global and backend-specific) back to
         * the original default values.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public static function reset(): void
        {

            // reset current configs to defaults
            self::$current_configs = self::$default_configs;

            // reset global config to defaults
            self::$global_config = [
                'path' => sys_get_temp_dir() . '/kpt_cache/',
                'prefix' => 'KPTV_APP:',
            ];
        }

        /**
         * Import configuration from backup
         *
         * Imports configuration data from a previously exported backup,
         * with validation to ensure data integrity.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $config_data The configuration data to import
         * @return bool Returns true if import was successful
         */
        public static function import(array $config_data): bool
        {

            // check required fields exist
            if (! isset($config_data['global']) || ! isset($config_data['current'])) {
                return false;
            }

            // try to import the configuration
            try {
                // set the configurations
                self::$global_config = $config_data['global'];
                self::$current_configs = $config_data['current'];
                self::$initialized = $config_data['initialized'] ?? true;

                // return success
                return true;

            // whoopsie...
            } catch (\Exception $e) {
                Logger::error('Cache Config Import Error', ['error' => $e -> getMessage()]);
                // return failure
                return false;
            }
        }

        /**
         * Get configuration summary for debugging
         *
         * Provides a summary of which backends are using global settings
         * versus custom settings, useful for debugging and system overview.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns configuration usage summary
         */
        public static function getSummary(): array
        {

            // make sure we're initialized
            self::initialize();

            // setup summary array
            $summary = [
                'global_settings' => self::$global_config,
                'backends_using_global_prefix' => [],
                'backends_using_global_path' => [],
                'backends_with_custom_prefix' => [],
                'backends_with_custom_path' => [],
            ];

            // loop over each backend
            foreach (array_keys(self::$current_configs) as $backend) {
                // get the raw config
                $raw_config = self::$current_configs[$backend];

                // Check prefix usage
                if (! isset($raw_config['prefix']) || $raw_config['prefix'] === null) {
                    // using global prefix
                    $summary['backends_using_global_prefix'][] = $backend;
                } else {
                    // using custom prefix
                    $summary['backends_with_custom_prefix'][] = $backend;
                }

                // Check path usage
                $path_field = match ($backend) {
                    'file', 'opcache' => 'path',
                    default => null
                };

                // if we have a path field and it's using global
                if ($path_field && ( ! isset($raw_config[$path_field]) || $raw_config[$path_field] === null )) {
                    // using global path
                    $summary['backends_using_global_path'][] = $backend;

                // otherwise if we have a path field
                } elseif ($path_field) {
                    // using custom path
                    $summary['backends_with_custom_path'][] = $backend;
                }
            }

            // return the summary
            return $summary;
        }

        /**
         * Apply global defaults to backend configuration
         *
         * Internal method to apply global defaults to a backend configuration
         * where backend-specific values are null or not set.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $config The backend configuration array
         * @param string $backend The backend name
         * @return array Returns configuration with global defaults applied
         */
        private static function applyGlobalDefaults(array $config, string $backend): array
        {

            // Apply global prefix if backend prefix is null
            if (! isset($config['prefix']) || $config['prefix'] === null) {
                // use global prefix
                $config['prefix'] = self::$global_config['prefix'];
            }

            // Apply global path based on backend type
            if ($backend === 'file') {
                // if file backend path is null
                if (! isset($config['path']) || $config['path'] === null) {
                    // use global path
                    $config['path'] = self::$global_config['path'];
                }
            } elseif ($backend === 'opcache') {
                // if opcache path is null
                if (! isset($config['path']) || $config['path'] === null) {
                    // use global path
                    $config['path'] = self::$global_config['path'];
                }
            }

            // return the updated config
            return $config;
        }
    }
}
