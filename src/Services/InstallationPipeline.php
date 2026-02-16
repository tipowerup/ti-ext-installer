<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Models\InstallLog;
use Tipowerup\Installer\Models\License;

class InstallationPipeline
{
    public function __construct(
        private readonly BackupManager $backupManager,
        private readonly CompatibilityChecker $compatibilityChecker,
        private readonly PowerUpApiClient $apiClient,
    ) {}

    /**
     * Execute the full installation pipeline for a single package.
     * Returns progress updates via a callback.
     */
    public function execute(
        string $packageCode,
        string $method,
        ?callable $onProgress = null,
    ): array {
        $this->validatePackageCode($packageCode);

        $batchId = (string) Str::uuid();
        $startTime = microtime(true);
        $onProgress ??= function (string $stage, int $percent, string $message): void {};

        try {
            // PREPARE (0-10%)
            $onProgress('preparing', 0, 'Starting installation...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 0, 'Starting installation...');

            $onProgress('preparing', 5, 'Verifying license...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 5, 'Verifying license...');
            $licenseData = $this->apiClient->verifyLicense($packageCode);

            if (!($licenseData['valid'] ?? false)) {
                throw PackageInstallationException::downloadFailed(
                    $packageCode,
                    'License validation failed'
                );
            }

            $packageRequirements = $licenseData['requirements'] ?? [];
            $packageName = $licenseData['package_name'] ?? $packageCode;
            $packageType = $licenseData['package_type'] ?? 'extension';
            $version = $licenseData['version'] ?? '0.0.0';

            // COMPATIBILITY (10-20%)
            $onProgress('compatibility', 10, 'Checking system compatibility...');
            $this->updateProgress($batchId, $packageCode, 'compatibility', 10, 'Checking system compatibility...');

            $compatResults = $this->compatibilityChecker->check($packageCode, $packageRequirements);
            $isSatisfied = $this->compatibilityChecker->isSatisfied($packageCode, $packageRequirements);

            if (!$isSatisfied) {
                $failures = $this->compatibilityChecker->getFailures($compatResults);
                $this->logFailure($packageCode, 'install', $method, 'Compatibility check failed', $startTime);

                throw PackageInstallationException::downloadFailed(
                    $packageCode,
                    'Compatibility check failed: '.implode(', ', $failures)
                );
            }

            $onProgress('compatibility', 20, 'Compatibility check passed');
            $this->updateProgress($batchId, $packageCode, 'compatibility', 20, 'Compatibility check passed');

            // BACKUP (20-30%) - Skip for fresh install
            $existingLicense = License::byPackage($packageCode)->first();
            if ($existingLicense) {
                $onProgress('backup', 20, 'Creating backup...');
                $this->updateProgress($batchId, $packageCode, 'backup', 20, 'Creating backup...');

                $this->backupManager->createBackup($packageCode);

                $onProgress('backup', 30, 'Backup created');
                $this->updateProgress($batchId, $packageCode, 'backup', 30, 'Backup created');
            } else {
                $onProgress('backup', 30, 'Skipping backup (fresh install)');
                $this->updateProgress($batchId, $packageCode, 'backup', 30, 'Skipping backup (fresh install)');
            }

            // DOWNLOAD/INSTALL (30-70%)
            $onProgress('installing', 30, 'Starting installation...');
            $this->updateProgress($batchId, $packageCode, 'installing', 30, 'Starting installation...');

            $installer = $method === 'composer' ? resolve(ComposerInstaller::class) : resolve(DirectInstaller::class);

            // Execute installation with progress callback
            $installResult = $installer->install($packageCode, function (int $percent, string $message) use ($batchId, $packageCode, $onProgress): void {
                $adjustedPercent = 30 + (int) ($percent * 0.4); // Map 0-100 to 30-70
                $onProgress('installing', $adjustedPercent, $message);
                $this->updateProgress($batchId, $packageCode, 'installing', $adjustedPercent, $message);
            });

            $onProgress('installing', 70, 'Installation completed');
            $this->updateProgress($batchId, $packageCode, 'installing', 70, 'Installation completed');

            // MIGRATE (70-85%)
            $onProgress('migrating', 70, 'Running migrations...');
            $this->updateProgress($batchId, $packageCode, 'migrating', 70, 'Running migrations...');

            try {
                // Migrations are typically handled by the installer
                // But we can trigger them explicitly if needed
                if (method_exists($installer, 'runMigrations')) {
                    $installer->runMigrations($packageCode);
                }

                $onProgress('migrating', 85, 'Migrations completed');
                $this->updateProgress($batchId, $packageCode, 'migrating', 85, 'Migrations completed');
            } catch (Throwable $e) {
                throw PackageInstallationException::migrationFailed($packageCode, $e->getMessage());
            }

            // FINALIZE (85-100%)
            $onProgress('finalizing', 85, 'Finalizing installation...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 85, 'Finalizing installation...');

            // Clear caches
            $this->clearCaches();

            $onProgress('finalizing', 90, 'Updating license records...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 90, 'Updating license records...');

            // Update or create License model
            License::updateOrCreate(
                ['package_code' => $packageCode],
                [
                    'package_name' => $packageName,
                    'package_type' => $packageType,
                    'version' => $version,
                    'install_method' => $method,
                    'license_hash' => $licenseData['license_hash'] ?? null,
                    'installed_at' => $existingLicense?->installed_at ?? now(),
                    'updated_at' => now(),
                    'expires_at' => isset($licenseData['expires_at']) ? new DateTime($licenseData['expires_at']) : null,
                    'is_active' => true,
                ]
            );

            $onProgress('finalizing', 95, 'Logging installation...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 95, 'Logging installation...');

            // Log success
            $this->logSuccess(
                $packageCode,
                'install',
                $method,
                $existingLicense?->version,
                $version,
                $startTime
            );

            $onProgress('complete', 100, 'Installation complete!');
            $this->updateProgress($batchId, $packageCode, 'complete', 100, 'Installation complete!');

            return [
                'success' => true,
                'package_code' => $packageCode,
                'version' => $version,
                'method' => $method,
                'duration' => microtime(true) - $startTime,
            ];

        } catch (Throwable $e) {
            Log::error(sprintf("Installation failed for '%s'", $packageCode), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Attempt restore if backup exists
            if ($this->backupManager->hasBackup($packageCode)) {
                $onProgress('restoring', 0, 'Installation failed. Restoring backup...');
                $this->updateProgress($batchId, $packageCode, 'restoring', 0, 'Installation failed. Restoring backup...');

                try {
                    $this->backupManager->restore($packageCode);
                    $onProgress('restoring', 100, 'Backup restored');
                    $this->updateProgress($batchId, $packageCode, 'restoring', 100, 'Backup restored');
                } catch (Throwable $restoreError) {
                    Log::error(sprintf("Backup restore failed for '%s'", $packageCode), [
                        'error' => $restoreError->getMessage(),
                    ]);
                }
            }

            // Log failure
            $this->logFailure($packageCode, 'install', $method, $e->getMessage(), $startTime);

            $this->updateProgress($batchId, $packageCode, 'failed', 0, 'Installation failed', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Execute the full update pipeline.
     */
    public function executeUpdate(
        string $packageCode,
        string $method,
        ?callable $onProgress = null,
    ): array {
        $this->validatePackageCode($packageCode);

        $batchId = (string) Str::uuid();
        $startTime = microtime(true);
        $onProgress ??= function (string $stage, int $percent, string $message): void {};

        try {
            // Get current version
            $existingLicense = License::byPackage($packageCode)->first();

            if (!$existingLicense) {
                throw new PackageInstallationException(
                    sprintf("Package '%s' is not installed. Cannot update.", $packageCode)
                );
            }

            $fromVersion = $existingLicense->version;

            // PREPARE (0-10%)
            $onProgress('preparing', 0, 'Starting update...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 0, 'Starting update...');

            $onProgress('preparing', 5, 'Verifying license...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 5, 'Verifying license...');
            $licenseData = $this->apiClient->verifyLicense($packageCode);

            if (!($licenseData['valid'] ?? false)) {
                throw PackageInstallationException::downloadFailed(
                    $packageCode,
                    'License validation failed'
                );
            }

            $toVersion = $licenseData['version'] ?? '0.0.0';
            $packageRequirements = $licenseData['requirements'] ?? [];

            // COMPATIBILITY (10-20%)
            $onProgress('compatibility', 10, 'Checking system compatibility...');
            $this->updateProgress($batchId, $packageCode, 'compatibility', 10, 'Checking system compatibility...');

            $this->compatibilityChecker->assertSatisfied($packageCode, $packageRequirements);

            $onProgress('compatibility', 20, 'Compatibility check passed');
            $this->updateProgress($batchId, $packageCode, 'compatibility', 20, 'Compatibility check passed');

            // BACKUP (20-30%) - Always backup before update
            $onProgress('backup', 20, 'Creating backup...');
            $this->updateProgress($batchId, $packageCode, 'backup', 20, 'Creating backup...');

            $this->backupManager->createBackup($packageCode);

            $onProgress('backup', 30, 'Backup created');
            $this->updateProgress($batchId, $packageCode, 'backup', 30, 'Backup created');

            // UPDATE (30-70%)
            $onProgress('updating', 30, 'Starting update...');
            $this->updateProgress($batchId, $packageCode, 'updating', 30, 'Starting update...');

            $installer = $method === 'composer' ? resolve(ComposerInstaller::class) : resolve(DirectInstaller::class);

            // Execute update
            if (method_exists($installer, 'update')) {
                $installer->update($packageCode, function (int $percent, string $message) use ($batchId, $packageCode, $onProgress): void {
                    $adjustedPercent = 30 + (int) ($percent * 0.4);
                    $onProgress('updating', $adjustedPercent, $message);
                    $this->updateProgress($batchId, $packageCode, 'updating', $adjustedPercent, $message);
                });
            } else {
                // Fallback to install method
                $installer->install($packageCode, function (int $percent, string $message) use ($batchId, $packageCode, $onProgress): void {
                    $adjustedPercent = 30 + (int) ($percent * 0.4);
                    $onProgress('updating', $adjustedPercent, $message);
                    $this->updateProgress($batchId, $packageCode, 'updating', $adjustedPercent, $message);
                });
            }

            $onProgress('updating', 70, 'Update completed');
            $this->updateProgress($batchId, $packageCode, 'updating', 70, 'Update completed');

            // MIGRATE (70-85%)
            $onProgress('migrating', 70, 'Running migrations...');
            $this->updateProgress($batchId, $packageCode, 'migrating', 70, 'Running migrations...');

            try {
                if (method_exists($installer, 'runMigrations')) {
                    $installer->runMigrations($packageCode);
                }

                $onProgress('migrating', 85, 'Migrations completed');
                $this->updateProgress($batchId, $packageCode, 'migrating', 85, 'Migrations completed');
            } catch (Throwable $e) {
                throw PackageInstallationException::migrationFailed($packageCode, $e->getMessage());
            }

            // FINALIZE (85-100%)
            $onProgress('finalizing', 85, 'Finalizing update...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 85, 'Finalizing update...');

            $this->clearCaches();

            $onProgress('finalizing', 90, 'Updating license records...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 90, 'Updating license records...');

            // Update License model
            $existingLicense->update([
                'version' => $toVersion,
                'updated_at' => now(),
            ]);

            $onProgress('finalizing', 95, 'Logging update...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 95, 'Logging update...');

            // Log success
            $this->logSuccess($packageCode, 'update', $method, $fromVersion, $toVersion, $startTime);

            $onProgress('complete', 100, 'Update complete!');
            $this->updateProgress($batchId, $packageCode, 'complete', 100, 'Update complete!');

            return [
                'success' => true,
                'package_code' => $packageCode,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'method' => $method,
                'duration' => microtime(true) - $startTime,
            ];

        } catch (Throwable $e) {
            Log::error(sprintf("Update failed for '%s'", $packageCode), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Attempt restore from backup
            if ($this->backupManager->hasBackup($packageCode)) {
                $onProgress('restoring', 0, 'Update failed. Restoring backup...');
                $this->updateProgress($batchId, $packageCode, 'restoring', 0, 'Update failed. Restoring backup...');

                try {
                    $this->backupManager->restore($packageCode);
                    $onProgress('restoring', 100, 'Backup restored');
                    $this->updateProgress($batchId, $packageCode, 'restoring', 100, 'Backup restored');
                } catch (Throwable $restoreError) {
                    Log::error(sprintf("Backup restore failed for '%s'", $packageCode), [
                        'error' => $restoreError->getMessage(),
                    ]);
                }
            }

            // Log failure
            $this->logFailure($packageCode, 'update', $method, $e->getMessage(), $startTime);

            $this->updateProgress($batchId, $packageCode, 'failed', 0, 'Update failed', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Execute uninstall pipeline.
     */
    public function executeUninstall(
        string $packageCode,
        string $method,
    ): void {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            // Get existing license
            $existingLicense = License::byPackage($packageCode)->first();

            if (!$existingLicense) {
                throw new PackageInstallationException(
                    sprintf("Package '%s' is not installed. Cannot uninstall.", $packageCode)
                );
            }

            Log::info(sprintf("Starting uninstallation for '%s'", $packageCode));

            // Create backup (safety net)
            $this->backupManager->createBackup($packageCode);

            // Get installer
            $installer = $method === 'composer' ? resolve(ComposerInstaller::class) : resolve(DirectInstaller::class);

            // Run uninstall
            if (method_exists($installer, 'uninstall')) {
                $installer->uninstall($packageCode);
            }

            // Update License model (mark as inactive)
            $existingLicense->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            // Clear caches
            $this->clearCaches();

            // Log success
            $this->logSuccess($packageCode, 'uninstall', $method, $existingLicense->version, null, $startTime);

            Log::info(sprintf("Uninstallation completed for '%s'", $packageCode));

        } catch (Throwable $e) {
            Log::error(sprintf("Uninstallation failed for '%s'", $packageCode), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Log failure
            $this->logFailure($packageCode, 'uninstall', $method, $e->getMessage(), $startTime);

            throw new PackageInstallationException(
                sprintf("Failed to uninstall package '%s': %s", $packageCode, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Validate package code format.
     */
    private function validatePackageCode(string $packageCode): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*\.[a-z][a-z0-9]*$/i', $packageCode)) {
            throw new InvalidArgumentException(
                sprintf("Invalid package code format: '%s'", $packageCode)
            );
        }
    }

    /**
     * Update installation progress in database.
     */
    private function updateProgress(
        string $batchId,
        string $packageCode,
        string $stage,
        int $percent,
        string $message,
        ?string $error = null
    ): void {
        DB::table('tipowerup_install_progress')->updateOrInsert(
            [
                'batch_id' => $batchId,
                'package_code' => $packageCode,
            ],
            [
                'stage' => $stage,
                'progress_percent' => $percent,
                'message' => $message,
                'error' => $error,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Log successful action.
     */
    private function logSuccess(
        string $packageCode,
        string $action,
        string $method,
        ?string $fromVersion,
        ?string $toVersion,
        float $startTime
    ): void {
        InstallLog::logAction($packageCode, $action, $method, [
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'success' => true,
            'duration_seconds' => (int) (microtime(true) - $startTime),
        ]);
    }

    /**
     * Log failed action.
     */
    private function logFailure(
        string $packageCode,
        string $action,
        string $method,
        string $errorMessage,
        float $startTime
    ): void {
        InstallLog::logAction($packageCode, $action, $method, [
            'success' => false,
            'error_message' => $errorMessage,
            'duration_seconds' => (int) (microtime(true) - $startTime),
        ]);
    }

    /**
     * Clear application caches.
     */
    private function clearCaches(): void
    {
        try {
            if (function_exists('artisan')) {
                artisan('cache:clear');
                artisan('config:clear');
                artisan('route:clear');
                artisan('view:clear');
            }
        } catch (Throwable $e) {
            Log::warning('Failed to clear caches', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
