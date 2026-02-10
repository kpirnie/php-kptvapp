<?php

/**
 * user/login.php
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

// check if we're already logged in
if (\KPTV_User::is_user_logged_in()) {

    // do the message and redirect
    KPTV::message_with_redirect('/', 'danger', 'You don\'t belong there.  Don\'t worry, our support team has been notified.');
} else {

?>
    <h2 class="kptv-heading uk-heading-bullet">Login to Your Account</h2>
    <form action="/users/login" method="POST" class="uk-form-stacked" id="t-login">
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: user"></span>
                <input class="uk-input" id="frmUsername" type="text" placeholder="Username..." name="frmUsername" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: lock"></span>
                <input class="uk-input" id="frmPassword" type="password" placeholder="Password..." name="frmPassword" />
            </div>
        </div>
        <div class="uk-margin uk-grid uk-grid-small">
            <div class="uk-width-1-1 recapt">
                <button class="uk-button uk-button-primary uk-border-rounded contact-button uk-align-right g-recaptcha" data-badge="inline" data-sitekey="<?php echo KPTV::get_setting('recaptcha')->sitekey; ?>" data-callback='onSubmit' data-action='submit'>
                    Login Now <span uk-icon="sign-in"></span>
                </button>
            </div>
        </div>
    </form>
    <script src="//www.google.com/recaptcha/api.js"></script>
    <script>
        function onSubmit(token) {
            document.getElementById("t-login").submit();
        }
    </script>
<?php

}

// pull in the footer
KPTV::pull_footer();
