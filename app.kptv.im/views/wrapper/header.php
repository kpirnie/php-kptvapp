<?php

/**
 * header.php
 * 
 * No direct access allowed!
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// get the route we're in
$current_route = \KPT\Router::getCurrentRoute();

// get the current user and role
$currentUser = KPTV_User::get_current_user() ?: null;
$user_for_export = KPTV::encrypt($currentUser?->id ?: 0);
$user_role = $currentUser?->role ?: 0;

// hold the route path
$route_path = $current_route->path;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Kevin C. Pirnie" />
    <title>KPTV Stream Manager - Dashboard</title>
    <meta name="description" content="KPTV Stream Manager - Manage your IPTV providers and streams">
    <link rel="dns-prefetch" href="//dev.kptv.im" />
    <link rel="dns-prefetch" href="//vjs.zencdn.net" />
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net" />
    <link rel="preconnect" href="//fonts.googleapis.com">
    <link rel="preconnect" href="//fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700&family=Rajdhani:wght@400;500;600&display=swap" rel="stylesheet">
    <?php echo \KPT\DataTables::getCssIncludes('uikit', true, true); ?>
    <link rel="stylesheet" href="/assets/css/kptv.min.css" />
    <link rel="stylesheet" href="/assets/css/custom.css?_=<?php echo time(); ?>" />
    <link rel="icon" type="image/png" href="/assets/images/kptv-icon.png" />
</head>

<body uk-height-viewport="offset-top: true">
    <?php
    // main navigation
    KPTV::include_view('wrapper/nav-main');
    ?>
    <div uk-grid class="uk-grid-collapse uk-flex-1" uk-height-viewport="expand: true">
        <?php
        // include the sidebar
        KPTV::include_view('wrapper/sidebar');
        ?>
        <div class="uk-width-expand">
            <main class="kptv-main">
                <?php

                // if there is a message to be shown
                if (isset($_SESSION) && isset($_SESSION['page_msg'])) {

                    // show the message
                    KPTV::show_message($_SESSION['page_msg']['type'], $_SESSION['page_msg']['msg']);

                    // remove it from the session
                    unset($_SESSION['page_msg']);
                }
