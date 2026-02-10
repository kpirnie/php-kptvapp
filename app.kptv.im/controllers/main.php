<?php

/**
 * main.php
 * 
 * This is the main include for the app
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// try to manage the session as early as possible
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// hold the app path
$appPath = dirname(__FILE__, 2) . '/';

// include our vendor autoloader
include_once $appPath . 'vendor/autoload.php';

// define the primary app path if not already defined
defined('KPTV_PATH') || define('KPTV_PATH', $appPath);

// create our fake alias if it doesn't already exist
if (! class_exists('KPTV')) {

    // redeclare this
    class KPTV extends KPTV_Static {}
}

// setup the database config definitions
$_db = KPTV::get_setting('database');

// configure the cache
\KPT\Cache::configure([
    'path' => KPTV_PATH . '.cache/',
    'prefix' => KPTV::get_cache_prefix(),
    'allowed_backends' => ['array', 'redis', 'memcached', 'opcache',], // also: apcu, yac, mysql, sqlite, shmop, file
]);

// define the app URI
defined('KPTV_URI') || define('KPTV_URI', KPTV::get_setting('mainuri') . '/');
defined('KPTV_XC_URI') || define('KPTV_XC_URI', KPTV::get_setting('xcuri'));

// define our app name
defined('APP_NAME') || define('APP_NAME', KPTV::get_setting('appname'));

// try to manage the session as early as possible
KPTV::manage_the_session();

// setup our environment
$_debug = KPTV::get_setting('debug_app') ?? false;
defined('KPTV_DEBUG') || define('KPTV_DEBUG', $_debug);

// if we are debugging
if ($_debug) {

    // force PHP to render our errors
    @ini_set('display_errors', 1);
    @ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {

    // force php to NOT render our errors
    @ini_set('display_errors', 0);
    error_reporting(0);
}

// initialize the logger
new \KPT\Logger(KPTV_DEBUG);

// hold our constant definitions
defined('DB_SERVER') || define('DB_SERVER', $_db->server);
defined('DB_SCHEMA') || define('DB_SCHEMA', $_db->schema);
defined('DB_USER') || define('DB_USER', $_db->username);
defined('DB_PASS') || define('DB_PASS', $_db->password);
defined('TBL_PREFIX') || define('TBL_PREFIX', $_db->tbl_prefix);

// hold the global cli args
global $argv;

// make sure this only runs if called from a web browser
if (
    php_sapi_name() !== 'cli' &&
    (! isset($argv) ||
        ! is_array($argv) ||
        empty($argv) ||
        realpath($argv[0]) !==
        realpath(__FILE__))
) {

    // make sure the routes file exists
    if (file_exists(KPTV_PATH . 'views/routes.php')) {

        // hold the routes path
        $routes_path = KPTV_PATH . 'views/routes.php';

        // Initialize the router with explicit base path
        $router = new \KPT\Router('');

        // enable the redis rate limiter
        $router->enableRateLimiter();

        // if the routes file exists... load it in to add the routes
        if (file_exists($routes_path)) {
            include_once $routes_path;
        }

        // Dispatch the router
        try {
            // Debug - check if routes are registered
            error_log("Registered routes: " . print_r($router->getRoutes(), true));
            $router->dispatch();

            // whoopsie...
        } catch (Throwable $e) {

            // log the error then throw a json response
            \KPT\Logger::error("Router error: " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }
}
