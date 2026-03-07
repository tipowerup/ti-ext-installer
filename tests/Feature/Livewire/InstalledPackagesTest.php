<?php

declare(strict_types=1);

use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Models\Theme;
use Igniter\System\Classes\ExtensionManager;
use Livewire\Livewire;
use Tipowerup\Installer\Livewire\InstalledPackages;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\PackageInstaller;
use Tipowerup\Installer\Services\PowerUpApiClient;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a fully-shaped installed-package array that satisfies the blade view.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function installedPackage(string $code = 'tipowerup/ti-ext-test', string $name = 'Test Package', array $overrides = []): array
{
    return array_merge([
        'code' => $code,
        'extension_code' => null,
        'theme_code' => null,
        'name' => $name,
        'description' => 'A test package',
        'version' => '1.0.0',
        'latest_version' => '1.0.0',
        'type' => 'extension',
        'install_method' => 'direct',
        'is_active' => true,
        'is_owned' => true,
        'expires_at' => null,
        'has_update' => false,
        'icon' => 'fa-puzzle-piece',
        'settings_url' => null,
        'edit_url' => null,
        'customize_url' => null,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Shared setup
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    $migrationPath = dirname(__DIR__, 3).'/database/migrations';
    $this->loadMigrationsFrom($migrationPath);

    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
        $mock->shouldReceive('findExtension')->andReturn(null);
        $mock->shouldReceive('isDisabled')->andReturn(false);
    });

    $this->mock(ThemeManager::class, function ($mock): void {
        $mock->shouldReceive('listThemes')->andReturn([]);
    });

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMyPackages')->andReturn(['data' => []]);
    });
});

// ---------------------------------------------------------------------------
// mount() / loadPackages()
// ---------------------------------------------------------------------------

it('renders the component', function (): void {
    Livewire::test(InstalledPackages::class)
        ->assertStatus(200)
        ->assertSet('isLoading', false);
});

it('initialises with empty installed and available packages when nothing is on system or API', function (): void {
    Livewire::test(InstalledPackages::class)
        ->assertSet('installedPackages', [])
        ->assertSet('availablePackages', [])
        ->assertSet('errorMessage', null);
});

it('populates availablePackages from API packages not on system', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMyPackages')->andReturn([
            'data' => [
                [
                    'code' => 'tipowerup/ti-ext-darkmode',
                    'name' => 'Dark Mode',
                    'description' => 'A dark mode extension',
                    'type' => 'extension',
                    'version' => '1.2.0',
                    'icon' => null,
                    'url' => 'https://tipowerup.com/darkmode',
                    'price' => 9.99,
                    'price_formatted' => '$9.99',
                ],
            ],
        ]);
    });

    $component = Livewire::test(InstalledPackages::class);

    expect($component->get('availablePackages'))->toHaveCount(1);
    expect($component->get('availablePackages')[0]['code'])->toBe('tipowerup/ti-ext-darkmode');
    expect($component->get('availablePackages')[0]['purchased'])->toBeTrue();
});

it('sets errorMessage and empties arrays when API throws an exception', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMyPackages')->andThrow(new RuntimeException('Service unavailable'));
    });

    Livewire::test(InstalledPackages::class)
        ->assertSet('installedPackages', [])
        ->assertSet('availablePackages', [])
        ->assertSet('errorMessage', 'Service unavailable')
        ->assertSet('isLoading', false);
});

it('sets isKeyError when API throws a 401 RuntimeException', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMyPackages')
            ->andThrow(new RuntimeException('Unauthorized', 401));
    });

    Livewire::test(InstalledPackages::class)
        ->assertSet('isKeyError', true)
        ->assertSet('installedPackages', [])
        ->assertSet('availablePackages', []);
});

