<?php

/**
 * KPT Cache Cleaner - Comprehensive Cache Management Utility (IMPROVED)
 *
 * A utility class for clearing and managing cache across all tiers with support
 * for CLI usage, selective clearing, detailed reporting, and robust error handling.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// use composer's autloader
use Composer\Autoload\ClassLoader;
use Exception;
use RuntimeException;
use KPT\CacheConfig;

// Prevent multiple executions of this script
if (defined('KPT_CACHECLEANER_LOADED')) {
    return;
}
define('KPT_CACHECLEANER_LOADED', true);

// no direct access via web, but allow CLI
if (php_sapi_name() !== 'cli') {
    die('Direct Access is not allowed!');
}

// Check if the class doesn't exist before defining it
if (!class_exists('KPT\CacheCleaner')) {

    /**
     * Cache Cleaner - Comprehensive Cache Management Utility
     *
     * Provides methods for clearing cache data across all tiers with support
     * for CLI operations, selective clearing, detailed reporting, and improved error isolation.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class CacheCleaner
    {
        /**
         * CLI entry point
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $args Command line arguments (without script name)
         * @return int Exit code
         */
        public static function cli(array $args = []): int
        {
            // get our autoloader and try to include it
            $autoloadPath = CacheCleaner::getComposerAutoloadPath();
            if ($autoloadPath && file_exists($autoloadPath)) {
                include_once $autoloadPath;
            } else {
                echo "ERROR: Composer autoload.php not found!\n";
                return 1;
            }

            // hold our CLI arguments
            $args = self::parseArguments();
            $hasErrors = false;

            echo "KPT Cache Cleaner Starting...\n";

            try {
                // if we have the clear_all flag
                if (isset($args['clear_all']) && $args['clear_all']) {
                    echo "Clearing all cache tiers...\n";
                    $result = self::clearAllWithReporting();
                    $hasErrors = !$result['success'];
                }

                // if we have the cleanup flag
                if (isset($args['cleanup']) && $args['cleanup']) {
                    echo "Running cache cleanup (expired items)...\n";
                    $cleaned = self::cleanupWithReporting();
                    echo "Cleanup completed. Removed {$cleaned['total_removed']} expired items.\n";
                    if (!$cleaned['success']) {
                        $hasErrors = true;
                    }
                }

                // if the clear tier argument is set
                if (isset($args['clear_tier'])) {
                    $tier = $args['clear_tier'];
                    echo "Clearing tier: {$tier}...\n";
                    $result = self::clearSpecificTierWithReporting($tier);
                    $hasErrors = !$result['success'];
                }

                // Close connections
                echo "Closing cache connections...\n";
                Cache::close();
            } catch (Exception $e) {
                echo "FATAL ERROR: " . $e->getMessage() . "\n";
                return 1;
            }

            echo "Cache cleaning completed " . ($hasErrors ? "with errors" : "successfully") . ".\n";
            return $hasErrors ? 1 : 0;
        }

        /**
         * Clear all cache tiers with detailed reporting
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Results with success status and details
         */
        private static function clearAllWithReporting(): array
        {
            $results = [
                'success' => true,
                'tiers_attempted' => 0,
                'tiers_succeeded' => 0,
                'tiers_failed' => 0,
                'errors' => []
            ];

            try {
                // Get available tiers
                $availableTiers = CacheTierManager::getAvailableTiers();

                // Apply allowed backends filter
                $allowed_backends = CacheConfig::getAllowedBackends();
                if ($allowed_backends !== null) {
                    $availableTiers = array_intersect($availableTiers, $allowed_backends);
                }

                $results['tiers_attempted'] = count($availableTiers);

                echo "Found " . count($availableTiers) . " available tiers: " . implode(', ', $availableTiers) . "\n";

                // Clear each tier individually with error isolation
                foreach ($availableTiers as $tier) {
                    echo "  Clearing {$tier}... ";

                    try {
                        $tierResult = Cache::clearSpecificTier($tier);

                        if ($tierResult) {
                            echo "SUCCESS\n";
                            $results['tiers_succeeded']++;
                        } else {
                            echo "FAILED\n";
                            $results['tiers_failed']++;
                            $results['errors'][] = "Failed to clear tier: {$tier}";
                            $results['success'] = false;
                        }
                    } catch (Exception $e) {
                        echo "ERROR: " . $e->getMessage() . "\n";
                        $results['tiers_failed']++;
                        $results['errors'][] = "Exception clearing {$tier}: " . $e->getMessage();
                        $results['success'] = false;
                    }
                }
            } catch (Exception $e) {
                echo "FATAL ERROR during tier discovery: " . $e->getMessage() . "\n";
                $results['success'] = false;
                $results['errors'][] = "Fatal error: " . $e->getMessage();
            }

            // Report summary
            echo "\nClear All Summary:\n";
            echo "  Attempted: {$results['tiers_attempted']}\n";
            echo "  Succeeded: {$results['tiers_succeeded']}\n";
            echo "  Failed: {$results['tiers_failed']}\n";

            if (!empty($results['errors'])) {
                echo "  Errors:\n";
                foreach ($results['errors'] as $error) {
                    echo "    - {$error}\n";
                }
            }

            return $results;
        }

        /**
         * Clean up expired items with reporting
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Results with counts and status
         */
        private static function cleanupWithReporting(): array
        {
            $results = [
                'success' => true,
                'total_removed' => 0,
                'errors' => []
            ];

            try {
                $removed = Cache::cleanup();
                $results['total_removed'] = $removed;
            } catch (Exception $e) {
                $results['success'] = false;
                $results['errors'][] = "Cleanup error: " . $e->getMessage();
                echo "ERROR during cleanup: " . $e->getMessage() . "\n";
            }

            return $results;
        }

        /**
         * Clear a specific tier with reporting
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to clear
         * @return array Results with success status
         */
        private static function clearSpecificTierWithReporting(string $tier): array
        {
            $results = [
                'success' => false,
                'tier' => $tier,
                'errors' => []
            ];

            // Validate tier first
            $validTiers = CacheTierManager::getValidTiers();
            if (!in_array($tier, $validTiers)) {
                $error = "Invalid tier '{$tier}'. Valid tiers: " . implode(', ', $validTiers);
                echo "ERROR: {$error}\n";
                $results['errors'][] = $error;
                return $results;
            }

            // Check if tier is allowed by configuration
            $allowedCheck = self::validateTierAllowed($tier);
            if (!$allowedCheck['allowed']) {
                echo "ERROR: {$allowedCheck['message']}\n";
                $results['errors'][] = $allowedCheck['message'];
                return $results;
            }

            // Check if tier is available
            if (!CacheTierManager::isTierAvailable($tier)) {
                echo "WARNING: Tier '{$tier}' is not available on this system.\n";
                $results['success'] = true; // Consider unavailable as "cleared"
                return $results;
            }

            try {
                $success = Cache::clearSpecificTier($tier);

                if ($success) {
                    echo "Successfully cleared tier: {$tier}\n";
                    $results['success'] = true;
                } else {
                    $error = "Failed to clear tier: {$tier}";
                    echo "ERROR: {$error}\n";
                    $results['errors'][] = $error;
                }
            } catch (Exception $e) {
                $error = "Exception clearing tier {$tier}: " . $e->getMessage();
                echo "ERROR: {$error}\n";
                $results['errors'][] = $error;
            }

            return $results;
        }

        /**
         * Validate tier against allowed backends configuration
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $tier The tier to validate
         * @return array Returns validation results
         */
        private static function validateTierAllowed(string $tier): array
        {
            $allowed_backends = CacheConfig::getAllowedBackends();

            if ($allowed_backends !== null && !in_array($tier, $allowed_backends)) {
                return [
                    'allowed' => false,
                    'message' => "Tier '{$tier}' is not in the allowed backends list: " . implode(', ', $allowed_backends)
                ];
            }

            return ['allowed' => true, 'message' => ''];
        }

        /**
         * Parse the arguments passed to the script
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Array of arguments passed
         */
        private static function parseArguments(): array
        {
            // setup the argv global and hold the options return
            global $argv;
            $options = [];

            // if we only have 1 argument (which is the script name)
            if (count($argv) > 1) {
                // loop over the arguments, but skip the first one
                foreach (array_slice($argv, 1) as $arg) {
                    // set the arguments to the return options
                    if ($arg === '--clear_all') {
                        $options['clear_all'] = true;
                    } elseif (strpos($arg, '--clear_tier=') === 0) {
                        $tier = substr($arg, strlen('--clear_tier='));
                        $options['clear_tier'] = $tier;
                    } elseif ($arg === '--cleanup') {
                        $options['cleanup'] = true;
                    } elseif ($arg === '--help' || $arg === '-h') {
                        self::showHelp();
                        exit(0);
                    }
                }
            } else {
                self::showHelp();
                exit(0);
            }

            // return the options
            return $options;
        }

        /**
         * Show help information
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void
         */
        private static function showHelp(): void
        {
            echo "KPT Cache Cleaner\n";
            echo "Usage: php cleaner.php [OPTIONS]\n\n";
            echo "OPTIONS:\n";
            echo "  --clear_all              Clear all cache tiers\n";
            echo "  --clear_tier=TIER        Clear specific tier (array, redis, memcached, etc.)\n";
            echo "  --cleanup                Clean up expired items from all tiers\n";
            echo "  --help, -h               Show this help message\n\n";
            echo "Examples:\n";
            echo "  php cleaner.php --clear_all\n";
            echo "  php cleaner.php --clear_tier=redis\n";
            echo "  php cleaner.php --cleanup\n";
        }

        /**
         * Get the path to composer's autoload.php file
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Path to autoload.php
         * @throws RuntimeException If autoload.php cannot be found
         */
        public static function getComposerAutoloadPath(): string
        {
            // Check if ClassLoader exists (Composer is installed)
            if (!class_exists(ClassLoader::class)) {
                // Try to find it by traversing directories
                $dir = __DIR__;
                $maxDepth = 10; // Safety limit

                // loop the path until we find the vendor autoload
                while ($dir !== '/' && $maxDepth-- > 0) {
                    // Check for custom main.php first, then standard autoload.php
                    $customAutoloadPath = $dir . '/vendor/main.php';
                    $standardAutoloadPath = $dir . '/vendor/autoload.php';

                    if (file_exists($customAutoloadPath)) {
                        return $customAutoloadPath;
                    }

                    if (file_exists($standardAutoloadPath)) {
                        return $standardAutoloadPath;
                    }

                    $dir = dirname($dir);
                }

                // cant find it at all... throw an exception
                throw new RuntimeException('Composer autoloader not found. Make sure Composer dependencies are installed.');
            }

            // we need reflection here to get composer's autoloader ;)
            $reflection = new \ReflectionClass(ClassLoader::class);
            $vendorDir = dirname($reflection->getFileName(), 2);

            // Check for custom main.php first, then standard autoload.php
            $customAutoloadPath = $vendorDir . '/main.php';
            $standardAutoloadPath = $vendorDir . '/autoload.php';

            if (file_exists($customAutoloadPath)) {
                return $customAutoloadPath;
            }

            if (file_exists($standardAutoloadPath)) {
                return $standardAutoloadPath;
            }

            // if neither file exists, throw an exception
            throw new RuntimeException('Composer autoloader not found at: ' . $vendorDir);
        }
    }
}

// CLI execution if called directly
if (php_sapi_name() === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    // clean the cache
    exit(CacheCleaner::cli());
}
