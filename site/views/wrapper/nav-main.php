<?php

/**
 * nav-main.php
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

?>

<!-- Navigation -->
<nav class="kptv-navbar">
    <div class="kptv-navbar-container">
        <div uk-navbar>

            <!-- Logo -->
            <div class="uk-navbar-left">
                <a href="/" class="uk-navbar-item kptv-logo">
                    <img src="/assets/images/kptv-logo.png" alt="KPTV Logo">
                    <span class="kptv-logo-text">Stream Manager</span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="uk-navbar-right uk-visible@m">
                <ul class="uk-navbar-nav kptv-nav">
                    <li class="<?php echo KPTV::active_link('home'); ?>"><a href="/">Home</a></li>
                    <li class="<?php echo KPTV::active_link('info'); ?>">
                        <a href="#">Info <span uk-navbar-parent-icon></span></a>
                        <div class="uk-navbar-dropdown">
                            <ul class="uk-nav uk-navbar-dropdown-nav uk-nav-parent-icon">
                                <li><a href="/users/faq">Account Management</a></li>
                                <li><a href="/streams/faq">Stream Management</a></li>
                                <li class="uk-nav-divider"></li>
                                <li><a href="/terms-of-use">Terms of Use</a></li>
                            </ul>
                        </div>
                    </li>
                    <?php if (KPTV_User::is_user_logged_in()): ?>
                        <li class="<?php echo KPTV::active_link('streams'); ?>">
                            <a href="#">Stream Manager <span uk-navbar-parent-icon></span></a>
                            <div class="uk-navbar-dropdown">
                                <ul class="uk-nav uk-navbar-dropdown-nav uk-nav-parent-icon">
                                    <li><a href="/providers">Your Providers</a></li>
                                    <li><a href="/filters">Your Filters</a></li>
                                    <li class="uk-parent">
                                        <a href="/streams/live/all">Live Streams</a>
                                        <ul class="uk-nav-sub">
                                            <li><a href="/streams/live/active">Active Streams</a></li>
                                            <li><a href="/streams/live/inactive">In-Active Streams</a></li>
                                            <li><a uk-tooltip="Click to Copy the M3U URL" href="<?php echo KPTV_URI; ?>playlist/<?php echo $user_for_export; ?>/live" class="copy-link">Export the Playlist</a></li>
                                        </ul>
                                    </li>
                                    <li class="uk-parent">
                                        <a href="/streams/series/all">Series Streams</a>
                                        <ul class="uk-nav-sub">
                                            <li><a href="/streams/series/active">Active Streams</a></li>
                                            <li><a href="/streams/series/inactive">In-Active Streams</a></li>
                                            <li><a uk-tooltip="Click to Copy the M3U URL" href="<?php echo KPTV_URI; ?>playlist/<?php echo $user_for_export; ?>/series" class="copy-link">Export the Playlist</a></li>
                                        </ul>
                                    </li>
                                    <!--<li class="uk-parent">
                                        <a href="/streams/vod/all">VOD Streams</a>
                                        <ul class="uk-nav-sub">
                                            <li><a href="/streams/vod/active">Active Streams</a></li>
                                            <li><a href="/streams/vod/inactive">In-Active Streams</a></li>
                                            <li><a uk-tooltip="Click to Copy the M3U URL" href="<?php echo KPTV_URI; ?>playlist/<?php echo $user_for_export; ?>/vod" class="copy-link">Export the Playlist</a></li>
                                        </ul>
                                        </li>-->
                                    <li><a href="/streams/other">Other Streams</a></li>
                                    <li><a href="/missing">Missing Streams</a></li>
                                </ul>
                            </div>
                        </li>
                    <?php endif; ?>
                    <li class="<?php echo KPTV::active_link('account'); ?>">
                        <a href="#">Your Account <span uk-navbar-parent-icon></span></a>
                        <div class="uk-navbar-dropdown">
                            <ul class="uk-nav uk-navbar-dropdown-nav uk-nav-parent-icon">
                                <?php

                                // check if there is a user object, and a user id
                                if (KPTV_User::is_user_logged_in()) {
                                ?>
                                    <li><a href="/users/changepass">Change Your Password</a></li>
                                    <li class="uk-nav-divider"></li>
                                    <li><a href="/users/logout">Logout of Your Account</a></li>
                                <?php
                                    // there isn't so replace this with the login stuff
                                } else {
                                ?>
                                    <li><a href="/users/login">Login to Your Account</a></li>
                                    <li class="uk-nav-divider"></li>
                                    <li><a href="/users/register">Register an Account</a></li>
                                    <li><a href="/users/forgot">Forgot Your Password?</a></li>
                                <?php
                                }
                                ?>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Mobile Toggle -->
            <div class="uk-navbar-right uk-hidden@m">
                <a class="uk-navbar-toggle kptv-navbar-toggle" uk-toggle="target: #kptv-mobile-nav">
                    <span uk-icon="more-vertical"></span>
                </a>
            </div>
        </div>
    </div>
</nav>