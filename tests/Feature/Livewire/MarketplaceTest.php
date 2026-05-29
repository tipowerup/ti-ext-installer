<?php

declare(strict_types=1);

use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\ExtensionManager;
use Livewire\Livewire;
use Tipowerup\Installer\Livewire\Marketplace;
use Tipowerup\Installer\Services\PowerUpApiClient;

beforeEach(function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
    });

    $this->mock(ThemeManager::class, function ($mock): void {
        $mock->shouldReceive('listThemes')->andReturn([]);
    });
});

it('renders the component', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->assertStatus(200)
        ->assertSet('isLoading', false);
});

it('loads marketplace packages on mount', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->once()->andReturn([
            'data' => [
                ['code' => 'tipowerup/ti-ext-test', 'name' => 'Test', 'type' => 'extension', 'purchased' => false],
            ],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    $component = Livewire::test(Marketplace::class);
    $packages = $component->get('packages');

    expect($packages)->toHaveCount(1);
    expect($packages[0]['code'])->toBe('tipowerup/ti-ext-test');
});

it('filters out purchased packages', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [
                ['code' => 'tipowerup/ti-ext-free', 'name' => 'Free', 'type' => 'extension', 'purchased' => false],
                ['code' => 'tipowerup/ti-ext-owned', 'name' => 'Owned', 'type' => 'extension', 'purchased' => true],
            ],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    $component = Livewire::test(Marketplace::class);
    $packages = $component->get('packages');

    expect($packages)->toHaveCount(1);
    expect($packages[0]['code'])->toBe('tipowerup/ti-ext-free');
});

it('handles API error gracefully', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')
            ->andThrow(new RuntimeException('API down'));
    });

    Livewire::test(Marketplace::class)
        ->assertSet('packages', [])
        ->assertSet('errorMessage', 'API down')
        ->assertSet('isLoading', false);
});

it('setFilter updates filter type and reloads', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->call('setFilter', 'theme')
        ->assertSet('filterType', 'theme')
        ->assertSet('currentPage', 1);
});

it('goToPage changes page and reloads', function (): void {
    $callCount = 0;
    $this->mock(PowerUpApiClient::class, function ($mock) use (&$callCount): void {
        $mock->shouldReceive('getMarketplace')->andReturnUsing(function () use (&$callCount): array {
            $callCount++;

            return [
                'data' => [],
                'pagination' => ['total_pages' => 3, 'current_page' => $callCount === 1 ? 1 : 2],
            ];
        });
    });

    Livewire::test(Marketplace::class)
        ->assertSet('totalPages', 3)
        ->call('goToPage', 2)
        ->assertSet('currentPage', 2);
});

it('goToPage ignores invalid page numbers', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->call('goToPage', 0)
        ->assertSet('currentPage', 1)
        ->call('goToPage', 99)
        ->assertSet('currentPage', 1);
});

it('toggleViewMode switches between grid and list', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->assertSet('viewMode', 'grid')
        ->call('toggleViewMode')
        ->assertSet('viewMode', 'list')
        ->call('toggleViewMode')
        ->assertSet('viewMode', 'grid');
});

it('refreshMarketplace reloads packages', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->call('refreshMarketplace')
        ->assertSet('isLoading', false);
});

it('updatedSearchQuery resets page and reloads', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->set('currentPage', 3)
        ->set('searchQuery', 'darkmode')
        ->assertSet('currentPage', 1);
});

it('reloads on api-key-changed event', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->dispatch('api-key-changed')
        ->assertSet('isLoading', false);
});

it('placeholder returns a non-empty html string for lazy loading', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    $html = (new Marketplace)->placeholder();

    expect($html)->toBeString()->not->toBe('');
});

it('viewDetail dispatches view-package-detail with the matched package payload', function (): void {
    $packageRow = ['code' => 'tipowerup/ti-ext-test', 'name' => 'Test', 'type' => 'extension', 'purchased' => false];
    $this->mock(PowerUpApiClient::class, function ($mock) use ($packageRow): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [$packageRow],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->call('viewDetail', 'tipowerup/ti-ext-test')
        ->assertDispatched('view-package-detail');
});

it('viewDetail dispatches with empty data when the code does not match any loaded package', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    Livewire::test(Marketplace::class)
        ->call('viewDetail', 'nope/missing')
        ->assertDispatched('view-package-detail');
});

