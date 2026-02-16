# Release & Publishing Guide

This guide covers the complete release and publishing workflow for TI PowerUp packages (extensions, themes, and tooling).

See [VERSIONING.md](./VERSIONING.md) for SemVer rules and stability checklists.

---

## Changelog

Keep a `CHANGELOG.md` in each package root. Update it with every release.

### Format

Follow [Keep a Changelog](https://keepachangelog.com) conventions:

```markdown
# Changelog

## [Unreleased]

### Added
- Feature in development

### Changed
- Changes in progress

## [0.2.0] - 2026-02-15

### Added
- New `HealthChecker` service for system diagnostics
- API endpoint for checking extension compatibility

### Changed
- `PackageInstaller::install()` now returns a result object instead of bool
- Improved error messages for shared hosting environments

### Deprecated
- `DirectExtractor` class (use `DirectInstaller` instead)

### Fixed
- Migration rollback failing on SQLite databases
- Race condition in backup manager

### Security
- Fixed SQL injection vulnerability in query builder

## [0.1.0] - 2026-02-12

### Added
- Initial release with DirectInstaller and ComposerInstaller
- HostingDetector for environment capability detection
- BackupManager for pre-install backups

[Unreleased]: https://github.com/tipowerup/installer/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/tipowerup/installer/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/tipowerup/installer/releases/tag/v0.1.0
```

### Categories

Only include sections that have entries:

| Section | When to Use |
|---------|------------|
| **Added** | New features |
| **Changed** | Changes to existing functionality |
| **Deprecated** | Features to be removed in future version |
| **Removed** | Removed features |
| **Fixed** | Bug fixes |
| **Security** | Vulnerability fixes |

### Rules

1. Write entries from **user's perspective**, not developer's
   - Bad: "Refactored install logic to use async/await"
   - Good: "Installation now completes faster on slow connections"

2. Link version headers to GitHub compare URLs (at bottom)

3. Keep `[Unreleased]` at the top — move entries to a versioned section on release

4. One entry per line, start with a verb
   - Good: "Fixed SQL injection in search", "Added TypeScript types"
   - Bad: "Various improvements", "Bug fixes"

5. Group entries by type (Added, Changed, Fixed, etc.)

### Example Entry Update

Before release:
```markdown
## [Unreleased]

### Added
- HostingDetector auto-detects shared hosting vs VPS
- New `--verify-only` flag for dry-run installations

### Fixed
- Backup fails when storage path contains symlinks
```

On release (e.g., v0.2.0 on 2026-02-15):
```markdown
## [Unreleased]

### Added
- [Next features here]

## [0.2.0] - 2026-02-15

### Added
- HostingDetector auto-detects shared hosting vs VPS
- New `--verify-only` flag for dry-run installations

### Fixed
- Backup fails when storage path contains symlinks

[Unreleased]: https://github.com/tipowerup/installer/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/tipowerup/installer/compare/v0.1.0...v0.2.0
```

---

## Release Process

Step-by-step process for releasing a new version.

### Pre-Release Checks

Run these checks before releasing:

```bash
# 1. Ensure you're on main and up to date
git checkout main
git pull origin main

# 2. Run the full test suite
composer test

# 3. Verify code style
vendor/bin/pint --test

# 4. Check for uncommitted changes
git status
```

**If any check fails, fix it before continuing.**

### Release Steps

```bash
# 1. Update CHANGELOG.md
#    - Move [Unreleased] entries to new version section
#    - Add release date (YYYY-MM-DD)
#    - Update compare URLs at bottom
#    - Example: ## [0.2.0] - 2026-02-15

# 2. Commit the changelog
git add CHANGELOG.md
git commit -m "Release v0.2.0"

# 3. Tag the release
git tag v0.2.0

# 4. Push commit and tag to GitHub
git push origin main
git push origin v0.2.0
```

**Important:** Always tag the commit AFTER making the changelog commit. The tag points to the release commit.

### Post-Release

1. **Verify on Packagist**
   - Visit https://packagist.org/packages/tipowerup/package-name
   - Confirm the new version appears within 1-2 minutes
   - If webhook is configured, it auto-updates (no manual action needed)

2. **Test Installation in Fresh Project**
   ```bash
   mkdir temp-test && cd temp-test
   composer require tipowerup/package:^0.2
   # Verify package installs and works
   ```

3. **Create GitHub Release (Optional but Recommended)**
   - Go to GitHub repo → **Releases** → **Draft a new release**
   - Select tag: `v0.2.0`
   - Title: `Release v0.2.0`
   - Description: Copy the CHANGELOG.md section for this version
   - Publish

4. **Announce (for major/user-facing releases)**
   - Notify customers in email/Slack if this is a significant release
   - Include highlights from CHANGELOG

---

## Packagist Publishing

### First-Time Setup (One-Time)

1. **Create Packagist Account**
   - Go to https://packagist.org
   - Click **Sign Up**
   - Use GitHub account or email

2. **Submit Package**
   - Click **Submit Package**
   - Enter GitHub repository URL (e.g., `https://github.com/tipowerup/installer`)
   - Packagist validates `composer.json` and creates listing

3. **Set Up GitHub Webhook (Recommended)**
   - Go to your Packagist package page
   - Copy the **Webhook URL** from top right
   - On GitHub: Repo → Settings → Webhooks → **Add webhook**
     - Payload URL: [paste Packagist webhook URL]
     - Content type: `application/json`
     - Secret: [copy from Packagist dashboard]
     - Events: Push events only
     - Active: ✓
   - Click **Add webhook**

Now, every time you push a tag, Packagist auto-updates within 1-2 minutes.

### Publishing Checklist

Before submitting a new package to Packagist:

- [ ] **composer.json**
  - `name` matches convention (e.g., `tipowerup/ti-ext-installer`)
  - `description` is clear and concise
  - `type` is correct (`tastyigniter-package` for extensions/themes)
  - `license` is specified (`MIT` or appropriate)
  - `authors` section with name/email

- [ ] **Dependencies**
  - `require` lists ONLY runtime dependencies (no dev tools)
  - `require-dev` has all dev tools (Pest, Pint, PHPStan, Rector)
  - Versions are appropriate (use `^` for compatibility)

- [ ] **Autoload**
  - `autoload.psr-4` mapping is correct
  - `autoload-dev.psr-4` mapping is correct
  - No `files` sections unless necessary

- [ ] **Installation Test**
  ```bash
  composer require vendor/package
  # Verify it installs without errors
  # Verify autoloader works
  # Run basic functionality check
  ```

- [ ] **Documentation**
  - `README.md` exists with installation instructions
  - `LICENSE` file exists (MIT recommended)
  - `CHANGELOG.md` started
  - `.github/CONTRIBUTING.md` if accepting external PRs

- [ ] **Repository Configuration**
  - `.gitignore` excludes `/vendor/`, `.phpunit.cache/`, `.phpstan.cache/`
  - For libraries: `composer.lock` in `.gitignore` (packages shouldn't lock)
  - For applications: `composer.lock` tracked
  - No secrets, API keys, or credentials in history
  - `composer.json` uses `-dev` for dev package versions

- [ ] **Package Naming**

| Type | Convention | Example |
|------|-----------|---------|
| Extension | `tipowerup/ti-ext-{name}` | `tipowerup/ti-ext-installer` |
| Theme | `tipowerup/ti-theme-{name}` | `tipowerup/ti-theme-orange-tw` |
| Shared tooling | `tipowerup/{name}` | `tipowerup/testbench` |

---

## CI Pipeline (GitHub Actions)

Automate testing and validation with GitHub Actions.

### Tests Workflow

Save as `.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  tests:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: ['8.3']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite3, pdo_sqlite, zip
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Code style
        run: vendor/bin/pint --test

      - name: Static analysis
        run: vendor/bin/phpstan analyse --memory-limit=1056M

      - name: Tests
        run: vendor/bin/pest --compact
```

### Release Workflow

Save as `.github/workflows/release.yml`:

This workflow creates a GitHub Release when you push a version tag.

```yaml
name: Release

on:
  push:
    tags: ['v*']

jobs:
  release:
    runs-on: ubuntu-latest
    permissions:
      contents: write

    steps:
      - uses: actions/checkout@v4

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v2
        with:
          generate_release_notes: true
```

This auto-generates release notes from commit messages and PRs merged since the last release.

### Actions Reference

| Action | Purpose |
|--------|---------|
| `actions/checkout@v4` | Fetch repository code |
| `shivammathur/setup-php@v2` | Install PHP with extensions |
| `softprops/action-gh-release@v2` | Create GitHub Release |

---

## Deprecation Process

When removing or renaming features, follow this timeline to give users time to migrate.

### Steps

**Step 1: Deprecate (Current Minor Version)**

Mark the feature as deprecated in code and docs:

```php
/**
 * @deprecated since 1.2.0, use PackageInstaller::installPackage() instead
 */
public function install(string $code): bool
{
    trigger_error(
        'install() is deprecated, use installPackage() instead',
        E_USER_DEPRECATED
    );
    return $this->installPackage($code)->success;
}
```

Add to CHANGELOG:

```markdown
### Deprecated
- `install()` method — use `installPackage()` instead (removed in 2.0)
```

**Step 2: Document (CHANGELOG + Migration Guide)**

Add a migration guide in your docs:

```markdown
# Migration Guide: v1.2 to v2.0

## Removed: `install()` method

**Old:**
```php
$installer->install('extension-code');
```

**New:**
```php
$result = $installer->installPackage('extension-code');
if ($result->success) {
    // Handle success
}
```
```

**Step 3: Remove (Next Major Version)**

Remove the feature in the next major release.

### Deprecation Timeline

| Version | For `0.x` | For `1.x+` |
|---------|-----------|-----------|
| Can be removed in next... | Next MINOR | Next MAJOR |
| Example | `0.1` deprecated, removed in `0.2` | `1.2` deprecated, removed in `2.0` |
| User notice time | 1-2 minor releases | 1+ major releases |

**Rule of thumb:** Deprecate for at least one full release cycle before removing.

---

## Security Releases

Handle security vulnerabilities with care.

### Process

1. **Do NOT disclose publicly** until a fix is available
   - Report privately via GitHub Security Advisory (if available)
   - Or email `security@tipowerup.com`

2. **Fix on a private branch**
   - Create a branch (don't push to main)
   - Fix the vulnerability
   - Commit and test thoroughly

3. **Release as PATCH version**
   - Example: `1.0.0` → `1.0.1`
   - NEVER include other changes (other fixes or features)

4. **Add to CHANGELOG**
   ```markdown
   ### Security
   - Fixed XSS vulnerability in user input validation
   ```

5. **Release and tag**
   ```bash
   git push origin main
   git tag v1.0.1
   git push origin v1.0.1
   ```

6. **Post-release disclosure**
   - After patch is on Packagist, disclose details
   - Include: what was vulnerable, affected versions, upgrade instructions
   - Optional: Submit to GitHub Security Advisory database

### Example Timeline

```
Day 1: Developer reports vulnerability in private communication
Day 2: Team confirms issue, creates fix
Day 3: Test patch thoroughly, merge to main, tag v1.0.1
Day 4: Publish v1.0.1 to Packagist
Day 5: Public announcement with details and upgrade path
```

---

## Version Support Policy

Define how long versions receive updates.

| Version | Status | Support | Examples |
|---------|--------|---------|----------|
| Latest major | Active | Bug fixes + new features | `1.x` when `2.x` exists |
| Previous major | Maintenance | Security fixes only | `0.x` when `1.x` exists |
| Older | EOL | No support | `0.1` when `0.3` exists (for `0.x`) |

### For `0.x` Packages

- Only the latest `0.x` version is actively supported
- Example: If `0.3.0` is out, don't backport to `0.2.x`
- Users should always upgrade to latest `0.x`

### For `1.x+` Packages

- Support **latest major** + **previous major**
- Example: When `2.0.0` is out:
  - `2.x.x` — Active (all features + fixes)
  - `1.x.x` — Maintenance (security fixes only)
  - `0.x.x` — EOL (no support)

---

## Quick Reference

Common tasks at a glance.

### Full Release Workflow

```bash
# 1. Ensure clean state and up to date
git checkout main
git pull origin main
composer test

# 2. Update changelog
# - Move [Unreleased] to [0.2.0] - 2026-02-15
# - Update compare URLs
# - git add CHANGELOG.md && git commit -m "Release v0.2.0"

# 3. Tag and push
git tag v0.2.0
git push origin main
git push origin v0.2.0

# 4. Wait for Packagist webhook (1-2 minutes)
# Visit https://packagist.org/packages/tipowerup/package-name
```

### Test Installation

```bash
mkdir /tmp/test-release
cd /tmp/test-release
composer create-project tastyigniter/tastyigniter .
composer require tipowerup/package:^0.2
# Verify it installs and works
```

### Rollback a Bad Release

If you release a breaking bug:

```bash
# Delete the tag locally and remotely
git tag -d v0.2.0
git push origin --delete v0.2.0

# Fix the bug
git add .
git commit -m "Fix critical bug"

# Re-tag and release
git tag v0.2.0
git push origin main
git push origin v0.2.0
```

Packagist may cache the old version for a few minutes. Use your package at exact version `0.2.1` while it updates, then revert once Packagist is in sync.

---

## Best Practices

### Semantic Versioning

- **MAJOR** = breaking changes (renaming classes, removing methods)
- **MINOR** = backwards-compatible features
- **PATCH** = backwards-compatible bug fixes
- See [VERSIONING.md](./VERSIONING.md) for detailed rules

### Changelog Discipline

- Update CHANGELOG **before** tagging, not after
- Use user-friendly language
- Group related changes
- Include links to issues/PRs for major items

### Code Quality

- Always run `composer test` before releasing
  - Tests must pass
  - Code style must be clean
  - Static analysis must pass
- Tag only clean, tested commits

### Communication

- Announce major releases (features users care about)
- Include upgrade instructions for breaking changes
- Link to migration guides
- For security releases, disclose after patch is available

### Automation

- Set up GitHub Actions (tests.yml, release.yml)
- Let Packagist webhook handle syncing
- No manual Packagist updates needed

---

## Related Documentation

- [VERSIONING.md](./VERSIONING.md) — SemVer rules and stability checklists
- [README.md](./README.md) — Package overview and installation
- [.github/CONTRIBUTING.md](./.github/CONTRIBUTING.md) — For external contributors
