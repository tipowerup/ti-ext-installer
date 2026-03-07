<?php

declare(strict_types=1);

use Igniter\System\Models\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\BackgroundUpdateChecker;
use Tipowerup\Installer\Services\PowerUpApiClient;

beforeEach(function (): void {
    $migrationPath = dirname(__DIR__, 3).'/database/migrations';
    $this->loadMigrationsFrom($migrationPath);
    Cache::flush();
    Log::spy();
});

afterEach(function (): void {
    Settings::setPref('tipowerup_api_key', '');
    Mockery::close();
});

it('returns throttled when cache key exists', function (): void {
    Cache::put('tipowerup_bg_update_check', true, 21600);

    $apiClient = Mockery::mock(PowerUpApiClient::class);
    $apiClient->shouldNotReceive('checkUpdates');

    $checker = new BackgroundUpdateChecker($apiClient);
    $response = $checker->handle();

    expect($response->getData(true)['status'])->toBe('throttled');
});

it('returns skipped when no API key configured', function (): void {
    // Default state: no API key set
    $apiClient = Mockery::mock(PowerUpApiClient::class);
    $apiClient->shouldNotReceive('checkUpdates');

    $checker = new BackgroundUpdateChecker($apiClient);
    $response = $checker->handle();

    expect($response->getData(true)['status'])->toBe('skipped')
        ->and($response->getData(true)['reason'])->toBe('no_api_key');
});

it('returns skipped when no active licenses exist', function (): void {
    Settings::setPref('tipowerup_api_key', 'test-key-123');

    $apiClient = Mockery::mock(PowerUpApiClient::class);
    $apiClient->shouldNotReceive('checkUpdates');

    $checker = new BackgroundUpdateChecker($apiClient);
    $response = $checker->handle();

    expect($response->getData(true)['status'])->toBe('skipped')
        ->and($response->getData(true)['reason'])->toBe('no_licenses');
});

it('calls API and returns checked when no updates found', function (): void {
    Settings::setPref('tipowerup_api_key', 'test-key-123');

    License::create([
        'package_code' => 'tipowerup/ti-ext-darkmode',
        'package_name' => 'Dark Mode',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    $apiClient = Mockery::mock(PowerUpApiClient::class);
    $apiClient->shouldReceive('setTimeout')->once()->andReturnSelf();
    $apiClient->shouldReceive('setMaxRetries')->once()->andReturnSelf();
    $apiClient->shouldReceive('checkUpdates')
        ->once()
        ->andReturn(['updates' => []]);

    $checker = new BackgroundUpdateChecker($apiClient);
    $response = $checker->handle();

    $data = $response->getData(true);
    expect($data['status'])->toBe('checked')
        ->and($data['updates'])->toBe(0);
});

it('returns error on API failure', function (): void {
    Settings::setPref('tipowerup_api_key', 'test-key-123');

    License::create([
        'package_code' => 'tipowerup/ti-ext-darkmode',
        'package_name' => 'Dark Mode',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    $apiClient = Mockery::mock(PowerUpApiClient::class);
    $apiClient->shouldReceive('setTimeout')->once()->andReturnSelf();
    $apiClient->shouldReceive('setMaxRetries')->once()->andReturnSelf();
    $apiClient->shouldReceive('checkUpdates')
        ->once()
        ->andThrow(new RuntimeException('Connection timeout'));

    $checker = new BackgroundUpdateChecker($apiClient);
    $response = $checker->handle();

    expect($response->getData(true)['status'])->toBe('error');

    Log::shouldHaveReceived('warning')->with(
        'PowerUp background update check failed',
        Mockery::type('array')
    );
});