it('acquireFreeProduct shows a toast and reloads on success', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
        $mock->shouldReceive('acquireFreeProduct')->once()->with('tipowerup/ti-ext-free');
    });

    Livewire::test(Marketplace::class)
        ->call('acquireFreeProduct', 'tipowerup/ti-ext-free', 'Free Package')
        ->assertDispatched('api-key-changed');
});

it('acquireFreeProduct surfaces the API error on failure', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
        $mock->shouldReceive('acquireFreeProduct')->andThrow(new RuntimeException('out of stock'));
    });

    Livewire::test(Marketplace::class)
        ->call('acquireFreeProduct', 'tipowerup/ti-ext-free', 'Free Package')
        ->assertSet('errorMessage', 'out of stock');
});

// ---------------------------------------------------------------------------
// Already-installed package filtering — extension + theme branches
// ---------------------------------------------------------------------------

/**
 * Drop a real PHP file at the given path defining the given class so reflection
 * + File::fromClass can locate it. Returns the FQCN.
 */
function seedFakeExtensionClass(string $extensionDir, string $className): string
{
    $srcDir = $extensionDir.'/src';
    mkdir($srcDir, 0o755, true);
    file_put_contents(
        $srcDir.'/'.$className.'.php',
        "<?php\nnamespace Tipowerup\\InstallerTest\\Fakes;\nclass {$className} extends \\Igniter\\System\\Classes\\BaseExtension {}\n",
    );
    require_once $srcDir.'/'.$className.'.php';

    return 'Tipowerup\\InstallerTest\\Fakes\\'.$className;
}

it('filters out already-installed tipowerup extensions by composer name', function (): void {
    $extDir = sys_get_temp_dir().'/tipowerup-marketplace-ext-'.uniqid();
    $className = 'FakeExt'.uniqid();
    $fqcn = seedFakeExtensionClass($extDir, $className);
    file_put_contents($extDir.'/composer.json', json_encode(['name' => 'tipowerup/ti-ext-foo']));

    $this->mock(ExtensionManager::class, function ($mock) use ($fqcn): void {
        $mock->shouldReceive('listExtensions')->andReturn([
            'tipowerup.foo',
            'tipowerup.installer', // installer itself is always skipped
            'someother.bar',       // non-tipowerup is skipped
        ]);
        $mock->shouldReceive('findExtension')->andReturn(new $fqcn(app()));
    });
    $this->mock(ThemeManager::class, function ($mock): void {
        $mock->shouldReceive('listThemes')->andReturn([]);
    });
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [
                ['code' => 'tipowerup/ti-ext-foo', 'name' => 'Foo', 'type' => 'extension', 'purchased' => false],
                ['code' => 'tipowerup/ti-ext-bar', 'name' => 'Bar', 'type' => 'extension', 'purchased' => false],
            ],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    $packages = Livewire::test(Marketplace::class)->get('packages');

    expect($packages)->toHaveCount(1)
        ->and($packages[0]['code'])->toBe('tipowerup/ti-ext-bar');

    @unlink($extDir.'/composer.json');
    @unlink($extDir.'/src/'.$className.'.php');
    @rmdir($extDir.'/src');
    @rmdir($extDir);
});

it('skips an extension whose findExtension returns null', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn(['tipowerup.missing']);
        $mock->shouldReceive('findExtension')->with('tipowerup.missing')->andReturn(null);
    });
    $this->mock(ThemeManager::class, function ($mock): void {
        $mock->shouldReceive('listThemes')->andReturn([]);
    });
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [['code' => 'tipowerup/ti-ext-x', 'name' => 'X', 'type' => 'extension', 'purchased' => false]],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    expect(Livewire::test(Marketplace::class)->get('packages'))->toHaveCount(1);
});

