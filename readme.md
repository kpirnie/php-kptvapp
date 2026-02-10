# KPTV Stream Manager

A PHP 8.4 web application for managing IPTV streams, providers, and playlists with Xtream Codes API compatibility. Includes a powerful CLI synchronization tool for automated stream management.

## Features

- **Multi-Provider Support**: Manage multiple IPTV providers (Xtream Codes API or M3U playlists)
- **Stream Organization**: Categorize streams as Live, Series, VOD, or Other
- **Advanced Filtering**: Regex-based include/exclude filters for automated stream management
- **Xtream Codes API**: Full XC API compatibility for IPTV apps (TiviMate, Smarters, etc.)
- **M3U Export**: Generate M3U playlists for any media player
- **CLI Sync Tool**: Automated synchronization from providers to database
- **User Management**: Multi-user support with role-based access control

## Requirements

- PHP 8.4+
- MySQL/MariaDB
- Composer
- Web server (nginx recommended)
- Docker/Podman
- PHP Extensions: `json`, `mbstring`, `pdo`, `pdo_mysql`, `openssl`

## Installation

### 1. Clone and Install Dependencies

```bash
git clone <repository-url>
cd kptv-stream-manager
composer install
```

### 2. Configure the Application

Create `assets/config.json` with your settings:

```json
{
    "appname": "KPTV Stream Manager",
    "mainuri": "https://your-domain.com",
    "mainkey": "your-encryption-key-here",
    "mainsecret": "your-secret-key-here",
    "debug_app": false,
    "database": {
        "server": "localhost",
        "port": 3306,
        "schema": "kptv_db",
        "username": "your_db_user",
        "password": "your_db_password",
        "tbl_prefix": "kptv_",
        "charset": "utf8mb4",
        "collation": "utf8mb4_unicode_ci"
    },
    "smtp": {
        "server": "smtp.example.com",
        "port": 587,
        "security": "tls",
        "username": "your_smtp_user",
        "password": "your_smtp_password",
        "fromemail": "noreply@example.com",
        "fromname": "KPTV Stream Manager",
        "forcehtml": true,
        "debug": false
    },
    "recaptcha": {
        "sitekey": "your_recaptcha_site_key",
        "secretkey": "your_recaptcha_secret_key"
    }
}
```

### 3. Import Database Schema

```bash
mysql -u your_username -p your_database < sync/db_schema.sql
```

### 4. Configure Web Server

See `.nginx.conf` for recommended nginx configuration. Key points:

- Route all requests through `index.php`
- Configure SSL/TLS
- Set appropriate security headers

### 5. Set Permissions

```bash
chmod 755 sync/kptv-sync.php
mkdir -p .cache
chmod 775 .cache
```

---

## CLI Synchronization Tool

The CLI tool (`sync/kptv-sync.php`) synchronizes streams from your IPTV providers into the database. It supports automated scheduling via cron and provides several management operations.

### Basic Usage

```bash
# Navigate to the sync directory
cd sync

# Show help
php kptv-sync.php --help

# Sync all providers for all users
php kptv-sync.php sync

# Sync with debug output
php kptv-sync.php sync --debug
```

### Available Actions

| Action | Description |
|--------|-------------|
| `sync` | Fetch streams from providers and update database |
| `testmissing` | Identify streams in database that no longer exist at provider |
| `fixup` | Run metadata consolidation across similar streams |

### Command Options

| Option | Description |
|--------|-------------|
| `--user-id <id>` | Filter operations to a specific user |
| `--provider-id <id>` | Filter operations to a specific provider |
| `--ignore <fields>` | Skip updating specific fields during sync |
| `--debug` | Enable verbose debug output |
| `--help` | Display help information |

### Examples

