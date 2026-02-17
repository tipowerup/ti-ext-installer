<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use ZipArchive;

class DirectInstaller
{
    private const int DOWNLOAD_TIMEOUT_SECONDS = 300;

    private const array DANGEROUS_EXTENSIONS = [
        'phar',
        'sh',
        'bash',
        'exe',
        'bat',
        'cmd',
        'com',
    ];

    /**
     * Install a package via direct ZIP extraction.
     */
    public function install(string $packageCode, array $licenseData): array
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('DirectInstaller: Starting installation', [
                'package_code' => $packageCode,
            ]);

            // Extract package metadata from license data
            $downloadUrl = $licenseData['download_url'] ?? throw PackageInstallationException::downloadFailed(
                $packageCode,
                'Download URL not provided in license data'
            );

            $checksum = $licenseData['checksum'] ?? null;
            $packageType = $licenseData['package_type'] ?? 'extension';
            $version = $licenseData['version'] ?? 'unknown';

            // Download package
            $zipPath = $this->downloadPackage($downloadUrl, $packageCode);

            // Verify checksum if provided
            if ($checksum && !$this->verifyChecksum($zipPath, $checksum)) {
                File::delete($zipPath);

                throw PackageInstallationException::checksumMismatch($packageCode);
            }

            // Determine target path based on package type
            $targetPath = $this->resolveTargetPath($packageCode, $packageType);

            // Extract package
            $this->extractPackage($zipPath, $targetPath, $packageCode);

            // Clean up ZIP file
            File::delete($zipPath);

            // Validate package structure
            if (!$this->validatePackageStructure($targetPath, $packageType)) {
                // Rollback extraction
                File::deleteDirectory($targetPath);

                throw PackageInstallationException::extractionFailed(
                    $packageCode,
                    'Invalid package structure - missing required files'
                );
            }

            // Register with TastyIgniter
            $this->registerWithTI($packageCode, $packageType, $targetPath);

            // Publish theme assets to public directory
            if ($packageType === 'theme') {
                $this->publishThemeAssets($packageCode, $targetPath);
            }

            // Run migrations for extensions
            if ($packageType === 'extension') {
                $this->runMigrations($packageCode);
            }

            // Clear caches
            $this->clearCaches();

            $duration = microtime(true) - $startTime;

            Log::info('DirectInstaller: Installation successful', [
                'package_code' => $packageCode,
                'duration_seconds' => round($duration, 2),
            ]);