it('resets errorMessage on each loadPackages call', function (): void {
    $callCount = 0;
    $this->mock(PowerUpApiClient::class, function ($mock) use (&$callCount): void {
        $mock->shouldReceive('getMyPackages')->andReturnUsing(function () use (&$callCount): array {
            $callCount++;
            if ($callCount === 1) {
                throw new RuntimeException('First call fails');
            }

            return ['data' => []];
        });
    });

    Livewire::test(InstalledPackages::class)
        ->assertSet('errorMessage', 'First call fails')
        ->call('loadPackages')
        ->assertSet('errorMessage', null);
});

it('marks availablePackages entries as purchased', function (): void {
    License::create([
        'package_code' => 'tipowerup/ti-ext-loyalty',
        'package_name' => 'Loyalty Points',
        'package_type' => 'extension',
        'version' => '2.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addYear(),
        'is_active' => true,
    ]);

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMyPackages')->andReturn([
            'data' => [
                [
                    'code' => 'tipowerup/ti-ext-loyalty',
                    'name' => 'Loyalty Points',
                    'description' => 'Loyalty extension',
                    'type' => 'extension',
                    'version' => '2.0.0',
                ],
            ],
        ]);
    });

    // With no on-system scan match the package ends up in availablePackages.
    $component = Livewire::test(InstalledPackages::class);

    expect($component->get('availablePackages'))->toHaveCount(1);
    expect($component->get('availablePackages')[0]['code'])->toBe('tipowerup/ti-ext-loyalty');
    expect($component->get('availablePackages')[0]['purchased'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// checkUpdates()
// ---------------------------------------------------------------------------

it('checkUpdates populates availableUpdates and reloads packages', function (): void {
    $this->mock(PackageInstaller::class, function ($mock): void {
        $mock->shouldReceive('checkUpdates')->once()->andReturn([
            'updates' => [
                [
                    'package_code' => 'tipowerup/ti-ext-darkmode',
                    'current_version' => '1.0.0',
                    'latest_version' => '2.0.0',
                ],
            ],
        ]);
    });

    Livewire::test(InstalledPackages::class)
        ->call('checkUpdates')
        ->assertSet('availableUpdates.tipowerup/ti-ext-darkmode.current_version', '1.0.0')
        ->assertSet('availableUpdates.tipowerup/ti-ext-darkmode.latest_version', '2.0.0')
        ->assertSet('isCheckingUpdates', false);
});

it('checkUpdates handles empty updates array', function (): void {
    $this->mock(PackageInstaller::class, function ($mock): void {
        $mock->shouldReceive('checkUpdates')->once()->andReturn(['updates' => []]);
    });

    Livewire::test(InstalledPackages::class)
        ->call('checkUpdates')
        ->assertSet('availableUpdates', [])
        ->assertSet('isCheckingUpdates', false);
});

it('checkUpdates sets errorMessage and resets isCheckingUpdates on failure', function (): void {
    $this->mock(PackageInstaller::class, function ($mock): void {
        $mock->shouldReceive('checkUpdates')
            ->andThrow(new RuntimeException('Update check failed'));
    });

    Livewire::test(InstalledPackages::class)
        ->call('checkUpdates')
        ->assertSet('errorMessage', 'Update check failed')
        ->assertSet('isCheckingUpdates', false);
});

// ---------------------------------------------------------------------------
// toggleViewMode()
// ---------------------------------------------------------------------------

it('toggleViewMode switches from grid to list and back', function (): void {
    Livewire::test(InstalledPackages::class)
        ->assertSet('viewMode', 'grid')
        ->call('toggleViewMode')
        ->assertSet('viewMode', 'list')
        ->call('toggleViewMode')
        ->assertSet('viewMode', 'grid');
});

// ---------------------------------------------------------------------------
// section collapse toggles
// ---------------------------------------------------------------------------

it('toggleInstalledSection flips installedCollapsed', function (): void {
    Livewire::test(InstalledPackages::class)
        ->assertSet('installedCollapsed', false)
        ->call('toggleInstalledSection')
        ->assertSet('installedCollapsed', true)
        ->call('toggleInstalledSection')
        ->assertSet('installedCollapsed', false);
});

it('toggleAvailableSection flips availableCollapsed', function (): void {
    Livewire::test(InstalledPackages::class)
        ->assertSet('availableCollapsed', false)
        ->call('toggleAvailableSection')
        ->assertSet('availableCollapsed', true)
        ->call('toggleAvailableSection')
        ->assertSet('availableCollapsed', false);
});

it('toggleUpdatesSection flips updatesCollapsed', function (): void {
    Livewire::test(InstalledPackages::class)
        ->assertSet('updatesCollapsed', false)
        ->call('toggleUpdatesSection')
        ->assertSet('updatesCollapsed', true)
        ->call('toggleUpdatesSection')
        ->assertSet('updatesCollapsed', false);
});

// ---------------------------------------------------------------------------
// installPackage()
// ---------------------------------------------------------------------------

it('installPackage dispatches install-started and begin-install events', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMyPackages')->andReturn([
            'data' => [
                [
                    'code' => 'tipowerup/ti-ext-darkmode',
                    'name' => 'Dark Mode',
                    'type' => 'extension',
                ],
            ],
        ]);
    });

    Livewire::test(InstalledPackages::class)
        ->call('installPackage', 'tipowerup/ti-ext-darkmode')
        ->assertDispatched('install-started')
        ->assertDispatched('begin-install', packageCode: 'tipowerup/ti-ext-darkmode', packageName: 'Dark Mode');
});

