<?php

declare(strict_types=1);

namespace Tipowerup\Installer;

use Facades\Igniter\System\Helpers\SystemHelper;
use Igniter\Admin\Facades\Template;
use Igniter\Flame\Support\Facades\Igniter;
use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\BaseExtension;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Override;
use Throwable;
use Tipowerup\Installer\Console\RepublishAssetsCommand;
use Tipowerup\Installer\Livewire\InstalledPackages;
use Tipowerup\Installer\Livewire\InstallerMain;
use Tipowerup\Installer\Livewire\InstallLogs;
use Tipowerup\Installer\Livewire\InstallProgress;
use Tipowerup\Installer\Livewire\Marketplace;
use Tipowerup\Installer\Livewire\Onboarding;
use Tipowerup\Installer\Livewire\PackageDetail;
use Tipowerup\Installer\Livewire\SettingsPanel;
use Tipowerup\Installer\Services\BackgroundUpdateChecker;

class Extension extends BaseExtension
{
    /**
     * Return extension metadata from the package root composer.json.
     *
     * TI resolves the config path from the Extension class file location,
     * which is `src/`. Our composer.json lives one level up at the package root.
     */
    #[Override]
    public function extensionMeta(): array
    {
        if (func_get_args()) {
            return $this->config = func_get_arg(0);
        }

        if (!is_null($this->config)) {
            return $this->config;
        }

        return $this->config = SystemHelper::extensionConfigFromFile(dirname(__DIR__));
    }

    /**
     * Register extension services.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/installer.php', 'tipowerup.installer');

        if (app()->runningInConsole()) {
            $this->commands([RepublishAssetsCommand::class]);
        }

        parent::register();
    }

    /**
     * Boot extension after all services are registered.
     */
    #[Override]
    public function boot(): void
    {
        $this->registerLivewireComponents();
        $this->registerStoragePackages();
        $this->registerAutoUpdateCheck();
        $this->defineRoutes();
        $this->selfInstallIfNeeded();
    }

