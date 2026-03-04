<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services\Concerns;

use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\ExtensionManager;
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
                $themeManager->loadTheme($path);
                $themeManager->installTheme($packageCode);
            }
        } catch (PackageInstallationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PackageInstallationException(
                'Failed to register with TastyIgniter: '.$e->getMessage()
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
