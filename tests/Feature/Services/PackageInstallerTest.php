<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Models\InstallLog;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\ComposerInstaller;
use Tipowerup\Installer\Services\DirectInstaller;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\PackageInstaller;
use Tipowerup\Installer\Services\PowerUpApiClient;

beforeEach(function (): void {
    $migrationPath = dirname(__DIR__, 3).'/database/migrations';
    $this->loadMigrationsFrom($migrationPath);

    $this->apiClient = Mockery::mock(PowerUpApiClient::class);
    $this->hostingDetector = Mockery::mock(HostingDetector::class);
    $this->directInstaller = Mockery::mock(DirectInstaller::class);
    $this->composerInstaller = Mockery::mock(ComposerInstaller::class);

    $this->installer = new PackageInstaller(
        $this->hostingDetector,
        $this->directInstaller,
        $this->composerInstaller,
        $this->apiClient,
    );

    Log::spy();
});

afterEach(function (): void {
    Mockery::close();
});

// ===========================================================================
// describe: install
// ===========================================================================

describe('install', function (): void {

    it('delegates to DirectInstaller when method is direct', function (): void {
        $this->apiClient->shouldReceive('verifyLicense')
            ->once()
            ->with('tipowerup/ti-ext-darkmode')
            ->andReturn([
                'package_name' => 'Dark Mode',
                'package_type' => 'extension',
                'version' => '1.0.0',
            ]);

        $this->hostingDetector->shouldReceive('getRecommendedMethod')
            ->once()
            ->andReturn('direct');

        $this->directInstaller->shouldReceive('install')
            ->once()
            ->with('tipowerup/ti-ext-darkmode', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'method' => 'direct',
                'version' => '1.0.0',
            ]);

        $result = $this->installer->install('tipowerup/ti-ext-darkmode');

        expect($result['success'])->toBeTrue()
            ->and($result['package_code'])->toBe('tipowerup/ti-ext-darkmode');

        expect(License::byPackage('tipowerup/ti-ext-darkmode')->exists())->toBeTrue();
        expect(InstallLog::byPackage('tipowerup/ti-ext-darkmode')->exists())->toBeTrue();
    });

    it('delegates to ComposerInstaller when method is composer', function (): void {
        $this->apiClient->shouldReceive('verifyLicense')
            ->once()
            ->andReturn([
                'package_name' => 'Dark Mode',
                'package_type' => 'extension',
                'version' => '1.0.0',
            ]);

        $this->composerInstaller->shouldReceive('install')
            ->once()
            ->with('tipowerup/ti-ext-darkmode', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'method' => 'composer',
                'version' => '1.0.0',
            ]);

        $result = $this->installer->install('tipowerup/ti-ext-darkmode', 'composer');

        expect($result['success'])->toBeTrue();
    });

    it('uses forced method over auto-detected method', function (): void {
        $this->apiClient->shouldReceive('verifyLicense')
            ->once()
            ->andReturn([
                'package_name' => 'Dark Mode',
                'package_type' => 'extension',
                'version' => '1.0.0',
            ]);

        // hostingDetector should NOT be called when method is forced
        $this->hostingDetector->shouldNotReceive('getRecommendedMethod');

        $this->composerInstaller->shouldReceive('install')
            ->once()
            ->andReturn([
                'success' => true,
                'method' => 'composer',
                'version' => '1.0.0',
            ]);

        $result = $this->installer->install('tipowerup/ti-ext-darkmode', 'composer');

        expect($result['success'])->toBeTrue();
    });

    it('throws when package is already installed', function (): void {
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

        $this->hostingDetector->shouldReceive('getRecommendedMethod')
            ->andReturn('direct');

        expect(fn () => $this->installer->install('tipowerup/ti-ext-darkmode'))
            ->toThrow(PackageInstallationException::class, 'already installed');
    });

    it('logs failure on installation error', function (): void {
        $this->apiClient->shouldReceive('verifyLicense')
            ->once()
            ->andReturn([
                'package_name' => 'Dark Mode',
                'package_type' => 'extension',
                'version' => '1.0.0',
            ]);

        $this->hostingDetector->shouldReceive('getRecommendedMethod')
            ->andReturn('direct');

        $this->directInstaller->shouldReceive('install')
            ->once()
            ->andThrow(new PackageInstallationException('Download failed'));

        expect(fn () => $this->installer->install('tipowerup/ti-ext-darkmode'))
            ->toThrow(PackageInstallationException::class, 'Download failed');

        $log = InstallLog::byPackage('tipowerup/ti-ext-darkmode')->first();
        expect($log)->not->toBeNull()
            ->and($log->success)->toBeFalse();
    });

    it('throws on invalid package code format', function (): void {
        expect(fn () => $this->installer->install('invalid-format'))
            ->toThrow(InvalidArgumentException::class);
    });
});

