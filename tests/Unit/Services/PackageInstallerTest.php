<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\ComposerInstaller;
use Tipowerup\Installer\Services\DirectInstaller;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\PackageInstaller;
use Tipowerup\Installer\Services\PowerUpApiClient;

it('validates composer package code format correctly', function (): void {
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
    $method = $reflection->getMethod('validatePackageCode');

    // Valid composer-format codes should not throw
    $method->invoke($installer, 'tipowerup/ti-ext-darkmode');
    $method->invoke($installer, 'vendor/package');
    $method->invoke($installer, 'tipowerup/ti-ext-loyalty-points');

    expect(true)->toBeTrue(); // Reached here = no exception
});

it('rejects invalid package code formats', function (): void {
    $hostingDetector = new HostingDetector;

    $apiClient = new class extends PowerUpApiClient
    {
        public function __construct() {}
    };

    $directInstaller = new class extends DirectInstaller
    {
        public function __construct() {}
    };

    $composerInstaller = new class extends ComposerInstaller
    {
        public function __construct() {}
    };

    $installer = new PackageInstaller(
        $hostingDetector,
        $directInstaller,
        $composerInstaller,
        $apiClient
    );

    $reflection = new ReflectionClass($installer);
    $method = $reflection->getMethod('validatePackageCode');

    // Dot notation should be rejected
    expect(fn () => $method->invoke($installer, 'tipowerup.darkmode'))
        ->toThrow(InvalidArgumentException::class);

    // No separator should be rejected
    expect(fn () => $method->invoke($installer, 'invalid'))
        ->toThrow(InvalidArgumentException::class);

    // Empty string should be rejected
    expect(fn () => $method->invoke($installer, ''))
        ->toThrow(InvalidArgumentException::class);
});

// Note: Full installation workflow tests require Laravel application context
// (Database, facades, models) which are not available in minimal test environment.
