# KPTV Stream Manager

A containerized PHP 8.4 web application for managing IPTV streams, providers, and playlists with Xtream Codes API compatibility. Ships as a single Docker image with PHP-FPM, nginx, SQLite, Redis, and cron built in.

## Features

- **Multi-Provider Support**: Manage multiple IPTV providers (Xtream Codes API or M3U playlists)
- **Stream Organization**: Categorize streams as Live, Series, or Other with inline editing and bulk move operations
- **Advanced Filtering**: Regex-based include/exclude filters applied automatically during sync
- **Xtream Codes API**: Full XC API compatibility for IPTV apps (TiviMate, Smarters, etc.)
- **M3U Playlist Export**: Generate M3U playlists per user or per provider
- **In-Browser Playback**: Multi-format video player with HLS, MPEG-TS, Video.js, and native HTML5 fallback chain
- **Stream Proxy**: Built-in CORS proxy for in-browser stream playback
- **CLI Sync Tool**: Automated synchronization with filtering, staging, deduplication, and missing stream detection
- **Metadata Fixup**: Propagate channel numbers, logos, and TVG IDs across matching streams
- **User Management**: Multi-user with role-based access, Argon2ID hashing, account lockout, and email activation
- **Maintenance Mode**: JSON-configurable maintenance mode with IP/CIDR allowlisting
- **Security Hardened**: CSP headers, HSTS, CSRF protection, reCAPTCHA, encrypted sessions, and nginx rules blocking sensitive files

---

## Quick Start

### 1. Create Your Configuration

```bash
cp config/config-example.json config.json
```

Edit `config.json` with your settings. At minimum you need `mainuri`, `mainkey`, `mainsecret`, and SQLite database settings. The container creates the SQLite file and imports the schema automatically on first run.

### 2. Start the Container

```bash
docker compose -f docker-compose-example.yaml up -d
```

Or run directly:

```bash
docker pull ghcr.io/kpirnie/kptv-app:latest
docker run -d \
  --name kptv-stream-manager \
  -p 8080:80 \
  -v ./data:/var/lib/data \
  -v ./config.json:/var/www/html/config.json \
  --restart unless-stopped \
  ghcr.io/kpirnie/kptv-app:latest
```

### 3. Access the Application

Browse to `http://localhost:8080`, register an account at `/users/register`, then promote the first user to admin (role `99`) directly in the database:

```bash
docker exec -it kptv-stream-manager sqlite3 /var/lib/data/kptv.sqlite \
  "UPDATE kptv_users SET u_role = 99, u_active = 1 WHERE id = 1;"
```

---

## Docker Image

### Available Tags

| Tag | Branch | Description |
|-----|--------|-------------|
| `latest` | `main` | Stable release |
| `dev` | `develop` | Development / pre-release |
| `<commit-sha>` | — | Pinned to a specific commit |

### Docker Compose

```yaml
services:
  kptv:
    # Use 'latest' for stable (main branch) or 'dev' for development branch
    image: ghcr.io/kpirnie/kptv-app:latest
    # image: ghcr.io/kpirnie/kptv-app:dev
    container_name: kptv-stream-manager
    ports:
      - "8080:80"
    volumes:
      - ./data:/var/lib/data
      # Required: Your application config
      - ./config.json:/var/www/html/config.json
      # Optional: Custom cron schedule
      - ./crontab:/etc/crontabs/root
    restart: unless-stopped

volumes:
  kptv-data:
```

### What's in the Container

All services are managed by the entrypoint script and run inside a single Alpine-based container:

| Service | Details |
|---------|---------|
| **nginx** | Web server on port 80, security headers, CSP, static asset caching |
| **PHP 8.4 FPM** | Application runtime with OPcache, Redis, PDO SQLite extensions |
| **SQLite** | File-based database, auto-initialized on first run from `config/schema.sqlite.sql` |
| **Redis** | Application caching layer |
| **cron** | Scheduled sync and missing stream checks |

Database data persists via the `/var/lib/data` volume mount.

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DEBUG` | `false` | Set to `true` for verbose entrypoint output |

### Volume Mounts

| Container Path | Purpose | Required |
|----------------|---------|----------|
| `/var/lib/data` | SQLite database persistence | Yes |
| `/var/www/html/config.json` | Application configuration | Yes |
| `/etc/crontabs/root` | Custom cron schedule | No |

### Default Cron Schedule

The container ships with this default schedule:

```cron
# Sync all providers every 6 hours
0 */6 * * * /usr/local/bin/php /var/www/html/sync/kptv-sync.php sync

