<?php

/**
 * sidebar.php
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

// Define nav items once to avoid duplication
ob_start();

// is there a logged in user?
if (KPTV_User::is_user_logged_in()):
?>

    <li class="uk-nav-header">STREAM MANAGER</li>
    <li>
        <a href="/providers">
            <span uk-icon="server"></span>
            Your Providers
        </a>
    </li>
    <li>
        <a href="/filters">
            <span uk-icon="settings"></span>
            Your Filters
        </a>
    </li>
    <li class=" uk-parent <?php echo KPTV::open_link('live'); ?>">
        <a href="#">
            <span uk-icon="tv"></span>
            Live Streams
        </a>
        <ul class="uk-nav-sub">
            <li><a href="/streams/live/active">Active Streams</a></li>
            <li><a href="/streams/live/inactive">Inactive Streams</a></li>
            <li><a uk-tooltip="Click to Copy the M3U URL" href="<?php echo KPTV_URI; ?>playlist/<?php echo $user_for_export; ?>/live" class="copy-link">Export Streams</a></li>
        </ul>
    </li>
    <li class="uk-parent <?php echo KPTV::open_link('series'); ?>">
        <a href="#">
            <span uk-icon="album"></span>
            Series Streams
        </a>
        <ul class="uk-nav-sub">
            <li><a href="/streams/series/active">Active Streams</a></li>
            <li><a href="/streams/series/inactive">Inactive Streams</a></li>
            <li><a uk-tooltip="Click to Copy the M3U URL" href="<?php echo KPTV_URI; ?>playlist/<?php echo $user_for_export; ?>/series" class="copy-link">Export Streams</a></li>
        </ul>
    </li>
    <!--<li class="uk-parent <?php echo KPTV::open_link('vod'); ?>">
        <a href="#">
            <span uk-icon="video-camera"></span>
            VOD Streams
        </a>
        <ul class="uk-nav-sub">
            <li><a href="/streams/vod/active">Active Streams</a></li>
            <li><a href="/streams/vod/inactive">Inactive Streams</a></li>
            <li><a uk-tooltip="Click to Copy the M3U URL" href="<?php echo KPTV_URI; ?>playlist/<?php echo $user_for_export; ?>/vod" class="copy-link">Export Streams</a></li>
        </ul>
    </li>-->
    <li>
        <a href="/streams/other">
            <span uk-icon="nut" class="kptv-icon-dual"></span>
            Other Streams
        </a>
    </li>
    <li>
        <a href="/missing">
            <span uk-icon="minus-circle" class="kptv-icon-dual"></span>
            Missing Streams
        </a>
    </li>
    <li class="uk-nav-divider"></li>
<?php endif; ?>
<li class="uk-nav-header">ACCOUNT MANAGER</li>
<li class="uk-parent <?php echo KPTV::open_link('account'); ?>">
    <a href="#">
        <span uk-icon="user" class="kptv-icon-dual"></span>
        Your Account
    </a>
    <ul class="uk-nav-sub">
        <?php
        // check if there is a user object, and a user id
        if (KPTV_User::is_user_logged_in()) {
        ?>
            <li><a href="/users/changepass">Change Your Password</a></li>
            <li><a href="/users/logout">Logout of Your Account</a></li>
        <?php
            // there isn't so replace this with the login stuff
        } else {
        ?>
            <li><a href="/users/login">Login to Your Account</a></li>
            <li><a href="/users/register">Register an Account</a></li>
            <li><a href="/users/forgot">Forgot Your Password?</a></li>
        <?php
        }
        ?>
    </ul>
</li>
<li class="uk-nav-header">INFO &amp; SUPPORT</li>
<li class="uk-parent <?php echo KPTV::open_link('info'); ?>">
    <a href="#">
        <span uk-icon="tv"></span>
        FAQ &amp; Info
    </a>
    <ul class="uk-nav-sub">
        <li><a href="/users/faq">Account Management</a></li>
        <li><a href="/streams/faq">Stream Management</a></li>
        <li><a href="/terms-of-use">Terms of Use</a></li>
    </ul>
</li>
<?php
$nav_items = ob_get_clean();
?>

<!-- Mobile Offcanvas Sidebar -->
<div id="kptv-mobile-nav" uk-offcanvas="overlay: true; mode: slide; flip: true" class="kptv-offcanvas">
    <div class="uk-offcanvas-bar">
        <span uk-icon="icon: close; ratio: 1.5" class="uk-offcanvas-close"></span>
        <ul class="uk-nav uk-nav-default uk-nav-parent-icon kptv-offcanvas-nav" uk-nav>
            <?php echo $nav_items; ?>
        </ul>
    </div>
</div>

<!-- Desktop Sidebar -->
<div class="uk-width-auto uk-visible@m uk-flex uk-flex-column">
    <aside class="kptv-sidebar uk-flex-1">
        <ul class="uk-nav uk-nav-default uk-nav-parent-icon kptv-sidebar-nav" uk-nav>
            <?php echo $nav_items; ?>
        </ul>
    </aside>
</div>