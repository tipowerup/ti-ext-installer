# TI PowerUp Installer - Technical Documentation

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Service Interaction Flow](#service-interaction-flow)
3. [Installation Pipeline](#installation-pipeline)
4. [Batch Installation](#batch-installation)
5. [Event System](#event-system)
6. [Database Schema](#database-schema)
7. [API Contract](#api-contract)
8. [Security Measures](#security-measures)
9. [Caching Strategy](#caching-strategy)

---

## Architecture Overview

The extension implements a **hybrid installer** with three methods, selecting the best approach based on hosting environment capabilities.

### Design Rationale

**Problem:** TastyIgniter v4 uses Composer for package management, but ~80% of shared hosting blocks CLI execution (`exec()`, `proc_open()`).

**Solution:** Three-tier approach with automatic detection:
- **Direct Extraction** (80% of users) — Download ZIP, verify, extract via `ZipArchive`
- **Composer Installation** (20% of users) — Use `composer require` via `Symfony\Process`
- **Hybrid Auto-Detect** — `HostingDetector` routes to best method

### Core Classes

| Class | Responsibility |
|-------|-----------------|
| `Extension` | Entry point, registers Livewire components and navigation; also registers storage-based packages on boot |
| `PackageInstaller` | Facade routing to Direct or Composer installer |
| `DirectInstaller` | Download ZIP, verify checksum, extract to `storage/app/tipowerup/`, register |
| `ComposerInstaller` | Configure repo, add auth, run `composer require` |
| `HostingDetector` | Analyze environment (exec, memory, composer available) |
| `InstallationPipeline` | Orchestrate full flow with progress tracking |
| `BackupManager` | Create/restore backups before/after changes |
| `PowerUpApiClient` | HTTP client for TI PowerUp API communication |
| `BatchInstaller` | Resolve dependencies and group packages |
| `HealthChecker` | Pre-installation system checks (includes public vendor writability check) |
| `CompatibilityChecker` | Verify package requirements |

### AdminController + Livewire Bridge

The extension uses **TastyIgniter's admin panel** with **Livewire v3 components**:

```
Admin Panel (HTTP Request)
        ↓
Installer Controller (route: /admin/tipowerup/installer)
        ↓
Livewire Component (InstallerMain)
        ↓
Sub-Components (Installed, Marketplace, Detail, Progress, etc.)
        ↓
Service Classes (PackageInstaller, DirectInstaller, etc.)
```

**Why Livewire:**
- Real-time progress updates during installation
- Reactive UI (tabs, modals, lists)
- No separate API endpoints needed
- Built-in CSRF protection

---

## Service Interaction Flow

### High-Level Flow

```
User Action
    ↓
[Livewire Component] (Marketplace, Installed Packages)
    ↓
[PackageInstaller] (choose method)
    ↓
┌─────────────────────────────────────────┐
│                                         │
├─→ [DirectInstaller]     OR   [ComposerInstaller]
│       ↓                             ↓
│   [PowerUpApiClient]         [PowerUpApiClient]
│       ↓                             ↓
│   Download ZIP               Configure auth
│       ↓                             ↓
│   Verify Checksum            Composer require
│       ↓                             ↓
│   Extract Files              Register with TI
│       ↓                             ↓
│   Publish Theme Assets       Run Migrations
│       ↓
│   Register with TI
│       ↓
│   Run Migrations
│
└─────────────────────────────────────────┘
    ↓
[BackupManager] (create/restore if needed)
    ↓
[InstallationPipeline] (log progress)
    ↓
License Model (save license info)
    ↓
InstallLog Model (audit trail)
```

### Service Dependencies

```
PackageInstaller
├── HostingDetector
├── DirectInstaller
│   ├── PowerUpApiClient
│   └── ExtensionManager / ThemeManager (TI)
├── ComposerInstaller
│   ├── PowerUpApiClient
│   └── Composer Process
└── PowerUpApiClient

InstallationPipeline
├── BackupManager
├── CompatibilityChecker
└── PowerUpApiClient

Livewire Components
├── PackageInstaller
├── PowerUpApiClient
├── HealthChecker
├── HostingDetector
└── CoreExtensionChecker
```

---

## Installation Pipeline

### Step-by-Step Execution

```
┌─────────────────────────────────────────────────────────────┐
│ 1. PREPARE PHASE (0-10%)                                    │
├─────────────────────────────────────────────────────────────┤
│ • Start timer                                               │
│ • Check if already installed                                │
│ • Verify license with API                                   │
│ • Extract package metadata (type, version, requirements)    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. COMPATIBILITY CHECK (10-20%)                             │
├─────────────────────────────────────────────────────────────┤
│ • Check TI version compatibility                            │
│ • Check PHP version requirements                            │
│ • Check extension dependencies                              │
│ • Fail gracefully if incompatible                           │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. BACKUP PHASE (20-30%) [UPDATES ONLY]                    │
├─────────────────────────────────────────────────────────────┤
│ • Create backup directory                                   │
│ • Copy existing files                                       │
│ • Save current database state                               │
│ • Skip for fresh installs                                   │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. DOWNLOAD/PREPARE PHASE (30-50%)                          │
├─────────────────────────────────────────────────────────────┤
│ Direct Method:                                              │
│ • Download ZIP from PowerUp API                             │
│ • Verify SHA256 checksum                                    │
│ • Detect corruption                                         │
│                                                             │
│ Composer Method:                                            │
│ • Add PowerUp repository to composer.json                   │
│ • Add auth credentials to auth.json                         │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. EXTRACTION PHASE (50-70%)                                │
├─────────────────────────────────────────────────────────────┤
│ Direct Method:                                              │
│ • Extract ZIP to storage/app/tipowerup/{type}/{name}       │
│ • Validate structure (Extension.php or src/Extension.php)  │
│ • Clean up ZIP file                                         │
│                                                             │
│ Composer Method:                                            │
│ • Run: composer require tipowerup/{package}                │
│ • Verify installation                                       │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. REGISTRATION PHASE (70-85%)                              │
├─────────────────────────────────────────────────────────────┤
│ • Load with TI ExtensionManager / ThemeManager              │
│ • Publish theme assets to public/vendor/{vendor}-{name}/   │
│ • Register service provider                                 │
│ • Create database model entry                               │
│ • Update installed extensions list                          │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 7. MIGRATION PHASE (85-95%)                                 │
├─────────────────────────────────────────────────────────────┤
│ • Run: php artisan igniter:up --extension=tipowerup.{pkg}  │
│ • Execute database migrations                               │
│ • Seed tables if needed                                     │
│ • Publish assets                                            │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 8. FINALIZATION PHASE (95-100%)                             │
├─────────────────────────────────────────────────────────────┤
│ • Save license record to database                           │
│ • Create install log entry                                  │
│ • Clear caches (app, config, view)                          │
│ • Return success with metadata                              │
│                                                             │
│ ON FAILURE:                                                 │
│ • Restore from backup (if exists)                           │
│ • Rollback migrations                                       │
│ • Delete extracted files                                    │
│ • Restore composer.json                                     │
└─────────────────────────────────────────────────────────────┘
```

### Method Selection Logic

```php
HostingDetector::getRecommendedMethod()
    ↓
if (can_exec AND memory >= 512MB AND composer_available)
    return 'composer'   ← VPS/Dedicated
else
    return 'direct'     ← Shared Hosting
```

---

## Batch Installation

### Dependency Resolution

When installing multiple packages, the system:

1. **Build Dependency Graph** — Map package → dependencies
2. **Topological Sort** — Arrange in install order (dependencies first)
3. **Group Independent Packages** — Packages without interdependencies
4. **Serial Installation** — Install one at a time (safer, simpler logging)

```php
BatchInstaller::install([
    'tipowerup.loyalty-points',
    'tipowerup.referral-system',
    'tipowerup.notifications',
])

// Step 1: Resolve dependencies
//   loyalty-points → requires: igniter.local
//   referral-system → no dependencies
//   notifications → requires: igniter.local, igniter.customer

// Step 2: Order for installation
//   [1] igniter.local (if not installed)
//   [2] loyalty-points
//   [3] referral-system
//   [4] igniter.customer (if not installed)
//   [5] notifications
```

### Progress Aggregation

Each package installation emits progress events:

```
Total: 50%
├── loyalty-points: 100% (20%)
├── referral-system: 0% (0%)
├── notifications: 0% (0%)

Then:
Total: 70%
├── loyalty-points: 100% (20%)
├── referral-system: 100% (25%)
├── notifications: 0% (0%)

Finally:
Total: 100%
├── loyalty-points: 100% (20%)
├── referral-system: 100% (25%)
├── notifications: 100% (25%)
```

---

## Event System

### Livewire Event Communication

```
Component Hierarchy:
├── InstallerMain (parent)
│   ├── Onboarding (modal)
│   ├── InstalledPackages (tab)
│   ├── Marketplace (tab)
│   ├── PackageDetail (modal)
│   ├── InstallProgress (modal)
│   └── SettingsPanel (modal)
```

### Event Flow

#### Installation Event

```
Marketplace.installPackage($code)
    ↓
emit('install-package', ['code' => $code])
    ↓
InstallerMain::onInstallPackage()
    ↓
emit('show-install-progress')
    ↓
InstallProgress::mount()
    ↓
exec PackageInstaller::install($code)
    ↓
emit('installation-progress', [
    'stage' => 'downloading',
    'percent' => 35,
    'message' => 'Downloading package...'
])
    ↓
InstallProgress::updateProgress()
    ↓
emit('installation-completed')
    ↓
InstalledPackages::refreshList()
```

#### Update Completion Event

```
InstallProgress::onInstallationCompleted()
    ↓
dispatch('installation-completed')
    ↓
InstalledPackages listens
    ↓
refresh() → reload list
    ↓
emit('install-progress-modal-close')
    ↓
Close modal in InstallerMain
```

### Key Events

| Event | Emitted By | Listened By | Payload |
|-------|-----------|------------|---------|
| `install-package` | Marketplace | InstallerMain | `{code, type}` |
| `update-package` | InstalledPackages | InstallerMain | `{code}` |
| `uninstall-package` | InstalledPackages | InstallerMain | `{code}` |
| `show-install-progress` | InstallerMain | InstallProgress | - |
| `installation-progress` | Backend job | InstallProgress | `{stage, percent, message}` |
| `installation-completed` | InstallProgress | InstalledPackages, Marketplace | `{code, success}` |
| `onboarding-completed` | Onboarding | InstallerMain | - |

---

## Database Schema

### `tipowerup_licenses` Table

Tracks installed packages and their license status.

| Column | Type | Nullable | Default | Purpose |
|--------|------|----------|---------|---------|
| `id` | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary key |
| `package_code` | VARCHAR(100) | No | - | Unique package identifier (e.g., `tipowerup.loyalty-points`) |
| `package_name` | VARCHAR(255) | Yes | - | Human-readable name (e.g., `Loyalty Points System`) |
| `package_type` | ENUM('extension', 'theme') | No | `'extension'` | Package type |
| `version` | VARCHAR(20) | No | - | Currently installed version |
| `install_method` | ENUM('direct', 'composer') | No | `'direct'` | How it was installed |
| `license_hash` | VARCHAR(64) | No | - | SHA256 hash for integrity verification |
| `installed_at` | TIMESTAMP | No | CURRENT_TIMESTAMP | Installation timestamp |
| `updated_at` | TIMESTAMP | Yes | NULL | Last update timestamp |
| `expires_at` | TIMESTAMP | Yes | NULL | License expiration date |
| `is_active` | BOOLEAN | No | `1` | Active/disabled status |

**Indexes:**
- `PRIMARY KEY (id)`
- `UNIQUE KEY unique_package_code (package_code)`
- `INDEX idx_expires_at (expires_at)`
- `INDEX idx_is_active (is_active)`

**Eloquent Scopes:**
- `active()` — WHERE `is_active` = TRUE
- `byPackage($code)` — WHERE `package_code` = $code

### `tipowerup_install_logs` Table

Audit trail of all installation operations.

| Column | Type | Nullable | Default | Purpose |
|--------|------|----------|---------|---------|
| `id` | BIGINT UNSIGNED | No | AUTO_INCREMENT | Primary key |
| `package_code` | VARCHAR(100) | No | - | Package being operated on |
| `action` | ENUM('install', 'update', 'uninstall') | No | - | Operation type |
| `method` | ENUM('direct', 'composer') | No | - | Installation method used |
| `version` | VARCHAR(20) | Yes | NULL | Version (from/to) |
| `success` | BOOLEAN | No | - | Success/failure status |
| `error_message` | LONGTEXT | Yes | NULL | Error details if failed |
| `extra_data` | JSON | Yes | NULL | Additional metadata (duration, from_version, etc.) |
| `created_at` | TIMESTAMP | No | CURRENT_TIMESTAMP | Timestamp |

**Indexes:**
- `PRIMARY KEY (id)`
- `INDEX idx_package_code (package_code)`
- `INDEX idx_created_at (created_at)`
- `INDEX idx_action (action)`

**Query:**
```php
// Audit all installations
InstallLog::where('action', 'install')->get();

// Find errors
InstallLog::whereNot('success', true)->latest()->get();

// Performance analysis
InstallLog::select('package_code')
    ->selectRaw('AVG(JSON_EXTRACT(extra_data, "$.duration_seconds")) as avg_duration')
    ->groupBy('package_code')
    ->get();
```

---

## API Contract

### Base Configuration

**Endpoint:** `https://api.tipowerup.com/v1`
**Authentication:** Bearer token in `Authorization` header
**Timeout:** 30 seconds with 3 retries (exponential backoff)

### Endpoints

#### 1. Verify API Key

```
POST /api/v1/verify-key
Authorization: Bearer {api_key}
Content-Type: application/json

{
    "domain": "restaurant.example.com"
}
```

**Response (200 OK):**
```json
{
    "valid": true,
    "user": {
        "id": 1234,
        "name": "John Doe",
        "email": "john@example.com"
    },
    "subscription": {
        "active": true,
        "plan": "pro",
        "expires_at": "2026-01-22T00:00:00Z"
    }
}
```

**Response (401 Unauthorized):**
```json
{
    "valid": false,
    "message": "Invalid or expired API key"
}
```

#### 2. Verify License

```
POST /api/v1/verify-license
Authorization: Bearer {api_key}
Content-Type: application/json

{
    "package_code": "tipowerup.loyalty-points",
    "domain": "restaurant.example.com",
    "ti_version": "4.0.0"
}
```

**Response (200 OK) - Has License:**
```json
{
    "valid": true,
    "package_code": "tipowerup.loyalty-points",
    "package_name": "Loyalty Points System",
    "package_type": "extension",
    "version": "1.2.0",
    "download_url": "https://pkg.tipowerup.com/loyalty-points/1.2.0.zip?token=xyz&expires=1234567890",
    "checksum": "sha256:abc123def456...",
    "auth_token": "composer-auth-token-for-private-repo",
    "expires_at": "2026-01-22T00:00:00Z",
    "requirements": {
        "igniter.local": ">=4.0",
        "igniter.user": "^4.0"
    }
}
```

**Response (403 Forbidden) - License Expired:**
```json
{
    "valid": false,
    "message": "License expired for this package",
    "expires_at": "2025-01-22T00:00:00Z"
}
```

**Response (404 Not Found):**
```json
{
    "valid": false,
    "message": "You do not have access to this package"
}
```

#### 3. Check Updates

```
POST /api/v1/check-updates
Authorization: Bearer {api_key}
Content-Type: application/json

{
    "packages": [
        {"package_code": "tipowerup.loyalty-points", "version": "1.0.0"},
        {"package_code": "tipowerup.referral-system", "version": "2.1.0"}
    ]
}
```

**Response (200 OK):**
```json
{
    "updates": {
        "tipowerup.loyalty-points": {
            "current": "1.0.0",
            "latest": "1.2.0",
            "changelog": "...",
            "breaking_changes": false
        }
    },
    "checked_at": "2024-01-15T10:30:00Z"
}
```

#### 4. Get Marketplace

```
GET /api/v1/marketplace?type=extension&search=loyalty&page=1&per_page=20
Authorization: Bearer {api_key}
```

**Response (200 OK):**
```json
{
    "data": [
        {
            "code": "tipowerup.loyalty-points",
            "name": "Loyalty Points System",
            "description": "Reward customers with points",
            "type": "extension",
            "author": "TI PowerUp",
            "version": "1.2.0",
            "price": 49.99,
            "purchased": true,
            "icon": "https://...",
            "rating": 4.8,
            "downloads": 1542,
            "updated_at": "2024-01-15T10:00:00Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "per_page": 20,
        "total": 95
    }
}
```

#### 5. Get Package Detail

```
GET /api/v1/packages/{package_code}
Authorization: Bearer {api_key}
```

**Response (200 OK):**
```json
{
    "code": "tipowerup.loyalty-points",
    "name": "Loyalty Points System",
    "description": "Full description here...",
    "type": "extension",
    "author": "TI PowerUp",
    "version": "1.2.0",
    "min_ti_version": "4.0.0",
    "max_ti_version": "4.x",
    "php_version": ">=8.3",
    "requirements": {
        "igniter.local": ">=4.0",
        "igniter.user": "^4.0"
    },
    "dependencies": [
        {
            "code": "tipowerup.referral-system",
            "name": "Referral System",
            "optional": true
        }
    ],
    "changelog": [
        {
            "version": "1.2.0",
            "date": "2024-01-15",
            "notes": "Added..., Fixed..., Improved..."
        }
    ],
    "screenshots": [
        {"url": "...", "caption": "Dashboard"}
    ],
    "rating": 4.8,
    "reviews": 42,
    "downloads": 1542,
    "updated_at": "2024-01-15T10:00:00Z",
    "documentation_url": "https://..."
}
```

#### 6. Get My Packages

```
GET /api/v1/my-packages
Authorization: Bearer {api_key}
```

**Response (200 OK):**
```json
{
    "packages": [
        {
            "code": "tipowerup.loyalty-points",
            "name": "Loyalty Points System",
            "version": "1.2.0",
            "purchased_at": "2024-01-01T00:00:00Z",
            "expires_at": "2026-01-01T00:00:00Z",
            "license_type": "lifetime|subscription",
            "update_available": true,
            "latest_version": "1.3.0"
        }
    ]
}
```

### Error Response Format

All errors follow this structure:

```json
{
    "error": "error_code",
    "message": "Human-readable description",
    "details": {
        "field": "package_code",
        "reason": "Package not found"
    }
}
```

### HTTP Status Codes

| Code | Meaning | Action |
|------|---------|--------|
| 200 | Success | Process response |
| 400 | Bad request | Fix request, retry |
| 401 | Unauthorized | Refresh API key |
| 403 | Forbidden | User lacks access |
| 404 | Not found | Resource doesn't exist |
| 429 | Rate limited | Backoff and retry |
| 500 | Server error | Retry with exponential backoff |
| 503 | Unavailable | Retry (maintenance window) |

### License Verification Flow

```
Client                          API Server
  │
  ├─► POST /verify-license
  │   {package_code, domain, ti_version}
  │
  │◄─────────────────────────────────────
  │   Check:
  │   • User has access to package
  │   • License not expired
  │   • Domain matches purchase
  │   • TI version compatible
  │   │
  │   Return: download_url, checksum, auth_token
```

### Package Download URL Structure

**Direct Installation:**
```
https://pkg.tipowerup.com/packages/{package_code}/{version}.zip?token={token}&expires={timestamp}
```

**Checksum Format:**
```
sha256:{hex}
```

**Composer Repository:**
```
Base: https://packages.tipowerup.com
Auth: username='license', password='{auth_token}'
```

---

## Security Measures

### ZIP Extraction Safety

```php
DirectInstaller::extractPackage()
    ├── Validate file count
    ├── Validate each file path
    │   ├── Reject: contains ".."
    │   ├── Reject: starts with "/" or "\"
    │   ├── Reject: dangerous extensions (phar, sh, exe)
    │   └── Accept: normal paths
    ├── Extract to storage/app/tipowerup/ (safer for shared hosting)
    └── Verify structure after extraction
```

### Checksum Verification

```php
// Hash file immediately after download
$actual = hash_file('sha256', $zipPath);

// Compare with API-provided hash using constant-time comparison
if (!hash_equals($expected, $actual)) {
    throw PackageInstallationException::checksumMismatch();
}
```

**Why constant-time comparison:**
- Prevents timing attacks
- Cannot deduce partial hash from timing

### License Hashing

```php
// Never store raw license keys
$hash = hash('sha256', [
    'package_code' => $code,
    'domain' => request()->getHost(),
    'expires_at' => $date,
]);

// Store hash in database for comparison
License::create(['license_hash' => $hash]);
```

### Auth.json Handling (Composer)

```php
// 1. Write auth credentials
$authJson = ['http-basic' => [
    'packages.tipowerup.com' => [
        'username' => 'license',
        'password' => $authToken,
    ]
]];

File::put(base_path('auth.json'), json_encode($authJson));

// 2. Secure with restricted permissions
chmod(base_path('auth.json'), 0600);  // Owner read/write only

// 3. Add to .gitignore
// (Should already be in .gitignore)
```

### API Security

- **HTTPS only** — All communication encrypted
- **Bearer tokens** — API key in Authorization header
- **Domain binding** — License tied to specific domain
- **Rate limiting** — 100 requests/minute per API key
- **No PII logging** — Never log API keys or license data

---

## Caching Strategy

### PowerUpApiClient Caching

**Not cached** — API calls happen for:
- License verification (once per install)
- Update checks (user-triggered)
- Package details (loaded on-demand)

**Why no caching?**
- License data time-sensitive
- API calls fast enough (30s timeout)
- Users expect real-time info

### Backup Manager Caching

```php
BackupManager::createBackup()
    ├── Location: storage/backups/tipowerup/{batch_id}/
    ├── Retention: 7 days (configurable)
    └── Cleanup: Automatic on restore or age
```

**Cache key format:**
```
backup:tipowerup:{package_code}:{timestamp}
```

### Installation Progress Caching

```php
// In-memory cache during installation
$progress = cache()->remember(
    'installation_progress:'.$batchId,
    now()->addHours(1),
    fn() => new InstallationProgress()
);
```

**TTL:** 1 hour (installation should complete in minutes)
**Auto-cleanup:** Laravel cache driver handles expiration

### Health Check Caching

```php
HealthChecker::check()
    ├── No caching (run fresh each time)
    └── Result: Transient, used for display only
```

**Why no cache?**
- System state changes frequently
- User needs current status
- Check is fast enough

### Model Query Caching

**License queries:**
```php
// Active licenses (scoped query)
License::active()->remember(5 * 60);  // Cache 5 minutes

// Single package
License::byPackage($code)->remember(1 * 60);  // Cache 1 minute
```

**Why cache?**
- Frequently accessed
- Data changes infrequently
- Reduces database load

### Artisan Command Caching

```php
// Clear after installation
cache()->flush('tipowerup:*');

// Or targeted clear
cache()->tags(['installer'])->flush();
```

---

## Configuration

### Environment Variables

```env
# Optional: Override API endpoint
TIPOWERUP_API_URL=https://api.tipowerup.com/v1

# Optional: Enable debug logging
TIPOWERUP_DEBUG=true
```

### Runtime Settings

Stored in TI `settings` table:

```php
settings()->put('tipowerup_api_key', '...');
settings()->put('tipowerup_onboarded', true);
settings()->put('tipowerup_install_method', 'auto');  // auto|direct|composer
```

### Constants

```php
class DirectInstaller {
    private const int DOWNLOAD_TIMEOUT_SECONDS = 300;
    private const array DANGEROUS_EXTENSIONS = [
        'phar', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com'
    ];
}

class PowerUpApiClient {
    private const string BASE_URL = 'https://api.tipowerup.com/v1';
    private const int TIMEOUT_SECONDS = 30;
    private const int MAX_RETRIES = 3;
}
```

---

## Performance Considerations

### Download Optimization

- **Chunked downloads** — Stream large files to disk
- **Progress reporting** — Real-time download percentage
- **Timeout handling** — 5 minutes per download
- **Retry logic** — Automatic retry on network failure

### Memory Management

- **Stream extraction** — ZipArchive doesn't load entire file to memory
- **Composer limits** — Unlimited memory for Composer (`COMPOSER_MEMORY_LIMIT=-1`)
- **Batch processing** — Install one package at a time, not parallel

### Database Optimization

- **Indexes on package_code** — Fast lookups by package
- **Indexes on expires_at** — Efficient license expiration checks
- **Soft deletes** — Not used (hard delete on uninstall for clean state)

---

## Monitoring & Logging

### Log Channels

All logs to `storage/logs/tipowerup-installer.log`:

```php
Log::info('PackageInstaller: Installation completed', [
    'package_code' => $code,
    'method' => $method,
    'duration_seconds' => 12.5,
]);
```

### Metrics to Monitor

1. **Installation success rate** — % successful vs failed
2. **Average installation time** — By method (direct vs composer)
3. **Failed installations** — Most common errors
4. **API reliability** — % successful API calls
5. **License validation** — % expired licenses

---

## Future Enhancements

- **Parallel batch installation** — Install independent packages simultaneously
- **Installation rollback UI** — Visual backup restore interface
- **Package dependency visualization** — Dependency tree graph
- **Scheduled updates** — Auto-update packages at specified time
- **Multi-domain management** — Manage multiple TI instances
- **Webhook notifications** — Real-time progress via WebSocket
