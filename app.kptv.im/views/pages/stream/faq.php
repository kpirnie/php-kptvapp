<?php

/**
 * streams-faq.php
 * 
 * FAQ page for stream management
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
    <h2 class="kptv-heading uk-heading-bullet">Stream Management FAQ</h2>

    <!-- Stream Basics Section -->
    <div class="uk-margin">
        <h3 class="uk-heading-bullet">Stream Basics</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">What are the different types of streams?</a>
                <div class="uk-accordion-content">
                    <p>The KPTV Stream Manager organizes your content into four main categories:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Live Streams:</strong> Real-time TV channels and broadcasts</li>
                        <li><strong>Series Streams:</strong> TV shows, series, and episodic content</li>
                        <li><strong>VOD Streams:</strong> Video on demand / movie content</li>
                        <li><strong>Other Streams:</strong> Uncategorized or miscellaneous content that needs to be organized</li>
                    </ul>
                    <p class="uk-text-meta dark-version">You can move streams between categories as needed to keep your content organized.</p>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What is the difference between active and inactive streams?</a>
                <div class="uk-accordion-content">
                    <p><strong>Active Streams:</strong> These are streams that are currently available and will be included in your exported playlists.</p>
                    <p><strong>Inactive Streams:</strong> These are streams that are temporarily disabled or not working. They won't appear in your exported playlists but remain in your library for future use.</p>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Tip:</strong> Use the active/inactive toggle to quickly enable or disable streams without deleting them permanently.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I play a stream to test it?</a>
                <div class="uk-accordion-content">
                    <p>You can test streams directly in your browser using the built-in player:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Navigate to any stream list (Live, Series, VOD, or Other)</li>
                        <li>Click the <span uk-icon="play"></span> play button next to the stream you want to test</li>
                        <li>The stream will open in a modal player that supports HLS (.m3u8) and MPEG-TS (.ts) formats</li>
                    </ol>
                    <p class="uk-text-meta dark-version">If a stream doesn't play in the browser, try copying the stream URL and opening it in VLC or another media player.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Provider Management Section -->
    <div class="uk-margin">
        <h3 class="uk-heading-bullet">Provider Management</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">What are providers and how do I add them?</a>
                <div class="uk-accordion-content">
                    <p>Providers are your IPTV sources - the services that supply your stream content. The system supports two types:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>XC API:</strong> Xtream Codes API providers (most common)</li>
                        <li><strong>M3U:</strong> Direct M3U playlist URLs</li>
                    </ul>
                    <p><strong>To add a provider:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Go to "Your Streams" → "Your Providers"</li>
                        <li>Click the <span uk-icon="plus"></span> add button</li>
                        <li>Fill in the provider details (name, domain, credentials)</li>
                        <li>Set the stream type (MPEGTS or HLS)</li>
                        <li>Configure priority and filtering options</li>
                    </ol>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What does provider priority mean?</a>
                <div class="uk-accordion-content">
                    <p>Provider priority determines the order of preference when you have multiple providers offering similar content.</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Lower numbers = Higher priority</strong> (Priority 1 is highest)</li>
                        <li>Streams from higher priority providers appear first in your lists</li>
                        <li>Use this to prioritize your most reliable providers</li>
                    </ul>
                    <div class="uk-alert-warning dark-version" uk-alert>
                        <p><strong>Example:</strong> If Provider A has priority 1 and Provider B has priority 5, streams from Provider A will be listed first.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I get my IPTV app credentials for a provider?</a>
                <div class="uk-accordion-content">
                    <p>Each provider in your list shows the connection credentials you need for IPTV apps:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Go to "Your Streams" → "Your Providers"</li>
                        <li>Find the provider you want to connect</li>
                        <li>Look for the XC credentials section showing Domain, Username, and Password</li>
                        <li>Copy these values into your IPTV app</li>
                    </ol>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Note:</strong> The Password field is your provider's ID number. Each provider has a unique password.</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <!-- IPTV App Setup Section -->
    <div class="uk-margin">
        <h3 class="uk-heading-bullet">IPTV App Setup (Xtream Codes)</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How do I add my streams to an IPTV app?</a>
                <div class="uk-accordion-content">
                    <p>KPTV Stream Manager supports the Xtream Codes API format, which is compatible with most popular IPTV apps including TiviMate, XCIPTV, Smarters Pro, OpenTV, GSE Smart IPTV, and many others.</p>
                    <p><strong>To connect your IPTV app:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Go to "Your Providers" in the KPTV Stream Manager</li>
                        <li>Find the provider you want to add to your IPTV app</li>
                        <li>Copy the Domain, Username, and Password shown for that provider</li>
                        <li>Open your IPTV app and select "Add Playlist" or "Xtream Codes Login"</li>
                        <li>Enter the credentials:</li>
                    </ol>
                    <div class="uk-overflow-auto">
                        <table class="uk-table uk-table-divider uk-table-small">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Server URL / Host</strong></td>
                                    <td>The Domain shown on the providers page (e.g., <code><?php echo rtrim(KPTV_URI, '/'); ?>/xc</code>)</td>
                                </tr>
                                <tr>
                                    <td><strong>Username</strong></td>
                                    <td>Your encrypted user string (shown on the providers page)</td>
                                </tr>
                                <tr>
                                    <td><strong>Password</strong></td>
                                    <td>The provider ID number (shown on the providers page)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="uk-alert-success dark-version" uk-alert>
                        <p><strong>Important:</strong> Each provider has its own unique Password (provider ID). This ensures you only see streams from that specific provider in your IPTV app.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What IPTV apps are compatible?</a>
                <div class="uk-accordion-content">
                    <p>Any IPTV app that supports Xtream Codes API login should work. Popular compatible apps include:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>TiviMate</strong> (Android/Android TV) - Recommended</li>
                        <li><strong>OpenTV</strong> (Android/Android TV)</li>
                        <li><strong>XCIPTV</strong> (Android/Android TV)</li>
                        <li><strong>Smarters Pro</strong> (Android/iOS/Smart TV)</li>
                        <li><strong>GSE Smart IPTV</strong> (iOS/Android)</li>
                        <li><strong>OTT Navigator</strong> (Android/Android TV)</li>
                        <li><strong>Perfect Player</strong> (Android)</li>
                        <li><strong>Sparkle TV</strong> (Apple TV)</li>
                        <li><strong>IPTV Pro</strong> (Android)</li>
                    </ul>
                    <p class="uk-text-meta dark-version">When adding a playlist, look for "Xtream Codes" or "XC Login" option rather than M3U URL.</p>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What's the difference between XC API and M3U export?</a>
                <div class="uk-accordion-content">
                    <p><strong>Xtream Codes API (Recommended):</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Streams are organized into categories automatically</li>
                        <li>Supports Live, VOD, and Series separately</li>
                        <li>Better app integration with channel logos and EPG</li>
                        <li>Apps can refresh stream lists without re-adding</li>
                        <li>Provider-specific filtering using the password field</li>
                    </ul>
                    <p><strong>M3U Playlist:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Simple playlist file format</li>
                        <li>Works with any media player (VLC, Kodi, etc.)</li>
                        <li>All streams in a single flat list</li>
                        <li>Must re-download playlist to see changes</li>
                    </ul>
                    <div class="uk-alert-success dark-version" uk-alert>
                        <p><strong>Recommendation:</strong> Use Xtream Codes API for IPTV apps, and M3U for general media players like VLC.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">Can I view all providers' streams in one IPTV app playlist?</a>
                <div class="uk-accordion-content">
                    <p>The Xtream Codes API connection is provider-specific - each provider requires its own login in your IPTV app.</p>
                    <p><strong>To view all streams from all providers:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Use the M3U export links instead, which combine all providers</li>
                        <li>Or add multiple XC logins in your IPTV app (one per provider)</li>
                    </ul>
                    <p class="uk-text-meta dark-version">Most IPTV apps support multiple playlists/logins that you can switch between.</p>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">Why aren't my streams playing in the IPTV app?</a>
                <div class="uk-accordion-content">
                    <p><strong>Common issues and solutions:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Authentication error:</strong> Double-check your Domain, Username, and Password from the providers page. Make sure you're using the correct provider ID as the password.</li>
                        <li><strong>No streams showing:</strong> Make sure you have active streams for that provider. Check that streams are marked as "Active".</li>
                        <li><strong>Streams buffering/not playing:</strong> This is usually a provider issue, not KPTV. Test the stream directly using the play button in the web interface.</li>
                        <li><strong>Categories empty:</strong> Streams need a TVG Group assigned to appear in categories.</li>
                        <li><strong>"Invalid login" or similar:</strong> Verify the Domain includes <code>/xc</code> at the end (e.g., <code><?php echo rtrim(KPTV_URI, '/'); ?>/xc</code>).</li>
                    </ul>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Testing tip:</strong> Try the M3U export first to verify your streams work, then troubleshoot the XC connection.</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <!-- Content Filtering Section -->
    <div class="uk-margin">
        <h3 class="uk-heading-bullet">Content Filtering</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">What are filters and how do they work?</a>
                <div class="uk-accordion-content">
                    <p>Filters help you automatically organize and exclude unwanted content from your streams. There are several filter types:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Include Name (regex):</strong> Only include streams matching a pattern</li>
                        <li><strong>Exclude Name:</strong> Remove streams with specific names</li>
                        <li><strong>Exclude Name (regex):</strong> Remove streams matching a pattern</li>
                        <li><strong>Exclude Stream (regex):</strong> Filter by stream URL patterns</li>
                        <li><strong>Exclude Group (regex):</strong> Filter by group/category patterns</li>
                    </ul>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I create effective filters?</a>
                <div class="uk-accordion-content">
                    <p><strong>Best Practices for Filtering:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Start with broad filters and refine them based on results</li>
                        <li>Use "Exclude Name" for simple text matching (e.g., "XXX", "Adult")</li>
                        <li>Use regex filters for complex patterns (e.g., ".*\b(word1|word2)\b.*")</li>
                        <li>Test your filters on a small subset first</li>
                    </ul>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Example:</strong> To exclude adult content, create an "Exclude Name" filter with terms like "XXX", "Adult", "18+"</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">Can I disable filtering for specific providers?</a>
                <div class="uk-accordion-content">
                    <p>Yes! When editing a provider, you can toggle the "Filter Content" option:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Yes:</strong> All active filters will be applied to this provider's content</li>
                        <li><strong>No:</strong> This provider's content will bypass all filters</li>
                    </ul>
                    <p class="uk-text-meta dark-version">This is useful for trusted providers where you want all content to be available.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Stream Organization Section -->
    <div class="uk-margin">
        <h3 class="uk-heading-bullet">Stream Organization</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How do I move streams between categories?</a>
                <div class="uk-accordion-content">
                    <p>You can move streams individually or in bulk:</p>
                    <p><strong>Individual Stream:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Click the category icon next to any stream (e.g., <span uk-icon="tv"></span> for Live, <span uk-icon="album"></span> for Series, or <span uk-icon="video-camera"></span> for VOD)</li>
                    </ul>
                    <p><strong>Bulk Move:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Select multiple streams using the checkboxes</li>
                        <li>Click the appropriate move button in the toolbar</li>
                        <li>All selected streams will be moved to the target category</li>
                    </ol>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I edit stream names and channel numbers?</a>
                <div class="uk-accordion-content">
                    <p>You can edit stream information in two ways:</p>
                    <p><strong>Quick Edit:</strong> Click directly on the stream name or channel number in the list to edit it inline.</p>
                    <p><strong>Full Edit:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Click the <span uk-icon="pencil"></span> edit button next to the stream</li>
                        <li>Modify any field in the edit modal</li>
                        <li>Click "Save" to apply changes</li>
                    </ol>
                    <div class="uk-alert-success dark-version" uk-alert>
                        <p><strong>Tip:</strong> Use descriptive names and logical channel numbers to make your streams easier to find.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What should I do with streams in the "Other" category?</a>
                <div class="uk-accordion-content">
                    <p>The "Other" category contains uncategorized content that needs to be organized:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Review the streams in the Other category</li>
                        <li>Move TV channels to "Live Streams"</li>
                        <li>Move TV shows and series to "Series Streams"</li>
                        <li>Move movies to "VOD Streams"</li>
                        <li>Delete any unwanted or broken streams</li>
                    </ol>
                    <p class="uk-text-meta dark-version">Keeping the Other category clean helps maintain an organized library.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Playlist Export Section -->
    <div class="uk-margin">
        <h3 class="uk-heading-bullet">Playlist Export</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How do I export my streams as M3U playlists?</a>
                <div class="uk-accordion-content">
                    <p>You can export playlists in several ways:</p>
                    <p><strong>Full Category Playlists:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Navigate to "Your Streams" in the sidebar</li>
                        <li>Click "Export the Playlist" for Live Streams, Series Streams, or VOD Streams</li>
                    </ul>
                    <p><strong>Provider-Specific Playlists:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Go to "Your Providers"</li>
                        <li>Use the M3U export buttons for each provider</li>
                    </ul>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Note:</strong> Only active streams are included in exported playlists.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">What's the difference between exported playlist types?</a>
                <div class="uk-accordion-content">
                    <p><strong>Full Playlists:</strong> Include all active streams from all providers in a category</p>
                    <p><strong>Provider-Specific Playlists:</strong> Include only streams from a single provider</p>
                    <p><strong>Use Cases:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Use full playlists for comprehensive channel lineups</li>
                        <li>Use provider-specific playlists to test individual providers</li>
                        <li>Share provider-specific playlists with others without exposing all your sources</li>
                    </ul>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I use exported playlists in media players?</a>
                <div class="uk-accordion-content">
                    <p>Your exported M3U playlists work with most IPTV-compatible media players:</p>
                    <p><strong>Popular Players:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>VLC Media Player</li>
                        <li>Kodi</li>
                        <li>Perfect Player (Android)</li>
                        <li>GSE Smart IPTV</li>
                        <li>TiviMate (Android TV)</li>
                    </ul>
                    <p><strong>To use:</strong> Simply copy the playlist URL and add it to your preferred media player's playlist manager.</p>
                    <div class="uk-alert-success dark-version" uk-alert>
                        <p><strong>Pro Tip:</strong> For dedicated IPTV apps, use the Xtream Codes credentials from the providers page for better organization and features.</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <!-- Troubleshooting Section -->
    <div class="uk-margin">
        <h3 class="uk-heading-bullet">Troubleshooting</h3>

        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">Why won't my stream play in the browser?</a>
                <div class="uk-accordion-content">
                    <p>Several factors can prevent streams from playing in the browser:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Format Compatibility:</strong> Some .ts streams may not play well in browsers</li>
                        <li><strong>CORS Issues:</strong> Some providers block cross-origin requests</li>
                        <li><strong>Stream Availability:</strong> The stream may be temporarily offline</li>
                    </ul>
                    <p><strong>Solutions:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Try copying the stream URL and opening it in VLC</li>
                        <li>Check if the stream works in other media players</li>
                        <li>Contact your provider if the stream consistently fails</li>
                    </ol>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">My exported playlist isn't working. What should I check?</a>
                <div class="uk-accordion-content">
                    <p><strong>Common Playlist Issues:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Check that you're using the correct playlist URL</li>
                        <li>Ensure your streams are marked as "active"</li>
                        <li>Verify your provider credentials are still valid</li>
                        <li>Test individual streams to isolate problems</li>
                    </ul>
                    <div class="uk-alert-warning dark-version" uk-alert>
                        <p><strong>Remember:</strong> Playlist URLs and XC credentials are unique to your account and should not be shared publicly.</p>
                    </div>
                </div>
            </li>

            <li>
                <a class="uk-accordion-title" href="#">How do I report bugs or request features?</a>
                <div class="uk-accordion-content">
                    <p>For technical issues, feature requests, or bug reports:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Visit our <a href="https://github.com/kpirnie/app.kptv.im/issues" target="_blank" class="uk-link">GitHub Issues</a> page</li>
                        <li>Search existing issues before creating a new one</li>
                        <li>Provide detailed information about the problem</li>
                        <li>Include steps to reproduce the issue if possible</li>
                    </ul>
                    <p class="uk-text-meta dark-version">Please note that support is provided on a best-effort basis.</p>
                </div>
            </li>
        </ul>
    </div>

</div>

<?php
// pull in the footer
KPTV::pull_footer();
