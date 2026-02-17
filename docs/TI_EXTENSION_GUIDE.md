# TastyIgniter v4 Extension Development Guide

A comprehensive guide for building TastyIgniter v4 extensions that install cleanly, run in isolation, and uninstall safely via the TI PowerUp Installer.

**Target audience:** Human developers and AI agents (Claude Code).
**TastyIgniter version:** v4.0+
**PHP version:** 8.3+

---

## Table of Contents

- [1. Introduction](#1-introduction)
- [2. Extension Anatomy](#2-extension-anatomy)
- [3. The Island Principle -- Extension Isolation](#3-the-island-principle----extension-isolation)
- [4. Registration Methods Reference](#4-registration-methods-reference)
- [5. Admin Controllers](#5-admin-controllers)
- [6. Eloquent Models](#6-eloquent-models)
- [7. Database Migrations](#7-database-migrations)
- [8. Hooking Into Core Behavior (Without Coupling)](#8-hooking-into-core-behavior-without-coupling)
- [9. Translations](#9-translations)
- [10. TI PowerUp Installer Compatibility Checklist](#10-ti-powerup-installer-compatibility-checklist)
- [11. Testing Your Extension](#11-testing-your-extension)
- [12. Common Pitfalls](#12-common-pitfalls)
- [13. Real-World Example: Complete Mini Extension](#13-real-world-example-complete-mini-extension)

---

## 1. Introduction

### What This Guide Covers

This guide teaches you how to build TastyIgniter v4 extensions that:

- Install and uninstall cleanly through the TI PowerUp Installer
- Run in complete isolation from the TI core and other extensions
- Follow TI v4 conventions for navigation, permissions, settings, and models
- Never cause breaking changes when removed

### Why Isolation Matters

**Every extension is an island.** This is the single most important principle in this guide.

When the TI PowerUp Installer uninstalls an extension, the following happens in order:

1. All migration `down()` methods for that extension are executed (via `UpdateManager::purgeExtension()`)
2. The extension's files are removed from the filesystem
3. The extension is deregistered from TastyIgniter

If your extension modified core tables (added columns, foreign keys, altered schemas), the migration rollback will either **fail** (breaking the uninstall) or **succeed but leave the site broken** (other code expects those columns).

### How This Relates to TI PowerUp Installer

The TI PowerUp Installer supports two installation methods:

- **Direct Extraction** (shared hosting, ~80% of users) -- Downloads ZIPs, extracts to `storage/app/tipowerup/` via PHP `ZipArchive`, no CLI needed. This is safer for shared hosting.
- **Composer Installation** (VPS/dedicated, ~20%) -- Uses `composer require` via Symfony Process

Both methods follow the same lifecycle: download -> verify checksum -> extract/install to storage paths -> run migrations -> register with TI. On uninstall: rollback migrations -> remove files -> deregister. Your extension must survive this entire cycle without leaving broken state behind.

---

## 2. Extension Anatomy

### Directory Structure

```
vendor/{vendor}/ti-ext-{name}/
  composer.json                    # Package metadata + TI extension config
  database/
    migrations/                    # Laravel-style migration files
  resources/
    lang/en/
      default.php                  # Translation strings
    models/
      {modelname}.php              # Form/list field definitions (PHP arrays)
    views/
      {controller}/                # Admin controller views
        index.blade.php
        create.blade.php
        edit.blade.php
      _partials/                   # Shared partial views
      mail/                        # Mail template views
    css/                           # Custom stylesheets
    js/                            # Custom JavaScript
  src/
    Extension.php                  # Entry point (MUST be here)
    Http/
      Controllers/                 # Admin controllers
      Middleware/                   # HTTP middleware
      Requests/                    # Form request validation
    Models/                        # Eloquent models
    Classes/                       # Service/utility classes
    Events/                        # Event classes
    Listeners/                     # Event listeners
    Exceptions/                    # Custom exceptions
  tests/                           # Pest/PHPUnit tests
```

### The `composer.json` Format

Every TI v4 extension requires a `composer.json` with specific fields:

```json
{
    "name": "tipowerup/ti-ext-loyaltypoints",
    "type": "tastyigniter-package",
    "description": "Loyalty points system for TastyIgniter",
    "license": "MIT",
    "authors": [
        {
            "name": "TI PowerUp Team",
            "email": "support@tipowerup.com"
        }
    ],
    "require": {
        "php": "^8.3",
        "tastyigniter/core": "^v4.0"
    },
    "autoload": {
        "psr-4": {
            "Tipowerup\\LoyaltyPoints\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tipowerup\\LoyaltyPoints\\Tests\\": "tests/"
        }
    },
    "extra": {
        "tastyigniter-extension": {
            "code": "tipowerup.loyaltypoints",
            "name": "Loyalty Points",
            "icon": {
                "class": "fa fa-star",
                "color": "#FFF",
                "backgroundColor": "#FF9800"
            },
            "homepage": "https://tipowerup.com/extensions/loyalty-points"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "composer/installers": true
        },
        "sort-packages": true
    }
}
```

**Key fields explained:**

| Field | Value | Purpose |
|-------|-------|---------|
| `type` | `"tastyigniter-package"` | **Required.** How TI's `PackageManifest` discovers the extension |
| `extra.tastyigniter-extension.code` | `"tipowerup.loyaltypoints"` | The dot-notation identifier used everywhere: database records, migration groups, view namespaces, translation namespaces |
| `autoload.psr-4` | Maps namespace to `src/` | The `Extension` class **must** live at `src/Extension.php` |

**Code format rules:**
- Format is `vendor.name` -- all lowercase, alphanumeric only
- The code becomes the extension identifier throughout the system
- Example: `tipowerup.loyaltypoints` maps to namespace `Tipowerup\LoyaltyPoints`

### The Extension.php Entry Point

`Extension.php` is the heart of your extension. It extends `BaseExtension`, which extends `EventServiceProvider`, which extends Laravel's `ServiceProvider`.

```php
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints;

use Igniter\System\Classes\BaseExtension;
use Override;

class Extension extends BaseExtension
{
    /**
     * Register bindings, merge config, define singletons.
     * Called even when the extension is disabled if the class is loaded.
     */
    #[Override]
    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/loyaltypoints.php', 'tipowerup-loyaltypoints');
    }

    /**
     * Boot the extension: register event listeners, extend models, etc.
     * This is NOT called when the extension is disabled.
     */
    #[Override]
    public function boot(): void
    {
        // Event listeners, model extensions, controller extensions
    }

    #[Override]
    public function registerNavigation(): array
    {
        return [];
    }

    #[Override]
    public function registerPermissions(): array
    {
        return [];
    }

    #[Override]
    public function registerSettings(): array
    {
        return [];
    }
}
```

### Extension Lifecycle

Understanding the lifecycle is critical for knowing where to put your code:

| Phase | What Happens | Your Code |
|-------|-------------|-----------|
| **1. Discovery** | TI scans `vendor/`, `extensions/`, and `storage/app/tipowerup/` for packages with `extra.tastyigniter-extension` in `composer.json` | Nothing -- automatic |
| **2. Loading** | PSR-4 autoloading registers your namespace; metadata is read from `composer.json` | Nothing -- automatic |
| **3. Registration** | `register()` is called on the extension class | Bind services, merge config |
| **4. Pre-Boot** | `bootingExtension()` auto-discovers translations, migrations, controllers, views, routes from standard directories | Nothing -- automatic |
| **4.5. Storage Registration** | For extensions installed via DirectInstaller to `storage/app/tipowerup/`, TI PowerUp Extension calls `ExtensionManager::loadExtension()` during boot | Nothing -- handled by TI PowerUp Extension |
| **5. Boot** | `boot()` is called (**skipped if disabled**) | Register event listeners, extend models/controllers |
| **6. Install** | DB record created in `extensions` table, migrations run | Write proper `up()` migrations |
| **7. Uninstall** | Extension disabled, migrations optionally rolled back | Write proper `down()` migrations |
| **8. Delete** | All migrations rolled back, files removed from filesystem | Ensure `down()` reverses everything cleanly |

**Critical detail:** `bootingExtension()` automatically registers resources from standard directories. You do **not** need to manually call `loadTranslationsFrom()`, `loadMigrationsFrom()`, or `loadViewsFrom()` if your files are in the standard locations.

---

## 3. The Island Principle -- Extension Isolation

This is the **most important section** of this guide. Every rule here exists to prevent real-world breakage during install/uninstall cycles.

### Why Isolation Matters

When TI PowerUp Installer uninstalls an extension:

1. It calls `UpdateManager::purgeExtension()` which runs **all** migration `down()` methods for that extension
2. If your migration added a column to a core table (e.g., `orders`) and the `down()` method removes it, **other extensions or core code that depends on that column will break**
3. If your migration added a foreign key to a core table and the `down()` method drops it, the column may still be referenced
4. If you **do not** have proper `down()` methods, orphaned columns/data accumulate over repeated install/uninstall cycles

### Rule 1: NEVER Modify Core Tables

```php
// ❌ BAD -- Adds column to core table
public function up(): void
{
    Schema::table('orders', function (Blueprint $table): void {
        $table->integer('loyalty_points_earned')->default(0);
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table): void {
        $table->dropColumn('loyalty_points_earned');
    });
}
```

```php
// ✅ GOOD -- Create your own table with a soft reference
public function up(): void
{
    Schema::create('tipowerup_order_loyalty_points', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();  // Soft reference, NO foreign key
        $table->integer('points_earned')->default(0);
        $table->integer('points_redeemed')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('tipowerup_order_loyalty_points');
}
```

**Why this matters:** When your extension is uninstalled, `Schema::dropIfExists('tipowerup_order_loyalty_points')` cleanly removes your table. The `orders` table is untouched. No breakage.

### Rule 2: NEVER Add Foreign Keys to Core Tables

```php
// ❌ BAD -- Foreign key creates a hard dependency on a core table
$table->foreign('order_id')->references('order_id')->on('orders');

// ❌ BAD -- Constrained shortcut has the same problem
$table->foreignId('order_id')->constrained('orders');

// ✅ GOOD -- Soft reference with just an index
$table->unsignedBigInteger('order_id')->index();
```

Foreign keys prevent table drops and can cause cascade issues. TI's own core extensions (`igniter.cart`, `igniter.user`, `igniter.local`) deliberately dropped all foreign keys in the `2022_06_30_010000_drop_foreign_key_constraints` migration. Follow the same pattern: use application-level integrity via Eloquent relationships instead of database-level constraints.

### Rule 3: Prefix ALL Your Tables

```php
// Convention: {vendor}_{tablename}
Schema::create('tipowerup_loyalty_points', ...);
Schema::create('tipowerup_loyalty_transactions', ...);
Schema::create('tipowerup_loyalty_tiers', ...);
```

This prevents naming collisions with core tables and other extensions, and makes it immediately clear which tables belong to your extension during debugging or manual database inspection.

**Real-world examples from TI core:**
- `igniter.coupons` uses: `igniter_coupon_categories`, `igniter_coupon_menus`, `igniter_coupon_customers`
- `igniter.frontend` uses: `igniter_subscribers`, `igniter_sliders`, `igniter_banners`

### Rule 4: ALWAYS Write Proper `down()` Methods

```php
// ✅ GOOD -- Every up() has a corresponding down()
public function up(): void
{
    Schema::create('tipowerup_loyalty_points', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('customer_id')->index();
        $table->integer('balance')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('tipowerup_loyalty_points');
}
```

```php
// ❌ BAD -- Empty down() method (leaves orphaned tables)
public function down(): void
{
    //
}
```

**Warning about TI's own extensions:** Many TI core extensions have empty `down()` methods. This is a known pattern in the core codebase -- do **not** copy it. Core extensions are never uninstalled by users, so the core team can afford this shortcut. Your extension **will** be uninstalled. TI PowerUp Installer depends on proper rollback.

### Rule 5: Use Events and Dynamic Extensions, Not Direct Modification

```php
// ✅ GOOD -- Extend in boot() (automatically cleaned up when extension is disabled)
#[Override]
public function boot(): void
{
    // Add relationship dynamically to a core model
    \Igniter\Cart\Models\Order::extend(function ($model): void {
        $model->relation['hasOne']['loyalty_points'] = [
            \Tipowerup\LoyaltyPoints\Models\OrderLoyaltyPoints::class,
            'foreignKey' => 'order_id',
        ];
    });

    // Listen to events
    \Illuminate\Support\Facades\Event::listen(
        'igniter.checkout.afterSaveOrder',
        function ($order): void {
            resolve(\Tipowerup\LoyaltyPoints\Classes\LoyaltyService::class)
                ->awardPointsForOrder($order);
        }
    );
}
```

When the extension is disabled or uninstalled, `boot()` is never called. The dynamic relationships and event listeners simply do not exist. No manual cleanup is needed. This is the safest integration pattern.

### Rule 6: Store Settings Properly

```php
// ✅ GOOD -- Uses TI's extension_settings table with a unique settings code
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints\Models;

use Igniter\Flame\Database\Model;
use Igniter\System\Actions\SettingsModel;

class LoyaltySettings extends Model
{
    public array $implement = [SettingsModel::class];

    public string $settingsCode = 'tipowerup_loyalty_settings';

    public string $settingsFieldsConfig = 'loyaltysettings';
}
```

Extension settings stored in the `extension_settings` table are **not** automatically cleaned up on uninstall. This is acceptable -- settings are lightweight configuration data that causes no harm if orphaned.

For `params()` storage: entries in the `settings` table are also **not** cleaned up. Use sparingly and with a clear prefix: `tipowerup_loyalty_*`.

### Rule 7: Use Morph Maps for Polymorphic Relationships

```php
// In Extension.php (class property)
protected array $morphMap = [
    'loyalty_transaction' => \Tipowerup\LoyaltyPoints\Models\LoyaltyTransaction::class,
    'loyalty_tier' => \Tipowerup\LoyaltyPoints\Models\LoyaltyTier::class,
];
```

TI's `BaseExtension` (via `EventServiceProvider`) automatically registers morph maps declared in this property. This ensures polymorphic relationships resolve correctly and use readable string keys instead of fully qualified class names in the database.

---

## 4. Registration Methods Reference

All registration methods are defined in `BaseExtension` and called automatically by TI during the boot phase. Override them to provide your extension's functionality.

### registerNavigation()

```php
#[Override]
public function registerNavigation(): array
{
    return [
        'marketing' => [  // Parent sidebar group
            'child' => [
                'loyalty' => [  // Child menu item
                    'priority' => 40,
                    'class' => 'loyalty',
                    'href' => admin_url('tipowerup/loyaltypoints/loyalty'),
                    'title' => lang('tipowerup.loyaltypoints::default.text_title'),
                    'permission' => 'Tipowerup.LoyaltyPoints.*',
                ],
            ],
        ],
    ];
}
```

**Available sidebar groups:** `orders`, `restaurant`, `customers`, `marketing`, `design`, `tools`, `system`

You can create top-level items (like `orders` does in `igniter.cart`) but prefer using existing groups to keep the admin sidebar clean.

**Real-world example from `igniter.cart`:**

```php
public function registerNavigation(): array
{
    return [
        'orders' => [
            'priority' => 10,
            'class' => 'orders',
            'icon' => 'fa-file-invoice-dollar',
            'href' => admin_url('orders'),
            'title' => lang('igniter.cart::default.text_side_menu_order'),
            'permission' => 'Admin.Orders',
        ],
        'restaurant' => [
            'child' => [
                'menus' => [
                    'priority' => 20,
                    'class' => 'menus',
                    'href' => admin_url('menus'),
                    'title' => lang('igniter.cart::default.text_side_menu_menu'),
                    'permission' => 'Admin.Menus',
                ],
            ],
        ],
    ];
}
```

### registerPermissions()

```php
#[Override]
public function registerPermissions(): array
{
    return [
        'Tipowerup.LoyaltyPoints.Manage' => [
            'description' => 'Create, modify and delete loyalty point rules',
            'group' => 'tipowerup.loyaltypoints::default.text_permission_group',
        ],
        'Tipowerup.LoyaltyPoints.View' => [
            'description' => 'View loyalty point transactions',
            'group' => 'tipowerup.loyaltypoints::default.text_permission_group',
        ],
    ];
}
```

**Convention:** `Vendor.ExtensionName.Action`

Use in controllers: `protected null|string|array $requiredPermissions = 'Tipowerup.LoyaltyPoints.*';`

Use the wildcard `*` to match all permissions in the group, or specify exact permission codes.

### registerSettings()

```php
#[Override]
public function registerSettings(): array
{
    return [
        'settings' => [
            'label' => 'Loyalty Points Settings',
            'description' => 'Configure loyalty point earning rules and tiers.',
            'icon' => 'fa fa-star',
            'model' => \Tipowerup\LoyaltyPoints\Models\LoyaltySettings::class,
            'permissions' => ['Tipowerup.LoyaltyPoints.Manage'],
        ],
    ];
}
```

The settings page URL will be: `admin/extensions/edit/tipowerup/loyaltypoints/settings`

### registerSchedule()

```php
public function registerSchedule(Schedule $schedule): void
{
    $schedule->call(function (): void {
        // Expire points older than 1 year
        \Tipowerup\LoyaltyPoints\Models\LoyaltyPoints::query()
            ->where('created_at', '<', now()->subYear())
            ->where('expired', false)
            ->update(['expired' => true]);
    })->name('tipowerup-loyalty-expire-points')->daily();
}
```

### registerFormWidgets()

```php
#[Override]
public function registerFormWidgets(): array
{
    return [
        \Tipowerup\LoyaltyPoints\FormWidgets\PointsEditor::class => [
            'label' => 'Points Editor',
            'code' => 'pointseditor',
        ],
    ];
}
```

### registerMailTemplates()

```php
#[Override]
public function registerMailTemplates(): array
{
    return [
        'tipowerup.loyaltypoints::mail.points_earned' => 'Notification when customer earns points',
        'tipowerup.loyaltypoints::mail.tier_upgrade' => 'Notification when customer reaches new tier',
    ];
}
```

Mail template views live at `resources/views/mail/points_earned.blade.php`.

### registerComponents()

For frontend/theme components:

```php
#[Override]
public function registerComponents(): array
{
    return [
        \Tipowerup\LoyaltyPoints\Components\LoyaltyDashboard::class => [
            'code' => 'loyaltyDashboard',
            'name' => 'Loyalty Dashboard',
            'description' => 'Displays customer loyalty points and tiers.',
        ],
    ];
}
```

### Other Registration Methods

| Method | Purpose |
|--------|---------|
| `registerDashboardWidgets()` | Admin dashboard widgets |
| `registerPaymentGateways()` | Payment integrations |
| `registerValidationRules()` | Custom validation rules |
| `registerConsoleCommand(string $key, string $class)` | Artisan commands |
| `registerOnboardingSteps()` | Dashboard onboarding steps |
| `registerAutomationRules()` | If using `igniter.automation` |
| `registerCartConditions()` | If using `igniter.cart` -- cart price modifiers |
| `registerOrderTypes()` | Custom order types (delivery, collection, etc.) |
| `registerLocationSettings()` | Per-location settings tabs |
| `registerEventBroadcasts()` | WebSocket event broadcasting |
| `registerListActionWidgets()` | Bulk action widgets for list views |

---

## 5. Admin Controllers

TI admin controllers extend `AdminController` and use action classes (`ListController`, `FormController`) for CRUD operations.

```php
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\AdminMenu;
use Igniter\Admin\Http\Actions\FormController;
use Igniter\Admin\Http\Actions\ListController;
use Tipowerup\LoyaltyPoints\Http\Requests\LoyaltyRuleRequest;
use Tipowerup\LoyaltyPoints\Models\LoyaltyRule;

class Loyalty extends AdminController
{
    public array $implement = [
        ListController::class,
        FormController::class,
    ];

    public array $listConfig = [
        'list' => [
            'model' => LoyaltyRule::class,
            'title' => 'tipowerup.loyaltypoints::default.text_title',
            'emptyMessage' => 'tipowerup.loyaltypoints::default.text_empty',
            'defaultSort' => ['id', 'DESC'],
            'configFile' => 'loyaltyrule',
        ],
    ];

    public array $formConfig = [
        'name' => 'tipowerup.loyaltypoints::default.text_form_name',
        'model' => LoyaltyRule::class,
        'request' => LoyaltyRuleRequest::class,
        'create' => [
            'title' => 'lang:igniter::admin.form.create_title',
            'redirect' => 'tipowerup/loyaltypoints/loyalty/edit/{id}',
            'redirectClose' => 'tipowerup/loyaltypoints/loyalty',
            'redirectNew' => 'tipowerup/loyaltypoints/loyalty/create',
        ],
        'edit' => [
            'title' => 'lang:igniter::admin.form.edit_title',
            'redirect' => 'tipowerup/loyaltypoints/loyalty/edit/{id}',
            'redirectClose' => 'tipowerup/loyaltypoints/loyalty',
        ],
        'preview' => [
            'title' => 'lang:igniter::admin.form.preview_title',
            'back' => 'tipowerup/loyaltypoints/loyalty',
        ],
        'delete' => [
            'redirect' => 'tipowerup/loyaltypoints/loyalty',
        ],
        'configFile' => 'loyaltyrule',
    ];

    protected null|string|array $requiredPermissions = 'Tipowerup.LoyaltyPoints.*';

    public function __construct()
    {
        parent::__construct();

        AdminMenu::setContext('loyalty', 'marketing');
    }
}
```

### URL Routing

Admin controller URLs follow the pattern:

```
admin/{vendor}/{extension}/{controller}/{action}/{id}
```

Examples:
- List view: `admin/tipowerup/loyaltypoints/loyalty`
- Create form: `admin/tipowerup/loyaltypoints/loyalty/create`
- Edit form: `admin/tipowerup/loyaltypoints/loyalty/edit/5`

### The configFile Reference

The `configFile` key points to form/list field definition files in `resources/models/`. For `'configFile' => 'loyaltyrule'`, TI looks for `resources/models/loyaltyrule.php`:

```php
<?php

// resources/models/loyaltyrule.php

return [
    'list' => [
        'columns' => [
            'edit' => [
                'type' => 'button',
                'iconCssClass' => 'fa fa-pencil',
            ],
            'name' => [
                'label' => 'tipowerup.loyaltypoints::default.column_name',
                'searchable' => true,
            ],
            'points_per_dollar' => [
                'label' => 'tipowerup.loyaltypoints::default.column_points',
                'type' => 'number',
            ],
            'is_active' => [
                'label' => 'lang:igniter::admin.label_status',
                'type' => 'switch',
            ],
        ],
    ],
    'form' => [
        'toolbar' => [
            'buttons' => [
                'back' => [
                    'label' => 'lang:igniter::admin.button_icon_back',
                    'class' => 'btn btn-outline-secondary',
                    'href' => 'tipowerup/loyaltypoints/loyalty',
                ],
                'save' => [
                    'label' => 'lang:igniter::admin.button_save',
                    'context' => ['create', 'edit'],
                    'partial' => 'form/toolbar_save_button',
                    'class' => 'btn btn-primary',
                    'data-request' => 'onSave',
                    'data-progress-indicator' => 'igniter::admin.text_saving',
                ],
                'delete' => [
                    'label' => 'lang:igniter::admin.button_icon_delete',
                    'class' => 'btn btn-danger',
                    'data-request' => 'onDelete',
                    'data-request-confirm' => 'lang:igniter::admin.alert_warning_confirm',
                    'context' => ['edit'],
                ],
            ],
        ],
        'fields' => [
            'name' => [
                'label' => 'tipowerup.loyaltypoints::default.label_name',
                'type' => 'text',
                'span' => 'left',
            ],
            'points_per_dollar' => [
                'label' => 'tipowerup.loyaltypoints::default.label_points_per_dollar',
                'type' => 'number',
                'span' => 'right',
                'comment' => 'tipowerup.loyaltypoints::default.help_points_per_dollar',
            ],
            'is_active' => [
                'label' => 'lang:igniter::admin.label_status',
                'type' => 'switch',
                'default' => true,
            ],
        ],
    ],
];
```

---

## 6. Eloquent Models

TI models extend `Igniter\Flame\Database\Model` (not Laravel's `Illuminate\Database\Eloquent\Model` directly). The key difference is the declarative `$relation` array for defining relationships.

```php
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints\Models;

use Igniter\Flame\Database\Model;
use Igniter\User\Models\Customer;

class LoyaltyPoints extends Model
{
    protected $table = 'tipowerup_loyalty_points';

    protected $fillable = [
        'customer_id',
        'balance',
        'lifetime_points',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'balance' => 'integer',
        'lifetime_points' => 'integer',
    ];

    public $timestamps = true;

    // Declarative relationships (TI convention)
    public $relation = [
        'belongsTo' => [
            'customer' => [Customer::class, 'foreignKey' => 'customer_id'],
        ],
        'hasMany' => [
            'transactions' => [LoyaltyTransaction::class],
        ],
    ];

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
```

### Why `$relation` Array Instead of Methods

Use the `$relation` array (TI convention), **not** Laravel's relationship methods. TI's Extendable trait dynamically generates the relationship methods from this array. The array is also used by TI's form system to auto-populate relation fields, and by the `extend()` mechanism to allow other extensions to add relationships dynamically.

**Real-world example from `igniter.coupons`:**

```php
public $relation = [
    'belongsToMany' => [
        'categories' => [Category::class, 'table' => 'igniter_coupon_categories'],
        'menus' => [Menu::class, 'table' => 'igniter_coupon_menus'],
        'customers' => [Customer::class, 'table' => 'igniter_coupon_customers'],
        'customer_groups' => [CustomerGroup::class, 'table' => 'igniter_coupon_customer_groups'],
    ],
    'hasMany' => [
        'history' => CouponHistory::class,
    ],
    'morphToMany' => [
        'locations' => [Location::class, 'name' => 'locationable'],
    ],
];
```

### Relationship Types

| Array Key | Laravel Equivalent | Example |
|-----------|-------------------|---------|
| `belongsTo` | `belongsTo()` | `'customer' => [Customer::class, 'foreignKey' => 'customer_id']` |
| `hasOne` | `hasOne()` | `'profile' => [Profile::class]` |
| `hasMany` | `hasMany()` | `'transactions' => [Transaction::class]` |
| `belongsToMany` | `belongsToMany()` | `'categories' => [Category::class, 'table' => 'pivot_table']` |
| `morphTo` | `morphTo()` | `'commentable' => []` |
| `morphOne` | `morphOne()` | `'image' => [Media::class, 'name' => 'imageable']` |
| `morphMany` | `morphMany()` | `'comments' => [Comment::class, 'name' => 'commentable']` |
| `morphToMany` | `morphToMany()` | `'locations' => [Location::class, 'name' => 'locationable']` |

### Referencing Core Models

Import and use core models directly. The relationship is defined in **your** model, not in the core model. If the extension is uninstalled, the core model is unaffected:

```php
use Igniter\Cart\Models\Order;
use Igniter\User\Models\Customer;
use Igniter\Local\Models\Location;
```

---

## 7. Database Migrations

### Full Migration Template

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against running twice
        if (Schema::hasTable('tipowerup_loyalty_points')) {
            return;
        }

        // ✅ Vendor-prefixed table name
        Schema::create('tipowerup_loyalty_points', function (Blueprint $table): void {
            $table->id();

            // ✅ Soft references to core tables (NO foreign keys)
            $table->unsignedBigInteger('customer_id')->index();

            $table->integer('balance')->default(0);
            $table->integer('lifetime_points')->default(0);
            $table->timestamps();
        });
    }

    // ✅ ALWAYS implement down() for clean uninstall
    public function down(): void
    {
        Schema::dropIfExists('tipowerup_loyalty_points');
    }
};
```

### Migration Rules

| Rule | Details |
|------|---------|
| Use anonymous classes | `return new class extends Migration` |
| Prefix tables with vendor name | `tipowerup_loyalty_*` |
| NEVER use `Schema::table()` on core tables | No `orders`, `customers`, `locations`, etc. |
| ALWAYS implement `down()` | Must reverse `up()` completely |
| Use `dropIfExists()` not `drop()` | Safety against double-execution |
| Index reference columns | `$table->unsignedBigInteger('customer_id')->index()` |
| NO foreign key constraints to core tables | Use soft references only |
| Guard against re-execution | `if (Schema::hasTable('...')) { return; }` |

### Migration Naming Convention

```
YYYY_MM_DD_HHMMSS_description.php
```

Examples:
```
2026_01_15_000001_create_loyalty_points_table.php
2026_01_15_000002_create_loyalty_transactions_table.php
2026_02_01_000001_add_tier_column_to_loyalty_points_table.php
```

### Migration Location and Grouping

- **Location:** `database/migrations/`
- **Grouping:** TI automatically groups migrations by extension code. In the `migrations` table, entries appear as: `tipowerup.loyaltypoints::2026_01_15_000001_create_loyalty_points_table`

### Adding Columns to Your Own Tables

When you need to alter your own tables in later versions:

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipowerup_loyalty_points', function (Blueprint $table): void {
            $table->string('tier')->default('bronze')->after('lifetime_points');
        });
    }

    public function down(): void
    {
        Schema::table('tipowerup_loyalty_points', function (Blueprint $table): void {
            $table->dropColumn('tier');
        });
    }
};
```

This is fine because you are modifying **your own** table, not a core table.

---

## 8. Hooking Into Core Behavior (Without Coupling)

### Event Listeners

Register event listeners in `boot()` so they are automatically cleaned up when the extension is disabled:

```php
#[Override]
public function boot(): void
{
    Event::listen('igniter.checkout.afterSaveOrder', function ($order): void {
        resolve(LoyaltyService::class)->awardPointsForOrder($order);
    });

    Event::listen('igniter.user.login', function ($customer): void {
        resolve(LoyaltyService::class)->checkBirthdayBonus($customer);
    });

    Event::listen('admin.order.paymentProcessed', function (Order $order): void {
        resolve(LoyaltyService::class)->processPayment($order);
    });
}
```

### Model Extensions

Dynamically add relationships, methods, and attributes to core models:

```php
#[Override]
public function boot(): void
{
    // Add relationship to core model dynamically
    \Igniter\Cart\Models\Order::extend(function ($model): void {
        $model->relation['hasOne']['loyalty_points'] = [
            OrderLoyaltyPoints::class,
            'foreignKey' => 'order_id',
        ];
    });

    // Add a dynamic method to a core model
    \Igniter\User\Models\Customer::extend(function ($model): void {
        $model->addDynamicMethod('getLoyaltyBalance', function () use ($model) {
            return LoyaltyPoints::forCustomer($model->getKey())->value('balance') ?? 0;
        });
    });
}
```

These are safe -- they only exist while the extension is booted. When disabled or uninstalled, these extensions vanish.

### Controller Extensions

Extend existing admin controller forms to add your own tabs or fields:

```php
#[Override]
public function boot(): void
{
    \Igniter\User\Http\Controllers\Customers::extendFormFields(
        function ($form, $model, $context): void {
            if (!$model instanceof \Igniter\User\Models\Customer) {
                return;
            }

            $form->addTabFields([
                'loyalty_balance' => [
                    'tab' => 'Loyalty',
                    'label' => 'Points Balance',
                    'type' => 'text',
                    'disabled' => true,
                ],
            ]);
        }
    );
}
```

### Model Observers

Declare observers as a class property -- TI's `EventServiceProvider` handles registration automatically:

```php
// In Extension.php (class property)
protected $observers = [
    \Igniter\User\Models\Customer::class => \Tipowerup\LoyaltyPoints\Observers\CustomerObserver::class,
];
```

### Event Subscribers

For complex event handling, use subscriber classes:

```php
// In Extension.php (class property)
protected $subscribe = [
    \Tipowerup\LoyaltyPoints\Listeners\LoyaltyEventSubscriber::class,
];
```

---

## 9. Translations

### File Location

`resources/lang/en/default.php`

### Namespace

`{extension.code}::default.key` -- for example, `tipowerup.loyaltypoints::default.text_title`

### Translation File Format

```php
<?php

// resources/lang/en/default.php

return [
    // General
    'text_title' => 'Loyalty Points',
    'text_empty' => 'No loyalty rules found.',
    'text_form_name' => 'Loyalty Rule',

    // List columns
    'column_name' => 'Rule Name',
    'column_points' => 'Points',
    'column_status' => 'Status',

    // Form labels
    'label_name' => 'Name',
    'label_points_per_dollar' => 'Points per Dollar',
    'label_minimum_order' => 'Minimum Order Amount',

    // Help text
    'help_points_per_dollar' => 'Number of loyalty points awarded per dollar spent.',
    'help_minimum_order' => 'Minimum order value required to earn points.',

    // Permissions
    'text_permission_group' => 'Loyalty Points',

    // Flash messages
    'alert_points_awarded' => 'Loyalty points awarded successfully.',
    'alert_rule_created' => 'Loyalty rule created successfully.',
];
```

### Usage

```php
// In PHP
lang('tipowerup.loyaltypoints::default.text_title')

// In Blade templates
{{ lang('tipowerup.loyaltypoints::default.text_title') }}

// In config arrays (deferred translation)
'title' => 'tipowerup.loyaltypoints::default.text_title',
// or
'title' => 'lang:tipowerup.loyaltypoints::default.text_title',
```

---

## 10. TI PowerUp Installer Compatibility Checklist

Use this checklist before submitting your extension. Every item must pass for clean install/uninstall cycles.

| # | Check | How to Verify |
|---|-------|---------------|
| 1 | `composer.json` has `"type": "tastyigniter-package"` | Read `composer.json` |
| 2 | `composer.json` has `extra.tastyigniter-extension` with `code`, `name`, `icon` | Read `composer.json` |
| 3 | Extension code matches namespace convention | `tipowerup.loyaltypoints` -> `Tipowerup\LoyaltyPoints` |
| 4 | `Extension.php` is at `src/Extension.php` | Check file exists |
| 5 | All tables are vendor-prefixed | `tipowerup_*` in all migrations |
| 6 | NO core table modifications | Search migrations for `Schema::table('orders'`, etc. |
| 7 | NO foreign keys to core tables | Search for `->foreign(`, `->constrained(` in migrations |
| 8 | ALL migrations have proper `down()` methods | Every `Schema::create()` has a `Schema::dropIfExists()` |
| 9 | All dynamic behavior is in `boot()` | Event listeners, model extensions, controller extensions |
| 10 | `register()` only does bindings and config | No side effects in `register()` |
| 11 | Settings use `SettingsModel` with unique `settingsCode` | Prefixed with vendor name |
| 12 | `params()` keys are prefixed | e.g., `tipowerup_loyalty_*` |
| 13 | No hardcoded paths | Uses `__DIR__` and extension path helpers |
| 14 | All PHP files have `declare(strict_types=1)` | Check every `.php` file |
| 15 | Standard PSR-4 autoloading | Namespace maps to `src/` |

### Quick Validation Script

Run these searches against your codebase to catch violations:

```bash
# Check for core table modifications
grep -r "Schema::table('orders'" database/migrations/
grep -r "Schema::table('customers'" database/migrations/
grep -r "Schema::table('locations'" database/migrations/
grep -r "Schema::table('users'" database/migrations/

# Check for foreign keys to core tables
grep -r "->foreign(" database/migrations/
grep -r "->constrained(" database/migrations/

# Check for empty down() methods
grep -A2 "function down" database/migrations/

# Check for declare(strict_types=1)
find src/ -name "*.php" -exec grep -L "declare(strict_types=1)" {} \;
```

---

## 11. Testing Your Extension

### Basic Extension Tests

```php
<?php

// tests/ExtensionTest.php

declare(strict_types=1);

use Tipowerup\LoyaltyPoints\Extension;

it('has valid extension class', function (): void {
    expect(class_exists(Extension::class))->toBeTrue();
});

it('registers navigation', function (): void {
    $extension = new Extension(app());
    $nav = $extension->registerNavigation();

    expect($nav)->toBeArray()
        ->and($nav)->toHaveKey('marketing');
});

it('registers permissions', function (): void {
    $extension = new Extension(app());
    $perms = $extension->registerPermissions();

    expect($perms)->toBeArray()
        ->and($perms)->toHaveKey('Tipowerup.LoyaltyPoints.Manage');
});

it('registers settings', function (): void {
    $extension = new Extension(app());
    $settings = $extension->registerSettings();

    expect($settings)->toBeArray();
});
```

### Testing Migrations

Test that `up()` creates expected tables and `down()` drops them cleanly:

```php
it('creates loyalty points table on migrate', function (): void {
    $this->artisan('migrate', ['--path' => 'vendor/tipowerup/ti-ext-loyaltypoints/database/migrations']);

    expect(Schema::hasTable('tipowerup_loyalty_points'))->toBeTrue()
        ->and(Schema::hasColumn('tipowerup_loyalty_points', 'customer_id'))->toBeTrue()
        ->and(Schema::hasColumn('tipowerup_loyalty_points', 'balance'))->toBeTrue();
});

it('drops loyalty points table on rollback', function (): void {
    $this->artisan('migrate', ['--path' => 'vendor/tipowerup/ti-ext-loyaltypoints/database/migrations']);
    $this->artisan('migrate:rollback', ['--path' => 'vendor/tipowerup/ti-ext-loyaltypoints/database/migrations']);

    expect(Schema::hasTable('tipowerup_loyalty_points'))->toBeFalse();
});

it('survives multiple install and uninstall cycles', function (): void {
    $path = 'vendor/tipowerup/ti-ext-loyaltypoints/database/migrations';

    // Cycle 1
    $this->artisan('migrate', ['--path' => $path]);
    $this->artisan('migrate:rollback', ['--path' => $path]);

    // Cycle 2
    $this->artisan('migrate', ['--path' => $path]);
    $this->artisan('migrate:rollback', ['--path' => $path]);

    // Cycle 3
    $this->artisan('migrate', ['--path' => $path]);
    expect(Schema::hasTable('tipowerup_loyalty_points'))->toBeTrue();
});
```

### Testing Models

```php
it('creates loyalty points for a customer', function (): void {
    $points = LoyaltyPoints::create([
        'customer_id' => 1,
        'balance' => 100,
        'lifetime_points' => 100,
    ]);

    expect($points)->toBeInstanceOf(LoyaltyPoints::class)
        ->and($points->balance)->toBe(100);
});

it('scopes by customer', function (): void {
    LoyaltyPoints::create(['customer_id' => 1, 'balance' => 50, 'lifetime_points' => 50]);
    LoyaltyPoints::create(['customer_id' => 2, 'balance' => 100, 'lifetime_points' => 100]);

    $result = LoyaltyPoints::forCustomer(1)->first();

    expect($result->customer_id)->toBe(1)
        ->and($result->balance)->toBe(50);
});
```

---

## 12. Common Pitfalls

### 1. Modifying Core Tables

**Problem:** Adding columns to `orders`, `customers`, or other core tables causes uninstall failures.

```php
// ❌ This will break on uninstall
Schema::table('orders', function (Blueprint $table): void {
    $table->integer('loyalty_points')->default(0);
});
```

**Solution:** Create your own table with a soft reference (see Rule 1).

### 2. Empty `down()` Methods

**Problem:** Many TI core extensions have empty `down()` methods. Copying this pattern leaves orphaned tables after uninstall.

```php
// ❌ Do NOT copy from core extensions
public function down(): void
{
    //
}
```

**Solution:** Always implement `down()` to fully reverse `up()`.

### 3. Foreign Keys to Core Tables

**Problem:** Foreign keys prevent clean rollback and can cause cascade issues.

**Solution:** TI deliberately dropped all foreign keys in a 2022 migration. Use indexes without constraints.

### 4. Registering Event Listeners in `register()`

**Problem:** `register()` is called even when the extension is in a disabled state if the class is loaded. Side effects in `register()` can affect the application when you do not expect them to.

```php
// ❌ Events in register() -- may fire when extension is disabled
public function register(): void
{
    Event::listen('some.event', fn () => ...);
}
```

**Solution:** Put event listeners, model extensions, and controller extensions in `boot()`.

### 5. Hardcoding File Paths

```php
// ❌ Hardcoded path
$path = '/var/www/html/extensions/tipowerup/loyaltypoints/config.php';

// ✅ Relative to extension
$path = __DIR__.'/../config/loyaltypoints.php';
```

### 6. Not Prefixing Table Names

**Problem:** Generic table names like `points` or `transactions` will collide with other extensions.

**Solution:** Always use `{vendor}_{tablename}`: `tipowerup_loyalty_points`.

### 7. Not Prefixing params() Keys

**Problem:** The `settings` table is a shared namespace. Keys like `api_key` or `enabled` will collide.

```php
// ❌ Generic key
params()->set('api_key', '...');

// ✅ Prefixed key
params()->set('tipowerup_loyalty_api_key', '...');
```

### 8. Using DB:: Facade Directly

```php
// ❌ Raw database queries
DB::table('tipowerup_loyalty_points')->where('customer_id', 1)->first();

// ✅ Eloquent models
LoyaltyPoints::forCustomer(1)->first();
```

### 9. Not Handling Nullable Relationships

When referencing core models that may have been deleted:

```php
// ❌ Assumes customer always exists
$name = $loyaltyPoints->customer->name;

// ✅ Null-safe operator
$name = $loyaltyPoints->customer?->name ?? 'Deleted Customer';
```

### 10. Tight Coupling to Other Extensions

If your extension optionally integrates with another third-party extension:

```php
// ✅ Guard with class_exists()
if (class_exists(\AnotherVendor\SomeExtension\Models\Widget::class)) {
    \AnotherVendor\SomeExtension\Models\Widget::extend(function ($model): void {
        // Optional integration
    });
}
```

---

## 13. Real-World Example: Complete Mini Extension

A complete, working Loyalty Points extension that follows all the rules above.

### File: `composer.json`

```json
{
    "name": "tipowerup/ti-ext-loyaltypoints",
    "type": "tastyigniter-package",
    "description": "Reward customers with loyalty points for every order.",
    "license": "MIT",
    "authors": [
        {
            "name": "TI PowerUp Team",
            "email": "support@tipowerup.com"
        }
    ],
    "require": {
        "php": "^8.3",
        "tastyigniter/core": "^v4.0"
    },
    "autoload": {
        "psr-4": {
            "Tipowerup\\LoyaltyPoints\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tipowerup\\LoyaltyPoints\\Tests\\": "tests/"
        }
    },
    "extra": {
        "tastyigniter-extension": {
            "code": "tipowerup.loyaltypoints",
            "name": "Loyalty Points",
            "icon": {
                "class": "fa fa-star",
                "color": "#FFF",
                "backgroundColor": "#FF9800"
            },
            "homepage": "https://tipowerup.com/extensions/loyalty-points"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

### File: `src/Extension.php`

```php
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints;

use Igniter\Cart\Models\Order;
use Igniter\System\Classes\BaseExtension;
use Illuminate\Support\Facades\Event;
use Override;
use Tipowerup\LoyaltyPoints\Models\LoyaltyRule;
use Tipowerup\LoyaltyPoints\Models\LoyaltyTransaction;

class Extension extends BaseExtension
{
    protected array $morphMap = [
        'loyalty_transaction' => LoyaltyTransaction::class,
    ];

    #[Override]
    public function register(): void
    {
        parent::register();
    }

    #[Override]
    public function boot(): void
    {
        // Dynamically add relationship to Order model
        Order::extend(function ($model): void {
            $model->relation['hasMany']['loyalty_transactions'] = [
                LoyaltyTransaction::class,
                'foreignKey' => 'order_id',
            ];
        });

        // Award points after payment is processed
        Event::listen('admin.order.paymentProcessed', function (Order $order): void {
            $rule = LoyaltyRule::query()
                ->where('is_active', true)
                ->where('min_order_amount', '<=', $order->order_total)
                ->orderByDesc('points_per_dollar')
                ->first();

            if ($rule && $order->customer_id) {
                $points = (int) floor($order->order_total * $rule->points_per_dollar);

                LoyaltyTransaction::create([
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->getKey(),
                    'points' => $points,
                    'type' => 'earned',
                    'description' => "Earned {$points} points for order #{$order->order_id}",
                ]);
            }
        });
    }

    #[Override]
    public function registerNavigation(): array
    {
        return [
            'marketing' => [
                'child' => [
                    'loyalty' => [
                        'priority' => 40,
                        'class' => 'loyalty',
                        'href' => admin_url('tipowerup/loyaltypoints/loyalty'),
                        'title' => lang('tipowerup.loyaltypoints::default.text_title'),
                        'permission' => 'Tipowerup.LoyaltyPoints.*',
                    ],
                ],
            ],
        ];
    }

    #[Override]
    public function registerPermissions(): array
    {
        return [
            'Tipowerup.LoyaltyPoints.Manage' => [
                'description' => 'Create, modify and delete loyalty point rules',
                'group' => 'tipowerup.loyaltypoints::default.text_permission_group',
            ],
        ];
    }

    #[Override]
    public function registerSettings(): array
    {
        return [];
    }
}
```

### File: `database/migrations/2026_01_15_000001_create_loyalty_tables.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tipowerup_loyalty_rules')) {
            return;
        }

        Schema::create('tipowerup_loyalty_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('points_per_dollar', 8, 2)->default(1.00);
            $table->decimal('min_order_amount', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tipowerup_loyalty_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->integer('points');
            $table->string('type', 20)->default('earned');  // earned, redeemed, expired, adjusted
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipowerup_loyalty_transactions');
        Schema::dropIfExists('tipowerup_loyalty_rules');
    }
};
```

### File: `src/Models/LoyaltyRule.php`

```php
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints\Models;

use Igniter\Flame\Database\Model;

class LoyaltyRule extends Model
{
    protected $table = 'tipowerup_loyalty_rules';

    protected $fillable = [
        'name',
        'points_per_dollar',
        'min_order_amount',
        'is_active',
    ];

    protected $casts = [
        'points_per_dollar' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public $timestamps = true;

    public $relation = [];
}
```

### File: `src/Models/LoyaltyTransaction.php`

```php
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints\Models;

use Igniter\Cart\Models\Order;
use Igniter\Flame\Database\Model;
use Igniter\User\Models\Customer;

class LoyaltyTransaction extends Model
{
    protected $table = 'tipowerup_loyalty_transactions';

    protected $fillable = [
        'customer_id',
        'order_id',
        'points',
        'type',
        'description',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'order_id' => 'integer',
        'points' => 'integer',
    ];

    public $timestamps = true;

    public $relation = [
        'belongsTo' => [
            'customer' => [Customer::class, 'foreignKey' => 'customer_id'],
            'order' => [Order::class, 'foreignKey' => 'order_id'],
        ],
    ];

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeEarned($query)
    {
        return $query->where('type', 'earned');
    }

    public function scopeRedeemed($query)
    {
        return $query->where('type', 'redeemed');
    }
}
```

### File: `src/Http/Controllers/Loyalty.php`

```php
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\AdminMenu;
use Igniter\Admin\Http\Actions\FormController;
use Igniter\Admin\Http\Actions\ListController;
use Tipowerup\LoyaltyPoints\Http\Requests\LoyaltyRuleRequest;
use Tipowerup\LoyaltyPoints\Models\LoyaltyRule;

class Loyalty extends AdminController
{
    public array $implement = [
        ListController::class,
        FormController::class,
    ];

    public array $listConfig = [
        'list' => [
            'model' => LoyaltyRule::class,
            'title' => 'tipowerup.loyaltypoints::default.text_title',
            'emptyMessage' => 'tipowerup.loyaltypoints::default.text_empty',
            'defaultSort' => ['id', 'DESC'],
            'configFile' => 'loyaltyrule',
        ],
    ];

    public array $formConfig = [
        'name' => 'tipowerup.loyaltypoints::default.text_form_name',
        'model' => LoyaltyRule::class,
        'request' => LoyaltyRuleRequest::class,
        'create' => [
            'title' => 'lang:igniter::admin.form.create_title',
            'redirect' => 'tipowerup/loyaltypoints/loyalty/edit/{id}',
            'redirectClose' => 'tipowerup/loyaltypoints/loyalty',
            'redirectNew' => 'tipowerup/loyaltypoints/loyalty/create',
        ],
        'edit' => [
            'title' => 'lang:igniter::admin.form.edit_title',
            'redirect' => 'tipowerup/loyaltypoints/loyalty/edit/{id}',
            'redirectClose' => 'tipowerup/loyaltypoints/loyalty',
        ],
        'preview' => [
            'title' => 'lang:igniter::admin.form.preview_title',
            'back' => 'tipowerup/loyaltypoints/loyalty',
        ],
        'delete' => [
            'redirect' => 'tipowerup/loyaltypoints/loyalty',
        ],
        'configFile' => 'loyaltyrule',
    ];

    protected null|string|array $requiredPermissions = 'Tipowerup.LoyaltyPoints.*';

    public function __construct()
    {
        parent::__construct();

        AdminMenu::setContext('loyalty', 'marketing');
    }
}
```

### File: `src/Http/Requests/LoyaltyRuleRequest.php`

```php
<?php

declare(strict_types=1);

namespace Tipowerup\LoyaltyPoints\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoyaltyRuleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'points_per_dollar' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
```

### File: `resources/models/loyaltyrule.php`

```php
<?php

return [
    'list' => [
        'columns' => [
            'edit' => [
                'type' => 'button',
                'iconCssClass' => 'fa fa-pencil',
            ],
            'name' => [
                'label' => 'tipowerup.loyaltypoints::default.column_name',
                'searchable' => true,
            ],
            'points_per_dollar' => [
                'label' => 'tipowerup.loyaltypoints::default.column_points_per_dollar',
                'type' => 'number',
            ],
            'min_order_amount' => [
                'label' => 'tipowerup.loyaltypoints::default.column_min_order',
                'type' => 'currency',
            ],
            'is_active' => [
                'label' => 'lang:igniter::admin.label_status',
                'type' => 'switch',
            ],
        ],
    ],
    'form' => [
        'toolbar' => [
            'buttons' => [
                'back' => [
                    'label' => 'lang:igniter::admin.button_icon_back',
                    'class' => 'btn btn-outline-secondary',
                    'href' => 'tipowerup/loyaltypoints/loyalty',
                ],
                'save' => [
                    'label' => 'lang:igniter::admin.button_save',
                    'context' => ['create', 'edit'],
                    'partial' => 'form/toolbar_save_button',
                    'class' => 'btn btn-primary',
                    'data-request' => 'onSave',
                    'data-progress-indicator' => 'igniter::admin.text_saving',
                ],
                'delete' => [
                    'label' => 'lang:igniter::admin.button_icon_delete',
                    'class' => 'btn btn-danger',
                    'data-request' => 'onDelete',
                    'data-request-confirm' => 'lang:igniter::admin.alert_warning_confirm',
                    'context' => ['edit'],
                ],
            ],
        ],
        'fields' => [
            'name' => [
                'label' => 'tipowerup.loyaltypoints::default.label_name',
                'type' => 'text',
                'span' => 'left',
            ],
            'points_per_dollar' => [
                'label' => 'tipowerup.loyaltypoints::default.label_points_per_dollar',
                'type' => 'number',
                'span' => 'right',
                'comment' => 'tipowerup.loyaltypoints::default.help_points_per_dollar',
            ],
            'min_order_amount' => [
                'label' => 'tipowerup.loyaltypoints::default.label_min_order',
                'type' => 'currency',
                'span' => 'left',
                'comment' => 'tipowerup.loyaltypoints::default.help_min_order',
            ],
            'is_active' => [
                'label' => 'lang:igniter::admin.label_status',
                'type' => 'switch',
                'span' => 'right',
                'default' => true,
            ],
        ],
    ],
];
```

### File: `resources/lang/en/default.php`

```php
<?php

return [
    // General
    'text_title' => 'Loyalty Points',
    'text_empty' => 'No loyalty rules found.',
    'text_form_name' => 'Loyalty Rule',

    // List columns
    'column_name' => 'Rule Name',
    'column_points_per_dollar' => 'Points / Dollar',
    'column_min_order' => 'Min. Order',

    // Form labels
    'label_name' => 'Rule Name',
    'label_points_per_dollar' => 'Points per Dollar',
    'label_min_order' => 'Minimum Order Amount',

    // Help text
    'help_points_per_dollar' => 'Number of loyalty points awarded per dollar spent.',
    'help_min_order' => 'Orders below this amount will not earn points. Set to 0 for no minimum.',

    // Permissions
    'text_permission_group' => 'Loyalty Points',
];
```

### Summary of Files

```
ti-ext-loyaltypoints/
  composer.json
  database/
    migrations/
      2026_01_15_000001_create_loyalty_tables.php
  resources/
    lang/en/
      default.php
    models/
      loyaltyrule.php
  src/
    Extension.php
    Http/
      Controllers/
        Loyalty.php
      Requests/
        LoyaltyRuleRequest.php
    Models/
      LoyaltyRule.php
      LoyaltyTransaction.php
```

This extension:
- Creates only its own tables (`tipowerup_loyalty_rules`, `tipowerup_loyalty_transactions`)
- Uses soft references to core tables (no foreign keys)
- Puts all event listeners and model extensions in `boot()`
- Has a complete `down()` migration that drops all tables cleanly
- Uses vendor-prefixed table names
- Follows TI conventions for admin controllers, permissions, navigation, and translations
- Can be installed and uninstalled repeatedly without leaving any trace
