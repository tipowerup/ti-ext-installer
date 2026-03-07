<?php

declare(strict_types=1);

use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\BatchInstaller;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\InstallationPipeline;
use Tipowerup\Installer\Services\PowerUpApiClient;
use Tipowerup\Installer\Services\ProgressTracker;

beforeEach(function (): void {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

    $this->pipeline = Mockery::mock(InstallationPipeline::class);
    $this->apiClient = Mockery::mock(PowerUpApiClient::class);
    $this->hostingDetector = Mockery::mock(HostingDetector::class);
    $this->progressTracker = new ProgressTracker;

    $this->batchInstaller = new BatchInstaller(
        $this->pipeline,
        $this->apiClient,
        $this->hostingDetector,
        $this->progressTracker,
    );
});

it('filterInstalled returns active license package codes', function (): void {
    License::create([
        'package_code' => 'tipowerup/ti-ext-alpha',
        'package_name' => 'Alpha',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    License::create([
        'package_code' => 'tipowerup/ti-ext-beta',
        'package_name' => 'Beta',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => false,
    ]);

    $installed = $this->batchInstaller->filterInstalled([
        'tipowerup/ti-ext-alpha',
        'tipowerup/ti-ext-beta',
        'tipowerup/ti-ext-gamma',
    ]);

    expect($installed)->toBe(['tipowerup/ti-ext-alpha']);
});

it('batchInstall returns already installed for all-installed packages', function (): void {
    License::create([
        'package_code' => 'tipowerup/ti-ext-alpha',
        'package_name' => 'Alpha',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    $results = $this->batchInstaller->batchInstall(['tipowerup/ti-ext-alpha']);

    expect($results)->toHaveKey('tipowerup/ti-ext-alpha')
        ->and($results['tipowerup/ti-ext-alpha']['success'])->toBeTrue()
        ->and($results['tipowerup/ti-ext-alpha']['error'])->toBe('Already installed');
});

it('batchInstall installs packages via pipeline', function (): void {
    $this->hostingDetector->shouldReceive('getRecommendedMethod')->andReturn('direct');

    $this->apiClient->shouldReceive('getPackageDetail')
        ->with('tipowerup/ti-ext-alpha')
        ->andReturn(['dependencies' => []]);

    $this->pipeline->shouldReceive('execute')
        ->with('tipowerup/ti-ext-alpha', 'direct', Mockery::type('callable'))
        ->once()
        ->andReturn(['success' => true, 'version' => '1.0.0']);

    $results = $this->batchInstaller->batchInstall(['tipowerup/ti-ext-alpha']);

    expect($results['tipowerup/ti-ext-alpha']['success'])->toBeTrue()
        ->and($results['tipowerup/ti-ext-alpha']['version'])->toBe('1.0.0');
});

it('batchInstall marks dependent packages as failed when dependency fails', function (): void {
    $this->hostingDetector->shouldReceive('getRecommendedMethod')->andReturn('direct');

    $this->apiClient->shouldReceive('getPackageDetail')
        ->with('tipowerup/ti-ext-alpha')
        ->andReturn(['dependencies' => []]);

    $this->apiClient->shouldReceive('getPackageDetail')
        ->with('tipowerup/ti-ext-beta')
        ->andReturn(['dependencies' => ['tipowerup/ti-ext-alpha']]);

    $this->pipeline->shouldReceive('execute')
        ->with('tipowerup/ti-ext-alpha', 'direct', Mockery::type('callable'))
        ->once()
        ->andThrow(new RuntimeException('Install failed'));

    $results = $this->batchInstaller->batchInstall([
        'tipowerup/ti-ext-alpha',
        'tipowerup/ti-ext-beta',
    ]);

    expect($results['tipowerup/ti-ext-alpha']['success'])->toBeFalse()
        ->and($results['tipowerup/ti-ext-beta']['success'])->toBeFalse()
        ->and($results['tipowerup/ti-ext-beta']['error'])->toBe('Dependency installation failed');
});

it('batchInstall calls onProgress callback', function (): void {
    $this->hostingDetector->shouldReceive('getRecommendedMethod')->andReturn('direct');

    $this->apiClient->shouldReceive('getPackageDetail')
        ->andReturn(['dependencies' => []]);

    $progressCalls = [];
    $this->pipeline->shouldReceive('execute')
        ->once()
        ->andReturnUsing(function (string $code, string $method, callable $onProgress) use (&$progressCalls): array {
            $onProgress('installing', 50, 'Downloading...');

            return ['success' => true, 'version' => '1.0.0'];
        });

    $this->batchInstaller->batchInstall(['tipowerup/ti-ext-alpha'], function (string $packageCode, string $stage, int $percent, string $message) use (&$progressCalls): void {
        $progressCalls[] = compact('packageCode', 'stage', 'percent', 'message');
    });

    expect($progressCalls)->toHaveCount(1)
        ->and($progressCalls[0]['packageCode'])->toBe('tipowerup/ti-ext-alpha')
        ->and($progressCalls[0]['stage'])->toBe('installing');
});

it('batchInstall includes already-installed packages in results with version', function (): void {
    License::create([
        'package_code' => 'tipowerup/ti-ext-alpha',
        'package_name' => 'Alpha',
        'package_type' => 'extension',
        'version' => '2.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    $this->hostingDetector->shouldReceive('getRecommendedMethod')->andReturn('direct');

    $this->apiClient->shouldReceive('getPackageDetail')
        ->with('tipowerup/ti-ext-beta')
        ->andReturn(['dependencies' => []]);

    $this->pipeline->shouldReceive('execute')
        ->with('tipowerup/ti-ext-beta', 'direct', Mockery::type('callable'))
        ->once()
        ->andReturn(['success' => true, 'version' => '1.0.0']);

    $results = $this->batchInstaller->batchInstall([
        'tipowerup/ti-ext-alpha',
        'tipowerup/ti-ext-beta',
    ]);

    expect($results['tipowerup/ti-ext-alpha']['success'])->toBeTrue()
        ->and($results['tipowerup/ti-ext-alpha']['version'])->toBe('2.0.0')
        ->and($results['tipowerup/ti-ext-beta']['success'])->toBeTrue();
});