// ===========================================================================
// describe: update
// ===========================================================================

describe('update', function (): void {

    it('delegates to DirectInstaller using license install_method', function (): void {
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

        $this->apiClient->shouldReceive('verifyLicense')
            ->once()
            ->andReturn([
                'package_name' => 'Dark Mode',
                'package_type' => 'extension',
                'version' => '2.0.0',
            ]);

        $this->directInstaller->shouldReceive('update')
            ->once()
            ->with('tipowerup/ti-ext-darkmode', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'method' => 'direct',
                'to_version' => '2.0.0',
            ]);

        $result = $this->installer->update('tipowerup/ti-ext-darkmode');

        expect($result['success'])->toBeTrue()
            ->and($result['package_code'])->toBe('tipowerup/ti-ext-darkmode');

        $license = License::byPackage('tipowerup/ti-ext-darkmode')->first();
        expect($license->version)->toBe('2.0.0');
    });

    it('delegates to ComposerInstaller when license method is composer', function (): void {
        License::create([
            'package_code' => 'tipowerup/ti-ext-darkmode',
            'package_name' => 'Dark Mode',
            'package_type' => 'extension',
            'version' => '1.0.0',
            'install_method' => 'composer',
            'installed_at' => now(),
            'updated_at' => now(),
            'is_active' => true,
        ]);

        $this->apiClient->shouldReceive('verifyLicense')
            ->once()
            ->andReturn(['version' => '2.0.0']);

        $this->composerInstaller->shouldReceive('update')
            ->once()
            ->with('tipowerup/ti-ext-darkmode')
            ->andReturn([
                'success' => true,
                'method' => 'composer',
                'to_version' => '2.0.0',
            ]);

        $result = $this->installer->update('tipowerup/ti-ext-darkmode');

        expect($result['success'])->toBeTrue();
    });

    it('throws when package is not installed', function (): void {
        $this->hostingDetector->shouldReceive('getRecommendedMethod')
            ->andReturn('direct');

        expect(fn () => $this->installer->update('tipowerup/ti-ext-darkmode'))
            ->toThrow(PackageInstallationException::class, 'not installed');
    });
});

// ===========================================================================
// describe: uninstall
// ===========================================================================

describe('uninstall', function (): void {

    it('uses license install_method when available', function (): void {
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

        $this->directInstaller->shouldReceive('uninstall')
            ->once()
            ->with('tipowerup/ti-ext-darkmode');

        $this->installer->uninstall('tipowerup/ti-ext-darkmode');

        expect(License::byPackage('tipowerup/ti-ext-darkmode')->exists())->toBeFalse();
    });

    it('defaults to direct when no license record exists', function (): void {
        $this->directInstaller->shouldReceive('uninstall')
            ->once()
            ->with('tipowerup/ti-ext-darkmode');

        $this->installer->uninstall('tipowerup/ti-ext-darkmode');
    });

    it('logs failure on uninstall error', function (): void {
        $this->directInstaller->shouldReceive('uninstall')
            ->once()
            ->andThrow(new PackageInstallationException('Uninstall failed'));

        expect(fn () => $this->installer->uninstall('tipowerup/ti-ext-darkmode'))
            ->toThrow(PackageInstallationException::class, 'Uninstall failed');

        $log = InstallLog::byPackage('tipowerup/ti-ext-darkmode')->first();
        expect($log)->not->toBeNull()
            ->and($log->success)->toBeFalse();
    });
});
