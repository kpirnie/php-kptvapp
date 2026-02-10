<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use RuntimeException;

class Config
{
    private static ?self $instance = null;

    private function __construct(
        public readonly string $dbserver,
        public readonly int $dbport,
        public readonly string $dbuser,
        public readonly string $dbpassword,
        public readonly string $dbschema,
        public readonly string $dbTblprefix
    ) {
    }

    public static function load(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Include the main app bootstrap to get access to KPT class
        $appPath = dirname(__DIR__, 2);
        
        // Define KPTV_PATH if not already defined
        if (!defined('KPTV_PATH')) {
            define('KPTV_PATH', $appPath . '/');
        }

        // Include vendor autoload from main app
        require_once $appPath . '/vendor/autoload.php';

        // Use the main app's configuration via KPT class
        use KPT\KPT;

        $dbConfig = KPTV::get_setting('database');

        if (!$dbConfig) {
            throw new RuntimeException('Database configuration not found in main app config');
        }

        self::$instance = new self(
            dbserver: $dbConfig->server ?? 'localhost',
            dbport: (int) ($dbConfig->port ?? 3306),
            dbuser: $dbConfig->username ?? '',
            dbpassword: $dbConfig->password ?? '',
            dbschema: $dbConfig->schema ?? '',
            dbTblprefix: $dbConfig->tbl_prefix ?? 'kptv_'
        );

        return self::$instance;
    }
}