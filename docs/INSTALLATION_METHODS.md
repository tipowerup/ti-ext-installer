# TastyIgniter Installation Methods Guide

Complete guide to installing, updating, and managing extensions and themes in TastyIgniter v4. Learn which method works best for your hosting environment and how to troubleshoot issues.

**For:** TastyIgniter v4 administrators
**Applies to:** Extensions, themes, and add-on packages

---

## Table of Contents

1. [Quick Comparison](#quick-comparison)
2. [Method 1: TI PowerUp Installer](#method-1-ti-powerup-installer)
3. [Method 2: Composer CLI Installation](#method-2-composer-cli-installation)
4. [Method 3: Manual File Extraction](#method-3-manual-file-extraction)
5. [File Locations Reference](#file-locations-reference)
6. [Discovery & Activation](#discovery--activation)
7. [Running Migrations](#running-migrations)
8. [Troubleshooting](#troubleshooting)
9. [Useful Commands](#useful-commands)

---

## Quick Comparison

| Feature | PowerUp Installer | Composer CLI | Manual |
|---------|-------------------|--------------|--------|
| **Best For** | Shared hosting users | Developers, VPS | Emergency recovery |
| **Ease of Use** | Very easy (web UI) | Moderate (CLI) | Technical only |
| **No CLI Required** | Yes | No | Yes |
| **Speed** | Fast (~2 min) | Moderate (~3-5 min) | Slowest (~5-10 min) |
| **Dependencies** | Auto-installed | Auto-resolved | Manual |
| **File Location** | `extensions/` or `vendor/` | `vendor/` | `extensions/` or `themes/` |
| **Hosting Support** | Shared hosting (80% users) | VPS/Dedicated (20% users) | All types |
| **Admin Panel UI** | Full support | Limited | No |

---

## Method 1: TI PowerUp Installer

The **TI PowerUp Installer extension** lets you install, update, and manage extensions and themes directly from your TastyIgniter admin panel. Works on shared hosting without requiring CLI access.

### Prerequisites

- TastyIgniter v4.0 or higher
- PHP 8.3+
- ZipArchive extension enabled
- cURL extension enabled
- Active API key from tipowerup.com

### Installation

#### Step 1: Install the PowerUp Installer Extension

1. Log in to your TastyIgniter admin panel
2. Navigate to **System > Extensions**
3. Find "TI PowerUp Installer" in the list
4. Click **Install**

Alternatively, install via Composer:

```bash
composer require tipowerup/ti-ext-installer
```

Then enable it in the admin panel.

#### Step 2: Access the Installer

After installation, you'll see **"PowerUp Installer"** under **Tools** in the admin navigation menu.

#### Step 3: Complete Onboarding

On first launch, the installer guides you through:

1. **System Health Check** — Verifies PHP version, ZipArchive, cURL, disk space, and memory limits
2. **API Key Setup** — Enter your TI PowerUp API key (get from https://tipowerup.com/profile/api-keys)
3. **Connection Verification** — Confirms your account and subscription status

### Using PowerUp Installer

#### Install an Extension or Theme

1. Click **Tools > PowerUp Installer**
2. Go to **Marketplace** tab
3. Search for the package or browse the catalog
4. Click on a package to see details
5. Click **Install** (if purchased) or **Buy on TI PowerUp** (to purchase first)
6. Wait for installation to complete

**What happens during installation:**
- Installer downloads from TI PowerUp API
- Integrity check (SHA256 verification)
- Direct extraction to `extensions/{vendor}/{name}/` or via Composer to `vendor/`
- Database migrations run automatically
- Assets published
- Extension is enabled

#### Update a Package

1. Click **Installed** tab
2. Look for packages marked "Update available"
3. Click the **Update** button
4. Confirm the version upgrade
5. Wait for installation to complete

**Automatic backup:** The current version is backed up before updating. If something goes wrong, you can restore it.

#### Disable a Package

Toggle the **Enable/Disable** switch on any installed package to temporarily disable it without uninstalling.

**Disabled packages:**
- Not loaded by TastyIgniter
- Can be re-enabled without reinstalling
- Database tables and data are preserved

#### Uninstall a Package

1. Click the **Uninstall** button on a package
2. Confirm: "This action cannot be undone"
3. Wait for uninstall to complete

**What happens during uninstall:**
- Database migrations rolled back (data deleted)
- Extension files removed
- Extension deregistered from TI

### Where Files Are Located

**Direct extraction method (shared hosting):**
```
extensions/
  vendor/
    extension-name/
      composer.json
      src/
      resources/
      database/
```

**Composer method (VPS/Dedicated):**
```
vendor/
  vendor/
    extension-name/
      composer.json
      src/
      resources/
      database/
```

### Batch Installation

Install multiple packages at once:

1. Select checkboxes next to packages
2. Click **Install Selected** (or **Update Selected**)
3. Installer handles them in dependency order
4. Required dependencies are automatically installed first

### Auto-Detect Installation Method

PowerUp Installer automatically chooses the best method for your hosting:

- **Shared hosting** (exec() disabled) → Direct extraction
- **VPS/Dedicated** (exec() available) → Composer

You can override this in **Settings** if needed.

---

## Method 2: Composer CLI Installation

For developers and VPS users. Install extensions and themes using the `composer` command from your server terminal.

### Prerequisites

- SSH access to your server
- Composer installed (https://getcomposer.org)
- File permissions: ability to modify `composer.json` and `vendor/` directory

### Available Installation Sources

#### TI PowerUp Marketplace (Recommended)

Install extensions and themes purchased from tipowerup.com:

```bash
# Add the TI PowerUp repository with authentication
composer config repositories.tipowerup composer https://packages.tipowerup.com
composer config http-basic.packages.tipowerup api_username "{YOUR_API_KEY}"

# Install a package
composer require tipowerup/ti-ext-loyaltypoints
```

Get your API key from: https://tipowerup.com/profile/api-keys

#### GitHub or Other Public Repositories

Install packages from GitHub or other public package repositories:

```bash
# Install from GitHub
composer require vendor/ti-ext-package-name
```

#### Custom Repositories

For self-hosted or private repositories:

```bash
# Add a custom repository
composer config repositories.custom composer https://your-domain.com/packages
composer config http-basic.your-domain.com username password

# Install a package
composer require your-vendor/ti-ext-custom
```

### Installation Steps

1. **SSH into your server:**

```bash
ssh user@your-domain.com
cd /path/to/tastyigniter
```

2. **Add repository (if using private packages):**

```bash
composer config repositories.tipowerup composer https://packages.tipowerup.com
composer config http-basic.packages.tipowerup api_username "{YOUR_API_KEY}"
```

3. **Require the package:**

```bash
composer require tipowerup/ti-ext-loyaltypoints
```

4. **Composer automatically:**
   - Downloads the package
   - Resolves and installs dependencies
   - Updates `vendor/composer/installed.json`
   - Rebuilds the autoloader

5. **The package is now in:** `vendor/tipowerup/ti-ext-loyaltypoints/`

### Running Migrations

After Composer installation, run migrations to create database tables:

```bash
php artisan igniter:up
```

Or for a specific extension:

```bash
php artisan igniter:extension-install tipowerup.loyaltypoints
```

### Where Files Are Located

Packages installed via Composer always go to:

```
vendor/
  {vendor}/
    ti-ext-{name}/
      composer.json
      src/
      resources/
      database/
```

Example:
```
vendor/tipowerup/ti-ext-loyaltypoints/
```

### Update via Composer

```bash
# Check for updates
composer update

# Update a specific package
composer update tipowerup/ti-ext-loyaltypoints

# Update to a specific version
composer require "tipowerup/ti-ext-loyaltypoints:^2.0"
```

### Remove via Composer

```bash
# Remove a package
composer remove tipowerup/ti-ext-loyaltypoints

# This automatically:
# 1. Rolls back all migrations
# 2. Removes files from vendor/
# 3. Updates composer.lock
```

### Troubleshooting Composer Issues

**"Could not find a matching version"**

```bash
# Refresh package cache
composer clear-cache
composer update
```

**"Authentication required"**

```bash
# Reconfigure authentication
composer config --unset http-basic.packages.tipowerup
composer config http-basic.packages.tipowerup api_username "{YOUR_API_KEY}"
```

**"Composer install takes too long"**

```bash
# Use --prefer-dist for faster downloads
composer update --prefer-dist
```

---

## Method 3: Manual File Extraction

For emergencies or advanced users. Manually download and extract package files to your server.

### Prerequisites

- FTP/SFTP access or file manager access
- Ability to create directories and upload files
- Understanding of TastyIgniter's directory structure

### For Extensions

#### Step 1: Get the Package Files

1. Download the extension ZIP file from your provider
2. Extract locally to see the structure

#### Step 2: Upload to Extensions Directory

Upload to: `extensions/{vendor}/{extension}/`

Example directory structure:

```
extensions/
  tipowerup/
    loyaltypoints/
      composer.json          (MUST be present)
      src/
        Extension.php        (MUST be present)
        Http/
        Models/
      resources/
        lang/
        models/
        views/
      database/
        migrations/
```

Required files:
- `composer.json` — Package metadata and TI configuration
- `src/Extension.php` — Extension entry point

#### Step 3: Discover the Extension

1. Log in to TastyIgniter admin
2. Navigate to **System > Extensions**
3. Click **Sync Extensions** (or refresh the page)
4. The new extension appears in the list

#### Step 4: Install and Enable

1. Click the **Install** button next to the extension
2. Database tables are created automatically
3. Click **Enable** to activate it

### For Themes

#### Step 1: Get the Theme Files

1. Download the theme ZIP file
2. Extract locally

#### Step 2: Upload to Themes Directory

Upload to: `themes/{theme-code}/`

Example directory structure:

```
themes/
  orange/
    theme.json             (MUST be present)
    config/
    layouts/
    pages/
    assets/
      css/
      js/
      images/
```

Required file:
- `theme.json` — Theme metadata

#### Step 3: Discover the Theme

1. Log in to TastyIgniter admin
2. Navigate to **Design > Themes**
3. Click **Sync Themes** (or refresh the page)
4. The new theme appears in the list

#### Step 4: Activate the Theme

1. Find the theme in the list
2. Click **Activate**
3. The theme is now live on your storefront

### Structure Requirements

#### Extension composer.json

```json
{
    "name": "vendor/ti-ext-name",
    "type": "tastyigniter-package",
    "extra": {
        "tastyigniter-extension": {
            "code": "vendor.name",
            "name": "Display Name",
            "icon": {
                "class": "fa fa-icon",
                "color": "#FFF",
                "backgroundColor": "#000"
            }
        }
    }
}
```

#### Theme theme.json

```json
{
    "code": "theme-code",
    "name": "Theme Display Name",
    "description": "Description",
    "version": "1.0.0",
    "author": "Author Name"
}
```

### Troubleshooting Manual Installation

**Extension not appearing in System > Extensions**

- Check file permissions (must be readable)
- Verify `composer.json` exists in the extension root
- Verify extension code follows format: `vendor.name` (lowercase, alphanumeric only)
- Clear cache: **System > Diagnostics > Clear Cache**

**"SystemException: Required extension configuration file not found"**

- Missing `composer.json` in the extension root
- The file is not in the correct location

**"LogicException: Missing Extension class"**

- Missing `src/Extension.php` file
- The class name doesn't match the namespace

---

## File Locations Reference

### Where Installed Packages Live

| Package Type | Source | Storage Location |
|--------------|--------|------------------|
| Extension (Direct) | PowerUp Installer | `extensions/vendor/extension/` |
| Extension (Composer) | PowerUp Installer or CLI | `vendor/vendor/ti-ext-extension/` |
| Theme (Direct) | PowerUp Installer | `themes/theme-code/` |
| Theme (Composer) | PowerUp Installer or CLI | `vendor/vendor/ti-theme-theme/` |
| Manual Extension | Uploaded via FTP | `extensions/vendor/extension/` |
| Manual Theme | Uploaded via FTP | `themes/theme-code/` |

### Discovery Mechanism

TastyIgniter discovers packages in this order:

1. **Filesystem scan:** `extensions/` directory for `extensions/*/*/composer.json`
2. **Composer packages:** `vendor/` directory and `bootstrap/cache/addons.php` manifest
3. **Manual themes:** `themes/` directory for `theme.json` files

### Cache Files

After installation, clear these caches to ensure TI detects the new package:

```bash
# Clear application cache
php artisan cache:clear

# Clear view cache
php artisan view:clear

# Rebuild package manifest
php artisan igniter:package-discover
```

---

## Discovery & Activation

### How TastyIgniter Finds Extensions

1. **TI scans** for packages with `extra.tastyigniter-extension` in `composer.json`
2. **Manifest built:** `bootstrap/cache/addons.php` contains list of all discovered extensions
3. **ClassLoader** automatically adds extension namespaces to PHP autoloading

### Enabling/Disabling

**Disabled packages:**
- Still discovered and listed in admin
- NOT loaded by TastyIgniter
- NOT callable in your code
- Database tables remain (safe to re-enable)

**Track disabled status:** `bootstrap/cache/disabled-addons.json`

```json
{
    "tipowerup.loyaltypoints": true,
    "another.extension": true
}
```

### Database Tracking

TastyIgniter records extension/theme info in:

- **`extensions` table** — Installed extension names, versions, status
- **`themes` table** — Installed theme codes, names, versions, is_default status
- **`migrations` table** — Tracks executed migrations (prefixed by extension code)

---

## Running Migrations

### All at Once

```bash
php artisan igniter:up
```

This runs all pending migrations from:
- TastyIgniter core
- All enabled extensions
- All enabled themes

### For a Specific Extension

```bash
# Install and run migrations for one extension
php artisan igniter:extension-install vendor.name
```

### For a Specific Theme

```bash
# Install and run migrations for one theme
php artisan igniter:theme-install theme-code
```

### Rollback Migrations

```bash
# Rollback all extensions
php artisan igniter:down

# Rollback migrations and remove extension
php artisan igniter:extension-remove vendor.name

# Rollback migrations and remove theme
php artisan igniter:theme-remove theme-code
```

### Check Migration Status

```bash
# View executed migrations
php artisan migrate:status
```

---

## Troubleshooting

### Installation Issues

#### "Connection Failed: Could not connect to the TI PowerUp server"

**Cause:** API connection error or wrong API key

**Solutions:**
1. Verify API key is correct (get from https://tipowerup.com/profile/api-keys)
2. Check internet connection
3. Verify firewall doesn't block tipowerup.com
4. Try again in a few minutes (server may be temporarily down)

#### "Checksum Mismatch: Package integrity check failed"

**Cause:** Downloaded file is corrupted or incomplete

**Solutions:**
1. Click **Retry** to re-download
2. Check disk space (need ~500MB free minimum)
3. Check internet stability
4. Try using Composer method instead

#### "License Invalid: License validation failed"

**Cause:** Package not purchased, expired, or domain mismatch

**Solutions:**
1. Verify purchase on tipowerup.com
2. Confirm domain matches license
3. Check expiration date
4. Try different API key (if managing multiple subscriptions)
5. Contact sales@tipowerup.com

#### "Extraction Failed: Could not extract package files"

**Cause:** File permissions, disk full, or invalid ZIP

**Solutions:**
1. Check permissions on `storage/app/tipowerup/` (should be 755 or 775)
2. Free up disk space (need 2x ZIP file size)
3. Verify ZIP file is not corrupted
4. Check error logs: `storage/logs/`

**Fix permissions via FTP:**

```bash
chmod 755 storage/app/tipowerup/
chmod 755 vendor/
chmod 755 extensions/
```

#### "Migration Failed: Database migration error"

**Cause:** Database permissions issue or schema conflict

**Solutions:**
1. Verify database user has CREATE/ALTER permissions
2. Check database disk space
3. Try again (temporary database lock)
4. Check for conflicting column names
5. Contact hosting provider with error details

**View database logs:**

Contact your hosting provider for database error logs from the error time.

### Extension Not Working After Installation

**Symptoms:**
- Extension installed but doesn't appear in admin
- Features not working
- Database tables not created

**Steps to fix:**

1. **Verify installation:**
   ```bash
   php artisan igniter:package-discover
   ```

2. **Check it's enabled:**
   - Go to **System > Extensions**
   - Verify extension is listed and enabled (not grayed out)

3. **Clear caches:**
   - **System > Diagnostics > Clear Cache** (in admin)
   - Or via CLI: `php artisan cache:clear && php artisan view:clear`

4. **Run migrations:**
   ```bash
   php artisan igniter:up
   ```

5. **Check logs:**
   - `storage/logs/laravel.log` for errors

6. **Last resort:**
   - Uninstall and reinstall the extension

### Performance Issues

#### "Memory Limit Exceeded: Allowed memory exhausted"

**Cause:** Package is large or system memory is too low

**Solutions:**

Via `.htaccess` (cPanel):
```
php_value memory_limit 512M
```

Via `php.ini`:
```ini
memory_limit = 512M
```

Contact hosting provider to increase permanent limit.

#### "Max Execution Time Exceeded"

**Cause:** Installation takes too long (usually Composer on slow hosts)

**Solutions:**
1. Switch to "Direct Extraction" method in PowerUp Installer settings
2. Contact hosting provider to increase `max_execution_time` (should be 120+ seconds)
3. Try installation during off-peak hours

#### Installation Timeout During Composer

**Solutions:**
1. Use PowerUp Installer instead (Direct Extraction mode)
2. Increase timeout in Composer:
   ```bash
   composer install --timeout=600
   ```

### Update Issues

#### "Backup Restore Failed"

**Cause:** Backup corrupted or disk space issue

**Solutions:**
1. Check disk space
2. Try restoring from manual backup if available
3. Manually restore previous version and try again

#### Update Interrupted (Lost Connection)

**Status:**
- Installation continues on server
- You can check **Installed** tab to see status

**Recovery:**
1. If incomplete: Click **Restore Backup**
2. Try installing again
3. Check logs if still failing

### Cache-Related Issues

After installation, if you don't see the extension in admin:

```bash
# Clear all caches
php artisan cache:clear
php artisan view:clear

# Rebuild the package manifest
php artisan igniter:package-discover

# Clear Laravel bootstrap cache
rm -rf bootstrap/cache/packages.php
```

### File Permission Issues

If you see permission errors during installation:

```bash
# Set correct permissions (via SSH)
chmod 755 storage/
chmod 755 storage/app/
chmod 755 vendor/
chmod 755 extensions/

# If using shared hosting (via FTP)
# Set all directories to 755, files to 644
```

### Database Conflicts

**Symptoms:** Installation fails or existing extension breaks

**Cause:** Column names, table names, or migration conflicts

**Solutions:**
1. Check for duplicate table names (prefix with vendor name)
2. Verify no other extensions use same table names
3. Check for conflicting column names in core tables
4. Review error message in `storage/logs/`

### Theme Not Appearing

**Solutions:**
1. Verify `theme.json` exists in theme root
2. Check `code` field is lowercase, alphanumeric only
3. Clear cache: **Design > Themes > Sync Themes**
4. Verify file permissions

---

## Useful Commands

### Extension Management

```bash
# List all extensions
php artisan igniter:extension-list

# Install extension (run migrations)
php artisan igniter:extension-install vendor.name

# Refresh extension (re-run migrations)
php artisan igniter:extension-refresh vendor.name

# Remove extension (rollback migrations)
php artisan igniter:extension-remove vendor.name
```

### Theme Management

```bash
# List all themes
php artisan igniter:theme-list

# Install theme (run migrations)
php artisan igniter:theme-install theme-code

# Remove theme (rollback migrations)
php artisan igniter:theme-remove theme-code
```

### Database Migrations

```bash
# Run all pending migrations
php artisan igniter:up

# Rollback all migrations
php artisan igniter:down

# Check migration status
php artisan migrate:status
```

### Cache & Discovery

```bash
# Clear all caches
php artisan cache:clear
php artisan view:clear

# Rebuild package manifest
php artisan igniter:package-discover

# Refresh autoloader
composer dump-autoload
```

### Troubleshooting

```bash
# View application logs
tail -f storage/logs/laravel.log

# Check system health
php artisan igniter:health

# List available Artisan commands
php artisan list igniter
```

---

## Best Practices

### Before Installing

1. **Backup your database:**
   ```bash
   mysqldump -u user -p database_name > backup.sql
   ```

2. **Test on staging first** (if available)

3. **Check compatibility:**
   - TastyIgniter version requirement
   - PHP version requirement
   - Other dependencies

4. **Ensure disk space:** At least 500MB free

5. **Check system health:**
   - Memory limit: 256MB+
   - Max execution time: 120+ seconds
   - ZipArchive extension (for PowerUp Installer)

### After Installing

1. **Enable the extension** (if not auto-enabled)

2. **Clear cache:**
   - **System > Diagnostics > Clear Cache** (in admin)
   - Or: `php artisan cache:clear`

3. **Test functionality** on your site

4. **Review settings** of the new extension

5. **Monitor logs** for errors: `storage/logs/laravel.log`

### Regular Maintenance

1. **Check for updates** monthly
2. **Review licenses** quarterly (expiration dates)
3. **Disable unused extensions** (improves performance)
4. **Monitor disk space** (remove old backups if needed)
5. **Review logs** regularly for errors or warnings

### Update Strategy

- **Security updates:** Install immediately
- **Bug fixes:** Install within 30 days
- **Feature updates:** Install when needed

Always test updates on staging first if possible.

---

## Need Help?

For specific issues with the TI PowerUp Installer, refer to [USAGE.md](./USAGE.md).

For TastyIgniter core documentation, visit https://tastyigniter.com/docs

For TI PowerUp support, visit https://tipowerup.com/support
