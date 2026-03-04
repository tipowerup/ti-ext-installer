<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use DateTime;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Tipowerup\Installer\Exceptions\BackupRestoreException;
use Tipowerup\Installer\Exceptions\CompatibilityException;
use Tipowerup\Installer\Exceptions\LicenseValidationException;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Models\InstallLog;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\Concerns\ClearsInstallerCaches;
use Tipowerup\Installer\Services\Concerns\ValidatesPackageCode;

class InstallationPipeline
{
    use ClearsInstallerCaches;
    use ValidatesPackageCode;

    private string $currentStage = 'preparing';

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
        ?string $batchId = null,
    ): array {
        $this->validatePackageCode($packageCode);

        $batchId ??= (string) Str::uuid();
        $startTime = microtime(true);
        $onProgress ??= function (string $stage, int $percent, string $message): void {};

        try {
            // PREPARE (0-10%)
            $this->currentStage = 'preparing';
            $onProgress('preparing', 0, 'Starting installation...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 0, 'Starting installation...');

            $onProgress('preparing', 5, 'Verifying license...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 5, 'Verifying license...');
            $licenseData = $this->apiClient->verifyLicense($packageCode);

            $packageRequirements = $licenseData['requirements'] ?? [];
            $packageName = $licenseData['package_name'] ?? $packageCode;
            $packageType = $licenseData['package_type'] ?? 'extension';
            $version = $licenseData['version'] ?? '0.0.0';

            $this->checkCancellation($batchId, $packageCode);

            // COMPATIBILITY (10-20%)
            $this->currentStage = 'compatibility';
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

            $this->checkCancellation($batchId, $packageCode);

            // BACKUP (20-30%) - Back up existing files if present on disk
            $this->currentStage = 'backup';
            if ($this->packageExistsOnDisk($packageCode)) {
                $onProgress('backup', 20, 'Creating backup...');
                $this->updateProgress($batchId, $packageCode, 'backup', 20, 'Creating backup...');

                $this->backupManager->createBackup($packageCode);

                $onProgress('backup', 30, 'Backup created');
                $this->updateProgress($batchId, $packageCode, 'backup', 30, 'Backup created');
            } else {
                $onProgress('backup', 30, 'Skipping backup (no existing files)');
                $this->updateProgress($batchId, $packageCode, 'backup', 30, 'Skipping backup (no existing files)');
            }

            $this->checkCancellation($batchId, $packageCode);

            // DOWNLOAD/INSTALL (30-70%)
            $this->currentStage = 'installing';
            $onProgress('installing', 30, 'Starting installation...');
            $this->updateProgress($batchId, $packageCode, 'installing', 30, 'Starting installation...');

            $installer = $method === 'composer' ? resolve(ComposerInstaller::class) : resolve(DirectInstaller::class);

            $composerProgress = function (int $percent, string $message) use ($batchId, $packageCode, $onProgress): void {
                $adjustedPercent = 30 + (int) ($percent * 0.4);
                $onProgress('installing', $adjustedPercent, $message);
                $this->updateProgress($batchId, $packageCode, 'installing', $adjustedPercent, $message);
            };

            // Execute installation with license data
            $installResult = $installer->install($packageCode, $licenseData, $composerProgress);

            $onProgress('installing', 70, 'Installation completed');
            $this->updateProgress($batchId, $packageCode, 'installing', 70, 'Installation completed');

            // MIGRATE (70-85%)
            $this->currentStage = 'migrating';
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
            $this->currentStage = 'finalizing';
            $onProgress('finalizing', 85, 'Finalizing installation...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 85, 'Finalizing installation...');

            // Clear caches
            $this->clearCaches();

            $onProgress('finalizing', 90, 'Updating license records...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 90, 'Updating license records...');

            // Update or create License model
            $existingLicense = License::byPackage($packageCode)->first();
            License::updateOrCreate(
                ['package_code' => $packageCode],
                [
                    'package_name' => $packageName,
                    'package_type' => $packageType,
                    'version' => $version,
                    'install_method' => $method,
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

            $errorCode = $this->classifyError($e);

            // Attempt backup restore (skip if user cancelled)
            if ($errorCode !== 'cancelled') {
                try {
                    if ($this->backupManager->hasBackup($packageCode)) {
                        $onProgress('restoring', 0, 'Installation failed. Restoring backup...');
                        $this->updateProgress($batchId, $packageCode, 'restoring', 0, 'Installation failed. Restoring backup...');

                        $this->backupManager->restore($packageCode);
                        $onProgress('restoring', 100, 'Backup restored');
                        $this->updateProgress($batchId, $packageCode, 'restoring', 100, 'Backup restored');
                    }
                } catch (Throwable $restoreError) {
                    Log::error(sprintf("Backup restore failed for '%s'", $packageCode), [
                        'error' => $restoreError->getMessage(),
                    ]);
                }
            }

            // Log failure
            $this->logFailure($packageCode, 'install', $method, $e->getMessage(), $startTime);

            $finalStage = $errorCode === 'cancelled' ? 'cancelled' : 'failed';
            $this->updateProgress($batchId, $packageCode, $finalStage, 0, 'Installation failed', $e->getMessage(), $errorCode, $this->currentStage);

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
        ?string $batchId = null,
    ): array {
        $this->validatePackageCode($packageCode);

        $batchId ??= (string) Str::uuid();
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
            $this->currentStage = 'preparing';
            $onProgress('preparing', 0, 'Starting update...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 0, 'Starting update...');

            $onProgress('preparing', 5, 'Verifying license...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 5, 'Verifying license...');
            $licenseData = $this->apiClient->verifyLicense($packageCode);

            $toVersion = $licenseData['version'] ?? '0.0.0';
            $packageRequirements = $licenseData['requirements'] ?? [];

            $this->checkCancellation($batchId, $packageCode);

            // COMPATIBILITY (10-20%)
            $this->currentStage = 'compatibility';
            $onProgress('compatibility', 10, 'Checking system compatibility...');
            $this->updateProgress($batchId, $packageCode, 'compatibility', 10, 'Checking system compatibility...');

            $this->compatibilityChecker->assertSatisfied($packageCode, $packageRequirements);

            $onProgress('compatibility', 20, 'Compatibility check passed');
            $this->updateProgress($batchId, $packageCode, 'compatibility', 20, 'Compatibility check passed');

            $this->checkCancellation($batchId, $packageCode);

            // BACKUP (20-30%) - Backup before update if files exist on disk
            $this->currentStage = 'backup';
            if ($this->packageExistsOnDisk($packageCode)) {
                $onProgress('backup', 20, 'Creating backup...');
                $this->updateProgress($batchId, $packageCode, 'backup', 20, 'Creating backup...');

                $this->backupManager->createBackup($packageCode);

                $onProgress('backup', 30, 'Backup created');
                $this->updateProgress($batchId, $packageCode, 'backup', 30, 'Backup created');
            } else {
                $onProgress('backup', 30, 'Skipping backup (package files not found)');
                $this->updateProgress($batchId, $packageCode, 'backup', 30, 'Skipping backup (package files not found)');
            }

            $this->checkCancellation($batchId, $packageCode);

            // UPDATE (30-70%)
            $this->currentStage = 'updating';
            $onProgress('updating', 30, 'Starting update...');
            $this->updateProgress($batchId, $packageCode, 'updating', 30, 'Starting update...');

            $installer = $method === 'composer' ? resolve(ComposerInstaller::class) : resolve(DirectInstaller::class);

            $composerProgress = function (int $percent, string $message) use ($batchId, $packageCode, $onProgress): void {
                $adjustedPercent = 30 + (int) ($percent * 0.4);
                $onProgress('updating', $adjustedPercent, $message);
                $this->updateProgress($batchId, $packageCode, 'updating', $adjustedPercent, $message);
            };

            // Execute update
            if (method_exists($installer, 'update')) {
                $installer->update($packageCode, $composerProgress);
            } else {
                $installer->install($packageCode, $licenseData, $composerProgress);
            }

            $onProgress('updating', 70, 'Update completed');
            $this->updateProgress($batchId, $packageCode, 'updating', 70, 'Update completed');

            // MIGRATE (70-85%)
            $this->currentStage = 'migrating';
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
            $this->currentStage = 'finalizing';
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

            $errorCode = $this->classifyError($e);

            // Attempt backup restore (skip if user cancelled)
            if ($errorCode !== 'cancelled') {
                try {
                    if ($this->backupManager->hasBackup($packageCode)) {
                        $onProgress('restoring', 0, 'Update failed. Restoring backup...');
                        $this->updateProgress($batchId, $packageCode, 'restoring', 0, 'Update failed. Restoring backup...');

                        $this->backupManager->restore($packageCode);
                        $onProgress('restoring', 100, 'Backup restored');
                        $this->updateProgress($batchId, $packageCode, 'restoring', 100, 'Backup restored');
                    }
                } catch (Throwable $restoreError) {
                    Log::error(sprintf("Backup restore failed for '%s'", $packageCode), [
                        'error' => $restoreError->getMessage(),
                    ]);
                }
            }

            // Log failure
            $this->logFailure($packageCode, 'update', $method, $e->getMessage(), $startTime);

            $finalStage = $errorCode === 'cancelled' ? 'cancelled' : 'failed';
            $this->updateProgress($batchId, $packageCode, $finalStage, 0, 'Update failed', $e->getMessage(), $errorCode, $this->currentStage);

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
     * Check if the package files exist on disk (extensions or vendor directory).
     */
    private function packageExistsOnDisk(string $packageCode): bool
    {
        [$vendor, $name] = explode('/', $packageCode, 2);

        $extensionsPath = base_path(sprintf('extensions/%s/%s', $vendor, $name));
        if (is_dir($extensionsPath)) {
            return true;
        }

        $vendorPath = base_path(sprintf('vendor/%s/%s', $vendor, $name));

        return is_dir($vendorPath);
    }

    /**
     * Check if the installation has been cancelled by the user.
     */
    private function checkCancellation(string $batchId, string $packageCode): void
    {
        $progress = DB::table('tipowerup_install_progress')
            ->where('batch_id', $batchId)
            ->where('package_code', $packageCode)
            ->first();

        if ($progress && $progress->stage === 'cancelled') {
            throw new PackageInstallationException(
                sprintf("Installation of '%s' was cancelled by user.", $packageCode)
            );
        }
    }

    /**
     * Classify an exception into a user-friendly error code.
     */
    private function classifyError(Throwable $e): string
    {
        return match (true) {
            $e instanceof LicenseValidationException => 'license_invalid',
            $e instanceof CompatibilityException => 'compatibility_failed',
            $e instanceof BackupRestoreException => 'backup_failed',
            $e instanceof ConnectionException => 'connection_failed',
            $e instanceof InvalidArgumentException => 'invalid_package',
            $e instanceof PackageInstallationException => $this->classifyPackageError($e),
            default => 'unknown',
        };
    }

    /**
     * Classify a PackageInstallationException by inspecting its message.
     */
    private function classifyPackageError(PackageInstallationException $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'cancelled') => 'cancelled',
            str_contains($message, 'checksum') => 'checksum_mismatch',
            str_contains($message, 'invalid package structure') => 'invalid_structure',
            str_contains($message, 'download') => 'download_failed',
            str_contains($message, 'extract') => 'extraction_failed',
            str_contains($message, 'migration') => 'migration_failed',
            str_contains($message, 'composer') => 'composer_failed',
            str_contains($message, 'register') => 'registration_failed',
            default => 'unknown',
        };
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
        ?string $error = null,
        ?string $errorCode = null,
        ?string $failedStage = null,
    ): void {
        $data = [
            'stage' => $stage,
            'progress_percent' => $percent,
            'message' => $message,
            'error' => $error,
            'updated_at' => now(),
        ];

        if ($errorCode !== null) {
            $data['error_code'] = $errorCode;
        }

        if ($failedStage !== null) {
            $data['failed_stage'] = $failedStage;
        }

        DB::table('tipowerup_install_progress')->updateOrInsert(
            [
                'batch_id' => $batchId,
                'package_code' => $packageCode,
            ],
            $data
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
}
