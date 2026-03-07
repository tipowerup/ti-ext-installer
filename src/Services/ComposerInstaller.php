<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Igniter\Main\Models\Theme;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Services\Concerns\ClearsInstallerCaches;
use Tipowerup\Installer\Services\Concerns\RegistersWithTI;
use Tipowerup\Installer\Services\Concerns\ValidatesPackageCode;

class ComposerInstaller
{
    use ClearsInstallerCaches;
    use RegistersWithTI;
    use ValidatesPackageCode;

    private const int TIMEOUT_SECONDS = 600;

    private ?string $authToken = null;

    private function repoUrl(): string
    {
        return config('tipowerup.installer.composer_repo_url');
    }

    /**
     * Install a package via Composer.
     */
    public function install(string $packageCode, array $licenseData, ?callable $onProgress = null): array
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('ComposerInstaller: Starting installation', [
                'package_code' => $packageCode,
            ]);

            $this->authToken = $licenseData['auth_token'] ?? throw new PackageInstallationException(
                'Authentication token not provided in license data'
            );

            $version = $licenseData['version'] ?? '*';
            $packageType = $licenseData['package_type'] ?? 'extension';

            // Ensure the TI PowerUp repository is configured
            $this->ensureRepository();

            // Install package via Composer
            $output = $this->runComposer([
                'require',
                sprintf('%s:%s', $packageCode, $version),
                '--no-interaction',
                '--no-progress',
                '--prefer-dist',
                '--optimize-autoloader',
                '--update-with-all-dependencies',
            ], $onProgress);

            // Determine installed path
            $vendorPath = base_path('vendor/'.$packageCode);

