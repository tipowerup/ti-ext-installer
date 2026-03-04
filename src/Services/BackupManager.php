<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;
use Tipowerup\Installer\Exceptions\BackupRestoreException;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\Concerns\ClearsInstallerCaches;
use Tipowerup\Installer\Services\Concerns\ValidatesPackageCode;
use ZipArchive;

class BackupManager
{
    use ClearsInstallerCaches;
    use ValidatesPackageCode;

    private const string BACKUP_BASE_PATH = 'app/tipowerup/backups';

    /**
     * Create a backup of the current package files before install/update.
     */
    public function createBackup(string $packageCode): void
    {
        $this->validatePackageCode($packageCode);

        try {
            $packagePath = $this->findPackagePath($packageCode);

            if (!$packagePath || !is_dir($packagePath)) {
                throw new BackupRestoreException(
                    sprintf("Package '%s' not found for backup.", $packageCode)
                );
            }

            $backupDir = $this->getBackupPath($packageCode);
            File::ensureDirectoryExists($backupDir);

            // Get current version from License model
            $license = License::byPackage($packageCode)->first();
            $currentVersion = $license?->version ?? '0.0.0';

            // Create manifest
            $manifest = [
                'package_code' => $packageCode,
                'version' => $currentVersion,
                'method' => $license?->install_method ?? 'unknown',
                'date' => now()->toIso8601String(),
                'migration_state' => $this->captureMigrationState($packageCode),
                'files_count' => 0,
            ];

            // Create ZIP of package directory
            $zipPath = $backupDir.'/files.zip';
            $filesCount = $this->createPackageZip($packagePath, $zipPath);
            $manifest['files_count'] = $filesCount;

            // Save manifest
            File::put(
                $backupDir.'/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // Save migration state as separate file
            File::put(
                $backupDir.'/migration-state.json',
                json_encode($manifest['migration_state'], JSON_PRETTY_PRINT)
            );

            Log::info(sprintf("Backup created for package '%s'", $packageCode), [
                'version' => $currentVersion,
                'files_count' => $filesCount,
                'backup_dir' => $backupDir,
            ]);

        } catch (Throwable $e) {
            Log::error(sprintf("Failed to create backup for '%s'", $packageCode), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BackupRestoreException(
                sprintf("Failed to create backup for package '%s': %s", $packageCode, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Restore a package from its backup.
     */
    public function restore(string $packageCode): void
    {
        $this->validatePackageCode($packageCode);

        try {
            $backupDir = $this->getBackupPath($packageCode);

            if (!$this->hasBackup($packageCode)) {
                throw new BackupRestoreException(
                    sprintf("No backup found for package '%s'.", $packageCode)
                );
            }

            // Read manifest
            $manifestPath = $backupDir.'/manifest.json';
            $manifest = json_decode(File::get($manifestPath), true);

            if (!$manifest) {
                throw new BackupRestoreException(
                    sprintf("Invalid backup manifest for package '%s'.", $packageCode)
                );
            }

            Log::info(sprintf("Starting restore for package '%s'", $packageCode), [
                'version' => $manifest['version'],
                'backup_date' => $manifest['date'],
            ]);

            // Determine target path
            $targetPath = $this->findPackagePath($packageCode);

            if (!$targetPath) {
                // If package doesn't exist, determine path based on package code
                $targetPath = $this->determineTargetPath($packageCode);
            }

            // Delete current package directory if it exists
            if (is_dir($targetPath)) {
                File::deleteDirectory($targetPath);
            }

            // Extract backup ZIP
            $zipPath = $backupDir.'/files.zip';
            $this->extractBackupZip($zipPath, $targetPath);

            // Restore migration state
            $this->restoreMigrationState($packageCode, $backupDir.'/migration-state.json');

            // Clear caches
            $this->clearCaches();

            Log::info(sprintf("Restore completed for package '%s'", $packageCode));

        } catch (Throwable $e) {
            Log::error(sprintf("Failed to restore backup for '%s'", $packageCode), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BackupRestoreException(
                sprintf("Failed to restore package '%s': %s", $packageCode, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Check if a backup exists for a package.
     */
    public function hasBackup(string $packageCode): bool
    {
        $this->validatePackageCode($packageCode);

        $backupDir = $this->getBackupPath($packageCode);

        return File::exists($backupDir.'/manifest.json')
            && File::exists($backupDir.'/files.zip');
    }

    /**
     * Get backup info (manifest data).
     */
    public function getBackupInfo(string $packageCode): ?array
    {
        $this->validatePackageCode($packageCode);

        if (!$this->hasBackup($packageCode)) {
            return null;
        }

        $manifestPath = $this->getBackupPath($packageCode).'/manifest.json';
        $manifest = json_decode(File::get($manifestPath), true);

        return $manifest ?: null;
    }

    /**
     * Delete a backup.
     */
    public function deleteBackup(string $packageCode): void
    {
        $this->validatePackageCode($packageCode);

        $backupDir = $this->getBackupPath($packageCode);

        if (!is_dir($backupDir)) {
            return;
        }

        try {
            File::deleteDirectory($backupDir);

            Log::info(sprintf("Backup deleted for package '%s'", $packageCode));
        } catch (Throwable $e) {
            Log::warning(sprintf("Failed to delete backup for '%s'", $packageCode), [
                'error' => $e->getMessage(),
            ]);

            throw new BackupRestoreException(
                sprintf("Failed to delete backup for package '%s': %s", $packageCode, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPackageCode(string $packageCode): array
    {
        return explode('/', $packageCode, 2);
    }

    /**
     * Get the backup directory path for a package.
     */
    private function getBackupPath(string $packageCode): string
    {
        return storage_path(self::BACKUP_BASE_PATH.'/'.str_replace('/', '.', $packageCode));
    }

    /**
     * Find the installation path of a package.
     */
    private function findPackagePath(string $packageCode): ?string
    {
        [$vendor, $name] = $this->splitPackageCode($packageCode);

        // Check extensions directory first
        $extensionsPath = base_path(sprintf('extensions/%s/%s', $vendor, $name));
        if (is_dir($extensionsPath)) {
            return $extensionsPath;
        }

        // Check vendor directory
        $vendorPath = base_path(sprintf('vendor/%s/%s', $vendor, $name));
        if (is_dir($vendorPath)) {
            return $vendorPath;
        }

        return null;
    }

    /**
     * Determine target path for package restoration.
     */
    private function determineTargetPath(string $packageCode): string
    {
        [$vendor, $name] = $this->splitPackageCode($packageCode);

        // Default to extensions directory for tipowerup packages
        if ($vendor === 'tipowerup') {
            return base_path(sprintf('extensions/%s/%s', $vendor, $name));
        }

        // Otherwise use vendor directory
        return base_path(sprintf('vendor/%s/%s', $vendor, $name));
    }

    /**
     * Create a ZIP archive of the package directory.
     */
    private function createPackageZip(string $sourcePath, string $zipPath): int
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupRestoreException(
                sprintf("Failed to create ZIP archive at '%s'.", $zipPath)
            );
        }

        $filesCount = 0;
        $files = File::allFiles($sourcePath);

        foreach ($files as $file) {
            $relativePath = str_replace($sourcePath.'/', '', $file->getRealPath());
            $zip->addFile($file->getRealPath(), $relativePath);
            $filesCount++;
        }

        $zip->close();

        return $filesCount;
    }

    /**
     * Extract a backup ZIP to the target directory.
     */
    private function extractBackupZip(string $zipPath, string $targetPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new BackupRestoreException(
                sprintf("Failed to open backup ZIP at '%s'.", $zipPath)
            );
        }

        File::ensureDirectoryExists($targetPath);

        if (!$zip->extractTo($targetPath)) {
            $zip->close();

            throw new BackupRestoreException(
                sprintf("Failed to extract backup ZIP to '%s'.", $targetPath)
            );
        }

        $zip->close();
    }

    /**
     * Capture the current migration state for a package.
     */
    private function captureMigrationState(string $packageCode): array
    {
        // Escape special LIKE characters
        $escapedCode = str_replace(['%', '_'], ['\\%', '\\_'], $packageCode);

        $migrations = DB::table('migrations')
            ->where('migration', 'like', sprintf('%%%s%%', $escapedCode))
            ->orderBy('batch')
            ->get(['migration', 'batch'])
            ->toArray();

        return array_map(fn ($migration): array => [
            'migration' => $migration->migration,
            'batch' => $migration->batch,
        ], $migrations);
    }

    /**
     * Restore migration state from backup.
     */
    private function restoreMigrationState(string $packageCode, string $stateFilePath): void
    {
        if (!File::exists($stateFilePath)) {
            Log::warning(sprintf("Migration state file not found for '%s'", $packageCode));

            return;
        }

        $savedState = json_decode(File::get($stateFilePath), true);

        if (empty($savedState)) {
            return;
        }

        // Escape special LIKE characters
        $escapedCode = str_replace(['%', '_'], ['\\%', '\\_'], $packageCode);

        // Delete current migrations for this package
        DB::table('migrations')
            ->where('migration', 'like', sprintf('%%%s%%', $escapedCode))
            ->delete();

        // Restore saved migration state
        foreach ($savedState as $migration) {
            DB::table('migrations')->insert([
                'migration' => $migration['migration'],
                'batch' => $migration['batch'],
            ]);
        }

        Log::info(sprintf("Migration state restored for '%s'", $packageCode), [
            'migrations_count' => count($savedState),
        ]);
    }
}