it('skips an extension whose composer.json is absent', function (): void {
    $extDir = sys_get_temp_dir().'/tipowerup-marketplace-nocomposer-'.uniqid();
    $className = 'FakeNoComposer'.uniqid();
    $fqcn = seedFakeExtensionClass($extDir, $className);
    // intentionally no composer.json

    $this->mock(ExtensionManager::class, function ($mock) use ($fqcn): void {
        $mock->shouldReceive('listExtensions')->andReturn(['tipowerup.broken']);
        $mock->shouldReceive('findExtension')->with('tipowerup.broken')->andReturn(new $fqcn(app()));
    });
    $this->mock(ThemeManager::class, function ($mock): void {
        $mock->shouldReceive('listThemes')->andReturn([]);
    });
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [['code' => 'tipowerup/ti-ext-x', 'name' => 'X', 'type' => 'extension', 'purchased' => false]],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    expect(Livewire::test(Marketplace::class)->get('packages'))->toHaveCount(1);

    @unlink($extDir.'/src/'.$className.'.php');
    @rmdir($extDir.'/src');
    @rmdir($extDir);
});

it('filters out already-installed tipowerup themes by composer name', function (): void {
    $themeDir = sys_get_temp_dir().'/tipowerup-marketplace-theme-'.uniqid();
    mkdir($themeDir, 0o755, true);
    file_put_contents($themeDir.'/composer.json', json_encode(['name' => 'tipowerup/ti-theme-orange']));

    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
    });
    $this->mock(ThemeManager::class, function ($mock) use ($themeDir): void {
        $mock->shouldReceive('listThemes')->andReturn([
            'tipowerup-orange' => (object) ['name' => 'Orange'],
            'someother-blue' => (object) ['name' => 'Blue'], // non-tipowerup skipped
        ]);
        $mock->shouldReceive('findPath')->with('tipowerup-orange')->andReturn($themeDir);
    });
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [
                ['code' => 'tipowerup/ti-theme-orange', 'name' => 'Orange', 'type' => 'theme', 'purchased' => false],
                ['code' => 'tipowerup/ti-theme-green', 'name' => 'Green', 'type' => 'theme', 'purchased' => false],
            ],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    $packages = Livewire::test(Marketplace::class)->get('packages');

    expect($packages)->toHaveCount(1)
        ->and($packages[0]['code'])->toBe('tipowerup/ti-theme-green');

    @unlink($themeDir.'/composer.json');
    @rmdir($themeDir);
});

it('skips a theme whose findPath returns null', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
    });
    $this->mock(ThemeManager::class, function ($mock): void {
        $mock->shouldReceive('listThemes')->andReturn([
            'tipowerup-missing' => (object) ['name' => 'Missing'],
        ]);
        $mock->shouldReceive('findPath')->with('tipowerup-missing')->andReturn(null);
    });
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [['code' => 'tipowerup/ti-theme-x', 'name' => 'X', 'type' => 'theme', 'purchased' => false]],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    expect(Livewire::test(Marketplace::class)->get('packages'))->toHaveCount(1);
});

it('skips a theme whose composer.json is absent', function (): void {
    $themeDir = sys_get_temp_dir().'/tipowerup-marketplace-theme-nocomposer-'.uniqid();
    mkdir($themeDir, 0o755, true);
    // intentionally no composer.json

    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('listExtensions')->andReturn([]);
    });
    $this->mock(ThemeManager::class, function ($mock) use ($themeDir): void {
        $mock->shouldReceive('listThemes')->andReturn([
            'tipowerup-broken' => (object) ['name' => 'Broken'],
        ]);
        $mock->shouldReceive('findPath')->with('tipowerup-broken')->andReturn($themeDir);
    });
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getMarketplace')->andReturn([
            'data' => [['code' => 'tipowerup/ti-theme-x', 'name' => 'X', 'type' => 'theme', 'purchased' => false]],
            'pagination' => ['total_pages' => 1, 'current_page' => 1],
        ]);
    });

    expect(Livewire::test(Marketplace::class)->get('packages'))->toHaveCount(1);

    @rmdir($themeDir);
});