            return [
                'success' => true,
                'method' => 'direct',
                'version' => $version,
                'path' => $targetPath,
                'duration_seconds' => round($duration, 2),
            ];

        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('DirectInstaller: Installation failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing package via direct ZIP extraction.
     */
    public function update(string $packageCode, array $licenseData): array
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('DirectInstaller: Starting update', [
                'package_code' => $packageCode,
            ]);

            // Extract package metadata from license data
            $downloadUrl = $licenseData['download_url'] ?? throw PackageInstallationException::downloadFailed(
                $packageCode,
                'Download URL not provided in license data'
            );

            $checksum = $licenseData['checksum'] ?? null;
            $packageType = $licenseData['package_type'] ?? 'extension';
            $version = $licenseData['version'] ?? 'unknown';
            $currentVersion = $licenseData['current_version'] ?? null;

            // Download package
            $zipPath = $this->downloadPackage($downloadUrl, $packageCode);

            // Verify checksum if provided
            if ($checksum && !$this->verifyChecksum($zipPath, $checksum)) {
                File::delete($zipPath);

                throw PackageInstallationException::checksumMismatch($packageCode);
            }

            // Determine target path based on package type
            $targetPath = $this->resolveTargetPath($packageCode, $packageType);

            // Create backup of existing installation
            $backupPath = storage_path(sprintf('app/tipowerup/backups/%s-', $packageCode).date('Y-m-d-His'));
            if (File::exists($targetPath)) {
                File::copyDirectory($targetPath, $backupPath);
            }

            // Delete existing installation
            if (File::exists($targetPath)) {
                File::deleteDirectory($targetPath);
            }

            // Extract new version
            $this->extractPackage($zipPath, $targetPath, $packageCode);

            // Clean up ZIP file
            File::delete($zipPath);

            // Validate package structure
            if (!$this->validatePackageStructure($targetPath, $packageType)) {
                // Rollback to backup
                if (File::exists($backupPath)) {
                    File::deleteDirectory($targetPath);
                    File::moveDirectory($backupPath, $targetPath);
                }

                throw PackageInstallationException::extractionFailed(
                    $packageCode,
                    'Invalid package structure - missing required files'
                );
            }

            // Publish theme assets to public directory
            if ($packageType === 'theme') {
                $this->publishThemeAssets($packageCode, $targetPath);
            }

            // Run migrations for extensions
            if ($packageType === 'extension') {
                try {
                    $this->runMigrations($packageCode);
                } catch (Throwable $e) {
                    // Migration failed - rollback
                    if (File::exists($backupPath)) {
                        File::deleteDirectory($targetPath);
                        File::moveDirectory($backupPath, $targetPath);
                    }

                    throw PackageInstallationException::migrationFailed($packageCode, $e->getMessage());
                }
            }

            // Clear caches
            $this->clearCaches();

            // Clean up backup on success
            if (File::exists($backupPath)) {
                File::deleteDirectory($backupPath);
            }

            $duration = microtime(true) - $startTime;

            Log::info('DirectInstaller: Update successful', [
                'package_code' => $packageCode,
                'from_version' => $currentVersion,
                'to_version' => $version,
                'duration_seconds' => round($duration, 2),
            ]);

            return [
                'success' => true,
                'method' => 'direct',
                'from_version' => $currentVersion,
                'to_version' => $version,
                'path' => $targetPath,
                'duration_seconds' => round($duration, 2),
            ];

        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('DirectInstaller: Update failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Uninstall a direct-installed package.
     */
    public function uninstall(string $packageCode): void
    {
        $this->validatePackageCode($packageCode);

        try {
            Log::info('DirectInstaller: Starting uninstall', [
                'package_code' => $packageCode,
            ]);

            $shortName = $this->getShortName($packageCode);
            $vendorName = $this->getVendorName($packageCode);

            // Check storage paths
            $extensionPath = storage_path('app/tipowerup/extensions/'.$vendorName.'/'.$shortName);
            $themePath = storage_path('app/tipowerup/themes/'.$vendorName.'-'.$shortName);

            $targetPath = File::exists($extensionPath) ? $extensionPath : $themePath;
            $packageType = File::exists($extensionPath) ? 'extension' : 'theme';

            if (!File::exists($targetPath)) {
                Log::warning('DirectInstaller: Package not found for uninstall', [
                    'package_code' => $packageCode,
                    'checked_paths' => [$extensionPath, $themePath],
                ]);

                return;
            }

            // Unregister from TI before deletion
            if ($packageType === 'extension') {
                try {
                    $extensionManager = resolve(ExtensionManager::class);
                    $extensionManager->uninstallExtension($packageCode);
                } catch (Throwable $e) {
                    Log::warning('Failed to unregister extension', [
                        'package_code' => $packageCode,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                try {
                    $themeManager = resolve(ThemeManager::class);
                    $themeManager->deleteTheme($packageCode);
                } catch (Throwable $e) {
                    Log::warning('Failed to unregister theme', [
                        'package_code' => $packageCode,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Delete package directory
            File::deleteDirectory($targetPath);

            // Clean up published theme assets
            if ($packageType === 'theme') {
                $assetsPath = public_path('vendor/'.$vendorName.'-'.$shortName);
                if (File::exists($assetsPath)) {
                    File::deleteDirectory($assetsPath);
                }
            }

            // Clear caches
            $this->clearCaches();

            Log::info('DirectInstaller: Uninstall successful', [
                'package_code' => $packageCode,
            ]);

        } catch (Throwable $e) {
            Log::error('DirectInstaller: Uninstall failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Download package from URL using cURL with chunked download.
     */
    private function downloadPackage(string $url, string $packageCode): string
    {
        // Validate download URL for security
        $this->validateDownloadUrl($url);

        $tmpDir = storage_path('app/tipowerup/tmp');

        // Ensure temp directory exists
        if (!File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $tmpFile = $tmpDir.'/'.$packageCode.'-'.uniqid().'.zip';

        try {
            Log::debug('DirectInstaller: Downloading package', [
                'url' => $url,
                'target' => $tmpFile,
            ]);

            $fp = fopen($tmpFile, 'w+');

            if ($fp === false) {
                throw PackageInstallationException::downloadFailed(
                    $packageCode,
                    'Failed to create temporary file'
                );
            }

            $ch = curl_init($url);

            if ($ch === false) {
                fclose($fp);

                throw PackageInstallationException::downloadFailed(
                    $packageCode,
                    'Failed to initialize cURL'
                );
            }

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::DOWNLOAD_TIMEOUT_SECONDS);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);
            fclose($fp);

            if ($result === false || $httpCode !== 200) {
                File::delete($tmpFile);

                throw PackageInstallationException::downloadFailed(
                    $packageCode,
                    $error ?: 'HTTP '.$httpCode
                );
            }

            Log::debug('DirectInstaller: Download complete', [
                'package_code' => $packageCode,
                'file_size' => filesize($tmpFile),
            ]);

            return $tmpFile;

        } catch (Throwable $e) {
            if (File::exists($tmpFile)) {
                File::delete($tmpFile);
            }

            throw $e;
        }
    }

    /**
     * Verify package checksum using SHA256.
     */
    private function verifyChecksum(string $filePath, string $expectedChecksum): bool
    {
        $actualChecksum = hash_file('sha256', $filePath);

        return hash_equals($expectedChecksum, $actualChecksum);
    }

    /**
     * Extract package with path traversal protection.
     */
    private function extractPackage(string $zipPath, string $targetPath, ?string $packageCode = null): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw PackageInstallationException::extractionFailed(
                $packageCode ?? basename($zipPath),
                'Failed to open ZIP archive'
            );
        }

        try {
            // Create target directory
            if (!File::exists($targetPath)) {
                File::makeDirectory($targetPath, 0755, true);
            }

            // Get real target path before validation
            $realTargetPath = realpath($targetPath);
            if ($realTargetPath === false) {
                $zip->close();

                throw PackageInstallationException::extractionFailed(
                    $packageCode ?? basename($zipPath),
                    'Failed to resolve target directory'
                );
            }

            // Validate all files before extraction
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);

                if ($filename === false) {
                    continue;
                }

                // Basic security checks
                $this->validateFilePath($filename);

                // Normalize path manually without relying on filesystem existence
                $parts = explode('/', str_replace('\\', '/', $filename));
                $resolved = [];
                foreach ($parts as $part) {
                    if ($part === '..') {
                        array_pop($resolved);
                    } elseif ($part !== '.' && $part !== '') {
                        $resolved[] = $part;
                    }
                }

                $normalizedPath = $realTargetPath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $resolved);

                // Ensure normalized path starts with target path
                if (!str_starts_with($normalizedPath, $realTargetPath)) {
                    $zip->close();

                    throw PackageInstallationException::extractionFailed(
                        $packageCode ?? basename($zipPath),
                        'Path traversal attempt detected: '.$filename
                    );
                }
            }

            // If all files passed validation, extract
            if (!$zip->extractTo($targetPath)) {
                $zip->close();

                throw PackageInstallationException::extractionFailed(
                    $packageCode ?? basename($zipPath),
                    'Failed to extract files'
                );
            }

            $zip->close();

        } catch (Throwable $e) {
            $zip->close();

            throw $e;
        }
    }

    /**
     * Validate file path for security issues.
     */
    private function validateFilePath(string $path): void
    {
        // Check for path traversal
        if (str_contains($path, '..')) {
            throw PackageInstallationException::extractionFailed(
                'security',
                'Path traversal detected: '.$path
            );
        }

        // Check for absolute paths
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path)) {
            throw PackageInstallationException::extractionFailed(
                'security',
                'Absolute path detected: '.$path
            );
        }

        // Check for dangerous file extensions
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            throw PackageInstallationException::extractionFailed(
                'security',
                'Dangerous file extension detected: '.$path
            );
        }
    }

    /**
     * Validate package structure.
     */
    private function validatePackageStructure(string $path, string $type): bool
    {
        if ($type === 'extension') {
            // Extensions must have Extension.php (or src/Extension.php) and composer.json
            $hasExtensionFile = File::exists($path.'/Extension.php')
                || File::exists($path.'/src/Extension.php');

            return $hasExtensionFile && File::exists($path.'/composer.json');
        }

        // Themes must have theme.json
        return File::exists($path.'/theme.json');
    }

    /**
     * Register package with TastyIgniter.
     */
    private function registerWithTI(string $packageCode, string $type, string $path): void
    {
        try {
            if ($type === 'extension') {
                $extensionManager = resolve(ExtensionManager::class);
                $extensionManager->loadExtension($path);
                $extensionManager->installExtension($packageCode);
            } else {
                $themeManager = resolve(ThemeManager::class);
                $themeManager->loadTheme($path);
                $themeManager->installTheme($packageCode);
            }
        } catch (Throwable $e) {
            throw PackageInstallationException::extractionFailed(
                $packageCode,
                'Failed to register with TastyIgniter: '.$e->getMessage()
            );
        }
    }

    /**
     * Run migrations for extension.
     */
    private function runMigrations(string $packageCode): void
    {
        try {
            Log::debug('DirectInstaller: Running migrations', [
                'package_code' => $packageCode,
            ]);

            Artisan::call('igniter:up', [
                '--force' => true,
            ]);

        } catch (Throwable $e) {
            throw PackageInstallationException::migrationFailed(
                $packageCode,
                $e->getMessage()
            );
        }
    }

    /**
     * Clear application caches.
     */
    private function clearCaches(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
        } catch (Throwable $e) {
            Log::warning('Failed to clear caches', [
                'error' => $e->getMessage(),
            ]);
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
     * Validate download URL to prevent SSRF attacks.
     */
    private function validateDownloadUrl(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw PackageInstallationException::downloadFailed('unknown', 'Invalid download URL');
        }

        if ($parsed['scheme'] !== 'https') {
            throw PackageInstallationException::downloadFailed('unknown', 'Only HTTPS download URLs are allowed');
        }

        $allowedHosts = ['pkg.tipowerup.com', 'packages.tipowerup.com', 'api.tipowerup.com'];
        if (!in_array($parsed['host'], $allowedHosts, true)) {
            throw PackageInstallationException::downloadFailed(
                'unknown',
                'Download URL host not in allowlist: '.$parsed['host']
            );
        }
    }

    /**
     * Extract short name from package code.
     */
    private function getShortName(string $packageCode): string
    {
        $parts = explode('.', $packageCode);

        return end($parts);
    }

    /**
     * Extract vendor name from package code.
     */
    private function getVendorName(string $packageCode): string
    {
        $parts = explode('.', $packageCode);

        return $parts[0];
    }

    /**
     * Resolve target path for package extraction.
     */
    private function resolveTargetPath(string $packageCode, string $packageType): string
    {
        $shortName = $this->getShortName($packageCode);
        $vendorName = $this->getVendorName($packageCode);

        if ($packageType === 'theme') {
            return storage_path('app/tipowerup/themes/'.$vendorName.'-'.$shortName);
        }

        return storage_path('app/tipowerup/extensions/'.$vendorName.'/'.$shortName);
    }

    /**
     * Publish theme assets to public directory.
     */
    private function publishThemeAssets(string $packageCode, string $themePath): void
    {
        try {
            $shortName = $this->getShortName($packageCode);
            $vendorName = $this->getVendorName($packageCode);
            $assetsSource = $themePath.'/assets';
            $assetsTarget = public_path('vendor/'.$vendorName.'-'.$shortName);

            if (!File::exists($assetsSource)) {
                return;
            }

            if (!File::exists(dirname($assetsTarget))) {
                File::makeDirectory(dirname($assetsTarget), 0755, true);
            }

            File::copyDirectory($assetsSource, $assetsTarget);

            Log::debug('DirectInstaller: Theme assets published', [
                'package_code' => $packageCode,
                'target' => $assetsTarget,
            ]);
        } catch (Throwable $e) {
            Log::warning('DirectInstaller: Failed to publish theme assets', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
