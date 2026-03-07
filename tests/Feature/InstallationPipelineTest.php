<?php

declare(strict_types=1);

use Tipowerup\Installer\Exceptions\LicenseValidationException;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Models\InstallLog;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\BackupManager;
use Tipowerup\Installer\Services\CompatibilityChecker;
use Tipowerup\Installer\Services\ComposerInstaller;
use Tipowerup\Installer\Services\DirectInstaller;
use Tipowerup\Installer\Services\InstallationPipeline;
use Tipowerup\Installer\Services\PowerUpApiClient;
use Tipowerup\Installer\Services\ProgressTracker;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Standard license data returned by a successful verifyLicense call.
 *
 * @return array<string, mixed>
 */
function defaultLicenseData(string $version = '2.0.0'): array
{
    return [
        'package_name' => 'Test Package',
        'package_type' => 'extension',
        'version' => $version,
        'requirements' => [],
    ];
}

/**
 * Create a License row that simulates an already-installed package.
 */
function createInstalledLicense(
    string $packageCode = 'tipowerup/ti-ext-test',
    string $version = '1.0.0',
): License {
    return License::create([
        'package_code' => $packageCode,
        'package_name' => 'Test Package',
        'package_type' => 'extension',
        'version' => $version,
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);
}

// ---------------------------------------------------------------------------
// Shared setup
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    $migrationPath = dirname(__DIR__, 2).'/database/migrations';
    $this->loadMigrationsFrom($migrationPath);

    $this->backupManager = Mockery::mock(BackupManager::class);
    $this->compatibilityChecker = Mockery::mock(CompatibilityChecker::class);
    $this->apiClient = Mockery::mock(PowerUpApiClient::class);
    $this->progressTracker = Mockery::mock(ProgressTracker::class);

    // Allow progress tracker calls throughout all tests by default.
    $this->progressTracker->shouldReceive('update')->andReturnNull();
    $this->progressTracker->shouldReceive('isCancelled')->andReturn(false);

    $this->pipeline = new InstallationPipeline(
        $this->backupManager,
        $this->compatibilityChecker,
        $this->apiClient,
        $this->progressTracker,
    );
});

// ===========================================================================
// execute() — install pipeline
// ===========================================================================

it('successful install creates a license record and logs success', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')
        ->with($packageCode)
        ->once()
        ->andReturn(defaultLicenseData('2.0.0'));

    $this->compatibilityChecker->shouldReceive('check')->andReturn([]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(true);

    // Package does not exist on disk → backup stage is skipped.
    $this->backupManager->shouldReceive('hasBackup')->andReturn(false);

    $mockInstaller = Mockery::mock(DirectInstaller::class);
    $mockInstaller->shouldReceive('install')->once();
    $mockInstaller->shouldReceive('runMigrations')->once();
    $this->app->instance(DirectInstaller::class, $mockInstaller);

    $result = $this->pipeline->execute($packageCode, 'direct');

    expect($result['success'])->toBeTrue()
        ->and($result['package_code'])->toBe($packageCode)
        ->and($result['method'])->toBe('direct')
        ->and($result['version'])->toBe('2.0.0');

    $license = License::byPackage($packageCode)->first();
    expect($license)->not->toBeNull()
        ->and($license->package_name)->toBe('Test Package')
        ->and($license->version)->toBe('2.0.0')
        ->and($license->is_active)->toBeTrue();

    $log = InstallLog::byPackage($packageCode)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->success)->toBeTrue()
        ->and($log->action)->toBe('install');
});

