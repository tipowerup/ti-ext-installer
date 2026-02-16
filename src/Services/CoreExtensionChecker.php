<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\Cache;

class CoreExtensionChecker
{
    private const string CACHE_KEY = 'tipowerup.core_extensions_check';

    private const int CACHE_TTL_SECONDS = 3600; // 1 hour

    /**
     * Required TI core extensions with their display names.
     *
     * @var array<string, string>
     */
    private const array REQUIRED_EXTENSIONS = [
        'igniter.cart' => 'Shopping Cart',
        'igniter.user' => 'User & Authentication',
        'igniter.local' => 'Local/Restaurant',
        'igniter.pages' => 'Pages',
        'igniter.frontend' => 'Frontend Module',
    ];

    /**
     * Check all required extensions and return their status.
     *
     * @return array<int, array{code: string, name: string, installed: bool, manage_url: string}>
     */
    public function check(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $results = [];

            $extensionManager = resolve(ExtensionManager::class);
            $manageUrl = admin_url('igniter/system/extensions');

            foreach (self::REQUIRED_EXTENSIONS as $code => $name) {
                $results[] = [
                    'code' => $code,
                    'name' => $name,
                    'installed' => $extensionManager->hasExtension($code),
                    'manage_url' => $manageUrl,
                ];
            }

            return $results;
        });
    }

    /**
     * Get only the extensions that are NOT installed.
     *
     * @return array<int, array{code: string, name: string, installed: bool, manage_url: string}>
     */
    public function getMissing(): array
    {
        $allExtensions = $this->check();

        return array_values(array_filter($allExtensions, fn (array $ext): bool => !$ext['installed']));
    }

    /**
     * Check if all required extensions are installed.
     */
    public function allInstalled(): bool
    {
        return $this->getMissing() === [];
    }

    /**
     * Clear the cached check results.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