```bash
# Sync all providers
php kptv-sync.php sync

# Sync only user ID 1's providers
php kptv-sync.php sync --user-id 1

# Sync a specific provider
php kptv-sync.php sync --provider-id 32

# Sync but preserve existing logos and TVG IDs
php kptv-sync.php sync --ignore tvg_id,logo

# Sync with all options combined
php kptv-sync.php sync --user-id 1 --provider-id 32 --ignore logo --debug

# Check for missing streams
php kptv-sync.php testmissing

# Check missing for specific user
php kptv-sync.php testmissing --user-id 1

# Run metadata fixup
php kptv-sync.php fixup

# Fixup for specific provider
php kptv-sync.php fixup --provider-id 32
```

### Ignore Fields

The `--ignore` option accepts a comma-separated list of fields to skip during sync:

| Field | Description |
|-------|-------------|
| `tvg_id` | EPG channel ID |
| `logo` | Channel logo URL |
| `tvg_group` | Stream group/category |

This is useful when you've manually curated metadata and don't want provider updates to overwrite your changes.

### Sync Process

The `sync` action performs the following steps:

1. **Fetch Streams**: Retrieves streams from the provider (XC API or M3U)
2. **Apply Filters**: Processes include/exclude filters configured in the web UI
3. **Stage Data**: Inserts streams into a temporary table
4. **Compare & Update**: Compares with existing streams, updates changed records
5. **Insert New**: Adds new streams as inactive (`s_active=0`)
6. **Cleanup**: Clears temporary staging data

New streams are always inserted as **inactive** to allow manual review before enabling.

### Missing Streams Check

The `testmissing` action:

1. Fetches current stream list from provider
2. Compares against database records marked as active
3. Records missing streams to `kptv_stream_missing` table
4. Useful for identifying dead/removed streams

### Metadata Fixup

The `fixup` action consolidates metadata across streams:

- Copies channel numbers from matching stream names
- Updates TVG IDs from most recently updated streams
- Updates logos from most recently updated streams
- Calls the `UpdateStreamMetadata` stored procedure

### Automated Scheduling (Cron)

Set up cron jobs for automated synchronization:

```bash
# Edit crontab
crontab -e

# Sync all providers every 6 hours
0 */6 * * * /usr/bin/php /path/to/kptv/sync/kptv-sync.php sync >> /var/log/kptv-sync.log 2>&1

# Check for missing streams daily at 3 AM
0 3 * * * /usr/bin/php /path/to/kptv/sync/kptv-sync.php testmissing >> /var/log/kptv-missing.log 2>&1

# Run fixup weekly on Sunday at 4 AM
0 4 * * 0 /usr/bin/php /path/to/kptv/sync/kptv-sync.php fixup >> /var/log/kptv-fixup.log 2>&1

# Sync specific high-priority provider every 2 hours
0 */2 * * * /usr/bin/php /path/to/kptv/sync/kptv-sync.php sync --provider-id 1 >> /var/log/kptv-provider1.log 2>&1
```

### Sync Output Example

```
Syncing provider 1 - My IPTV Provider
Fetching streams from Xtreme Codes API...
Fetching live streams...
Retrieved 5,432 live streams
Fetching VOD streams...
Retrieved 12,845 VOD streams
Fetching series...
Retrieved 3,211 series
Total streams retrieved: 21,488
Applying filters...
Found 15 active filters for user 1
Filter results: 18,234 streams kept, 3,254 filtered out (15.1% filtered)
Clearing temporary table...
Inserting streams into temporary table...
Inserted 18,234 records into temporary table
Syncing to main streams table...
Analysis: 1,245 to update, 523 to insert, 16,466 unchanged
Updating existing streams...
Updated 1,245 streams
Inserting new streams...
Inserted 523 new streams
Cleaning up temporary table...
Sync complete: 1,768 streams processed
============================================================
SYNC COMPLETE
============================================================
Providers processed: 1
Streams synced: 1,768
Errors: 0
============================================================
```

### Troubleshooting CLI

**"This script can only be run from the command line"**

- The script detected a web request. Only CLI execution is allowed.

