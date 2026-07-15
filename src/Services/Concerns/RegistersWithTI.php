<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services\Concerns;

use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Models\Theme as ThemeModel;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Throwable;
use Tipowerup\Installer\Exceptions\PackageInstallationException;

/**
 * Provides TastyIgniter registration helpers for installer services.
 * Handles loading and installing extensions and themes via TI's managers.
 */
trait RegistersWithTI
{
    /**
     * Register a package with TastyIgniter after installation.
     *
     * Uses Reflection to resolve the real TI extension code from the loaded extension class,
     * then delegates to ExtensionManager::installExtension() or ThemeManager::installTheme().
     *
     * @throws PackageInstallationException
     */
    private function registerWithTI(string $packageCode, string $type, string $path): void
    {
        try {
            if ($type === 'extension') {
                $extensionManager = resolve(ExtensionManager::class);
                $extension = $extensionManager->loadExtension($path);
                $extensionCode = $extensionManager->getIdentifier(
                    (new ReflectionClass($extension))->getNamespaceName()
                );

                if ($extensionCode === false || $extensionCode === '') {
                    throw new PackageInstallationException(
                        'Failed to determine extension code after loading: '.$packageCode
                    );
                }

                $extensionManager->installExtension($extensionCode);
            } else {
                $themeManager = resolve(ThemeManager::class);
                $theme = $themeManager->loadTheme($path);
                if ($theme === null) {
                    throw new PackageInstallationException(
                        'Failed to load theme after install: '.$path
                    );
                }

                // TI's ThemeManager::installTheme() omits the `data` column, which has a
                // NOT NULL JSON CHECK constraint on fresh rows. Pre-seed the row so the
                // subsequent installTheme() call updates an existing record. Best-effort
                // only: if the model layer is in a broken state (e.g. residual static
                // state from a prior test polluting Igniter's ExtendableTrait), we let
                // installTheme() be the source of truth for success.
                try {
                    ThemeModel::updateOrCreate(
                        ['code' => $theme->name],
                        ['name' => $theme->label ?? $theme->name, 'data' => []],
                    );
                } catch (Throwable $seedError) {
                    Log::debug('Theme row pre-seed skipped; falling through to installTheme()', [
                        'theme' => $theme->name,
                        'error' => $seedError->getMessage(),
                    ]);
                }

                $themeManager->installTheme($theme->name);

                try {
                    ThemeModel::where('code', $theme->name)->update(['status' => true]);
                } catch (Throwable $statusError) {
                    Log::debug('Theme status update skipped', [
                        'theme' => $theme->name,
                        'error' => $statusError->getMessage(),
                    ]);
                }
            }
        } catch (PackageInstallationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PackageInstallationException(
                'Failed to register with TastyIgniter: '.$e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Resolve the TI extension code (dot notation) from the vendor path.
     * Returns an empty string if the extension cannot be resolved.
     */
    private function resolveExtensionCode(string $vendorPath): string
    {
        $extensionManager = resolve(ExtensionManager::class);
        $extension = $extensionManager->loadExtension($vendorPath);

        if ($extension === null) {
            return '';
        }

        $code = $extensionManager->getIdentifier(
            (new ReflectionClass($extension))->getNamespaceName()
        );

        return ($code !== false && $code !== '') ? $code : '';
    }
}