    /**
     * Auto-install on first admin request when composer require was used without `igniter:extension-install`.
     *
     * Guarded by an in-process latch (`once()`) plus a long-lived cache flag so a
     * single admin request can't recurse through `installExtension()` from inside
     * boot, and so subsequent requests don't re-probe the schema on every hit.
     */
    protected function selfInstallIfNeeded(): void
    {
        if (app()->runningInConsole() || !Igniter::runningInAdmin()) {
            return;
        }

        once(function (): void {
            if (Cache::get('tipowerup.installer.self_installed') === true) {
                return;
            }

            try {
                if (Schema::hasTable('tip_licenses')) {
                    Cache::forever('tipowerup.installer.self_installed', true);

                    return;
                }

                resolve(ExtensionManager::class)->installExtension('tipowerup.installer');
                Cache::forever('tipowerup.installer.self_installed', true);
            } catch (Throwable $e) {
                Log::warning('TI PowerUp Installer self-install failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Register admin navigation menu items.
     */
    #[Override]
    public function registerNavigation(): array
    {
        return [
            'tools' => [
                'child' => [
                    'installer' => [
                        'priority' => 10,
                        'class' => 'installer',
                        'href' => admin_url('tipowerup/installer'),
                        'title' => lang('tipowerup.installer::default.text_title'),
                        'permission' => 'Tipowerup.Installer.*',
                    ],
                ],
            ],
        ];
    }

    /**
     * Register backend permissions.
     */
    #[Override]
    public function registerPermissions(): array
    {
        return [
            'Tipowerup.Installer.Manage' => [
                'description' => 'Install, update, and manage TI PowerUp extensions and themes',
                'group' => 'igniter::system.permissions.name',
            ],
        ];
    }

    /**
     * Register the background auto-update check script on admin pages.
     */
    protected function registerAutoUpdateCheck(): void
    {
        if (app()->runningInConsole() || !Igniter::runningInAdmin()) {
            return;
        }

        Template::registerHook('endScripts', function (): string {
            $apiKey = params('tipowerup_api_key', '');
            if ($apiKey === '' || $apiKey === '0') {
                return '';
            }

            return view('tipowerup.installer::_partials.auto_update_check')->render();
        });
    }

    /**
     * Register the background update check route.
     */
    protected function defineRoutes(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        Route::middleware(config('igniter-routes.adminMiddleware', ['web']))
            ->domain(config('igniter-routes.adminDomain'))
            ->prefix(Igniter::adminUri())
            ->group(function (): void {
                Route::post('tipowerup/installer/check-updates-bg', fn () => resolve(BackgroundUpdateChecker::class)->handle())
                    ->name('tipowerup.installer.check-updates-bg');
            });
    }

    /**
     * Register Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('tipowerup-installer::installer-main', InstallerMain::class);
        Livewire::component('tipowerup-installer::onboarding', Onboarding::class);
        Livewire::component('tipowerup-installer::installed-packages', InstalledPackages::class);
        Livewire::component('tipowerup-installer::marketplace', Marketplace::class);
        Livewire::component('tipowerup-installer::package-detail', PackageDetail::class);
        Livewire::component('tipowerup-installer::install-progress', InstallProgress::class);
        Livewire::component('tipowerup-installer::install-logs', InstallLogs::class);
        Livewire::component('tipowerup-installer::settings-panel', SettingsPanel::class);
    }

    /**
     * Register storage-based packages with TastyIgniter.
     */
    protected function registerStoragePackages(): void
    {
        $extensionsPath = Storage::disk('local')->path('tipowerup/extensions');
        $themesPath = Storage::disk('local')->path('tipowerup/themes');

        if (File::isDirectory($extensionsPath)) {
            $extensionManager = resolve(ExtensionManager::class);
            $extensionManager->addDirectory($extensionsPath);

            foreach (File::glob($extensionsPath.'/*/*/{extension,composer}.json', GLOB_BRACE) as $configFile) {
                $packagePath = dirname($configFile);

                try {
                    $this->bootStoragePackage($packagePath);
                    $extension = $extensionManager->loadExtension($packagePath);
                    app()->register($extension);
                } catch (Throwable $e) {
                    Log::warning('Failed to load storage extension', [
                        'path' => $packagePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (File::isDirectory($themesPath)) {
            $this->app->booted(function () use ($themesPath): void {
                $themeManager = resolve(ThemeManager::class);

                foreach (File::directories($themesPath) as $themePath) {
                    try {
                        $this->bootStoragePackage($themePath);
                        $theme = $themeManager->loadTheme($themePath);

                        if ($theme !== null) {
                            $themeManager->bootTheme($theme);
                        }
                    } catch (Throwable $e) {
                        Log::warning('Failed to load storage theme', [
                            'path' => $themePath,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        }
    }

    /**
     * Bring a storage-installed package's bundled Composer environment online.
     *
     * Storage-installed packages are invisible to Laravel's PackageManifest
     * (which only reads the host's vendor/composer/installed.json). This method
     * runs the same algorithm Laravel runs at boot — autoload + provider
     * discovery from extra.laravel.providers — against the nested manifest
     * inside the storage package's own vendor/.
     *
     * Idempotent and host-safe: every provider is deduped against
     * app()->getLoadedProviders() so host-provided packages (Livewire, etc.)
     * are never double-registered.
     */
    protected function bootStoragePackage(string $packagePath): void
    {
        $autoloadFile = $packagePath.'/vendor/autoload.php';

        if (File::isFile($autoloadFile)) {
            try {
                require_once $autoloadFile;
            } catch (Throwable $e) {
                Log::warning('Failed to load bundled vendor autoload', [
                    'path' => $autoloadFile,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        $loaded = app()->getLoadedProviders();

        foreach ($this->discoverStorageProviders($packagePath) as $provider) {
            if (isset($loaded[$provider]) || !class_exists($provider)) {
                continue;
            }

            try {
                app()->register($provider);
            } catch (Throwable $e) {
                Log::warning('Failed to register storage package provider', [
                    'provider' => $provider,
                    'path' => $packagePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Collect service-provider FQCNs declared by a storage package and the
     * Composer deps bundled inside its vendor/.
     *
     * Reads the nested vendor/composer/installed.json when present (the
     * authoritative list Composer wrote at publish time); falls back to the
     * package's own composer.json when the dist ships without that file.
     *
     * @return list<string>
     */
    protected function discoverStorageProviders(string $packagePath): array
    {
        $providers = [];

        $installedJson = $packagePath.'/vendor/composer/installed.json';

        if (File::isFile($installedJson)) {
            try {
                $manifest = json_decode((string) File::get($installedJson), true, flags: JSON_THROW_ON_ERROR);
                $packages = $manifest['packages'] ?? $manifest ?? [];

                foreach ($packages as $package) {
                    foreach ((array) ($package['extra']['laravel']['providers'] ?? []) as $provider) {
                        if (is_string($provider)) {
                            $providers[] = $provider;
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Failed to parse bundled installed.json', [
                    'path' => $installedJson,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $manifestFile = $packagePath.'/composer.json';

        if (File::isFile($manifestFile)) {
            try {
                $manifest = json_decode((string) File::get($manifestFile), true, flags: JSON_THROW_ON_ERROR);

                foreach ((array) ($manifest['extra']['laravel']['providers'] ?? []) as $provider) {
                    if (is_string($provider)) {
                        $providers[] = $provider;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Failed to parse storage package composer.json', [
                    'path' => $manifestFile,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return array_values(array_unique($providers));
    }
}