**"Configuration file not found"**

- Ensure `assets/config.json` exists with valid database settings.

**"No providers found"**

- Check that providers exist in the database for the specified user.
- Verify the `--user-id` or `--provider-id` values are correct.

**Connection/timeout errors**

- Provider may be rate limiting requests.
- Built-in retry logic (3 attempts with backoff) should handle temporary issues.
- Check provider URL and credentials in the web interface.

**Memory issues with large providers**

- The sync processes streams in batches to manage memory.
- Garbage collection runs after each provider.
- For very large providers (100k+ streams), consider running per-provider.

**Filters not working as expected**

- Type 0 filters (include) take precedence over exclude filters.
- If include filters exist, streams must match at least one to be kept.
- Test regex patterns in the web interface before relying on them.

---

## Web Application

The web interface provides full stream management capabilities. See the built-in FAQ pages for detailed documentation:

- `/users/faq` - Account management help
- `/streams/faq` - Stream management help
- `/terms-of-use` - Terms of service

### Key Web Features

- **Provider Management**: Add/edit XC API or M3U providers
- **Filter Configuration**: Create include/exclude filters with regex support
- **Stream Organization**: Move streams between categories, edit metadata
- **Playlist Export**: Generate M3U playlists or use XC API credentials
- **User Administration**: Manage users, roles, and account status

### Xtream Codes API Endpoints

The application exposes XC-compatible endpoints for IPTV apps:

| Endpoint | Description |
|----------|-------------|
| `/xc` | Main XC API endpoint |
| `/player_api.php` | Legacy XC API endpoint |
| `/live/{user}/{pass}/{stream}` | Live stream redirect |
| `/movie/{user}/{pass}/{stream}` | VOD stream redirect |
| `/series/{user}/{pass}/{stream}` | Series stream redirect |

---

## Project Structure

```
kptv-stream-manager/
├── assets/
│   ├── config.json          # Application configuration
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript files
│   └── images/               # Static images
├── controllers/
│   ├── main.php              # Application bootstrap
│   ├── static.php            # KPT utility class
│   ├── kpt-user.php          # User management
│   ├── kpt-stream-playlists.php  # Playlist generation
│   ├── kpt-xtream-api.php    # XC API emulation
│   └── ...
├── sync/
│   ├── kptv-sync.php         # CLI entry point
│   ├── db_schema.sql         # Database schema
│   └── src/
│       ├── Config.php        # Configuration loader
│       ├── KpDb.php          # Database wrapper
│       ├── ProviderManager.php
│       ├── FilterManager.php
│       ├── SyncEngine.php
│       ├── MissingChecker.php
│       ├── FixupEngine.php
│       └── Parsers/
│           ├── BaseProvider.php
│           ├── XtremeCodesProvider.php
│           ├── M3UProvider.php
│           └── ProviderFactory.php
├── views/
│   ├── routes.php            # Route definitions
│   ├── pages/                # Page templates
│   └── wrapper/              # Layout templates
├── vendor/                   # Composer dependencies
├── composer.json
├── index.php                 # Web entry point
├── .nginx.conf               # Nginx configuration
└── README.md
```

---

## Database Tables

| Table | Description |
|-------|-------------|
| `kptv_users` | User accounts and authentication |
| `kptv_streams` | Main stream storage |
| `kptv_stream_providers` | Provider configurations |
| `kptv_stream_filters` | User filter rules |
| `kptv_stream_temp` | Temporary sync staging |
| `kptv_stream_missing` | Missing stream tracking |

---

## Security Considerations

- Passwords are hashed using Argon2ID
- Session data is encrypted
- Account lockout after failed login attempts
- CSRF protection on forms
- reCAPTCHA on authentication pages
- CLI tool restricted to command-line execution only

---

## License

MIT License - See `LICENSE` for details.

---

## Support

For issues, feature requests, or bug reports, open a GitHub issue.

Support is provided on a best-effort basis.
