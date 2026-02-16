<?php

declare(strict_types=1);

namespace Tipowerup\Installer;

use Facades\Igniter\System\Helpers\SystemHelper;
use Igniter\System\Classes\BaseExtension;
use Livewire\Livewire;
use Override;
use Tipowerup\Installer\Livewire\InstalledPackages;
use Tipowerup\Installer\Livewire\InstallerMain;
use Tipowerup\Installer\Livewire\InstallProgress;
use Tipowerup\Installer\Livewire\Marketplace;
use Tipowerup\Installer\Livewire\Onboarding;
use Tipowerup\Installer\Livewire\PackageDetail;
use Tipowerup\Installer\Livewire\SettingsPanel;

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
        parent::register();
    }

    /**
     * Boot extension after all services are registered.
     */
    #[Override]
    public function boot(): void
    {
        $this->registerLivewireComponents();
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
                        'href' => admin_url('tipowerup/installer/installer'),
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
     * Register extension settings.
     */
    #[Override]
    public function registerSettings(): array
    {
        return [];
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
        Livewire::component('tipowerup-installer::settings-panel', SettingsPanel::class);
    }
}
