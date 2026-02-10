<?php

/**
 * accounts-faq.php
 * 
 * FAQ page for user account management
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
<div class="uk-container">
    <h2 class="kptv-heading uk-heading-bullet">User Account FAQ</h2>

    <!-- Account Registration Section -->
    <div class="uk-margin">
        <h3 class="kptv-heading uk-heading-bullet">Account Registration</h3>
        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How do I create a new account?</a>
                <div class="uk-accordion-content">
                    <p>Creating an account is simple and free:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Click "Register" in the navigation menu</li>
                        <li>Fill out the registration form with your details</li>
                        <li>Complete the reCAPTCHA verification</li>
                        <li>Click "Register Now"</li>
                        <li>Check your email for an activation link</li>
                        <li>Click the activation link to activate your account</li>
                    </ol>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Important:</strong> You must click the activation link in your email before you can log in to your account.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What are the password requirements?</a>
                <div class="uk-accordion-content">
                    <p>Your password must meet these security requirements:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Length:</strong> Between 8 and 64 characters</li>
                        <li><strong>Uppercase letters:</strong> At least one (A-Z)</li>
                        <li><strong>Lowercase letters:</strong> At least one (a-z)</li>
                        <li><strong>Numbers:</strong> At least one (0-9)</li>
                        <li><strong>Special characters:</strong> At least one from: <code>!@#$%*</code></li>
                    </ul>
                    <div class="uk-alert-success dark-version" uk-alert>
                        <p><strong>Example:</strong> "MySecure123!" meets all requirements</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">I didn't receive my activation email. What should I do?</a>
                <div class="uk-accordion-content">
                    <p>If you haven't received your activation email:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li><strong>Check your spam/junk folder</strong> - activation emails sometimes end up there</li>
                        <li><strong>Wait a few minutes</strong> - email delivery can sometimes be delayed</li>
                        <li><strong>Check the email address</strong> - make sure you entered it correctly during registration</li>
                        <li><strong>Try registering again</strong> - if the email was incorrect, you'll need to register with the right address</li>
                    </ol>
                    <p class="uk-text-meta dark-version">If you continue having issues, you can contact support through the GitHub issues page.</p>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">Can I change my username after registration?</a>
                <div class="uk-accordion-content">
                    <p>No, usernames cannot be changed after account creation. Your username is permanent and serves as your unique identifier in the system.</p>
                    <div class="uk-alert-warning dark-version" uk-alert>
                        <p><strong>Choose carefully:</strong> Make sure you're happy with your username during registration, as it cannot be modified later.</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <!-- Login & Authentication Section -->
    <div class="uk-margin">
        <h3 class="kptv-heading uk-heading-bullet">Login & Authentication</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How do I log into my account?</a>
                <div class="uk-accordion-content">
                    <p>To access your account:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Click "Login" in the navigation menu</li>
                        <li>Enter your username and password</li>
                        <li>Complete the reCAPTCHA verification</li>
                        <li>Click "Login Now"</li>
                    </ol>
                    <p class="uk-text-meta dark-version">Make sure your account has been activated via the email link before attempting to log in.</p>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">Why is my account locked and how do I unlock it?</a>
                <div class="uk-accordion-content">
                    <p>Accounts are automatically locked for security after <strong>5 failed login attempts</strong>.</p>
                    <p><strong>Lockout Details:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Duration:</strong> 15 minutes</li>
                        <li><strong>Automatic unlock:</strong> The lockout expires automatically</li>
                        <li><strong>Manual unlock:</strong> An administrator can unlock your account immediately</li>
                    </ul>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Prevention:</strong> Double-check your username and password. Consider using a password manager to avoid typos.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I log out securely?</a>
                <div class="uk-accordion-content">
                    <p>To log out of your account:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Click "Your Account" in the navigation menu</li>
                        <li>Select "Logout of Your Account"</li>
                        <li>Close your browser for complete security</li>
                    </ol>
                    <div class="uk-alert-success dark-version" uk-alert>
                        <p><strong>Security Tip:</strong> Always log out when using shared or public computers, and close your browser completely to ensure your session is terminated.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What information is tracked about my login activity?</a>
                <div class="uk-accordion-content">
                    <p>For security purposes, the system tracks:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Last login time:</strong> When you last successfully logged in</li>
                        <li><strong>Failed attempts:</strong> Number of consecutive failed login attempts</li>
                        <li><strong>Account locks:</strong> When your account was locked due to failed attempts</li>
                    </ul>
                    <p class="uk-text-meta dark-version">This information helps protect your account from unauthorized access attempts.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Password Management Section -->
    <div class="uk-margin">
        <h3 class="kptv-heading uk-heading-bullet">Password Management</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How do I change my password?</a>
                <div class="uk-accordion-content">
                    <p>To change your password while logged in:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Go to "Your Account" → "Change Your Password"</li>
                        <li>Enter your current password</li>
                        <li>Enter your new password twice for confirmation</li>
                        <li>Click "Change Your Password"</li>
                    </ol>
                    <p>You'll receive an email confirmation when your password is successfully changed.</p>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Security:</strong> Change your password immediately if you think your account may have been compromised.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">I forgot my password. How do I reset it?</a>
                <div class="uk-accordion-content">
                    <p>If you can't remember your password:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Click "Forgot Your Password?" on the login page</li>
                        <li>Enter your username and email address</li>
                        <li>Complete the reCAPTCHA verification</li>
                        <li>Click "Reset Your Password"</li>
                        <li>Check your email for the new temporary password</li>
                        <li>Log in with the temporary password</li>
                        <li><strong>Important:</strong> Change the temporary password immediately after logging in</li>
                    </ol>
                    <div class="uk-alert-warning dark-version" uk-alert>
                        <p><strong>Security Note:</strong> Temporary passwords are only meant for immediate use. Change it to a secure password of your choice as soon as possible.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How often should I change my password?</a>
                <div class="uk-accordion-content">
                    <p><strong>Best Practices for Password Security:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Regular changes:</strong> Every 3-6 months for optimal security</li>
                        <li><strong>Immediate changes:</strong> If you suspect your account has been compromised</li>
                        <li><strong>After sharing:</strong> If you've shared your password with anyone</li>
                        <li><strong>Unique passwords:</strong> Don't reuse passwords from other accounts</li>
                    </ul>
                    <p class="uk-text-meta dark-version">Consider using a password manager to generate and store strong, unique passwords.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Account Information Section -->
    <div class="uk-margin">
        <h3 class="kptv-heading uk-heading-bullet">Account Information</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">Can I update my personal information?</a>
                <div class="uk-accordion-content">
                    <p>Currently, personal information updates are limited:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Username:</strong> Cannot be changed after registration</li>
                        <li><strong>Email address:</strong> Can be updated by administrators</li>
                        <li><strong>Name:</strong> Can be updated by administrators</li>
                        <li><strong>Password:</strong> You can change this yourself anytime</li>
                    </ul>
                    <p>If you need to update your email address or name, contact an administrator or submit a request through GitHub issues.</p>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What are user roles and permissions?</a>
                <div class="uk-accordion-content">
                    <p>The system has two user roles:</p>
                    <p><strong>Regular User (Role: User):</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Manage your own streams, providers, and filters</li>
                        <li>Export playlists</li>
                        <li>Change your own password</li>
                        <li>Access all stream management features</li>
                    </ul>
                    <p><strong>Administrator (Role: Admin):</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>All regular user permissions</li>
                        <li>Manage other user accounts</li>
                        <li>Activate/deactivate user accounts</li>
                        <li>Unlock locked accounts</li>
                        <li>Update user information</li>
                        <li>Delete user accounts (except their own)</li>
                    </ul>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I delete my account?</a>
                <div class="uk-accordion-content">
                    <p>Account deletion must be performed by an administrator:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Contact an administrator or submit a request through GitHub issues</li>
                        <li>Provide your username and reason for deletion</li>
                        <li>An administrator will process your request</li>
                    </ol>
                    <div class="uk-alert-danger dark-version" uk-alert>
                        <p><strong>Warning:</strong> Account deletion is permanent and cannot be undone. All your streams, providers, filters, and data will be permanently removed.</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <!-- Security & Privacy Section -->
    <div class="uk-margin">
        <h3 class="kptv-heading uk-heading-bullet">Security & Privacy</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How is my data protected?</a>
                <div class="uk-accordion-content">
                    <p><strong>Security Measures:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Password encryption:</strong> Passwords are hashed using Argon2ID algorithm</li>
                        <li><strong>Account lockouts:</strong> Protection against brute force attacks</li>
                        <li><strong>Session management:</strong> Secure session handling</li>
                        <li><strong>Data encryption:</strong> Sensitive data is encrypted in the database</li>
                    </ul>
                    <p class="uk-text-meta dark-version">Your stream URLs and provider credentials are stored securely and are only accessible to your account.</p>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What data is stored about me?</a>
                <div class="uk-accordion-content">
                    <p><strong>Account Information:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Username and email address</li>
                        <li>First and last name</li>
                        <li>Encrypted password</li>
                        <li>Account creation and last update dates</li>
                        <li>Last login time and failed login attempts</li>
                    </ul>
                    <p><strong>Stream Data:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Your providers, streams, and filters</li>
                        <li>Stream organization and preferences</li>
                        <li>Provider credentials (encrypted)</li>
                    </ul>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">Are my playlist URLs private?</a>
                <div class="uk-accordion-content">
                    <p>Yes, your playlist URLs are private and secure:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Encrypted user IDs:</strong> URLs contain encrypted identifiers</li>
                        <li><strong>Account-specific:</strong> Only work with your account data</li>
                        <li><strong>No authentication bypass:</strong> Cannot be used to access other accounts</li>
                    </ul>
                    <div class="uk-alert-warning dark-version" uk-alert>
                        <p><strong>Best Practice:</strong> Don't share your playlist URLs publicly, as they provide access to your stream content.</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <!-- Admin Functions Section -->
    <div class="uk-margin">
        <h3 class="kptv-heading uk-heading-bullet">Admin Functions</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">What can administrators do?</a>
                <div class="uk-accordion-content">
                    <p><strong>User Management Capabilities:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>View all users:</strong> See complete user list with status information</li>
                        <li><strong>Account status:</strong> Activate or deactivate user accounts</li>
                        <li><strong>Unlock accounts:</strong> Remove lockouts from failed login attempts</li>
                        <li><strong>Edit profiles:</strong> Update user names, emails, and roles</li>
                        <li><strong>Delete accounts:</strong> Permanently remove user accounts and data</li>
                    </ul>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Limitation:</strong> Administrators cannot change their own role or delete their own account for security reasons.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I become an administrator?</a>
                <div class="uk-accordion-content">
                    <p>Administrator privileges must be granted by an existing administrator:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Contact an existing administrator</li>
                        <li>Explain why you need admin access</li>
                        <li>The administrator can update your role in the user management section</li>
                    </ol>
                    <p class="uk-text-meta dark-version">Administrator access is typically only granted to trusted users who need to manage the system.</p>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">Can administrators see my streams and providers?</a>
                <div class="uk-accordion-content">
                    <p>No, administrators cannot access your stream content:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>User data isolation:</strong> Each user's streams and providers are private</li>
                        <li><strong>Account management only:</strong> Admins can only manage account information</li>
                        <li><strong>No content access:</strong> Admins cannot view your providers, streams, or filters</li>
                    </ul>
                    <p class="uk-text-meta dark-version">Administrative privileges are limited to user account management and do not extend to personal stream data.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Troubleshooting Section -->
    <div class="uk-margin">
        <h3 class="kptv-heading uk-heading-bullet">Troubleshooting</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">I can't log in. What should I check?</a>
                <div class="uk-accordion-content">
                    <p><strong>Common login issues and solutions:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li><strong>Check your credentials:</strong> Verify username and password are correct</li>
                        <li><strong>Account activation:</strong> Make sure you clicked the activation link in your email</li>
                        <li><strong>Account lockout:</strong> Wait 15 minutes if you've had failed login attempts</li>
                        <li><strong>Caps Lock:</strong> Check if Caps Lock is on (passwords are case-sensitive)</li>
                        <li><strong>Browser issues:</strong> Try clearing your browser cache or using a different browser</li>
                        <li><strong>reCAPTCHA:</strong> Make sure you complete the reCAPTCHA verification</li>
                    </ol>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">I'm getting "Invalid username or password" errors. Why?</a>
                <div class="uk-accordion-content">
                    <p>This error can occur for several reasons:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Incorrect credentials:</strong> Double-check your username and password</li>
                        <li><strong>Unactivated account:</strong> Your account may not be activated yet</li>
                        <li><strong>Case sensitivity:</strong> Passwords are case-sensitive</li>
                        <li><strong>Typos:</strong> Check for typos in your username or password</li>
                    </ul>
                    <div class="uk-alert-warning dark-version" uk-alert>
                        <p><strong>Security Feature:</strong> The system shows the same error for invalid usernames and passwords to prevent username enumeration attacks.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I report account-related problems?</a>
                <div class="uk-accordion-content">
                    <p>For account issues that you can't resolve:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Visit our <a href="https://github.com/kpirnie/app.kptv.im/issues" target="_blank" class="uk-link">GitHub Issues</a> page</li>
                        <li>Search for existing reports of similar issues</li>
                        <li>Create a new issue with detailed information:
                            <ul class="uk-list uk-list-disc">
                                <li>Your username (never include your password)</li>
                                <li>Description of the problem</li>
                                <li>Steps you've already tried</li>
                                <li>Browser and device information</li>
                            </ul>
                        </li>
                    </ol>
                    <p class="uk-text-meta dark-version">Support is provided on a best-effort basis by the development team.</p>
                </div>
            </li>
        </ul>
    </div>
</div>

<?php
// pull in the footer
KPTV::pull_footer();