# Check for missing streams daily at 3 AM
0 3 * * * /usr/local/bin/php /var/www/html/sync/kptv-sync.php testmissing
```

Override by mounting your own crontab file.

---

## Configuration

### config.json

```json
{
    "appname": "KPTV Stream Manager",
    "debug_app": false,
    "mainuri": "https://your-domain.com",
    "xcuri": "https://your-domain.com",
    "mainkey": "your-encryption-key-here",
    "mainsecret": "your-secret-key-here",
    "database": {
        "tbl_prefix": "kptv_",
        "server": "localhost",
        "schema": "kptv_db",
        "username": "your_db_user",
        "password": "your_db_password",
        "charset": "utf8mb4",
        "collation": "utf8mb4_unicode_ci",
        "persistent": true
    },
    "user-agent": "VLC/3.0.18 LibVLC/3.0.18",
    "smtp": {
        "debug": false,
        "server": "smtp.example.com",
        "port": 587,
        "username": "your_smtp_user",
        "password": "your_smtp_password",
        "security": "tls",
        "fromname": "KPTV Stream Manager",
        "fromemail": "noreply@example.com",
        "forcehtml": true
    },
    "recaptcha": {
        "sitekey": "your_recaptcha_site_key",
        "secretkey": "your_recaptcha_secret_key",
        "expectedhostname": "your-domain.com"
    }
}
```

| Setting | Description |
|---------|-------------|
| `mainuri` | Your app's public URL (no trailing slash) |
| `xcuri` | URL that IPTV apps connect to for the XC API (usually same as `mainuri`) |
| `mainkey` / `mainsecret` | Used for AES-256-CBC encryption of session data and user tokens |
| `database` | SQLite connection settings (`driver`, `sqlite_path`) and legacy DB options |
| `smtp` | Required for registration activation emails and password resets |
| `recaptcha` | Google reCAPTCHA v3 keys for login, register, and forgot password forms |

### Maintenance Mode

Control via `/var/www/html/.maintenance.json` (or mount your own):

```json
{
    "enabled": false,
    "allowed_ips": ["127.0.0.1", "192.168.2.0/24"],
    "message": "Down for maintenance",
    "until": "2025-12-25T15:00:00Z"
}
```

When enabled, all requests return `503` unless the client IP matches an allowed IP or CIDR range.

---

## Web Application

### Key Features

- **Provider Management** — Add/edit XC API or M3U providers with connection limits, priority ordering, and per-provider filtering toggle
- **Stream Management** — Inline-editable datatables for stream names, channels, and metadata. Click-to-edit, bulk activate/deactivate, bulk move between categories
- **Filter Configuration** — Create include/exclude filters with regex support. Include filters (type 0) whitelist by name; exclude filters block by name (string or regex), stream URI, or group
- **Playlist Export** — M3U playlist URLs per user or per provider, with copy-to-clipboard links in the providers table
- **Missing Streams** — View streams that exist in your database but were removed from the provider, with options to delete the master stream or just clear the missing record
- **In-Browser Player** — Click any stream to attempt playback via HLS.js → mpegts.js → Video.js → native HTML5, with automatic `.ts` to `.m3u8` fallback
- **User Administration** — Admin users (role 99) can manage accounts, toggle active status, unlock locked accounts, and delete users

### Xtream Codes API Endpoints

These endpoints let IPTV apps (TiviMate, IPTV Smarters, etc.) connect directly:

| Endpoint | Description |
|----------|-------------|
| `/xc` | Main XC API endpoint |
| `/player_api.php` | Standard XC API endpoint (rewritten to `/api/xtream`) |
| `/live/{username}/{password}/{streamId}` | Live stream redirect |
| `/movie/{username}/{password}/{streamId}` | VOD stream redirect |
| `/series/{username}/{password}/{streamId}` | Series stream redirect |

**Authentication**: The `username` field is the provider ID, and the `password` field is the user's encrypted ID (shown in the providers table export links).

### Web Routes

| Route | Description |
|-------|-------------|
| `/` | Dashboard with stream counts per provider |
| `/providers` | Manage stream providers |
| `/filters` | Manage sync filters |
| `/streams/live/active` | Active live streams |
| `/streams/live/inactive` | Inactive live streams |
| `/streams/series/active` | Active series streams |
| `/streams/series/inactive` | Inactive series streams |
| `/streams/other/all` | Uncategorized/other streams |
| `/missing` | Missing stream tracking |
| `/playlist/{user}/{which}` | M3U export (all providers) |
| `/playlist/{user}/{provider}/{which}` | M3U export (specific provider) |
| `/users/login` | Login |
| `/users/register` | Registration |
| `/users/forgot` | Password reset |
| `/users/changepass` | Change password |
| `/users/faq` | Account FAQ |
| `/streams/faq` | Stream management FAQ |

---

## CLI Synchronization Tool

Run sync commands inside the container:

```bash
docker exec -it kptv-stream-manager php /var/www/html/sync/kptv-sync.php <action> [options]
```

### Actions

| Action | Description |
|--------|-------------|
| `sync` | Fetch streams from providers, apply filters, and update database |
| `testmissing` | Identify active streams no longer present at the provider |
| `fixup` | Propagate metadata across matching streams |
| `cleanup` | Remove orphans, deduplicate by URI, clear staging table |

### Options

| Option | Description |
|--------|-------------|
| `--user-id <id>` | Filter to a specific user |
| `--provider-id <id>` | Filter to a specific provider |
| `--ignore <fields>` | Skip fields during fixup (comma-separated: `tvg_id`, `logo`, `tvg_group`, `name`, `channel`) |
| `--check-all` | Include inactive streams in missing check |
| `--debug` | Verbose output |

### Examples

```bash
# Sync all providers
docker exec -it kptv-stream-manager php /var/www/html/sync/kptv-sync.php sync

