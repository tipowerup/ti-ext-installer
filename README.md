# TI PowerUp Installer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tipowerup/ti-ext-installer.svg)](https://packagist.org/packages/tipowerup/ti-ext-installer)
![Tests](https://github.com/tipowerup/ti-ext-installer/actions/workflows/tests.yml/badge.svg)
[![Coverage](https://codecov.io/gh/tipowerup/ti-ext-installer/branch/main/graph/badge.svg)](https://codecov.io/gh/tipowerup/ti-ext-installer)
![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue)
![License](https://img.shields.io/badge/license-MIT-green)

A TastyIgniter v4 extension that enables users to install, update, and manage extensions and themes purchased from the [TI PowerUp marketplace](https://tipowerup.com?source=installer) directly from the TI admin panel.

## Features

- **Hybrid Installation** — Auto-detects hosting environment and selects the best install method
- **Direct Extraction** — Downloads ZIPs and extracts via PHP `ZipArchive` (works on shared hosting)
- **Composer Installation** — Uses `composer require` via `Symfony\Process` (VPS/dedicated)
- **Batch Installation** — Install multiple packages with dependency-aware ordering
- **Background Update Checks** — Periodically checks for updates and notifies admins
- **Health Checks** — Validates PHP version, extensions, storage permissions, and API connectivity
- **Install Logging** — Tracks all install/update/uninstall actions with environment metadata
- **Backup & Restore** — Creates backups before updates, auto-restores on failure

## Requirements

- PHP ^8.3
- TastyIgniter ^v4.0
- PHP extensions: `zip`, `curl`, `mbstring`
- Writable `storage/` directory

## Installation

```bash
composer require tipowerup/ti-ext-installer
```

## Configuration

1. Navigate to **Tools > TI PowerUp Installer** in the TI admin panel
2. Enter your TI PowerUp API key (obtained from [tipowerup.com](https://tipowerup.com/profile?source=installer))
3. The installer will auto-detect your hosting environment and recommend an installation method

## Installation Methods

### Direct (Shared Hosting)

- Downloads package ZIP from the PowerUp API
- Verifies checksum integrity
- Extracts to `storage/app/tipowerup/extensions/` or `storage/app/tipowerup/themes/`
- Registers with TastyIgniter's extension/theme system
- No CLI access required

### Composer (VPS/Dedicated)

- Configures the TI PowerUp private Composer repository
- Runs `composer require` with authenticated access
- Packages installed to `vendor/` like standard Composer packages

**Additional requirements for Composer method:**

- `proc_open` and `proc_close` PHP functions enabled (not disabled in `php.ini`)
- 128MB+ PHP memory limit
- Shell access (`exec` or `proc_open` not blocked by hosting provider)
- Composer installed globally or auto-downloaded as `composer.phar`

## License

MIT