it('installPackage falls back to package code as name when not in availablePackages', function (): void {
    Livewire::test(InstalledPackages::class)
        ->call('installPackage', 'tipowerup/ti-ext-unknown')
        ->assertDispatched('begin-install', packageCode: 'tipowerup/ti-ext-unknown', packageName: 'tipowerup/ti-ext-unknown');
});

// ---------------------------------------------------------------------------
// updatePackage()
// ---------------------------------------------------------------------------

it('updatePackage dispatches install-started and begin-update events', function (): void {
    Livewire::test(InstalledPackages::class)
        ->call('updatePackage', 'tipowerup/ti-ext-darkmode')
        ->assertDispatched('install-started')
        ->assertDispatched('begin-update', packageCode: 'tipowerup/ti-ext-darkmode');
});

// ---------------------------------------------------------------------------
// confirmUninstall() / cancelConfirm()
// ---------------------------------------------------------------------------

it('confirmUninstall sets confirm state', function (): void {
    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-ext-loyalty', 'Loyalty Points'),
    ]);

    $component->call('confirmUninstall', 'tipowerup/ti-ext-loyalty')
        ->assertSet('confirmAction', 'uninstall')
        ->assertSet('confirmCode', 'tipowerup/ti-ext-loyalty')
        ->assertSet('confirmName', 'Loyalty Points');
});

it('confirmUninstall falls back to code as name when package not in installedPackages', function (): void {
    Livewire::test(InstalledPackages::class)
        ->call('confirmUninstall', 'tipowerup/ti-ext-unknown')
        ->assertSet('confirmAction', 'uninstall')
        ->assertSet('confirmCode', 'tipowerup/ti-ext-unknown')
        ->assertSet('confirmName', 'tipowerup/ti-ext-unknown');
});

it('cancelConfirm clears all confirm state', function (): void {
    Livewire::test(InstalledPackages::class)
        ->call('confirmUninstall', 'tipowerup/ti-ext-unknown')
        ->assertSet('confirmAction', 'uninstall')
        ->call('cancelConfirm')
        ->assertSet('confirmAction', null)
        ->assertSet('confirmCode', null)
        ->assertSet('confirmName', null);
});

// ---------------------------------------------------------------------------
// executeConfirmedAction()
// ---------------------------------------------------------------------------

it('executeConfirmedAction does nothing when confirmCode is null', function (): void {
    $this->mock(PackageInstaller::class, function ($mock): void {
        $mock->shouldNotReceive('uninstall');
    });

    Livewire::test(InstalledPackages::class)
        ->assertSet('confirmCode', null)
        ->call('executeConfirmedAction');
});

