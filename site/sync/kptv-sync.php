<?php

declare(strict_types=1);

// Ensure CLI-only execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

// Set up the application path
$appPath = dirname(__DIR__);

// Define KPTV_PATH for the main application
if (!defined('KPTV_PATH')) {
    define('KPTV_PATH', $appPath . '/');
}

// Include the main application's autoloader
require_once $appPath . '/vendor/autoload.php';

// Disable caching for CLI operations
use KPT\Cache;
Cache::configure([
    'enabled' => false,
    'path' => KPTV_PATH . '.cache/',
    'prefix' => 'cli_sync_',
    'allowed_backends' => ['array'],
]);

// Use the main app's KPT class for configuration
use KPT\KPT;
use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\ProviderManager;
use Kptv\IptvSync\SyncEngine;
use Kptv\IptvSync\MissingChecker;
use Kptv\IptvSync\FixupEngine;

class IptvSyncApp
{
    private KpDb $db;
    private ProviderManager $providerManager;
    private SyncEngine $syncEngine;
    private MissingChecker $missingChecker;
    private FixupEngine $fixupEngine;

    public function __construct(array $ignoreFields = [], bool $debug = false, bool $checkAll = false)
    {
        // Get database configuration from main app config file directly
        // to avoid any caching issues
        $configPath = KPTV_PATH . 'assets/config.json';
        
        if (!file_exists($configPath)) {
            throw new RuntimeException('Configuration file not found: ' . $configPath);
        }

        $config = json_decode(file_get_contents($configPath));
        
        if (!$config || !isset($config->database)) {
            throw new RuntimeException('Database configuration not found in application config');
        }

        $dbConfig = $config->database;

        $this->db = new KpDb(
            host: $dbConfig->server ?? 'localhost',
            port: (int) ($dbConfig->port ?? 3306),
            database: $dbConfig->schema ?? '',
            user: $dbConfig->username ?? '',
            password: $dbConfig->password ?? '',
            table_prefix: $dbConfig->tbl_prefix ?? 'kptv_',
            pool_size: 10,
            chunk_size: 1000
        );

        $this->providerManager = new ProviderManager($this->db);
        $this->syncEngine = new SyncEngine($this->db);
        $this->missingChecker = new MissingChecker($this->db, $checkAll);
        $this->fixupEngine = new FixupEngine($this->db, $ignoreFields);
    }

    public function runSync(?int $userId = null, ?int $providerId = null): void
    {
        $providers = $this->providerManager->getProviders($userId, $providerId);

        if (empty($providers)) {
            echo "No providers found\n";
            return;
        }

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($providers as $provider) {
            try {
                echo "Syncing provider {$provider['id']} - {$provider['sp_name']}\n";
                $count = $this->syncEngine->syncProvider($provider);
                $totalSynced += $count;

                $this->providerManager->updateLastSynced($provider['id']);

                unset($count);
            } catch (\Exception $e) {
                $totalErrors++;
                echo "Error syncing provider {$provider['id']}: {$e->getMessage()}\n";
            }

            // Force garbage collection at the end of each iteration
            gc_collect_cycles();
        }

        echo str_repeat('=', 60) . "\n";
        echo "SYNC COMPLETE\n";
        echo str_repeat('=', 60) . "\n";
        echo "Providers processed: " . count($providers) . "\n";
        echo "Streams synced: {$totalSynced}\n";
        echo "Errors: {$totalErrors}\n";
        echo str_repeat('=', 60) . "\n\n";
    }

    public function runTestMissing(?int $userId = null, ?int $providerId = null): void
    {
        $providers = $this->providerManager->getProviders($userId, $providerId);

        if (empty($providers)) {
            echo "No providers found\n";
            return;
        }

        $totalMissing = 0;

        foreach ($providers as $provider) {
            try {
                echo "Checking provider {$provider['id']} - {$provider['sp_name']}\n";
                $missing = $this->missingChecker->checkProvider($provider);
                $totalMissing += count($missing);
                echo sprintf("Found %s missing streams\n", number_format(count($missing)));
            } catch (\Exception $e) {
                echo "Error checking provider {$provider['id']}: {$e->getMessage()}\n";
            }

            gc_collect_cycles();
        }

        echo str_repeat('=', 60) . "\n";
        echo "MISSING CHECK COMPLETE\n";
        echo str_repeat('=', 60) . "\n";
        echo "Providers checked: " . count($providers) . "\n";
        echo "Missing streams: {$totalMissing}\n";
        echo str_repeat('=', 60) . "\n\n";
    }

    public function runFixup(?int $userId = null, ?int $providerId = null): void
    {
        $providers = $this->providerManager->getProviders($userId, $providerId);

        if (empty($providers)) {
            echo "No providers found\n";
            return;
        }

        // Get unique user IDs from providers
        $userIds = array_unique(array_column($providers, 'u_id'));
        
        $totalFixed = 0;

        foreach ($userIds as $uid) {
            try {
                echo "Fixing up streams for user {$uid}\n";
                // Pass a dummy provider array with just u_id
                $count = $this->fixupEngine->fixupProvider(['u_id' => $uid]);
                $totalFixed += $count;
            } catch (\Exception $e) {
                echo "Error fixing user {$uid}: {$e->getMessage()}\n";
            }

            gc_collect_cycles();
        }

        echo str_repeat('=', 60) . "\n";
        echo "FIXUP COMPLETE\n";
        echo str_repeat('=', 60) . "\n";
        echo "Users processed: " . count($userIds) . "\n";
        echo "Records fixed: {$totalFixed}\n";
        echo str_repeat('=', 60) . "\n\n";
    }
        

