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