it('executeConfirmedAction calls uninstall and clears confirm state', function (): void {
    $this->mock(PackageInstaller::class, function ($mock): void {
        $mock->shouldReceive('uninstall')
            ->with('tipowerup/ti-ext-loyalty')
            ->once();
    });

    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-ext-loyalty', 'Loyalty Points'),
    ]);

    $component->call('confirmUninstall', 'tipowerup/ti-ext-loyalty')
        ->call('executeConfirmedAction')
        ->assertSet('confirmAction', null)
        ->assertSet('confirmCode', null)
        ->assertSet('confirmName', null);
});

// ---------------------------------------------------------------------------
// uninstallPackage()
// ---------------------------------------------------------------------------

it('uninstallPackage calls PackageInstaller::uninstall and reloads packages', function (): void {
    $this->mock(PackageInstaller::class, function ($mock): void {
        $mock->shouldReceive('uninstall')
            ->with('tipowerup/ti-ext-darkmode')
            ->once();
    });

    Livewire::test(InstalledPackages::class)
        ->call('uninstallPackage', 'tipowerup/ti-ext-darkmode')
        ->assertSet('errorMessage', null)
        ->assertSet('isLoading', false);
});

it('uninstallPackage sets errorMessage on failure', function (): void {
    $this->mock(PackageInstaller::class, function ($mock): void {
        $mock->shouldReceive('uninstall')
            ->andThrow(new RuntimeException('Uninstall failed'));
    });

    Livewire::test(InstalledPackages::class)
        ->call('uninstallPackage', 'tipowerup/ti-ext-darkmode')
        ->assertSet('errorMessage', 'Uninstall failed');
});

// ---------------------------------------------------------------------------
// enableExtension() / disableExtension()
// ---------------------------------------------------------------------------

it('enableExtension calls updateInstalledExtensions with true', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
        $mock->shouldReceive('findExtension')->andReturn(null);
        $mock->shouldReceive('isDisabled')->andReturn(false);
        $mock->shouldReceive('updateInstalledExtensions')
            ->with('tipowerup.darkmode', true)
            ->once();
    });

    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-ext-darkmode', 'Dark Mode', [
            'extension_code' => 'tipowerup.darkmode',
            'is_active' => false,
        ]),
    ]);

    // enableExtension redirects after success — just ensure no errorMessage
    $component->call('enableExtension', 'tipowerup/ti-ext-darkmode')
        ->assertSet('errorMessage', null);
});

it('enableExtension sets errorMessage on failure', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
        $mock->shouldReceive('findExtension')->andReturn(null);
        $mock->shouldReceive('isDisabled')->andReturn(false);
        $mock->shouldReceive('updateInstalledExtensions')
            ->andThrow(new RuntimeException('Enable failed'));
    });

    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-ext-darkmode', 'Dark Mode', [
            'extension_code' => 'tipowerup.darkmode',
            'is_active' => false,
        ]),
    ]);

    $component->call('enableExtension', 'tipowerup/ti-ext-darkmode')
        ->assertSet('errorMessage', 'Enable failed');
});

it('disableExtension calls updateInstalledExtensions with false', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
        $mock->shouldReceive('findExtension')->andReturn(null);
        $mock->shouldReceive('isDisabled')->andReturn(false);
        $mock->shouldReceive('updateInstalledExtensions')
            ->with('tipowerup.darkmode', false)
            ->once();
    });

    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-ext-darkmode', 'Dark Mode', [
            'extension_code' => 'tipowerup.darkmode',
        ]),
    ]);

    $component->call('disableExtension', 'tipowerup/ti-ext-darkmode')
        ->assertSet('errorMessage', null);
});

