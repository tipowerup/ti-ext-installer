<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\ComposerInstaller;
use Tipowerup\Installer\Services\DirectInstaller;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\PackageInstaller;
use Tipowerup\Installer\Services\PowerUpApiClient;

it('extracts short name from package code correctly', function (): void {
    $hostingDetector = new HostingDetector;

    $apiClient = new class extends PowerUpApiClient
    {
        public function __construct()
        {
            // Skip parent constructor to avoid HTTP client initialization
        }
    };

    $directInstaller = new class extends DirectInstaller
    {
        public function __construct()
        {
            // Skip parent constructor to avoid dependencies
        }
    };

    $composerInstaller = new class extends ComposerInstaller
    {
        public function __construct()
        {
            // Skip parent constructor to avoid dependencies
        }
    };

    $installer = new PackageInstaller(
        $hostingDetector,
        $directInstaller,
        $composerInstaller,
        $apiClient
    );

    // Use reflection to test private method
    $reflection = new ReflectionClass($installer);
    $method = $reflection->getMethod('getShortName');

    expect($method->invoke($installer, 'tipowerup.loyaltypoints'))->toBe('loyaltypoints');
    expect($method->invoke($installer, 'vendor.package'))->toBe('package');
    expect($method->invoke($installer, 'single'))->toBe('single');
});

it('converts package code to composer package name correctly', function (): void {
    $hostingDetector = new HostingDetector;

    $apiClient = new class extends PowerUpApiClient
    {
        public function __construct()
        {
            // Skip parent constructor
        }
    };

    $directInstaller = new class extends DirectInstaller
    {
        public function __construct()
        {
            // Skip parent constructor
        }
    };

    $composerInstaller = new class extends ComposerInstaller
    {
        public function __construct()
        {
            // Skip parent constructor
        }
    };

    $installer = new PackageInstaller(
        $hostingDetector,
        $directInstaller,
        $composerInstaller,
        $apiClient
    );

    // Use reflection to test private method
    $reflection = new ReflectionClass($installer);
    $method = $reflection->getMethod('getComposerPackageName');

    expect($method->invoke($installer, 'tipowerup.loyaltypoints'))->toBe('tipowerup/loyaltypoints');
    expect($method->invoke($installer, 'vendor.package'))->toBe('vendor/package');
});

// Note: Full installation workflow tests require Laravel application context
// (Database, facades, models) which are not available in minimal test environment.
