<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provides unified cache-clearing for installer services.
 * Runs all four Artisan cache commands and resets OPcache if available.
 */
trait ClearsInstallerCaches
{
    /**
     * Clear all application caches after a package operation.
     */
    private function clearCaches(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
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
