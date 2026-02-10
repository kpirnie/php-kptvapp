<?php

/**
 * KPT_Routes
 * 
 * This class provides a comprehensive routing solution for the KPTV Manager application.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// =============================================================
// ==================== MIDDLEWARE DEFINITIONS ===============
// =============================================================

$middlewareDefinitions = [

    // Guest-only middleware (user must NOT be logged in)
    'guest_only' => function () {
        if (KPTV_User::is_user_logged_in()) {
            KPTV::message_with_redirect('/', 'danger', 'You are already logged in.');
            return false;
        }
        return true;
    },

    // Authentication required middleware
    'auth_required' => function () {
        if (! KPTV_User::is_user_logged_in()) {
            KPTV::message_with_redirect('/users/login', 'danger', 'You must be logged in to access this page.');
            return false;
        }
        return true;
    },

    // Admin-only middleware
    'admin_required' => function () {
        if (! KPTV_User::is_user_logged_in()) {
            KPTV::message_with_redirect('/users/login', 'danger', 'You must be logged in to access this page.');
            return false;
        }

        $user = KPTV_User::get_current_user();
        if ($user->role != 99) {
            KPTV::message_with_redirect('/', 'danger', 'You do not have permission to access this page.');
            return false;
        }

        return true;
    },

];

// =============================================================
// ===================== ROUTE DEFINITIONS ====================
// =============================================================

// =============================================================
// ===================== GET ROUTES ============================
// =============================================================

// Static page routes
$get_static_routes = [
    // Home page route
    [
        'method' => 'GET',
        'path' => '/',
        'handler' => 'view:pages/home.php',
        'should_cache' => true,
        'cache_length' => KPTV::DAY_IN_SECONDS
    ],
    // Stream FAQ
    [
        'method' => 'GET',
        'path' => '/streams/faq',
        'handler' => 'view:pages/stream/faq.php',
        'should_cache' => true,
        'cache_length' => KPTV::DAY_IN_SECONDS
    ],
    // Account FAQ
    [
        'method' => 'GET',
        'path' => '/users/faq',
        'handler' => 'view:pages/users/faq.php',
        'should_cache' => true,
        'cache_length' => KPTV::DAY_IN_SECONDS
    ],

];

// User-related GET routes
$get_user_routes = [
    // Login page
    [
        'method' => 'GET',
        'path' => '/users/login',
        'middleware' => ['guest_only'],
        'handler' => 'view:pages/users/login.php'
    ],

    // Logout action (using controller)
    [
        'method' => 'GET',
        'path' => '/users/logout',
        'middleware' => ['auth_required'],
        'handler' => 'KPTV_User@logout' // Class@Method
    ],

    // Registration page
    [
        'method' => 'GET',
        'path' => '/users/register',
        'middleware' => ['guest_only'],
        'handler' => 'view:pages/users/register.php'
    ],

    // Forgot password page
    [
        'method' => 'GET',
        'path' => '/users/forgot',
        'middleware' => ['guest_only'],
        'handler' => 'view:pages/users/forgot.php'
    ],

    // Change password page
    [
        'method' => 'GET',
        'path' => '/users/changepass',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/users/changepass.php'
    ],

    // Account validation (using controller)
    [
        'method' => 'GET',
        'path' => '/validate',
        'handler' => 'KPTV_User@validate_user' // Class@Method
    ],
];

// Stream-related GET routes
$get_stream_routes = [
    // Providers management
    [
        'method' => 'GET',
        'path' => '/providers',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/providers.php'
    ],

    // Filters management
    [
        'method' => 'GET',
        'path' => '/filters',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/filters.php'
    ],

    // missing streams
    [
        'method' => 'GET',
        'path' => '/missing',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/missing.php'
    ],

    // Streams management
    [
        'method' => 'GET',
        'path' => '/streams/{which}',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/streams.php',
        'data' => ['currentRoute' => true]
    ],
    [
        'method' => 'GET',
        'path' => '/streams/{which}/{type}',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/streams.php',
        'data' => ['currentRoute' => true]
    ],

    // M3U Playlist export (user + which)
    [
        'method' => 'GET',
        'path' => '/playlist/{user}/{which}',
        'handler' => 'KPTV_Stream_Playlists@handleUserPlaylist',
        'should_cache' => false,
    ],

    // M3U Playlist export (user + provider + which)
    [
        'method' => 'GET',
        'path' => '/playlist/{user}/{provider}/{which}',
        'handler' => 'KPTV_Stream_Playlists@handleProviderPlaylist',
        'should_cache' => false,
    ],

    // EPGs
    [
        'method' => 'GET',
        'path' => '/epgs',
        'handler' => 'view:pages/stream/epgs.php',
        'should_cache' => false,
    ],

    // EPG Proxy (user + provider) - XC providers only
    [
        'method' => 'GET',
        'path' => '/epg/{user}/{provider}',
        'handler' => 'KPTV_EPG_Proxy@handleEpgRequest',
        'should_cache' => false,
    ],

    // EPG Proxy - xmltv.php route (XC standard endpoint)
    [
        'method' => 'GET',
        'path' => '/xmltv.php',
        'handler' => 'KPTV_EPG_Proxy@handleEpgRequest',
        'should_cache' => false,
    ],

    // stream player proxy
    [
        'method' => 'GET',
        'path' => '/proxy/stream',
        'middleware' => ['auth_required'],
        'handler' => 'KPTV_Proxy@handleStreamPlayback'
    ],

    // XtreamCodes API routes - Standard player_api.php endpoint
    [
        'method' => 'GET',
        'path' => '/player_api.php',
        'handler' => 'KPTV_Xtream_API@handleRequest',
        'should_cache' => false,
    ],
    // XtreamCodes API short endpoint
    [
        'method' => 'GET',
        'path' => '/xc',
        'handler' => 'KPTV_Xtream_API@handleRequest',
        'should_cache' => false,
    ],

    // XtreamCodes API routes - Legacy endpoint (keep for backward compatibility)
    [
        'method' => 'GET',
        'path' => '/api/xtream',
        'handler' => 'KPTV_Xtream_API@handleRequest',
        'should_cache' => false,
    ],

    // XtreamCodes stream redirect
    [
        'method' => 'GET',
        'path' => '/live/{username}/{password}/{streamId}',
        'handler' => 'KPTV_Xtream_API@handleStreamRedirect',
        'should_cache' => false,
    ],
    [
        'method' => 'GET',
        'path' => '/movie/{username}/{password}/{streamId}',
        'handler' => 'KPTV_Xtream_API@handleStreamRedirect',
        'should_cache' => false,
    ],
    [
        'method' => 'GET',
        'path' => '/series/{username}/{password}/{streamId}',
        'handler' => 'KPTV_Xtream_API@handleStreamRedirect',
        'should_cache' => false,
    ],

];

// Admin-related GET routes
$get_admin_routes = [
    // Legal notice
    [
        'method' => 'GET',
        'path' => '/terms-of-use',
        'handler' => 'view:pages/terms.php',
        'should_cache' => true,
        'cache_length' => KPTV::DAY_IN_SECONDS
    ],
];

// =============================================================
// ===================== POST ROUTES ===========================
// =============================================================

// User-related POST routes
$post_user_routes = [
    // Login form submission (using controller)
    [
        'method' => 'POST',
        'path' => '/users/login',
        'middleware' => ['guest_only'],
        'handler' => 'KPTV_User@login' // Class@Method
    ],

    // Registration form submission (using controller)
    [
        'method' => 'POST',
        'path' => '/users/register',
        'middleware' => ['guest_only'],
        'handler' => 'KPTV_User@register' // Class@Method
    ],

    // Change password form submission (using controller)
    [
        'method' => 'POST',
        'path' => '/users/changepass',
        'middleware' => ['auth_required'],
        'handler' => 'KPTV_User@change_pass' // Class@Method
    ],

    // Forgot password form submission (using controller)
    [
        'method' => 'POST',
        'path' => '/users/forgot',
        'middleware' => ['guest_only'],
        'handler' => 'KPTV_User@forgot' // Class@Method
    ],
];

// Stream-related POST routes
$post_stream_routes = [
    // Filters form submission
    [
        'method' => 'POST',
        'path' => '/filters',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/filters.php', // Class@Method
        //'handler' => 'KPTV_Stream_Filters@handleFormSubmission', // Class@Method
    ],

    // Providers form submission
    [
        'method' => 'POST',
        'path' => '/providers',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/providers.php', // Class@Method
        //'handler' => 'KPTV_Stream_Providers@handleFormSubmission', // Class@Method
    ],

    // EPGs form submission
    [
        'method' => 'POST',
        'path' => '/epgs',
        'handler' => 'view:pages/stream/epgs.php',
        'should_cache' => false,
    ],

    // Streams form submission with parameters
    [
        'method' => 'POST',
        'path' => '/streams/{which}',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/streams.php',
        'data' => ['currentRoute' => true]
    ],
    [
        'method' => 'POST',
        'path' => '/streams/{which}/{type}',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/streams.php',
        //'handler' => 'KPTV_Streams@handleFormSubmission', // Class@Method
        'data' => ['currentRoute' => true]
    ],

    // missing streams
    [
        'method' => 'POST',
        'path' => '/missing',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/missing.php'
    ],
];

// =============================================================
// ==================== MERGE ALL ROUTES =====================
// =============================================================

// Merge all route arrays into one comprehensive routes array
$routes = array_merge(
    $get_static_routes,
    $get_user_routes,
    $get_stream_routes,
    $get_admin_routes,
    $post_user_routes,
    $post_stream_routes,
);

// =============================================================
// ==================== ROUTE CACHING ========================
// =============================================================

// Setup the cache settings
$routesFile = __FILE__;
$cacheKey = 'compiled_routes_' . md5($routesFile . filemtime($routesFile));
$cacheTTL = KPTV::DAY_IN_SECONDS; // Cache for 1 day

// Try to get cached routes (NOTE: We can't cache middleware definitions with closures)
$cachedData = \KPT\Cache::get($cacheKey);

if ($cachedData !== false && is_array($cachedData) && isset($cachedData['routes'])) {

    // Use cached routes (but always define middleware fresh since they contain closures)
    $routes = $cachedData['routes'];

    // Log cache hit for debugging (optional)
    \KPT\Logger::debug("Route cache HIT for key: {$cacheKey}");
} else {

    // Cache miss - store routes for next time (but not middleware definitions)
    $cacheData = [
        'routes' => $routes,
        'cached_at' => time(),
        'expires_at' => time() + $cacheTTL
    ];

    \KPT\Cache::set($cacheKey, $cacheData, $cacheTTL);

    // Log cache miss for debugging (optional)  
    \KPT\Logger::debug("Route cache MISS for key: {$cacheKey} - Routes cached");
}

// =============================================================
// ==================== REGISTER ROUTES ======================
// =============================================================

// Register middleware definitions (always fresh since they contain closures)
$router->registerMiddlewareDefinitions($middlewareDefinitions);

// Register all routes
$router->registerRoutes($routes);

// =============================================================
// ==================== GLOBAL MIDDLEWARE ====================
// =============================================================

// Maintenance mode middleware
$router->addMiddleware(function () {

    // Check for maintenance mode configuration
    $configFile = $_SERVER['DOCUMENT_ROOT'] . '/.maintenance.json';

    // Skip if no maintenance config exists
    if (! file_exists($configFile)) return true;

    // Load maintenance configuration
    $config = json_decode(file_get_contents($configFile), true);
    $enabled = $config['enabled'] ?? false;
    $allowedIPs = $config['allowed_ips'] ?? ['127.0.0.1/32'];
    $message = $config['message'] ?? 'Down for maintenance';

    // Skip if maintenance not enabled
    if (! $enabled) return true;

    // Check if client IP is in any allowed CIDR range
    $clientIp = KPTV::get_user_ip();
    foreach ($allowedIPs as $allowed) {
        if (KPTV::cidrMatch($clientIp, $allowed)) {
            return true;
        }
    }

    // Return maintenance mode response
    http_response_code(503);
    header('Content-Type: application/json');
    header('Retry-After: 3600'); // Retry after 1 hour
    die(json_encode([
        'error' => 'maintenance',
        'message' => $message,
        'until' => $config['until'] ?? null,
        'status' => 503
    ]));
});

// =============================================================
// ==================== ERROR HANDLING =========================
// =============================================================

// 404 Not Found handler
$router->notFound(function () {

    if (ob_get_length() > 0 || headers_sent()) {
        return; // Page already rendered, don't output 404
    }

    // Log the 404 error
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $ip = KPTV::get_user_ip();
    error_log("404 Error: $method $uri from $ip");

    // Check if it's an API request
    if (strpos($uri, '/api/') !== false) {
        // Return JSON 404 response for API
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'API endpoint not found',
            'request_uri' => $uri,
            'method' => $method,
            'timestamp' => date('c')
        ]);

        // otherwise it's a normal request
    } else {

        // Return HTML 404 response for web
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo 'Page Not Found';
    }
    exit; // Make sure we exit after 404
});