it('disableExtension sets errorMessage on failure', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
        $mock->shouldReceive('findExtension')->andReturn(null);
        $mock->shouldReceive('isDisabled')->andReturn(false);
        $mock->shouldReceive('updateInstalledExtensions')
            ->andThrow(new RuntimeException('Disable failed'));
    });

    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-ext-darkmode', 'Dark Mode', [
            'extension_code' => 'tipowerup.darkmode',
        ]),
    ]);

    $component->call('disableExtension', 'tipowerup/ti-ext-darkmode')
        ->assertSet('errorMessage', 'Disable failed');
});

// ---------------------------------------------------------------------------
// activateTheme()
// ---------------------------------------------------------------------------

it('activateTheme sets package is_active to true on success', function (): void {
    // Use an alias mock so the static Theme::activateTheme() call is intercepted.
    $aliasMock = Mockery::mock('alias:'.Theme::class);
    $aliasMock->shouldReceive('activateTheme')->with('tipowerup-orange-tw')->once();
    $aliasMock->shouldReceive('clearDefaultModel')->once();

    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-thm-orange', 'Orange Theme', [
            'theme_code' => 'tipowerup-orange-tw',
            'type' => 'theme',
            'icon' => 'fa-paint-brush',
            'is_active' => false,
        ]),
    ]);

    $component->call('activateTheme', 'tipowerup/ti-thm-orange')
        ->assertSet('errorMessage', null);

    $packages = $component->get('installedPackages');
    expect($packages[0]['is_active'])->toBeTrue();
});

it('activateTheme sets errorMessage when Theme::activateTheme throws', function (): void {
    // No matching theme row in DB — activateTheme will throw when TI can't find the theme.
    // We verify the component catches the exception and surfaces it via errorMessage.
    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-thm-nonexistent', 'Ghost Theme', [
            'theme_code' => 'tipowerup-nonexistent',
            'type' => 'theme',
            'icon' => 'fa-paint-brush',
            'is_active' => false,
        ]),
    ]);

    // Calling activateTheme will trigger Theme::activateTheme() which throws
    // because no real theme exists — the component must catch it.
    $component->call('activateTheme', 'tipowerup/ti-thm-nonexistent');

    expect($component->get('errorMessage'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// viewDetail()
// ---------------------------------------------------------------------------

it('viewDetail dispatches view-package-detail event to InstallerMain', function (): void {
    $component = Livewire::test(InstalledPackages::class);

    $component->set('installedPackages', [
        installedPackage('tipowerup/ti-ext-loyalty', 'Loyalty Points', [
            'description' => 'Earn points',
        ]),
    ]);

    $component->call('viewDetail', 'tipowerup/ti-ext-loyalty')
        ->assertDispatched('view-package-detail');
});

it('viewDetail dispatches event even for unknown package code', function (): void {
    Livewire::test(InstalledPackages::class)
        ->call('viewDetail', 'tipowerup/ti-ext-nonexistent')
        ->assertDispatched('view-package-detail');
});

// ---------------------------------------------------------------------------
// onInstallCompleted() — event listeners
// ---------------------------------------------------------------------------

it('reloads packages on install-completed event', function (): void {
    Livewire::test(InstalledPackages::class)
        ->dispatch('install-completed')
        ->assertSet('isLoading', false)
        ->assertSet('errorMessage', null);
});

it('reloads packages on api-key-changed event', function (): void {
    Livewire::test(InstalledPackages::class)
        ->dispatch('api-key-changed')
        ->assertSet('isLoading', false)
        ->assertSet('errorMessage', null);
});

it('clears errorMessage after api-key-changed when previous load had an error', function (): void {
    $callCount = 0;

    $this->mock(PowerUpApiClient::class, function ($mock) use (&$callCount): void {
        $mock->shouldReceive('getMyPackages')->andReturnUsing(function () use (&$callCount): array {
            $callCount++;
            if ($callCount === 1) {
                throw new RuntimeException('Bad key');
            }

            return ['data' => []];
        });
    });

    Livewire::test(InstalledPackages::class)
        ->assertSet('errorMessage', 'Bad key')
        ->dispatch('api-key-changed')
        ->assertSet('errorMessage', null);
});