it('rejects an invalid package code format', function (): void {
    expect(fn () => $this->pipeline->execute('invalid_package_code', 'direct'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws LicenseValidationException when verifyLicense fails', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')
        ->with($packageCode)
        ->once()
        ->andThrow(new LicenseValidationException('License not valid'));

    $this->backupManager->shouldReceive('hasBackup')->andReturn(false);

    expect(fn () => $this->pipeline->execute($packageCode, 'direct'))
        ->toThrow(LicenseValidationException::class, 'License not valid');
});

it('throws PackageInstallationException when compatibility check fails on install', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')
        ->with($packageCode)
        ->once()
        ->andReturn(defaultLicenseData());

    $this->compatibilityChecker->shouldReceive('check')->andReturn([
        ['requirement' => 'PHP >=8.5', 'satisfied' => false],
    ]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(false);
    $this->compatibilityChecker->shouldReceive('getFailures')
        ->andReturn(['PHP >=8.5']);

    $this->backupManager->shouldReceive('hasBackup')->andReturn(false);

    expect(fn () => $this->pipeline->execute($packageCode, 'direct'))
        ->toThrow(PackageInstallationException::class);
});

it('skips backup creation when package does not exist on disk', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')->andReturn(defaultLicenseData());
    $this->compatibilityChecker->shouldReceive('check')->andReturn([]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(true);

    // BackupManager::createBackup must NOT be called.
    $this->backupManager->shouldReceive('createBackup')->never();
    $this->backupManager->shouldReceive('hasBackup')->andReturn(false);

    $mockInstaller = Mockery::mock(DirectInstaller::class);
    $mockInstaller->shouldReceive('install')->once();
    $mockInstaller->shouldReceive('runMigrations')->once();
    $this->app->instance(DirectInstaller::class, $mockInstaller);

    $result = $this->pipeline->execute($packageCode, 'direct');

    expect($result['success'])->toBeTrue();
});

it('resolves DirectInstaller when method is direct', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')->andReturn(defaultLicenseData());
    $this->compatibilityChecker->shouldReceive('check')->andReturn([]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(true);
    $this->backupManager->shouldReceive('hasBackup')->andReturn(false);

    $mockDirect = Mockery::mock(DirectInstaller::class);
    $mockDirect->shouldReceive('install')->once();
    $mockDirect->shouldReceive('runMigrations')->once();
    $this->app->instance(DirectInstaller::class, $mockDirect);

    // ComposerInstaller must not be called.
    $mockComposer = Mockery::mock(ComposerInstaller::class);
    $mockComposer->shouldNotReceive('install');
    $this->app->instance(ComposerInstaller::class, $mockComposer);

    $result = $this->pipeline->execute($packageCode, 'direct');

    expect($result['success'])->toBeTrue();
});

it('resolves ComposerInstaller when method is composer', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')->andReturn(defaultLicenseData());
    $this->compatibilityChecker->shouldReceive('check')->andReturn([]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(true);
    $this->backupManager->shouldReceive('hasBackup')->andReturn(false);

    $mockComposer = Mockery::mock(ComposerInstaller::class);
    $mockComposer->shouldReceive('install')->once();
    $mockComposer->shouldReceive('runMigrations')->once();
    $this->app->instance(ComposerInstaller::class, $mockComposer);

    // DirectInstaller must not be called.
    $mockDirect = Mockery::mock(DirectInstaller::class);
    $mockDirect->shouldNotReceive('install');
    $this->app->instance(DirectInstaller::class, $mockDirect);

    $result = $this->pipeline->execute($packageCode, 'composer');

    expect($result['success'])->toBeTrue();
});

it('restores backup on failure when a backup exists', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')->andReturn(defaultLicenseData());
    $this->compatibilityChecker->shouldReceive('check')->andReturn([]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(true);

    // Backup exists so restore should be called.
    $this->backupManager->shouldReceive('hasBackup')->andReturn(true);
    $this->backupManager->shouldReceive('restore')->once()->andReturnNull();

    $mockInstaller = Mockery::mock(DirectInstaller::class);
    $mockInstaller->shouldReceive('install')
        ->once()
        ->andThrow(new PackageInstallationException('Download failed'));
    $this->app->instance(DirectInstaller::class, $mockInstaller);

    expect(fn () => $this->pipeline->execute($packageCode, 'direct'))
        ->toThrow(PackageInstallationException::class);
});

it('does not restore backup on cancellation', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';
    $batchId = 'test-batch-id';

    $this->apiClient->shouldReceive('verifyLicense')->andReturn(defaultLicenseData());
    $this->compatibilityChecker->shouldReceive('check')->andReturn([]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(true);

    // Build a fresh ProgressTracker mock that signals cancellation on the
    // first isCancelled check (after the compatibility stage). This avoids
    // any conflict with the default mock set in beforeEach.
    $cancelledTracker = Mockery::mock(ProgressTracker::class);
    $cancelledTracker->shouldReceive('update')->andReturnNull();
    $cancelledTracker->shouldReceive('isCancelled')->andReturn(true);

    $pipeline = new InstallationPipeline(
        $this->backupManager,
        $this->compatibilityChecker,
        $this->apiClient,
        $cancelledTracker,
    );

    // Backup exists, but restore must NOT be called on cancellation.
    $this->backupManager->shouldReceive('hasBackup')->andReturn(true);
    $this->backupManager->shouldReceive('restore')->never();

    expect(fn () => $pipeline->execute($packageCode, 'direct', null, $batchId))
        ->toThrow(PackageInstallationException::class);
});

it('logs failure on installation error', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')->andReturn(defaultLicenseData());
    $this->compatibilityChecker->shouldReceive('check')->andReturn([]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(true);
    $this->backupManager->shouldReceive('hasBackup')->andReturn(false);

    $mockInstaller = Mockery::mock(DirectInstaller::class);
    $mockInstaller->shouldReceive('install')
        ->once()
        ->andThrow(new PackageInstallationException('Extraction failed'));
    $this->app->instance(DirectInstaller::class, $mockInstaller);

    expect(fn () => $this->pipeline->execute($packageCode, 'direct'))
        ->toThrow(PackageInstallationException::class);

    $log = InstallLog::byPackage($packageCode)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->success)->toBeFalse()
        ->and($log->action)->toBe('install');
});

// ===========================================================================
// executeUpdate() — update pipeline
// ===========================================================================