# Sync a specific provider with debug
docker exec -it kptv-stream-manager php /var/www/html/sync/kptv-sync.php sync --provider-id 32 --debug

# Check for missing streams (including inactive)
docker exec -it kptv-stream-manager php /var/www/html/sync/kptv-sync.php testmissing --check-all

# Fixup metadata but don't touch logos or channels
docker exec -it kptv-stream-manager php /var/www/html/sync/kptv-sync.php fixup --ignore logo,channel

# Run cleanup
docker exec -it kptv-stream-manager php /var/www/html/sync/kptv-sync.php cleanup
```

### Sync Process Detail

1. **Fetch** — Retrieves streams from the provider via XC API or M3U parsing. VOD streams are automatically skipped.
2. **Filter** — Applies user-configured filters. Include filters (type 0, regex) whitelist streams; exclude filters block by name (type 1 string, type 2 regex), URI (type 3 regex), or group (type 4 regex). If any include filter exists, streams must match at least one.
3. **Stage** — Inserts filtered streams into `kptv_stream_temp` in batches of 1,000.
4. **Compare** — Matches existing streams by `s_orig_name` first, then `s_stream_uri`. Only updates the changed field (URI or name), leaving all other metadata untouched.
5. **Insert** — New streams are inserted as inactive (`s_active=0`) for manual review.
6. **Cleanup** — Clears the provider's rows from `kptv_stream_temp`.

### Troubleshooting

**"This script can only be run from the command line"** — The sync script blocks web access. Run it via `docker exec` or cron only.

**"Configuration file not found"** — Ensure `config.json` is mounted at `/var/www/html/config.json`.

**"No providers found"** — Verify providers exist for the specified user/provider ID. Check the web UI.

**Connection/timeout errors** — Providers may be rate limiting. The sync has built-in retry logic (3 attempts with backoff). Check provider URLs and credentials.

**Memory issues with large providers** — Streams are processed in batches with garbage collection between providers. For very large providers (100k+), sync per-provider with `--provider-id`.

---

## Database

### Tables

| Table | Description |
|-------|-------------|
| `kptv_users` | User accounts, authentication, login tracking |
| `kptv_streams` | Main stream storage with fulltext indexes on name fields |
| `kptv_stream_providers` | Provider configurations (XC API or M3U) |
| `kptv_stream_filters` | User filter rules (include/exclude, regex/string) |
| `kptv_stream_temp` | Staging table for provider syncs |
| `kptv_stream_missing` | Missing stream tracking |

### Stored Procedures

| Procedure | Description |
|-----------|-------------|
| `CleanupStreams` | Removes orphaned streams, deduplicates by URI, clears temp table |
| `ResetStreamIDs` | Renumbers stream IDs sequentially and resets auto_increment |

---

## Project Structure

```
kptv-stream-manager/
├── config/
│   ├── config-example.json       # Example app configuration
│   ├── crontab                   # Default container cron schedule
│   ├── nginx.conf                # Main nginx server block
│   ├── php-custom.ini            # PHP overrides (memory, upload, timeout)
│   └── schema.sql                # Database schema and stored procedures
├── site/                         # → /var/www/html/ in the container
│   ├── .maintenance.json         # Maintenance mode config
│   ├── .nginx.conf               # App-level nginx includes (security headers, CSP, rewrites)
│   ├── index.php                 # Web entry point
│   ├── config.json               # App configuration (mounted at runtime)
│   ├── composer.json
│   ├── package.json              # Build scripts (CSS/JS minification)
│   ├── robots.txt                # Disallow all crawlers
│   ├── assets/
│   │   ├── css/                  # kptv.css, datatables.css, custom.css
│   │   ├── js/                   # kptv.js (UI), video.js (player), custom.js
│   │   └── schema.sql            # Schema copy for reference
│   ├── controllers/
│   │   ├── main.php              # Application bootstrap, routing, config loading
│   │   ├── kptv-static.php       # KPTV utility class (encryption, validation, helpers)
│   │   ├── kptv-user.php         # User management (auth, registration, password)
│   │   ├── kptv-stream-playlists.php  # M3U playlist generation
│   │   ├── kptv-xtreme-api.php   # XC API emulation
│   │   └── kptv-proxy.php        # Stream CORS proxy
│   ├── sync/
│   │   ├── kptv-sync.php         # CLI entry point
│   │   └── src/
│   │       ├── KpDb.php          # Database wrapper for sync engine
│   │       ├── ProviderManager.php
│   │       ├── FilterManager.php
│   │       ├── SyncEngine.php
│   │       ├── MissingChecker.php
│   │       ├── FixupEngine.php
│   │       ├── Database/         # WhereClause, OrderByClause, ComparisonOperator
│   │       └── Parsers/
│   │           ├── BaseProvider.php        # HTTP client with retry logic
│   │           ├── XtremeCodesProvider.php  # XC API parser
│   │           ├── M3UProvider.php          # M3U playlist parser
│   │           └── ProviderFactory.php
│   ├── views/
│   │   ├── routes.php            # All route definitions and middleware
│   │   ├── common/               # Shared components (control panel)
│   │   ├── wrapper/              # Header/footer layout templates
│   │   └── pages/                # Page templates (home, streams, users, etc.)
│   └── vendor/                   # Composer dependencies
├── data/                         # Persistent data directory (gitignored)
├── Dockerfile                    # Alpine PHP 8.4 FPM + nginx + SQLite + Redis
├── docker-compose-example.yaml
├── entrypoint.sh                 # Container init (DB setup, service startup)
├── refresh.sh                    # Bare-metal deploy/refresh script
└── README.md
```

---

## Security

- Passwords hashed with **Argon2ID** (64MB memory, 4 iterations, 2 threads)
- Session data encrypted with **AES-256-CBC**
- Account lockout after 5 failed attempts (15-minute cooldown)
- **CSRF protection** on all forms
- **reCAPTCHA v3** on login, registration, and forgot password
- **HSTS**, **CSP**, **X-Frame-Options**, **Referrer-Policy**, and other security headers via nginx
- nginx blocks access to `/sync/`, `config.json`, `.sql`, `.log`, `.git/`, `composer.json`, and other sensitive files
- CLI sync tool rejects web requests
- `robots.txt` disallows all crawlers

---

## Building from Source

### CSS/JS Build

The app uses `clean-css-cli` and `terser` for minification:

```bash
cd site
npm install
npm run build        # Minify CSS and JS
npm run watch        # Watch for changes during development
```

### Docker Image

```bash
docker build -t kptv-app:local .
```

The GitHub Actions workflow (`.github/workflows/docker-build.yml`) automatically builds and pushes to GHCR on push to `main` (tagged `latest`) or `develop` (tagged `dev`).

---

## License

MIT License — See `LICENSE` for details.

---

## Support

For issues, feature requests, or bug reports, open a GitHub issue. Support is provided on a best-effort basis.
