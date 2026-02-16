<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Throwable;
use Tipowerup\Installer\Exceptions\PackageInstallationException;

class ComposerInstaller
{
    private const string REPO_URL = 'https://packages.tipowerup.com';

    private const int TIMEOUT_SECONDS = 600;

    /**
     * Install a package via Composer.
     */
    public function install(string $packageCode, array $licenseData): array
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('ComposerInstaller: Starting installation', [
                'package_code' => $packageCode,
            ]);

            $authToken = $licenseData['auth_token'] ?? throw new PackageInstallationException(
                'Authentication token not provided in license data'
            );

            $version = $licenseData['version'] ?? '*';
            $packageType = $licenseData['package_type'] ?? 'extension';

            // Configure Composer repository if not already configured
            $this->configureRepository();

            // Configure authentication
            $this->configureAuth($authToken);

            // Get Composer package name
            $packageName = $this->getComposerPackageName($packageCode);

            // Install package via Composer
            $output = $this->runComposer([
                'require',
                sprintf('%s:%s', $packageName, $version),
                '--no-interaction',
                '--no-progress',
                '--prefer-dist',
            ]);

            Log::debug('ComposerInstaller: Composer output', [
                'output' => $output,
            ]);

            // Determine installed path
            $vendorPath = base_path('vendor/'.$packageName);

            // Register with TastyIgniter
            $this->registerWithTI($packageCode, $packageType, $vendorPath);

            // Run migrations for extensions
            if ($packageType === 'extension') {
                $this->runMigrations($packageCode);
            }

            // Clear caches
            $this->clearCaches();

            $duration = microtime(true) - $startTime;

            Log::info('ComposerInstaller: Installation successful', [
                'package_code' => $packageCode,
                'duration_seconds' => round($duration, 2),
            ]);

