# TI PowerUp Installer - User Guide

## Table of Contents

1. [Installation](#installation)
2. [First Launch](#first-launch)
3. [Managing Packages](#managing-packages)
4. [Marketplace](#marketplace)
5. [Batch Installation](#batch-installation)
6. [Settings](#settings)
7. [Troubleshooting](#troubleshooting)
8. [FAQ](#faq)

---

## Installation

### Prerequisites

- TastyIgniter v4.0 or higher
- PHP 8.3 or higher
- ZipArchive extension enabled
- cURL extension enabled

### Install via Composer

```bash
composer require tipowerup/ti-ext-installer
```

### Enable in TastyIgniter

1. Log in to your TastyIgniter admin panel
2. Navigate to **System > Extensions**
3. Find "PowerUp Installer" in the list
4. Click the **Install** button

### Verify Installation

The "PowerUp Installer" menu item appears under **Tools** in the admin navigation.

---

## First Launch

### Onboarding Wizard

When you access the PowerUp Installer for the first time, you'll see the onboarding wizard:

#### Step 1: System Health Check

The system verifies your environment is ready:

**Checks performed:**
- ✅ PHP version (8.3+)
- ✅ ZipArchive extension
- ✅ cURL extension
- ✅ Storage directory writable
- ✅ Vendor directory writable
- ✅ Public vendor directory writable
- ✅ Memory limit (256MB+)
- ✅ Max execution time (120s+)

**If checks fail:**
Each failed check shows "Fix Instructions" — follow them to resolve the issue.

#### Step 2: Enter API Key

Get your API key from [TI PowerUp](https://tipowerup.com/profile/api-keys):

1. Log in to tipowerup.com
2. Go to **Profile > API Keys**
3. Generate a new key (or copy existing)
4. Paste into the "API Key" field
5. Click **Verify**

**Connection will show:**
```
✓ Connected to TI PowerUp
✓ Your account: John Doe (john@example.com)
✓ Subscription: Pro Plan (Active)
```

#### Step 3: Welcome

You're ready to go!

- Click **Get Started** to access the marketplace
- Or **Install an Extension** to browse available packages

---

## Managing Packages

### View Installed Packages

Click the **"Installed"** tab to see all installed extensions and themes.

**For each package:**
- Name and current version
- Installation date
- License expiration date (if applicable)
- Update status (if updates available)
- Enable/Disable toggle
- Action buttons

### Install a Package

#### From Installed Packages Tab

If you purchased a package, it may appear in the "Available to Install" section:

1. Click the **Install** button
2. Installation progress modal appears
3. Wait for completion
4. Package is now active

#### From Marketplace Tab

1. Click **Marketplace**
2. Search or browse packages
3. Click package name to view details
4. Click **Install** (for purchased) or **Buy on TI PowerUp** (for new purchases)

### Update a Package

When an update is available:

1. Click **Update to v1.2.0** button
2. Confirm: "Update from v1.0.0 to v1.2.0?"
3. Wait for completion
4. Package is now at latest version

**What happens during update:**
- ✅ Current version is backed up
- ✅ New version is downloaded
- ✅ Database migrations run
- ✅ Assets are published
- ⚠️ Your data and configurations are preserved

### Uninstall a Package

1. Click the **Uninstall** button on a package
2. Confirm: "Are you sure? This action cannot be undone."
3. Wait for completion

**What happens during uninstall:**
- ✅ Files are deleted
- ✅ Database tables are purged (WARNING)
- ✅ Configuration is removed
- ⚠️ All data in that package's tables is permanently deleted

### Enable/Disable a Package

Toggle the **Enable/Disable** switch to temporarily disable a package without uninstalling it.

**Disabled packages:**
- Still appear in installed list
- Are not loaded by TastyIgniter
- Can be re-enabled without reinstalling
- Preserve database tables and data

---

## Marketplace

### Browse All Packages

1. Click the **Marketplace** tab
2. Browse the full catalog of extensions and themes

### Search

Type in the search box to find packages by name or description:

```
Search: "loyalty points"

Results:
├── Loyalty Points System (Extension)
├── Rewards Dashboard (Extension)
└── Points Leaderboard (Theme)
```

### Filter by Type

- **All** — Show extensions and themes
- **Extensions** — Show only extensions
- **Themes** — Show only themes

### View Package Details

Click a package name to open the detail modal:

**Tabs:**
- **Description** — Full details and features
- **Screenshots** — Visual previews
- **Reviews** — User ratings and comments
- **Changelog** — Version history and updates
- **Compatibility** — Requirements and dependencies

**Key information:**
- Version number
- Author and rating
- Last updated date
- Dependencies (other required packages)

### Install from Marketplace

1. Click **Install** button (if purchased)
2. Or click **Buy on TI PowerUp** (to purchase first)
3. After purchasing, click **Install**

---

## Batch Installation

### Install Multiple Packages

You can select and install multiple packages at once:

1. In **Installed** or **Marketplace** tabs, click checkboxes next to packages
2. A toolbar appears at the bottom: **"2 selected: Install Selected"**
3. Click **Install Selected**
4. Multi-install progress modal shows each package

**Progress shows:**
```
Installing Multiple Packages
├── ✓ loyalty-points (100%)
├── ⏳ referral-system (50%)
└── ⬜ notifications (0%)

Total: 50%
```

### Dependency Handling

The system automatically installs required dependencies:

```
You select: notifications (requires: igniter.local, igniter.customer)

System installs in order:
1. igniter.local ← dependency
2. notifications ← requested
3. (igniter.customer if not already installed)
```

### Cancel Installation

During multi-package installation, click **Cancel** to stop:

- ✅ Completed packages remain installed
- ✅ In-progress package is rolled back
- ⬜ Remaining packages are skipped

---

## Settings

Click the **⚙ Settings** button (top right) to access configuration:

### API Key Management

**Current API Key:**
- Shows masked key (e.g., `sk_live_****...****`)
- Shows account name and subscription status
- Click **Verify Key** to test connectivity

**Change API Key:**
1. Click **Change Key**
2. Enter new key
3. Click **Verify**

### Installation Method

**Default: Auto-Detect (Recommended)**

Choose how packages are installed:

- **Auto-Detect** — System chooses best method based on hosting
- **Direct Extraction** — Always download and extract ZIPs
- **Composer** — Always use `composer require` (VPS/Dedicated only)

**Why auto-detect is best:**
- Shared hosting gets Direct (CLI disabled)
- VPS gets Composer (more reliable)
- No manual configuration needed

### Environment Info

View your system capabilities:

**Detected:**
- Environment Type (Shared Hosting / VPS / Dedicated)
- Recommended Installation Method
- PHP Version
- Memory Limit
- Execution Time Limit
- Composer Available (Yes/No)
- Disk Space Available

**Example output:**
```
Environment: Shared Hosting
Recommended Method: Direct Extraction
PHP: 8.3.5
Memory: 256MB
Max Time: 120s
Composer: Not available (exec disabled)
Disk Free: 50 GB
```

---

## Troubleshooting

### Installation Fails: "Connection Failed"

**Error message:** "Could not connect to the TI PowerUp server"

**Solutions:**
1. Check internet connection
2. Verify API key is correct
3. Check firewall/VPN isn't blocking tipowerup.com
4. Try again in a few minutes (server may be down)
5. Contact [support](https://tipowerup.com/support)

### Installation Fails: "Checksum Mismatch"

**Error message:** "Package integrity check failed"

**Why:** Downloaded file is corrupted

**Solutions:**
1. Click **Retry** to re-download
2. Check disk space (need 500MB free)
3. Check internet stability
4. Contact support if persists

### Installation Fails: "License Invalid"

**Error message:** "License validation failed for this package"

**Why:** Package not purchased, expired, or domain mismatch

**Solutions:**
1. Verify you purchased the package on tipowerup.com
2. Confirm you're on the correct domain
3. Check license hasn't expired (see expiration date)
4. Try a different API key if managing multiple subscriptions
5. Contact [sales](https://tipowerup.com/sales)

### Installation Fails: "Extraction Failed"

**Error message:** "Failed to extract the package files"

**Why:** File permissions issue, disk full, or invalid ZIP

**Solutions:**
1. Check write permissions on `storage/app/tipowerup/` directory
2. Free up disk space (need 2x ZIP size minimum)
3. Try uninstalling and reinstalling from scratch
4. Contact support with logs

**Check permissions:**
```bash
# Should be writable (755 or 775)
chmod 755 storage/app/tipowerup/
```

### Installation Fails: "Migration Failed"

**Error message:** "Database migration failed"

**Why:** Database error during extension setup

**Solutions:**
1. Check database permissions
2. Verify database has free space
3. Check for conflicting extensions
4. Try again (temporary database lock)
5. Contact support with error details

**To view database logs:**
1. Contact your hosting provider
2. Ask for database error logs from the error time

### Update Fails: "Backup Restore Failed"

**Error message:** "Failed to restore from backup"

**Why:** Backup is corrupted or permissions issue

**Solutions:**
1. Check disk space (need backup size + new version size)
2. Check write permissions
3. Restore from manual backup if you have one
4. Contact support

### Marketplace Won't Load

**Error message:** "Failed to load marketplace"

**Why:** API connection issue or invalid key

**Solutions:**
1. Check internet connection
2. Verify API key is valid (see Settings)
3. Check if tipowerup.com is up (visit website)
4. Try again after a few minutes
5. Contact support

### "Memory Limit Exceeded"

**Error message:** "Allowed memory exhausted"

**Why:** Package is large or system memory is low

**Solutions:**

If you can edit `.htaccess` (cPanel hosting):
```
php_value memory_limit 512M
```

If you can edit `php.ini`:
```
memory_limit = 512M
```

If you can edit `wp-config.php` or similar (if Laravel config):
```php
ini_set('memory_limit', '512M');
```

Contact your host to increase memory limit.

### "Max Execution Time Exceeded"

**Error message:** "Maximum execution time exceeded"

**Why:** Installation takes too long (usually Composer on slow host)

**Solutions:**

1. Switch installation method to "Direct Extraction" in Settings
2. Contact host to increase `max_execution_time` (should be 120+)
3. Try at off-peak hours (less host load)

### Package Appears Installed but Not Working

**Steps to fix:**

1. Disable and re-enable the package (Settings toggle)
2. Clear application cache: **System > Diagnostics > Clear Cache**
3. Check that all dependencies are installed
4. Review error logs: `storage/logs/`
5. Uninstall and reinstall the package

---

## FAQ

<details>
<summary><strong>Can I install packages without purchasing?</strong></summary>

No. Packages must be purchased on [tipowerup.com](https://tipowerup.com) before you can install them. Your API key is tied to your account and purchased licenses.
</details>

<details>
<summary><strong>What's the difference between Direct and Composer installation?</strong></summary>

**Direct Extraction:**
- Downloads a ZIP file
- Extracts directly to server
- No command-line tools needed
- Works on shared hosting
- Slower for large packages

**Composer:**
- Uses package manager
- Fetches from private repository
- Cleaner for dependency management
- Requires VPS/Dedicated
- Faster for updates
- Better for development

Most users should use **Auto-Detect** (recommended).
</details>

<details>
<summary><strong>Can I install packages on multiple domains?</strong></summary>

Yes, but:
- Each domain needs its own TastyIgniter installation
- Each installation needs a valid license for each package
- One API key can manage multiple installations across multiple domains
- Licenses are domain-locked (tied to specific domain)

Contact sales if you need multi-domain licenses.
</details>

<details>
<summary><strong>What happens to my data when I uninstall?</strong></summary>

When you uninstall a package:
- ✅ Plugin files are deleted
- ✅ Database tables are dropped (all data lost)
- ✅ Configuration is removed
- ⚠️ This is permanent and cannot be undone

To keep data, disable instead of uninstall.
</details>

<details>
<summary><strong>Can I update all packages at once?</strong></summary>

Yes! Use batch installation:
1. Go to **Installed** tab
2. Select all packages with updates
3. Click **Update Selected**

System installs them in dependency order automatically.
</details>

<details>
<summary><strong>How long does installation take?</strong></summary>

Typical times:
- **Small package (direct):** 30-60 seconds
- **Large package (direct):** 1-3 minutes
- **Any package (composer):** 2-5 minutes
- **Batch (3 packages):** 5-10 minutes total

Times depend on:
- Package size
- Internet speed
- Server CPU
- Current server load
</details>

<details>
<summary><strong>What if installation is interrupted?</strong></summary>

If you lose connection or close browser during installation:

**Status:**
- Installation continues on server
- You can check Installed tab to see current state
- Backup is created and preserved

**Recovery:**
- Click **Restore Backup** to revert to previous version
- Try installing again
- Contact support if stuck
</details>

<details>
<summary><strong>Can I rollback to a previous version?</strong></summary>

Yes. Automatic backups are created before updates. To restore:

1. Go to **Installed** tab
2. Find the package
3. Click **Restore Backup** (if available)
4. Confirm: "Restore from backup?"

Backups are kept for 7 days.
</details>

<details>
<summary><strong>How do I know if my license is expiring?</strong></summary>

Check the **Installed** tab:
- Each package shows "Expires: Jan 22, 2026"
- System shows warning if expiring within 30 days
- Expired licenses cannot be updated

To renew:
1. Visit [tipowerup.com/account](https://tipowerup.com/account)
2. Go to **Licenses**
3. Click **Renew**
4. Complete payment

You can install without renewal, but won't get updates.
</details>

<details>
<summary><strong>What are dependencies?</strong></summary>

Some packages require other packages to work (dependencies). For example:

```
Loyalty Points requires: igniter.local, igniter.user
```

When you install, the system:
- ✅ Checks if dependencies are installed
- ✅ Installs them automatically if missing
- ✅ Installs them first (before the main package)

You cannot install a package if dependencies cannot be satisfied.
</details>

<details>
<summary><strong>Is there a way to see installation history?</strong></summary>

Yes. Installation history is logged but not visible in the UI yet. To view logs:

1. Access your server via SSH/FTP
2. Navigate to: `storage/logs/`
3. Open the latest `.log` file
4. Search for "tipowerup" or "installation"

Each entry shows: date, package, action, success/failure, duration.
</details>

<details>
<summary><strong>Can I use this on a local development machine?</strong></summary>

Yes! Setup:

1. Install TastyIgniter locally
2. Install PowerUp Installer extension
3. Get API key from tipowerup.com (works for any domain)
4. Install packages locally

Note: Domain is locked to your local domain (e.g., `restaurant.local`).
</details>

<details>
<summary><strong>How do I get support?</strong></summary>

**Getting help:**

1. Check this guide first
2. Read [FAQ on tipowerup.com](https://tipowerup.com/support)
3. Contact [support@tipowerup.com](mailto:support@tipowerup.com)
4. Join [Discord community](https://discord.gg/tipowerup)
5. Post on [GitHub discussions](https://github.com/tipowerup/installer/discussions)

Always include:
- Error message (exact text)
- Installation method (direct/composer/auto)
- Package name and version
- PHP and TI version
- Server logs (if available)
</details>

---

## Best Practices

### Before Installing

1. **Backup your database:**
   ```bash
   mysqldump -u user -p database > backup.sql
   ```

2. **Test on staging first** (if available)

3. **Check compatibility:**
   - TI version requirement
   - PHP version requirement
   - Other dependencies

### After Installing

1. **Enable the package** (check it's active in Installed tab)

2. **Clear cache:** System > Diagnostics > Clear Cache

3. **Test functionality** on your staging or live site

4. **Review settings** of the new package

5. **Monitor logs** for errors: `storage/logs/`

### Regular Maintenance

1. **Check for updates** monthly
2. **Review licenses** quarterly (expiration dates)
3. **Disable unused packages** (better performance)
4. **Remove old backups** if disk space is low
5. **Monitor logs** for errors or warnings

---

## Performance Tips

**Installation optimization:**

1. **Use Direct Extraction** if:
   - On shared hosting
   - Package is large (>50MB)
   - Need fast installation

2. **Use Composer** if:
   - On VPS/Dedicated
   - Installing many packages
   - Need better dependency management

3. **Batch install during off-hours** (low traffic time)

4. **Update all at once** rather than one at a time

**System optimization:**

1. Ensure 500MB free disk space minimum
2. Keep PHP memory limit at 512MB+
3. Set max execution time to 120+ seconds
4. Use SSD hosting (faster disk I/O)
5. Close unnecessary browser tabs (free RAM)

---

## Updates & Notifications

### When are updates released?

Packages receive updates for:
- Security fixes (released immediately)
- Bug fixes (released monthly)
- New features (released quarterly)

### Auto-notifications

You'll see a badge on the Installed tab when updates are available:
```
Installed (2)  ← "2" indicates 2 packages have updates
```

### Update strategy

**Recommended:**
- Security updates: Install immediately
- Bug fixes: Install within a month
- Features: Install when needed

**Safe:**
- Test on staging first
- Update during low-traffic periods
- Keep daily backups
