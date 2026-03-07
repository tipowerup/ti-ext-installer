<?php

declare(strict_types=1);

use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\Cache;
use Tipowerup\Installer\Services\CoreExtensionChecker;

beforeEach(function (): void {
    Cache::forget('tipowerup.core_extensions_check');
    $this->checker = new CoreExtensionChecker;
});

it('check returns results for all required extensions', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')->andReturn(true);
    });

    $results = $this->checker->check();

    expect($results)->toBeArray()
        ->and(count($results))->toBe(5);

    foreach ($results as $result) {
        expect($result)->toHaveKeys(['code', 'name', 'installed', 'manage_url']);
    }
});

it('getMissing returns only uninstalled extensions', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')
            ->with('igniter.cart')->andReturn(false);
        $mock->shouldReceive('hasExtension')
            ->with('igniter.user')->andReturn(true);
        $mock->shouldReceive('hasExtension')
            ->with('igniter.local')->andReturn(true);
        $mock->shouldReceive('hasExtension')
            ->with('igniter.pages')->andReturn(true);
        $mock->shouldReceive('hasExtension')
            ->with('igniter.frontend')->andReturn(false);
    });

    $missing = $this->checker->getMissing();

    expect($missing)->toHaveCount(2);
    $codes = array_column($missing, 'code');
    expect($codes)->toContain('igniter.cart')
        ->and($codes)->toContain('igniter.frontend');
});

it('allInstalled returns true when all extensions present', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')->andReturn(true);
    });

    expect($this->checker->allInstalled())->toBeTrue();
});

it('allInstalled returns false when any extension missing', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')->andReturn(false);
    });

    expect($this->checker->allInstalled())->toBeFalse();
});

it('caches check results', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')->andReturn(true);
    });

    $this->checker->check();

    expect(Cache::has('tipowerup.core_extensions_check'))->toBeTrue();
});

it('clearCache removes cached results', function (): void {
    $this->mock(ExtensionManager::class, function ($mock): void {
        $mock->shouldReceive('hasExtension')->andReturn(true);
    });

    $this->checker->check();
    $this->checker->clearCache();

    expect(Cache::has('tipowerup.core_extensions_check'))->toBeFalse();
});
