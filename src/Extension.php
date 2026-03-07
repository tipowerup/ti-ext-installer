<?php

declare(strict_types=1);

namespace Tipowerup\Installer;

use Facades\Igniter\System\Helpers\SystemHelper;
use Igniter\Admin\Facades\Template;
use Igniter\Flame\Support\Facades\Igniter;
use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\BaseExtension;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Override;
use Throwable;
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

        // Register storage-based extensions
        if (File::isDirectory($extensionsPath)) {
            $extensionManager = resolve(ExtensionManager::class);
            $extensionManager->addDirectory($extensionsPath);

            // Must manually load each extension since ExtensionManager already
            // ran loadExtensions() during __construct() before our boot()
            foreach (File::glob($extensionsPath.'/*/*/{extension,composer}.json', GLOB_BRACE) as $configFile) {
                try {
                    $extension = $extensionManager->loadExtension(dirname($configFile));

                    // Register the extension as a service provider so its
                    // bootingExtension() runs — this registers lang, views,
                    // resources, and route paths with the application.
                    app()->register($extension);
                } catch (Throwable $e) {
                    Log::warning('Failed to load storage extension', [
                        'path' => dirname($configFile),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Register storage-based themes (lazy loading handles discovery)
        if (File::isDirectory($themesPath)) {
            $themeManager = resolve(ThemeManager::class);
            $themeManager->addDirectory($themesPath);
        }
    }
}
