<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use DateTime;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Models\InstallLog;
use Tipowerup\Installer\Models\License;

class PackageInstaller
{
    public function __construct(
        private readonly HostingDetector $hostingDetector,
        private readonly DirectInstaller $directInstaller,
        private readonly ComposerInstaller $composerInstaller,
        private readonly PowerUpApiClient $apiClient,
    ) {}

    /**
     * Install using best method (or forced method).
     */
    public function install(string $packageCode, ?string $forceMethod = null): array
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('PackageInstaller: Starting installation', [
                'package_code' => $packageCode,
                'force_method' => $forceMethod,
            ]);

            // Check if already installed
            $existingLicense = License::byPackage($packageCode)->first();
            if ($existingLicense) {
                throw new PackageInstallationException(
                    sprintf("Package '%s' is already installed", $packageCode)
                );
            }

            // Verify license with API
            $licenseData = $this->apiClient->verifyLicense($packageCode);

            // Determine installation method
            $method = $forceMethod ?? $this->hostingDetector->getRecommendedMethod();

            Log::info('PackageInstaller: Using installation method', [
                'package_code' => $packageCode,
                'method' => $method,
            ]);

            // Perform installation based on method
            $result = match ($method) {
                'composer' => $this->composerInstaller->install($packageCode, $licenseData),
                'direct' => $this->directInstaller->install($packageCode, $licenseData),
                default => throw new PackageInstallationException('Invalid installation method: '.$method),
            };

            // Create license record
            License::create([
                'package_code' => $packageCode,
                'package_name' => $licenseData['package_name'] ?? $packageCode,
                'package_type' => $licenseData['package_type'] ?? 'extension',
                'version' => $result['version'] ?? 'unknown',
                'install_method' => $method,
                'installed_at' => now(),
                'updated_at' => now(),
                'expires_at' => isset($licenseData['expires_at'])
                    ? new DateTime($licenseData['expires_at'])
                    : null,
                'is_active' => true,
            ]);

            // Log successful installation
            InstallLog::logAction(
                packageCode: $packageCode,
                action: 'install',
                method: $method,
                extra: [
                    'success' => true,
                    'to_version' => $result['version'] ?? null,
                    'duration_seconds' => $result['duration_seconds'] ?? null,
                ]
            );

            $duration = microtime(true) - $startTime;

            Log::info('PackageInstaller: Installation completed', [
                'package_code' => $packageCode,
                'method' => $method,
                'duration_seconds' => round($duration, 2),
            ]);

            return array_merge($result, [
                'package_code' => $packageCode,
            ]);

        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            // Log failed installation
            InstallLog::logAction(
                packageCode: $packageCode,
                action: 'install',
                method: $forceMethod ?? 'auto',
                extra: [
                    'success' => false,
                    'error_message' => $e->getMessage(),
                    'duration_seconds' => round($duration, 2),
                ]
            );

