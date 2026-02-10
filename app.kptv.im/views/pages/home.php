<?php

/**
 * views/home.php
 * 
 * No direct access allowed!
 * 
 * @since 8.3
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// pull in the header
KPTV::pull_header();

// is there a logged in user?
if (KPTV_User::is_user_logged_in()):

    // get all counts for the stats cards
    $counts = KPTV::get_counts();
?>

    <!-- Page Header -->
    <div class="uk-margin-bottom">
        <h2 class="kptv-heading kptv-page-title-lg"><span uk-icon="icon: home;"></span> Your Stream Stats</h2>
    </div>

    <!-- Stats Grid -->
    <div uk-grid class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@m uk-margin-bottom">
        <div>
            <div class="kptv-card kptv-stat-card">
                <div class="kptv-stat-value"><?php echo $counts['total']; ?></div>
                <div class="kptv-stat-label">Total Streams</div>
            </div>
        </div>
        <div>
            <div class="kptv-card kptv-stat-card success">
                <div class="kptv-stat-value"><?php echo $counts['live']; ?></div>
                <div class="kptv-stat-label">Active Live</div>
            </div>
        </div>
        <div>
            <div class="kptv-card kptv-stat-card danger">
                <div class="kptv-stat-value"><?php echo $counts['series']; ?></div>
                <div class="kptv-stat-label">Active Series</div>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div uk-grid class="uk-grid-small uk-child-width-1-1 uk-margin-bottom">

        <!-- Providers Card -->
        <div>
            <div class="kptv-card">

                <div class="kptv-card-title">
                    <span uk-icon="server"></span>
                    Your Provider's Active Streams
                </div>

                <ul class="uk-list uk-list-divider kptv-list-divided">
                    <?php
                    foreach ($counts['per_provider'] as $prov) {
                    ?>
                        <li>
                            <div class="uk-flex uk-flex-between uk-flex-middle">
                                <span class="uk-text-bold"><?php echo $prov->provider_name; ?></span>
                                <div class="uk-flex uk-flex-middle" style="gap: 8px;">
                                    <span class="kptv-badge kptv-badge-primary"><?php echo $prov->total_streams; ?></span>
                                    <span class="kptv-badge kptv-badge-success"><?php echo $prov->active_live; ?></span>
                                    <span class="kptv-badge kptv-badge-danger"><?php echo $prov->active_series; ?></span>
                                </div>
                            </div>
                        </li>
                    <?php
                    }
                    ?>
                </ul>

            </div>
        </div>

    </div>
<?php

// Otherwise
else: ?>

    <div class="uk-margin-bottom">
        <h2 class="kptv-heading uk-heading-bullet">Welcome and Warnings</h2>
    </div>

    <p>Please understand that I built this primarily for me as practice to keep my PHP and MySQL coding skills up to snuff. I decided to make it publicly available in case anyone else feels they could use something similar.</p>
    <p>Now, that that is out of the way, please understand that I can only minimally support this app, if you decide to use it, you agree that it is at your own discretion and that I am under no obligation to help you, fix your items, or fix this website if it seems broken to you.</p>
    <p>You also understand that I do not host, nor have I ever hosted any kind of media for public consumption or use. Thus said, do not ask me to provide you with anything related to that.</p>
    <p>I also make this statement that this tool is to be used to legitimate IPTV purposes, and data stored that violates this is beyond my control.</p>
    <p>You can send suggestions through this <a href="https://kcp.im/contact" target="_blank">Contact Us</a> form, but please understand that I may not answer you.</p>
<?php endif; ?>

<?php

// pull in the footer
KPTV::pull_footer();