            return [
                'success' => true,
                'method' => 'composer',
                'version' => $version,
                'path' => $vendorPath,
                'duration_seconds' => round($duration, 2),
            ];

        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('ComposerInstaller: Installation failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing package via Composer.
     */
    public function update(string $packageCode): array
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('ComposerInstaller: Starting update', [
                'package_code' => $packageCode,
            ]);

            // Get Composer package name
            $packageName = $this->getComposerPackageName($packageCode);

            // Get current version before update
            $currentVersion = $this->getInstalledVersion($packageName);

            // Update package via Composer
            $output = $this->runComposer([
                'update',
                $packageName,
                '--no-interaction',
                '--no-progress',
                '--prefer-dist',
                '--with-dependencies',
            ]);

            Log::debug('ComposerInstaller: Composer output', [
                'output' => $output,
            ]);

            // Get new version after update
            $newVersion = $this->getInstalledVersion($packageName);

            // Determine package type from vendor path
            $vendorPath = base_path('vendor/'.$packageName);
            $packageType = File::exists($vendorPath.'/Extension.php') ? 'extension' : 'theme';

            // Run migrations for extensions
            if ($packageType === 'extension') {
                try {
                    $this->runMigrations($packageCode);
                } catch (Throwable $e) {
                    throw PackageInstallationException::migrationFailed($packageCode, $e->getMessage());
                }
            }

            // Clear caches
            $this->clearCaches();

            $duration = microtime(true) - $startTime;

            Log::info('ComposerInstaller: Update successful', [
                'package_code' => $packageCode,
                'from_version' => $currentVersion,
                'to_version' => $newVersion,
                'duration_seconds' => round($duration, 2),
            ]);

            return [
                'success' => true,
                'method' => 'composer',
                'from_version' => $currentVersion,
                'to_version' => $newVersion,
                'path' => $vendorPath,
                'duration_seconds' => round($duration, 2),
            ];

        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('ComposerInstaller: Update failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Uninstall a package via Composer.
     */
    public function uninstall(string $packageCode): void
    {
        $this->validatePackageCode($packageCode);

        try {
            Log::info('ComposerInstaller: Starting uninstall', [
                'package_code' => $packageCode,
            ]);

            // Get Composer package name
            $packageName = $this->getComposerPackageName($packageCode);

            // Determine package type before uninstall
            $vendorPath = base_path('vendor/'.$packageName);
            $packageType = File::exists($vendorPath.'/Extension.php') ? 'extension' : 'theme';

            // Unregister from TI before removal
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

            // Remove package via Composer
            $output = $this->runComposer([
                'remove',
                $packageName,
                '--no-interaction',
                '--no-progress',
            ]);

            Log::debug('ComposerInstaller: Composer output', [
                'output' => $output,
            ]);

            // Clear caches
            $this->clearCaches();

            Log::info('ComposerInstaller: Uninstall successful', [
                'package_code' => $packageCode,
            ]);

        } catch (Throwable $e) {
            Log::error('ComposerInstaller: Uninstall failed', [
                'package_code' => $packageCode,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Configure TI PowerUp Composer repository in composer.json.
     */
    private function configureRepository(): void
    {
        $composerJsonPath = base_path('composer.json');

        if (!File::exists($composerJsonPath)) {
            throw new PackageInstallationException('composer.json not found');
        }

        $composerData = json_decode(File::get($composerJsonPath), true);

        if ($composerData === null) {
            throw new PackageInstallationException('Failed to parse composer.json');
        }

        // Check if repository already exists
        $repositories = $composerData['repositories'] ?? [];
        $repoExists = false;

        foreach ($repositories as $repo) {
            if (isset($repo['url']) && $repo['url'] === self::REPO_URL) {
                $repoExists = true;

                break;
            }
        }

        // Add repository if not present
        if (!$repoExists) {
            $repositories[] = [
                'type' => 'composer',
                'url' => self::REPO_URL,
            ];

            $composerData['repositories'] = $repositories;

            // Write back to composer.json
            File::put(
                $composerJsonPath,
                json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
            );

            Log::info('ComposerInstaller: Added TI PowerUp repository to composer.json');
        }
    }

    /**
     * Configure HTTP basic authentication for TI PowerUp repository.
     */
    private function configureAuth(string $authToken): void
    {
        $authJsonPath = base_path('auth.json');

        // Read existing auth.json or create new structure
        $authData = File::exists($authJsonPath)
            ? json_decode(File::get($authJsonPath), true) ?? []
            : [];

        // Set HTTP basic auth for TI PowerUp repository
        $authData['http-basic'] ??= [];
        $authData['http-basic']['packages.tipowerup.com'] = [
            'username' => 'token',
            'password' => $authToken,
        ];

        // Write auth.json
        File::put(
            $authJsonPath,
            json_encode($authData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        // Set proper permissions (readable only by owner)
        chmod($authJsonPath, 0600);

        Log::info('ComposerInstaller: Configured authentication');
    }

    /**
     * Run Composer command using Symfony Process.
     */
    private function runComposer(array $command): string
    {
        // Prepend 'composer' to command
        array_unshift($command, 'composer');

        $process = new Process(
            command: $command,
            cwd: base_path(),
            env: [
                'COMPOSER_MEMORY_LIMIT' => '-1',
            ],
            timeout: self::TIMEOUT_SECONDS
        );

        try {
            Log::debug('ComposerInstaller: Running command', [
                'command' => implode(' ', $command),
            ]);

            $process->mustRun();

            return $process->getOutput();

        } catch (Throwable $e) {
            $errorOutput = $process->getErrorOutput();

            Log::error('ComposerInstaller: Composer command failed', [
                'command' => implode(' ', $command),
                'error' => $e->getMessage(),
                'output' => $process->getOutput(),
                'error_output' => $errorOutput,
            ]);

            throw new PackageInstallationException(
                sprintf('Composer command failed: %s%s%s', $e->getMessage(), PHP_EOL, $errorOutput)
            );
        }
    }

    /**
     * Convert package code to Composer package name.
     */
    private function getComposerPackageName(string $packageCode): string
    {
        return str_replace('.', '/', $packageCode);
    }

    /**
     * Get installed version of a package from composer.lock.
     */
    private function getInstalledVersion(string $packageName): ?string
    {
        $composerLockPath = base_path('composer.lock');

        if (!File::exists($composerLockPath)) {
            return null;
        }

        $lockData = json_decode(File::get($composerLockPath), true);

        if ($lockData === null) {
            return null;
        }

        $packages = array_merge(
            $lockData['packages'] ?? [],
            $lockData['packages-dev'] ?? []
        );

        foreach ($packages as $package) {
            if ($package['name'] === $packageName) {
                return $package['version'] ?? null;
            }
        }

        return null;
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
            throw new PackageInstallationException(
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
            Log::debug('ComposerInstaller: Running migrations', [
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
}