    public function runCleanup(): void
    {
        echo "Running cleanup...\n";
        echo "  - Removing orphaned streams (provider no longer exists)\n";
        echo "  - Removing duplicate streams (keeping highest ID per URI)\n";
        echo "  - Clearing temporary table\n";

        try {
            $this->db->call_proc('CleanupStreams', fetch: false);
            echo str_repeat('=', 60) . "\n";
            echo "CLEANUP COMPLETE\n";
            echo str_repeat('=', 60) . "\n\n";
        } catch (\Exception $e) {
            echo "Error running cleanup: {$e->getMessage()}\n";
        }
    }
}


function printHelp(): void
{
    echo <<<HELP
IPTV Provider Sync - PHP 8.4

Usage: php kptv-sync.php <action> [options]

Actions:
  sync          Sync streams from providers (updates only s_orig_name and s_stream_uri)
  testmissing   Check for missing streams
  fixup         Run metadata fixup (propagates metadata across matching streams)
  cleanup       Remove orphaned streams, duplicates, and clear temp table

Options:
  --user-id <id>        Filter by user ID
  --provider-id <id>    Filter by provider ID
  --debug               Enable debug logging
  --check-all           Check all streams including inactive (testmissing only)
  --ignore <fields>     Fields to ignore during fixup (comma-separated)
                        Available: tvg_id, logo, tvg_group, name, channel
  --help                Show this help

Examples:
  php kptv-sync.php sync
  php kptv-sync.php sync --user-id 1
  php kptv-sync.php sync --provider-id 32
  php kptv-sync.php testmissing
  php kptv-sync.php testmissing --check-all
  php kptv-sync.php fixup
  php kptv-sync.php fixup --ignore logo,channel
  php kptv-sync.php cleanup

HELP;
}

// Main execution
try {
    // Manually parse arguments to support options anywhere in the command line
    $action = null;
    $options = [];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === '--help') {
            $options['help'] = true;
        } elseif ($arg === '--debug') {
            $options['debug'] = true;
        } elseif ($arg === '--check-all') {
            $options['check-all'] = true;
        } elseif ($arg === '--user-id' && isset($argv[$i + 1])) {
            $options['user-id'] = $argv[++$i];
        } elseif ($arg === '--provider-id' && isset($argv[$i + 1])) {
            $options['provider-id'] = $argv[++$i];
        } elseif ($arg === '--ignore' && isset($argv[$i + 1])) {
            $options['ignore'] = $argv[++$i];
        } elseif ($arg[0] !== '-' && $action === null) {
            $action = $arg;
        }
    }

    if (isset($options['help']) || $action === null) {
        printHelp();
        exit(0);
    }

    $validActions = ['sync', 'testmissing', 'fixup', 'cleanup'];
    if (!in_array($action, $validActions, true)) {
        echo "Error: Invalid action '{$action}'\n\n";
        printHelp();
        exit(1);
    }

    $debug = isset($options['debug']);
    $checkAll = isset($options['check-all']);
    $userId = isset($options['user-id']) ? (int) $options['user-id'] : null;
    $providerId = isset($options['provider-id']) ? (int) $options['provider-id'] : null;

    // Process ignore fields (only applies to fixup)
    $ignoreFields = [];
    if (isset($options['ignore'])) {
        $ignoreFields = array_map('trim', explode(',', $options['ignore']));
        $validIgnoreFields = ['tvg_id', 'logo', 'tvg_group', 'name', 'channel'];
        $invalidFields = array_diff($ignoreFields, $validIgnoreFields);
        if (!empty($invalidFields)) {
            echo "Error: Invalid ignore fields: " . implode(', ', $invalidFields) . "\n";
            exit(1);
        }
        
        if ($action !== 'fixup') {
            echo "Note: --ignore only applies to fixup action\n";
        }
    }

    if ($checkAll && $action !== 'testmissing') {
        echo "Note: --check-all only applies to testmissing action\n";
    }

    if (!empty($ignoreFields) && $action === 'fixup') {
        echo "Ignoring fields: " . implode(', ', $ignoreFields) . "\n";
    }

    if ($checkAll && $action === 'testmissing') {
        echo "Checking all streams (including inactive)\n";
    }

    $app = new IptvSyncApp($ignoreFields, $debug, $checkAll);

    match ($action) {
        'sync' => $app->runSync($userId, $providerId),
        'testmissing' => $app->runTestMissing($userId, $providerId),
        'fixup' => $app->runFixup($userId, $providerId),
        'cleanup' => $app->runCleanup(),
    };

} catch (\Exception $e) {
    echo "Fatal error: {$e->getMessage()}\n";
    if (isset($options['debug']) && $options['debug']) {
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}