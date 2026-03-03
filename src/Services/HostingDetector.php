<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;
use ZipArchive;

class HostingDetector
{
    private const string CACHE_KEY = 'tipowerup.hosting_analysis';

    private const int CACHE_TTL_SECONDS = 86400; // 24 hours

    public function analyze(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => $this->performAnalysis());
    }

    /**
     * Run a fresh analysis, bypassing and replacing the cache.
     */
    public function freshAnalyze(): array
    {
        Cache::forget(self::CACHE_KEY);

        $result = $this->performAnalysis();

        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL_SECONDS);

        return $result;
    }

    private function performAnalysis(): array
    {
        return [
            'can_exec' => $this->canExec(),
            'can_shell_exec' => $this->canShellExec(),
            'can_proc_open' => $this->canProcOpen(),
            'memory_limit_mb' => $this->getMemoryLimitMB(),
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'has_zip_archive' => $this->hasZipArchive(),
            'has_curl' => $this->hasCurl(),
            'composer_available' => $this->isComposerAvailable(),
            'composer_source' => $this->getComposerSource(),
            'storage_writable' => $this->isStorageWritable(),
            'vendor_writable' => $this->isVendorWritable(),
            'recommended_method' => $this->getRecommendedMethod(),
        ];
    }

    public function canExec(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        return !$this->isFunctionDisabled('exec');
    }

    public function canShellExec(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        return !$this->isFunctionDisabled('shell_exec');
    }

    public function canProcOpen(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        return !$this->isFunctionDisabled('proc_open');
    }

    public function getMemoryLimitMB(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return -1; // Unlimited
        }

        if (!is_string($limit)) {
            return 0;
        }

        $unit = strtoupper(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'G' => $value * 1024,
            'M' => $value,
            'K' => (int) ($value / 1024),
            default => (int) ($value / (1024 * 1024)),
        };
    }

    public function isComposerAvailable(): bool
    {
        return $this->getComposerSource() !== null;
    }

    public function isSystemComposerAvailable(): bool
    {
        if (!$this->canExec()) {
            return false;
        }

        try {
            exec('composer --version 2>&1', $output, $exitCode);
            if ($exitCode === 0) {
                return true;
            }
        } catch (Throwable) {
            // Fall through to path checks
        }

        // Composer not in PATH — check common locations
        foreach ($this->getComposerSearchPaths() as $path) {
            if (is_file($path) && is_executable($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the full path to the system composer binary, or null if not found.
     */
    public function getComposerBinaryPath(): ?string
    {
        if (!$this->canExec()) {
            return null;
        }

        try {
            exec('which composer 2>/dev/null', $output, $exitCode);
            if ($exitCode === 0 && !empty($output[0])) {
                return $output[0];
            }
        } catch (Throwable) {
            // Fall through
        }

        foreach ($this->getComposerSearchPaths() as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function getComposerSearchPaths(): array
    {
        $paths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
        ];

        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '');
        if ($home !== '') {
            $paths[] = $home.'/.composer/vendor/bin/composer';
            $paths[] = $home.'/.config/composer/vendor/bin/composer';
        }

        return $paths;
    }

    public function isComposerPharAvailable(): bool
    {
        return file_exists(storage_path('app/tipowerup/bin/composer.phar'));
    }

    /**
     * Determine the source of Composer: 'system', 'downloaded', or null.
     */
    public function getComposerSource(): ?string
    {
        if ($this->isSystemComposerAvailable()) {
            return 'system';
        }

        if ($this->isComposerPharAvailable()) {
            return 'downloaded';
        }

        return null;
    }

    public function getRecommendedMethod(): string
    {
        $memory = $this->getMemoryLimitMB();
        $hasAdequateMemory = ($memory >= 128 || $memory === -1);

        if ($this->canProcOpen() && $hasAdequateMemory && $this->isComposerAvailable() && $this->isComposerWritable()) {
            return 'composer';
        }

        return 'direct';
    }

    /**
     * Check whether all paths required by the Composer install method are writable.
     */
    public function isComposerWritable(): bool
    {
        return empty($this->getUnwritableComposerPaths());
    }

    /**
     * Return a list of paths required by Composer that are not currently writable.
     *
     * @return array<int, string>
     */
    public function getUnwritableComposerPaths(): array
    {
        $failed = [];

        // composer.json must be writable (or its parent directory if the file doesn't exist yet)
        $composerJson = base_path('composer.json');
        if (file_exists($composerJson)) {
            if (!is_writable($composerJson)) {
                $failed[] = $composerJson;
            }
        } elseif (!is_writable(dirname($composerJson))) {
            $failed[] = $composerJson;
        }

        // composer.lock must be writable (or its parent directory if it doesn't exist yet)
        $composerLock = base_path('composer.lock');
        if (file_exists($composerLock)) {
            if (!is_writable($composerLock)) {
                $failed[] = $composerLock;
            }
        } elseif (!is_writable(dirname($composerLock))) {
            $failed[] = $composerLock;
        }

        // vendor directory must exist and be writable
        $vendor = base_path('vendor');
        if (!is_dir($vendor) || !is_writable($vendor)) {
            $failed[] = $vendor;
        }

        return $failed;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function hasZipArchive(): bool
    {
        return class_exists(ZipArchive::class);
    }

    private function hasCurl(): bool
    {
        return function_exists('curl_version');
    }

    private function isStorageWritable(): bool
    {
        $storagePath = storage_path('app/tipowerup');

        try {
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            return is_writable($storagePath);
        } catch (Throwable) {
            return false;
        }
    }

    private function isVendorWritable(): bool
    {
        $vendorPath = base_path('vendor');

        return is_dir($vendorPath) && is_writable($vendorPath);
    }

    private function isFunctionDisabled(string $function): bool
    {
        $disabledFunctions = ini_get('disable_functions');

        if (!is_string($disabledFunctions) || $disabledFunctions === '') {
            return false;
        }

        $disabled = array_map(trim(...), explode(',', $disabledFunctions));

        return in_array($function, $disabled, true);
    }
}
