# Versioning Guide

This guide helps the TI PowerUp team decide when and how to version their packages.

## Semantic Versioning (SemVer)

Format: `MAJOR.MINOR.PATCH`

| Bump | When | Example |
|------|------|---------|
| PATCH | Bug fixes, no API changes | `1.0.0` → `1.0.1` |
| MINOR | New features, backwards compatible | `1.0.1` → `1.1.0` |
| MAJOR | Breaking changes | `1.1.0` → `2.0.0` |

## Starting Version

Start at `0.1.0` for packages still in development. The `0.x` range signals "API not stable yet" — Composer treats `0.x` specially (minor bumps can be breaking).

Move to `1.0.0` when:
- The extension is released to paying customers
- The public API (config keys, Livewire events, service contracts) is stable

## Independent Versioning

Version each package independently — don't synchronize versions across packages since they evolve at different rates. Example:

```
tipowerup/testbench        v0.1.0  (dev tool, changes less often)
tipowerup/ti-ext-installer v0.1.0  (still building)
tipowerup/ti-ext-template  v0.1.0  (changes when conventions change)
ti-theme-orange-tw         v0.1.0
```

## Tagging

Tag with `v` prefix (Composer convention):

```bash
git tag v0.1.0
git push --tags
```

## Constraint Strategy

In `composer.json`:
- `^0.1` for `0.x` packages (allows `0.1.x` patches only)
- `^1.0` once stable (allows `1.x.x` but not `2.0`)
- `^v4.0` for `tastyigniter/core` (match their range)

## When to Bump

| Change | Bump |
|--------|------|
| Fix a bug in a service class | PATCH |
| Add a new Livewire component | MINOR |
| Rename a service class or config key | MAJOR (or MINOR if `0.x`) |
| Update a shared trait API | MINOR on the shared package |

## What Qualifies as Stable (1.0.0)?

A package is stable when:

1. **Public API is settled** — Class names, method signatures, config keys, event names, and component names won't change without a major version bump
2. **Core features work** — The happy path is reliable and tested end-to-end
3. **Real users can depend on it** — Someone can `composer require` the package, follow the docs, and it works

Stable does NOT mean:
- Feature-complete (add features in 1.1, 1.2, etc.)
- Bug-free (that's what patch releases are for)
- Battle-tested by thousands (that comes with time)

## Stability Checklist

### Extensions

**Functionality**
- [ ] Can a user install via `composer require` and `igniter:extension-install` and get a working result?
- [ ] Are all public class names, method signatures, and config keys finalized?
- [ ] Do all registered permissions, navigation items, and settings work?
- [ ] Is the happy path (main use case) working end-to-end?
- [ ] Are breaking changes unlikely in the near term?
- [ ] Is there documentation (README) sufficient for a new user?

**Tests (minimum for 1.0)**
- [ ] Extension boots without errors (Feature test)
- [ ] Migrations create and rollback cleanly — `assertMigrationCycle` (Feature test)
- [ ] Migrations survive 3 install/uninstall cycles — `assertSurvivesInstallCycles` (Feature test)
- [ ] Migrations have proper `down()` methods — `assertProperDownMethods` (Feature test)
- [ ] Migrations don't touch core TI tables — `assertNoCoreTables` (Feature test)
- [ ] Navigation and permissions registered correctly (Feature test)
- [ ] Each service class's core method has at least one test (Unit or Feature)
- [ ] Edge cases and error paths for critical business logic (Unit test)
- [ ] All tests pass: `vendor/bin/pest --compact`
- [ ] Code style passes: `vendor/bin/pint --test`

**Test types by layer:**

| Layer | Test Type | What to Test |
|-------|-----------|-------------|
| Extension registration | Feature | Boot, navigation, permissions, settings |
| Migrations | Feature | Cycle, rollback, down methods, no core tables |
| Services / business logic | Unit | Pure logic, edge cases, error handling |
| Livewire components | Feature | Render, actions, dispatched events, validation |
| API clients | Unit | Response parsing, error handling (mock HTTP) |
| Models | Feature | Scopes, relationships, accessors, mutators |

### Themes

**Functionality**
- [ ] Can a user install and activate the theme without errors?
- [ ] Are all pages rendering correctly (home, menu, checkout, account)?
- [ ] Is the theme responsive across mobile, tablet, and desktop?
- [ ] Are all partials, layouts, and assets finalized (no placeholder content)?
- [ ] Does the theme work with TI's default demo data?
- [ ] Are customization options (colors, logo, layout settings) working?
- [ ] Is there documentation (README) covering setup and customization?

**Tests (minimum for 1.0)**
- [ ] Theme boots and activates without errors (Feature test)
- [ ] All layouts render without exceptions (Feature test)
- [ ] Key pages return 200 status: home, menu, checkout, account (Feature test)
- [ ] Theme assets compile without errors (build step)
- [ ] All tests pass: `vendor/bin/pest --compact`
- [ ] Code style passes: `vendor/bin/pint --test`

**Test types by layer:**

| Layer | Test Type | What to Test |
|-------|-----------|-------------|
| Theme activation | Feature | Boot, register, no errors |
| Page rendering | Feature | HTTP 200 for all key routes |
| Partials / components | Feature | Render without exceptions |
| Custom PHP logic (if any) | Unit | Helpers, formatters, view composers |

## Current Package Status

| Package | Current | Ready for 1.0? | Reasoning |
|---------|---------|----------------|-----------|
| `tipowerup/testbench` | `0.1.0` | Close | API is small and settled (TestCase + 3 traits). Promote after a few extensions use it without changes |
| `tipowerup/ti-ext-template` | `0.1.0` | Yes | Template — the "API" is the file structure. If it works, ship 1.0.0 |
| `tipowerup/ti-ext-installer` | `0.1.0` | No | Still building core features. Stay 0.x until install/update/uninstall work end-to-end |
