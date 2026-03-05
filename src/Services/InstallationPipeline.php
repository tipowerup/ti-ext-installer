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

        return $this->executePipeline($packageCode, $method, 'install', $onProgress, $batchId);
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

        return $this->executePipeline($packageCode, $method, 'update', $onProgress, $batchId);
    }

    private function executePipeline(
        string $packageCode,
        string $method,
        string $action,
        ?callable $onProgress,
        ?string $batchId,
    ): array {
        $batchId ??= (string) Str::uuid();
        $startTime = microtime(true);
        $onProgress ??= function (string $stage, int $percent, string $message): void {};
        $isUpdate = $action === 'update';
        $actionLabel = $isUpdate ? 'update' : 'installation';
        $installStage = $isUpdate ? 'updating' : 'installing';

        try {
            $existingLicense = License::byPackage($packageCode)->first();

            if ($isUpdate && !$existingLicense) {
                throw new PackageInstallationException(
                    sprintf("Package '%s' is not installed. Cannot update.", $packageCode)
                );
            }

            $fromVersion = $existingLicense?->version;

            // PREPARE (0-10%)
            $this->currentStage = 'preparing';
            $onProgress('preparing', 0, sprintf('Starting %s...', $actionLabel));
            $this->updateProgress($batchId, $packageCode, 'preparing', 0, sprintf('Starting %s...', $actionLabel));

            $onProgress('preparing', 5, 'Verifying license...');
            $this->updateProgress($batchId, $packageCode, 'preparing', 5, 'Verifying license...');
            $licenseData = $this->apiClient->verifyLicense($packageCode);

            $packageRequirements = $licenseData['requirements'] ?? [];
            $packageName = $licenseData['package_name'] ?? $packageCode;
            $packageType = $licenseData['package_type'] ?? 'extension';
            $toVersion = $licenseData['version'] ?? '0.0.0';

            $this->checkCancellation($batchId, $packageCode);

            // COMPATIBILITY (10-20%)
            $this->currentStage = 'compatibility';
            $onProgress('compatibility', 10, 'Checking system compatibility...');
            $this->updateProgress($batchId, $packageCode, 'compatibility', 10, 'Checking system compatibility...');

            if ($isUpdate) {
                $this->compatibilityChecker->assertSatisfied($packageCode, $packageRequirements);
            } else {
                $compatResults = $this->compatibilityChecker->check($packageCode, $packageRequirements);
                $isSatisfied = $this->compatibilityChecker->isSatisfied($packageCode, $packageRequirements);

                if (!$isSatisfied) {
                    $failures = $this->compatibilityChecker->getFailures($compatResults);
                    $this->logFailure($packageCode, $action, $method, 'Compatibility check failed', $startTime);

                    throw PackageInstallationException::downloadFailed(
                        $packageCode,
                        'Compatibility check failed: '.implode(', ', $failures)
                    );
                }
            }

            $onProgress('compatibility', 20, 'Compatibility check passed');
            $this->updateProgress($batchId, $packageCode, 'compatibility', 20, 'Compatibility check passed');

            $this->checkCancellation($batchId, $packageCode);

            // BACKUP (20-30%)
            $this->currentStage = 'backup';
            if ($this->packageExistsOnDisk($packageCode)) {
                $onProgress('backup', 20, 'Creating backup...');
                $this->updateProgress($batchId, $packageCode, 'backup', 20, 'Creating backup...');

                $this->backupManager->createBackup($packageCode);

                $onProgress('backup', 30, 'Backup created');
                $this->updateProgress($batchId, $packageCode, 'backup', 30, 'Backup created');
            } else {
                $skipMsg = $isUpdate ? 'Skipping backup (package files not found)' : 'Skipping backup (no existing files)';
                $onProgress('backup', 30, $skipMsg);
                $this->updateProgress($batchId, $packageCode, 'backup', 30, $skipMsg);
            }

            $this->checkCancellation($batchId, $packageCode);

            // DOWNLOAD/INSTALL (30-70%)
            $this->currentStage = $installStage;
            $onProgress($installStage, 30, sprintf('Starting %s...', $actionLabel));
            $this->updateProgress($batchId, $packageCode, $installStage, 30, sprintf('Starting %s...', $actionLabel));

            $installer = $method === 'composer' ? resolve(ComposerInstaller::class) : resolve(DirectInstaller::class);

            $composerProgress = function (int $percent, string $message) use ($batchId, $packageCode, $onProgress, $installStage): void {
                $adjustedPercent = 30 + (int) ($percent * 0.4);
                $onProgress($installStage, $adjustedPercent, $message);
                $this->updateProgress($batchId, $packageCode, $installStage, $adjustedPercent, $message);
            };

            if ($isUpdate && method_exists($installer, 'update')) {
                $installer->update($packageCode, $composerProgress);
            } else {
                $installer->install($packageCode, $licenseData, $composerProgress);
            }

            $onProgress($installStage, 70, ucfirst($actionLabel).' completed');
            $this->updateProgress($batchId, $packageCode, $installStage, 70, ucfirst($actionLabel).' completed');

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
            $onProgress('finalizing', 85, sprintf('Finalizing %s...', $actionLabel));
            $this->updateProgress($batchId, $packageCode, 'finalizing', 85, sprintf('Finalizing %s...', $actionLabel));

            $this->clearCaches();

            $onProgress('finalizing', 90, 'Updating license records...');
            $this->updateProgress($batchId, $packageCode, 'finalizing', 90, 'Updating license records...');

            if ($isUpdate) {
                $existingLicense->update([
                    'version' => $toVersion,
                    'updated_at' => now(),
                ]);
            } else {
                License::updateOrCreate(
                    ['package_code' => $packageCode],
                    [
                        'package_name' => $packageName,
                        'package_type' => $packageType,
                        'version' => $toVersion,
                        'install_method' => $method,
                        'installed_at' => $existingLicense?->installed_at ?? now(),
                        'updated_at' => now(),
                        'expires_at' => isset($licenseData['expires_at']) ? new DateTime($licenseData['expires_at']) : null,
                        'is_active' => true,
                    ]
                );
            }

            $onProgress('finalizing', 95, sprintf('Logging %s...', $actionLabel));
            $this->updateProgress($batchId, $packageCode, 'finalizing', 95, sprintf('Logging %s...', $actionLabel));

            $this->logSuccess($packageCode, $action, $method, $fromVersion, $toVersion, $startTime);

            $onProgress('complete', 100, ucfirst($actionLabel).' complete!');
            $this->updateProgress($batchId, $packageCode, 'complete', 100, ucfirst($actionLabel).' complete!');

            $result = [
                'success' => true,
                'package_code' => $packageCode,
                'method' => $method,
                'duration' => microtime(true) - $startTime,
            ];

            if ($isUpdate) {
                $result['from_version'] = $fromVersion;
                $result['to_version'] = $toVersion;
            } else {
                $result['version'] = $toVersion;
            }

            return $result;

        } catch (Throwable $e) {
            Log::error(sprintf("%s failed for '%s'", ucfirst($actionLabel), $packageCode), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorCode = $this->classifyError($e);

            if ($errorCode !== 'cancelled') {
                try {
                    if ($this->backupManager->hasBackup($packageCode)) {
                        $onProgress('restoring', 0, sprintf('%s failed. Restoring backup...', ucfirst($actionLabel)));
                        $this->updateProgress($batchId, $packageCode, 'restoring', 0, sprintf('%s failed. Restoring backup...', ucfirst($actionLabel)));

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

            $this->logFailure($packageCode, $action, $method, $e->getMessage(), $startTime);

            $finalStage = $errorCode === 'cancelled' ? 'cancelled' : 'failed';
            $this->updateProgress($batchId, $packageCode, $finalStage, 0, ucfirst($actionLabel).' failed', $e->getMessage(), $errorCode, $this->currentStage);

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
     * Check if the package files exist on disk (storage, extensions, or vendor directory).
     */
    private function packageExistsOnDisk(string $packageCode): bool
    {
        [$vendor, $name] = explode('/', $packageCode, 2);
        $shortName = preg_replace('/^ti-(ext|theme)-/', '', $name);

        // Check storage paths (direct-installed packages)
        $storageExtPath = storage_path(sprintf('app/tipowerup/extensions/%s/%s', $vendor, $shortName));
        if (is_dir($storageExtPath)) {
            return true;
        }

        $storageThemePath = storage_path(sprintf('app/tipowerup/themes/%s-%s', $vendor, $shortName));
        if (is_dir($storageThemePath)) {
            return true;
        }

        // Check extensions directory
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