            Log::error('PackageInstaller: Installation failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Update using the method the package was originally installed with.
     */
    public function update(string $packageCode): array
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('PackageInstaller: Starting update', [
                'package_code' => $packageCode,
            ]);

            // Get existing license
            $license = License::byPackage($packageCode)->first();

            if (!$license) {
                throw new PackageInstallationException(
                    sprintf("Package '%s' is not installed", $packageCode)
                );
            }

            $method = $license->install_method ?? $this->getInstalledMethod($packageCode);

            if (!$method) {
                throw new PackageInstallationException(
                    sprintf("Cannot determine installation method for '%s'", $packageCode)
                );
            }

            Log::info('PackageInstaller: Using update method', [
                'package_code' => $packageCode,
                'method' => $method,
            ]);

            // Verify license is still valid
            $licenseData = $this->apiClient->verifyLicense($packageCode);

            // Store current version
            $currentVersion = $license->version;

            // Perform update based on method
            $result = match ($method) {
                'composer' => $this->composerInstaller->update($packageCode),
                'direct' => $this->directInstaller->update($packageCode, $licenseData),
                default => throw new PackageInstallationException('Invalid installation method: '.$method),
            };

            // Update license record
            $license->update([
                'version' => $result['to_version'] ?? $result['version'] ?? 'unknown',
                'updated_at' => now(),
                'expires_at' => isset($licenseData['expires_at'])
                    ? new DateTime($licenseData['expires_at'])
                    : null,
            ]);

            // Log successful update
            InstallLog::logAction(
                packageCode: $packageCode,
                action: 'update',
                method: $method,
                extra: [
                    'success' => true,
                    'from_version' => $currentVersion,
                    'to_version' => $result['to_version'] ?? $result['version'] ?? null,
                    'duration_seconds' => $result['duration_seconds'] ?? null,
                ]
            );

            $duration = microtime(true) - $startTime;

            Log::info('PackageInstaller: Update completed', [
                'package_code' => $packageCode,
                'from_version' => $currentVersion,
                'to_version' => $result['to_version'] ?? $result['version'] ?? null,
                'duration_seconds' => round($duration, 2),
            ]);

            return array_merge($result, [
                'package_code' => $packageCode,
            ]);

        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            // Log failed update
            InstallLog::logAction(
                packageCode: $packageCode,
                action: 'update',
                method: $license->install_method ?? 'unknown',
                extra: [
                    'success' => false,
                    'error_message' => $e->getMessage(),
                    'duration_seconds' => round($duration, 2),
                ]
            );

            Log::error('PackageInstaller: Update failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Uninstall using the method the package was originally installed with.
     */
    public function uninstall(string $packageCode): void
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('PackageInstaller: Starting uninstall', [
                'package_code' => $packageCode,
            ]);

            // Get existing license
            $license = License::byPackage($packageCode)->first();

            $method = $license?->install_method ?? $this->getInstalledMethod($packageCode);

            if (!$method) {
                throw new PackageInstallationException(
                    sprintf("Cannot determine installation method for '%s'", $packageCode)
                );
            }

            Log::info('PackageInstaller: Using uninstall method', [
                'package_code' => $packageCode,
                'method' => $method,
            ]);

            // Perform uninstall based on method
            match ($method) {
                'composer' => $this->composerInstaller->uninstall($packageCode),
                'direct' => $this->directInstaller->uninstall($packageCode),
                default => throw new PackageInstallationException('Invalid installation method: '.$method),
            };

            // Remove license record
            if ($license) {
                $license->delete();
            }

            // Log successful uninstall
            InstallLog::logAction(
                packageCode: $packageCode,
                action: 'uninstall',
                method: $method,
                extra: [
                    'success' => true,
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                ]
            );

            $duration = microtime(true) - $startTime;

            Log::info('PackageInstaller: Uninstall completed', [
                'package_code' => $packageCode,
                'duration_seconds' => round($duration, 2),
            ]);

        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            // Log failed uninstall
            InstallLog::logAction(
                packageCode: $packageCode,
                action: 'uninstall',
                method: $license?->install_method ?? 'unknown',
                extra: [
                    'success' => false,
                    'error_message' => $e->getMessage(),
                    'duration_seconds' => round($duration, 2),
                ]
            );

            Log::error('PackageInstaller: Uninstall failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Get installed method for a package.
     */
    public function getInstalledMethod(string $packageCode): ?string
    {
        $this->validatePackageCode($packageCode);

        // Check License model first
        $license = License::byPackage($packageCode)->first();
        if ($license && $license->install_method) {
            return $license->install_method;
        }

        // Fall back to filesystem detection
        $shortName = $this->getShortName($packageCode);
        $vendorName = $this->getVendorName($packageCode);

        // Check storage extensions directory (direct installation)
        $extensionPath = storage_path('app/tipowerup/extensions/'.$vendorName.'/'.$shortName);
        if (File::exists($extensionPath)) {
            return 'direct';
        }

        // Check storage themes directory (direct installation)
        $themePath = storage_path('app/tipowerup/themes/'.$vendorName.'-'.$shortName);
        if (File::exists($themePath)) {
            return 'direct';
        }

        // Check vendor directory (Composer installation)
        $composerPackageName = $this->getComposerPackageName($packageCode);
        $vendorPath = base_path('vendor/'.$composerPackageName);
        if (File::exists($vendorPath)) {
            return 'composer';
        }

        return null;
    }

    /**
     * Check updates for all installed packages.
     */
    public function checkUpdates(): array
    {
        try {
            Log::info('PackageInstaller: Checking for updates');

            // Get all active licenses
            $licenses = License::active()->get();

            if ($licenses->isEmpty()) {
                return [];
            }

            // Build array of installed packages with versions
            $installedPackages = $licenses->map(fn (License $license): array => [
                'package_code' => $license->package_code,
                'version' => $license->version,
            ])->toArray();

            // Check for updates via API
            $updates = $this->apiClient->checkUpdates($installedPackages);

            Log::info('PackageInstaller: Update check completed', [
                'packages_checked' => count($installedPackages),
                'updates_available' => count($updates['updates'] ?? []),
            ]);

            return $updates;

        } catch (Throwable $e) {
            Log::error('PackageInstaller: Update check failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
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
     * Convert package code to Composer package name.
     */
    private function getComposerPackageName(string $packageCode): string
    {
        return str_replace('.', '/', $packageCode);
    }
}
