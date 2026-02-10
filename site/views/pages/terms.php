<?php

/**
 * Legal Notice Component
 * 
 * Comprehensive legal notice for KPTV Stream Manager
 * Can be included on any page
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// pull in the header
KPTV::pull_header();

?>

<!-- Comprehensive Legal Notice -->
<h1 class="uk-heading-bullet uk-margin-remove-top">Important Legal Notice & Terms of Use</h1>

<div class="uk-margin-medium">
    <h4 class="uk-heading-line uk-text-bold"><span>Legitimate Use Only</span></h4>
    <p>This platform is intended <strong>exclusively for legitimate IPTV management purposes</strong>. Users are fully responsible for ensuring they have proper legal authorization for any content, streams, or media they manage through this service. The KPTV Stream Manager is a tool for organizing and managing legally obtained IPTV content only.</p>
</div>

<div class="uk-margin-medium">
    <h4 class="uk-heading-line uk-text-bold"><span>User Responsibility & Content Authorization</span></h4>
    <p>By using this service, you acknowledge and agree that:</p>
    <ul class="uk-list uk-list-bullet uk-margin-small-top">
        <li>You have valid subscriptions and proper authorization for all IPTV content you manage</li>
        <li>You will not use this service to organize, distribute, or access unauthorized copyrighted material</li>
        <li>You are solely responsible for compliance with all applicable local, national, and international laws</li>
        <li>Any content stored or managed violates these terms is beyond our control and strictly prohibited</li>
    </ul>
</div>

<div class="uk-margin-medium">
    <h4 class="uk-heading-line uk-text-bold"><span>Account Terms & Service Usage</span></h4>
    <p>By creating an account and using this service, you agree to:</p>
    <ul class="uk-list uk-list-bullet uk-margin-small-top">
        <li>Use this service responsibly and in accordance with all applicable laws and regulations</li>
        <li>Maintain the security and confidentiality of your account credentials</li>
        <li>Not share your account access or playlist URLs with unauthorized individuals</li>
        <li>Accept that account privileges may be revoked immediately for misuse or violation of these terms</li>
    </ul>
</div>

<div class="uk-margin-medium">
    <h4 class="uk-heading-line uk-text-bold"><span>Service Disclaimer & Limitations</span></h4>
    <p>Please understand that:</p>
    <ul class="uk-list uk-list-bullet uk-margin-small-top">
        <li><strong>No Content Hosting:</strong> We do not host, store, or distribute any media content</li>
        <li><strong>Limited Support:</strong> This service is provided with minimal support on a best-effort basis</li>
        <li><strong>No Warranties:</strong> The service is provided "as-is" without warranties of any kind</li>
        <li><strong>Service Availability:</strong> We reserve the right to modify, suspend, or discontinue the service at any time</li>
        <li><strong>Data Protection:</strong> While we implement security measures, users are responsible for their own data backup</li>
    </ul>
</div>

<div class="uk-margin-medium">
    <h4 class="uk-heading-line uk-text-bold"><span>Privacy & Data Security</span></h4>
    <p>Your privacy is important to us. We implement industry-standard security measures including encrypted password storage, secure session management, and data protection protocols. However, you acknowledge that no system is completely secure, and you use this service at your own risk.</p>
</div>

<div class="uk-margin-medium">
    <h4 class="uk-heading-line uk-text-bold"><span>Copyright & DMCA Compliance</span></h4>
    <p>We respect intellectual property rights and expect our users to do the same. If you believe any content managed through our service infringes your copyright, please contact us through our <a href="https://github.com/kpirnie/app.kptv.im/issues" target="_blank" class="uk-link">GitHub Issues page</a> with detailed information about the alleged infringement.</p>
</div>

<div class="uk-margin-medium">
    <h4 class="uk-heading-line uk-text-bold"><span>Contact & Support</span></h4>
    <p>For technical issues, feature requests, or legal concerns, please visit our <a href="https://github.com/kpirnie/app.kptv.im/issues" target="_blank" class="uk-link">GitHub Issues page</a>. Please note that support is provided on a volunteer basis and response times may vary.</p>
</div>

<div class="uk-margin uk-text-center">
    <p class="">
        <strong>By continuing to use this service, you acknowledge that you have read, understood, and agree to be bound by these terms.</strong>
    </p>
</div>
<?php
// pull in the footer
KPTV::pull_footer();