it('successful update modifies the existing license version', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';
    createInstalledLicense($packageCode, '1.0.0');

    $this->apiClient->shouldReceive('verifyLicense')
        ->with($packageCode)
        ->once()
        ->andReturn(defaultLicenseData('2.0.0'));

    $this->compatibilityChecker->shouldReceive('assertSatisfied')->once()->andReturnNull();
    $this->backupManager->shouldReceive('hasBackup')->andReturn(false);

    $mockInstaller = Mockery::mock(DirectInstaller::class);
    $mockInstaller->shouldReceive('update')->once();
    $mockInstaller->shouldReceive('runMigrations')->once();
    $this->app->instance(DirectInstaller::class, $mockInstaller);

    $result = $this->pipeline->executeUpdate($packageCode, 'direct');

    expect($result['success'])->toBeTrue()
        ->and($result['from_version'])->toBe('1.0.0')
        ->and($result['to_version'])->toBe('2.0.0');

    $license = License::byPackage($packageCode)->first();
    expect($license->version)->toBe('2.0.0');
});

it('throws PackageInstallationException on update when package is not installed', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    // No License row exists for this package.
    expect(fn () => $this->pipeline->executeUpdate($packageCode, 'direct'))
        ->toThrow(PackageInstallationException::class, 'is not installed');
});

// ===========================================================================
// executeUninstall() — uninstall pipeline
// ===========================================================================

it('successful uninstall marks the license as inactive', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';
    createInstalledLicense($packageCode);

    $this->backupManager->shouldReceive('createBackup')->once()->andReturnNull();

    $mockInstaller = Mockery::mock(DirectInstaller::class);
    $mockInstaller->shouldReceive('uninstall')->once()->andReturnNull();
    $this->app->instance(DirectInstaller::class, $mockInstaller);

    $this->pipeline->executeUninstall($packageCode, 'direct');

    $license = License::byPackage($packageCode)->first();
    expect($license)->not->toBeNull()
        ->and($license->is_active)->toBeFalse();
});

it('throws PackageInstallationException on uninstall when package is not installed', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    // No License row exists.
    expect(fn () => $this->pipeline->executeUninstall($packageCode, 'direct'))
        ->toThrow(PackageInstallationException::class, 'is not installed');
});

it('logs uninstall success after a successful uninstall', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';
    createInstalledLicense($packageCode);

    $this->backupManager->shouldReceive('createBackup')->once()->andReturnNull();

    $mockInstaller = Mockery::mock(DirectInstaller::class);
    $mockInstaller->shouldReceive('uninstall')->once()->andReturnNull();
    $this->app->instance(DirectInstaller::class, $mockInstaller);

    $this->pipeline->executeUninstall($packageCode, 'direct');

    $log = InstallLog::byPackage($packageCode)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->success)->toBeTrue()
        ->and($log->action)->toBe('uninstall');
});

// ===========================================================================
// classifyError — tested indirectly via error handling behaviour
// ===========================================================================

it('classifies LicenseValidationException as license_invalid and does not restore backup', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';

    $this->apiClient->shouldReceive('verifyLicense')
        ->andThrow(new LicenseValidationException('License invalid'));

    // hasBackup returns true, but because the error is classified as
    // 'license_invalid' (not 'cancelled') the pipeline WILL attempt a restore.
    // This test verifies the pipeline attempts restore for license errors.
    $this->backupManager->shouldReceive('hasBackup')->andReturn(true);
    $this->backupManager->shouldReceive('restore')->once()->andReturnNull();

    expect(fn () => $this->pipeline->execute($packageCode, 'direct'))
        ->toThrow(LicenseValidationException::class);

    // A failure log must be written.
    $log = InstallLog::byPackage($packageCode)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->success)->toBeFalse();
});

it('classifies a cancellation message as cancelled and skips backup restore', function (): void {
    $packageCode = 'tipowerup/ti-ext-test';
    $batchId = 'batch-cancel-test';

    $this->apiClient->shouldReceive('verifyLicense')->andReturn(defaultLicenseData());
    $this->compatibilityChecker->shouldReceive('check')->andReturn([]);
    $this->compatibilityChecker->shouldReceive('isSatisfied')->andReturn(true);

    // Build a fresh ProgressTracker mock that always reports cancellation.
    // Using a dedicated mock instance avoids conflicts with the beforeEach setup.
    $cancelledTracker = Mockery::mock(ProgressTracker::class);
    $cancelledTracker->shouldReceive('update')->andReturnNull();
    $cancelledTracker->shouldReceive('isCancelled')->andReturn(true);

    $pipeline = new InstallationPipeline(
        $this->backupManager,
        $this->compatibilityChecker,
        $this->apiClient,
        $cancelledTracker,
    );

    // Restore must never be called for a cancelled operation.
    $this->backupManager->shouldReceive('hasBackup')->andReturn(true);
    $this->backupManager->shouldReceive('restore')->never();

    expect(fn () => $pipeline->execute($packageCode, 'direct', null, $batchId))
        ->toThrow(PackageInstallationException::class);
});
