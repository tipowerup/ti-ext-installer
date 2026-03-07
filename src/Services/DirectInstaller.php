<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Services\Concerns\ClearsInstallerCaches;
use Tipowerup\Installer\Services\Concerns\RegistersWithTI;
use Tipowerup\Installer\Services\Concerns\ValidatesPackageCode;
use ZipArchive;

class DirectInstaller
{
    use ClearsInstallerCaches;
    use RegistersWithTI;
    use ValidatesPackageCode;

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
    public function install(string $packageCode, array $licenseData, ?callable $onProgress = null): array
    {
        $this->validatePackageCode($packageCode);

        return $this->performInstallation($packageCode, $licenseData, false, $onProgress);
    }

    /**
     * Update an existing package via direct ZIP extraction.
     */
    public function update(string $packageCode, array $licenseData, ?callable $onProgress = null): array
    {
        $this->validatePackageCode($packageCode);

        return $this->performInstallation($packageCode, $licenseData, true, $onProgress);
    }

    /**
     * @param  array{download_url: string, checksum?: string, package_type?: string, version?: string, current_version?: string}  $licenseData
     */
    private function performInstallation(
        string $packageCode,
        array $licenseData,
        bool $isUpdate,
        ?callable $onProgress,
    ): array {
        $onProgress ??= function (int $percent, string $message): void {};
        $startTime = microtime(true);
        $action = $isUpdate ? 'update' : 'installation';

        try {
            Log::info(sprintf('DirectInstaller: Starting %s', $action), [
                'package_code' => $packageCode,
            ]);

            $downloadUrl = $licenseData['download_url'] ?? throw PackageInstallationException::downloadFailed(
                $packageCode,
                'Download URL not provided in license data'
            );

            $checksum = $licenseData['checksum'] ?? null;
            $packageType = $licenseData['package_type'] ?? 'extension';
            $version = $licenseData['version'] ?? 'unknown';
            $currentVersion = $licenseData['current_version'] ?? null;

            $onProgress(5, 'Downloading package...');
            $zipPath = $this->downloadPackage($downloadUrl, $packageCode);
            $onProgress($isUpdate ? 40 : 50, 'Download complete');

            if ($checksum) {
                $onProgress($isUpdate ? 42 : 52, 'Verifying checksum...');

                if (!$this->verifyChecksum($zipPath, $checksum)) {
                    File::delete($zipPath);

                    throw PackageInstallationException::checksumMismatch($packageCode);
                }

                $onProgress($isUpdate ? 45 : 55, 'Checksum verified');
            }

            $targetPath = $this->resolveTargetPath($packageCode, $packageType);
            $backupPath = null;

            if ($isUpdate && File::exists($targetPath)) {
                $onProgress(48, 'Creating backup...');
                $backupPath = Storage::disk('local')->path(sprintf('tipowerup/backups/%s-', $packageCode).date('Y-m-d-His'));
                File::copyDirectory($targetPath, $backupPath);
                $onProgress(55, 'Backup created');

                File::deleteDirectory($targetPath);
            }

            $onProgress($isUpdate ? 58 : 55, 'Extracting package...');
            $this->extractPackage($zipPath, $targetPath, $packageCode);
            $onProgress(75, 'Extraction complete');

            File::delete($zipPath);

            $onProgress(78, 'Validating package structure...');

            if (!$this->validatePackageStructure($targetPath, $packageType)) {
                if ($backupPath !== null && File::exists($backupPath)) {
                    File::deleteDirectory($targetPath);
                    File::moveDirectory($backupPath, $targetPath);
                } else {
                    File::deleteDirectory($targetPath);
                }

                throw PackageInstallationException::extractionFailed(
                    $packageCode,
                    'Invalid package structure - missing required files'
                );
            }

            if (!$isUpdate) {
                $onProgress(80, 'Registering with TastyIgniter...');
                $this->registerWithTI($packageCode, $packageType, $targetPath);
                $onProgress(90, 'Registered successfully');
            }

            if ($packageType === 'theme') {
                $onProgress($isUpdate ? 82 : 92, 'Publishing theme assets...');
                $this->publishThemeAssets($packageCode, $targetPath);
            }

            if ($packageType === 'extension') {
                $onProgress($isUpdate ? 82 : 92, 'Running migrations...');

                try {
                    $this->runMigrations($packageCode);
                } catch (Throwable $e) {
                    if ($backupPath !== null && File::exists($backupPath)) {
                        File::deleteDirectory($targetPath);
                        File::moveDirectory($backupPath, $targetPath);
                    }

                    throw PackageInstallationException::migrationFailed($packageCode, $e->getMessage());
                }

                if ($isUpdate) {
                    $onProgress(90, 'Migrations complete');
                }
            }

            $onProgress($isUpdate ? 92 : 95, 'Clearing caches...');
            $this->clearCaches();

            if ($backupPath !== null && File::exists($backupPath)) {
                $onProgress(96, 'Cleaning up...');
                File::deleteDirectory($backupPath);
            }

            $onProgress(100, ucfirst($action).' complete');

            $duration = microtime(true) - $startTime;

            Log::info(sprintf('DirectInstaller: %s successful', ucfirst($action)), [
                'package_code' => $packageCode,
                ...($isUpdate ? ['from_version' => $currentVersion, 'to_version' => $version] : []),
                'duration_seconds' => round($duration, 2),
            ]);

            $result = [
                'success' => true,
                'method' => 'direct',
                'path' => $targetPath,
                'duration_seconds' => round($duration, 2),
            ];

            if ($isUpdate) {
                $result['from_version'] = $currentVersion;
                $result['to_version'] = $version;
            } else {
                $result['version'] = $version;
            }

            return $result;

        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error(sprintf('DirectInstaller: %s failed', ucfirst($action)), [
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

            // Check storage paths (direct-installed packages live in storage/)
            $extensionPath = Storage::disk('local')->path('tipowerup/extensions/'.$vendorName.'/'.$shortName);
            $themePath = Storage::disk('local')->path('tipowerup/themes/'.$vendorName.'-'.$shortName);

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
     * Download package from URL, streaming directly to disk with resume support.
     */
    private function downloadPackage(string $url, string $packageCode): string
    {
        $this->validateDownloadUrl($url);

        $tmpDir = Storage::disk('local')->path('tipowerup/tmp');

        if (!File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $tmpFile = $tmpDir.'/'.str_replace('/', '-', $packageCode).'-'.uniqid().'.zip';

        try {
            $apiKey = params('tipowerup_api_key', '');

            $headers = [
                'Accept' => 'application/octet-stream',
                'Accept-Encoding' => 'gzip',
            ];

            // Resume partial download if tmp file exists from a previous attempt
            if (File::exists($tmpFile) && ($existingSize = filesize($tmpFile)) > 0) {
                $headers['Range'] = 'bytes='.$existingSize.'-';
            }

            $response = Http::withToken($apiKey)
                ->withHeaders($headers)
                ->timeout(self::DOWNLOAD_TIMEOUT_SECONDS)
                ->connectTimeout(30)
                ->retry(2, 1000, function (Throwable $e, $response): bool {
                    // Only retry on server errors or timeouts, not auth/client errors
                    if ($response === null) {
                        return true;
                    }

                    return $response->status() >= 500;
                }, throw: false)
                ->sink($tmpFile)
                ->get($url);

            if ($response->failed()) {
                File::delete($tmpFile);

                throw PackageInstallationException::downloadFailed(
                    $packageCode,
                    'HTTP '.$response->status().': '.$response->body()
                );
            }

            $fileSize = filesize($tmpFile);

            if ($fileSize === 0 || $fileSize === false) {
                File::delete($tmpFile);

                throw PackageInstallationException::downloadFailed(
                    $packageCode,
                    'Downloaded file is empty'
                );
            }

            return $tmpFile;

        } catch (Throwable $e) {
            if (File::exists($tmpFile)) {
                File::delete($tmpFile);
            }

            throw $e;
        }
    }

    /**
     * Verify package checksum, parsing the "algorithm:hash" format.
     */
    private function verifyChecksum(string $filePath, string $expectedChecksum): bool
    {
        if (str_contains($expectedChecksum, ':')) {
            [$algorithm, $hash] = explode(':', $expectedChecksum, 2);
        } else {
            $algorithm = 'sha1';
            $hash = $expectedChecksum;
        }

        $actualChecksum = hash_file($algorithm, $filePath);

        return hash_equals($hash, $actualChecksum);
    }

    /**
     * Extract package with path traversal protection in a single pass.
     * Handles GitHub-style ZIPs with a single root folder wrapper.
     */
    private function extractPackage(string $zipPath, string $targetPath, ?string $packageCode = null): void
    {
        $zip = new ZipArchive;
        $label = $packageCode ?? basename($zipPath);

        if ($zip->open($zipPath) !== true) {
            throw PackageInstallationException::extractionFailed($label, 'Failed to open ZIP archive');
        }

        try {
            // Detect single root folder wrapper (e.g. "vendor-package-abc123/")
            $rootPrefix = $this->detectRootPrefix($zip);

            // If wrapped, extract to a temp dir then rename — one FS operation instead of N
            $extractPath = $rootPrefix !== null
                ? Storage::disk('local')->path('tipowerup/tmp/extract-'.uniqid())
                : $targetPath;

            if (!File::exists($extractPath)) {
                File::makeDirectory($extractPath, 0755, true);
            }

            $realExtractPath = realpath($extractPath);
            if ($realExtractPath === false) {
                throw PackageInstallationException::extractionFailed($label, 'Failed to resolve extract directory');
            }

            $filesToExtract = [];

            // Single pass: validate + collect safe file names
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);

                if ($filename === false) {
                    continue;
                }

                $this->validateFilePath($filename);

                // Normalize and check for path traversal
                $parts = explode('/', str_replace('\\', '/', $filename));
                $resolved = [];
                foreach ($parts as $part) {
                    if ($part === '..') {
                        array_pop($resolved);
                    } elseif ($part !== '.' && $part !== '') {
                        $resolved[] = $part;
                    }
                }

                $normalizedPath = $realExtractPath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $resolved);

                if (!str_starts_with($normalizedPath, $realExtractPath)) {
                    throw PackageInstallationException::extractionFailed(
                        $label,
                        'Path traversal attempt detected: '.$filename
                    );
                }

                $filesToExtract[] = $filename;
            }

            // Extract only validated files in one call
            if ($filesToExtract !== [] && !$zip->extractTo($extractPath, $filesToExtract)) {
                throw PackageInstallationException::extractionFailed($label, 'Failed to extract files');
            }

            // If wrapped, move the inner folder to the target path in one operation
            if ($rootPrefix !== null) {
                $nestedPath = $realExtractPath.DIRECTORY_SEPARATOR.rtrim($rootPrefix, '/');

                // Ensure target parent directory exists
                $targetParent = dirname($targetPath);
                if (!File::isDirectory($targetParent)) {
                    File::makeDirectory($targetParent, 0755, true);
                }

                // Remove stale target if present
                if (File::isDirectory($targetPath)) {
                    File::deleteDirectory($targetPath);
                }

                File::moveDirectory($nestedPath, $targetPath);

                // Clean up temp extract dir
                File::deleteDirectory($extractPath);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Detect if all ZIP entries share a single root folder prefix.
     */
    private function detectRootPrefix(ZipArchive $zip): ?string
    {
        if ($zip->numFiles === 0) {
            return null;
        }

        $firstEntry = $zip->getNameIndex(0);
        if ($firstEntry === false || !str_contains($firstEntry, '/')) {
            return null;
        }

        $prefix = explode('/', $firstEntry, 2)[0].'/';

        // Verify ALL entries share this prefix
        for ($i = 1; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_starts_with($name, $prefix)) {
                return null;
            }
        }

        return $prefix;
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
     * Run migrations for extension.
     */
    public function runMigrations(string $packageCode): void
    {
        // Migrations are already handled by registerWithTI() → ExtensionManager::installExtension()
        // which calls UpdateManager::migrateExtension() internally.
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

        $allowedHosts = config('tipowerup.installer.allowed_download_hosts', []);

        if (!in_array($parsed['host'], $allowedHosts, true)) {
            throw PackageInstallationException::downloadFailed(
                'unknown',
                'Download URL host not in allowlist: '.$parsed['host']
            );
        }
    }

    /**
     * Extract the short extension name from a Composer package code.
     * Strips the "ti-ext-" or "ti-theme-" prefix if present.
     * e.g. "tipowerup/ti-ext-darkmode" → "darkmode"
     */
    private function getShortName(string $packageCode): string
    {
        // Handle Composer format (vendor/package)
        if (str_contains($packageCode, '/')) {
            $name = explode('/', $packageCode, 2)[1];

            return preg_replace('/^ti-(ext|theme)-/', '', $name);
        }

        // Handle TI dot notation (vendor.name)
        $parts = explode('.', $packageCode);

        return end($parts);
    }

    /**
     * Extract vendor name from package code.
     * e.g. "tipowerup/ti-ext-darkmode" → "tipowerup"
     */
    private function getVendorName(string $packageCode): string
    {
        if (str_contains($packageCode, '/')) {
            return explode('/', $packageCode, 2)[0];
        }

        return explode('.', $packageCode)[0];
    }

    /**
     * Resolve target path for package extraction.
     * Packages are extracted to storage/ and discovered by Extension::registerStoragePackages().
     */
    private function resolveTargetPath(string $packageCode, string $packageType): string
    {
        $shortName = $this->getShortName($packageCode);
        $vendorName = $this->getVendorName($packageCode);

        if ($packageType === 'theme') {
            return Storage::disk('local')->path('tipowerup/themes/'.$vendorName.'-'.$shortName);
        }

        return Storage::disk('local')->path('tipowerup/extensions/'.$vendorName.'/'.$shortName);
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

        } catch (Throwable $e) {
            Log::warning('DirectInstaller: Failed to publish theme assets', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