            // Register with TastyIgniter
            $this->registerWithTI($packageCode, $packageType, $vendorPath);

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
    public function update(string $packageCode, ?callable $onProgress = null): array
    {
        $this->validatePackageCode($packageCode);

        $startTime = microtime(true);

        try {
            Log::info('ComposerInstaller: Starting update', [
                'package_code' => $packageCode,
            ]);

            // Get current version before update
            $currentVersion = $this->getInstalledVersion($packageCode);

            // Update package via Composer
            $output = $this->runComposer([
                'update',
                $packageCode,
                '--no-interaction',
                '--no-progress',
                '--prefer-dist',
                '--with-dependencies',
                '--optimize-autoloader',
            ], $onProgress);

            // Get new version after update
            $newVersion = $this->getInstalledVersion($packageCode);

            // Determine package type from vendor path
            $vendorPath = base_path('vendor/'.$packageCode);
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
     * Uninstall a package using direct PHP file operations instead of `composer remove`.
     */
    public function uninstall(string $packageCode): void
    {
        $this->validatePackageCode($packageCode);

        try {
            Log::info('ComposerInstaller: Starting fast uninstall', [
                'package_code' => $packageCode,
            ]);

            $vendorPath = base_path('vendor/'.$packageCode);
            $isExtension = File::exists($vendorPath.'/Extension.php');

            // Step 1: Resolve TI code before deleting files
            // Step 2: Unregister from TI using correct TI code
            if ($isExtension) {
                $extensionCode = $this->resolveExtensionCode($vendorPath);
                if ($extensionCode !== '') {
                    try {
                        $extensionManager = resolve(ExtensionManager::class);
                        $extensionManager->uninstallExtension($extensionCode);
                    } catch (Throwable $e) {
                        Log::warning('ComposerInstaller: Failed to unregister extension', [
                            'package_code' => $packageCode,
                            'extension_code' => $extensionCode,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } else {
                // Read theme code from package's composer.json extra field
                $pkgComposerPath = $vendorPath.'/composer.json';
                if (File::exists($pkgComposerPath)) {
                    $pkgComposer = json_decode(file_get_contents($pkgComposerPath), true);
                    $themeCode = $pkgComposer['extra']['tastyigniter-theme']['code'] ?? null;
                    if ($themeCode !== null) {
                        try {
                            // Don't call ThemeManager::deleteTheme() — it spawns composer remove
                            Theme::where('code', $themeCode)->delete();
                        } catch (Throwable $e) {
                            Log::warning('ComposerInstaller: Failed to unregister theme', [
                                'package_code' => $packageCode,
                                'theme_code' => $themeCode,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // Step 3: Remove from composer.json
            $this->removeFromComposerJson($packageCode);

            // Step 4: Remove from composer.lock
            $this->removeFromComposerLock($packageCode);

            // Step 5: Remove from vendor/composer/installed.json
            $this->removeFromInstalledJson($packageCode);

            // Step 6: Delete vendor directory
            if (File::exists($vendorPath)) {
                File::deleteDirectory($vendorPath);
            }

            // Step 7: Regenerate autoload (fast — no dependency resolution)
            $this->runComposer(['dump-autoload', '--optimize', '--no-interaction']);

            // Step 8: Clear caches
            $this->clearCaches();

            Log::info('ComposerInstaller: Fast uninstall successful', [
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
     * Determine the Composer binary to use.
     *
     * Priority: system composer -> downloaded phar -> auto-download phar -> fail.
     *
     * @return list<string>
     */
    private function findComposerBinary(): array
    {
        // 1. Try system composer
        try {
            exec('composer --version 2>&1', $output, $exitCode);

            if ($exitCode === 0) {
                return ['composer'];
            }
        } catch (Throwable) {
            // System composer not available
        }

        // 2. Check downloaded phar
        $pharManager = resolve(ComposerPharManager::class);

        if ($pharManager->isPharAvailable()) {
            return $pharManager->getPharCommand();
        }

        // 3. Safety net: try to download phar
        if ($pharManager->download()) {
            return $pharManager->getPharCommand();
        }

        throw new PackageInstallationException(
            'Composer is not available. Neither system composer nor composer.phar could be found or downloaded.'
        );
    }

    /**
     * Ensure the TI PowerUp Composer repository is configured in composer.json.
     */
    private function ensureRepository(): void
    {
        $composerJsonPath = base_path('composer.json');

        if (!file_exists($composerJsonPath)) {
            return;
        }

        $composerData = json_decode(file_get_contents($composerJsonPath), true);

        // Check if repository already exists
        foreach ($composerData['repositories'] ?? [] as $repo) {
            if (($repo['url'] ?? '') === $this->repoUrl()) {
                return;
            }
        }

        // Add repository via composer config
        $this->runComposer([
            'config',
            'repositories.tipowerup',
            'composer',
            $this->repoUrl(),
            '--no-interaction',
        ]);

        // Repository added to composer.json
    }

    /**
     * Run Composer command using Symfony Process.
     */
    private function runComposer(array $command, ?callable $onProgress = null): string
    {
        $binary = $this->findComposerBinary();
        array_unshift($command, ...$binary);

        $env = [
            'COMPOSER_MEMORY_LIMIT' => '-1',
            'COMPOSER_NO_AUDIT' => '1',
        ];

        if ($this->authToken !== null) {
            $env['COMPOSER_AUTH'] = json_encode([
                'bearer' => [
                    parse_url($this->repoUrl(), PHP_URL_HOST) => $this->authToken,
                ],
            ]);
        }

        $process = new Process(
            command: $command,
            cwd: base_path(),
            env: $env,
            timeout: self::TIMEOUT_SECONDS
        );

        try {
            if ($onProgress !== null) {
                $process->start();
                $lastPercent = 0;
                $stderrLines = [];

                foreach ($process as $type => $data) {
                    if ($type === Process::ERR && trim($data) !== '') {
                        $line = trim($data);
                        $stderrLines[] = $line;
                        $newPercent = $this->parseComposerProgress($line, $lastPercent);
                        if ($newPercent > $lastPercent) {
                            $lastPercent = $newPercent;
                            $onProgress($lastPercent, $line);
                        }
                    }
                }

                if (!$process->isSuccessful()) {
                    $errorOutput = implode(PHP_EOL, $stderrLines);
                    Log::error('ComposerInstaller: Composer command failed', [
                        'exit_code' => $process->getExitCode(),
                        'exit_text' => $process->getExitCodeText(),
                        'output' => $process->getOutput(),
                        'error_output' => $errorOutput,
                    ]);

                    throw new PackageInstallationException(
                        sprintf('Composer command failed: %s%s%s', $process->getExitCodeText(), PHP_EOL, $errorOutput)
                    );
                }
            } else {
                $process->mustRun();
            }

            return $process->getOutput();

        } catch (PackageInstallationException $e) {
            throw $e;
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
     * Run migrations for extension.
     */
    public function runMigrations(string $packageCode): void
    {
        try {
            $vendorPath = base_path('vendor/'.$packageCode);
            $extensionCode = $this->resolveExtensionCode($vendorPath);

            if ($extensionCode !== '') {
                resolve(\Igniter\System\Classes\UpdateManager::class)->migrateExtension($extensionCode);

                return;
            }

            Log::warning('ComposerInstaller: Could not resolve extension code, skipping migrations', [
                'package_code' => $packageCode,
            ]);
        } catch (Throwable $e) {
            throw PackageInstallationException::migrationFailed(
                $packageCode,
                $e->getMessage()
            );
        }
    }

    private function parseComposerProgress(string $line, int $lastPercent): int
    {
        return match (true) {
            str_contains($line, 'Loading composer repositories') => 10,
            str_contains($line, 'Updating dependencies') => 20,
            str_contains($line, 'Resolving dependencies') => 30,
            str_contains($line, 'Dependency resolution completed') => 40,
            str_contains($line, 'Package operations') => 50,
            str_contains($line, 'Installing') || str_contains($line, 'Updating') => 60,
            str_contains($line, 'Downloading') => 65,
            str_contains($line, 'Extracting') => 70,
            str_contains($line, 'Generating') && str_contains($line, 'autoload') => 85,
            str_contains($line, 'Generated') && str_contains($line, 'autoload') => 90,
            str_contains($line, 'No security vulnerability') => 95,
            default => $lastPercent,
        };
    }

    /**
     * Remove a package from composer.json require/require-dev.
     */
    private function removeFromComposerJson(string $packageCode): void
    {
        $path = base_path('composer.json');

        if (!file_exists($path)) {
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        $changed = false;

        if (isset($data['require'][$packageCode])) {
            unset($data['require'][$packageCode]);
            $changed = true;
        }

        if (isset($data['require-dev'][$packageCode])) {
            unset($data['require-dev'][$packageCode]);
            $changed = true;
        }

        if ($changed) {
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }
    }

    /**
     * Remove a package from composer.lock.
     */
    private function removeFromComposerLock(string $packageCode): void
    {
        $path = base_path('composer.lock');

        if (!File::exists($path)) {
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        if ($data === null) {
            return;
        }

        $filter = fn (array $pkg): bool => ($pkg['name'] ?? '') !== $packageCode;

        $data['packages'] = array_values(array_filter($data['packages'] ?? [], $filter));
        $data['packages-dev'] = array_values(array_filter($data['packages-dev'] ?? [], $filter));

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * Remove a package from vendor/composer/installed.json.
     */
    private function removeFromInstalledJson(string $packageCode): void
    {
        $path = base_path('vendor/composer/installed.json');

        if (!File::exists($path)) {
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        if ($data === null) {
            return;
        }

        $data['packages'] = array_values(
            array_filter($data['packages'] ?? [], fn (array $pkg): bool => ($pkg['name'] ?? '') !== $packageCode)
        );

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}
