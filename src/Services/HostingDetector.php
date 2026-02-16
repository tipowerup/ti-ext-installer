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
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => [
            'can_exec' => $this->canExec(),
            'can_shell_exec' => $this->canShellExec(),
            'can_proc_open' => $this->canProcOpen(),
            'memory_limit_mb' => $this->getMemoryLimitMB(),
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'has_zip_archive' => $this->hasZipArchive(),
            'has_curl' => $this->hasCurl(),
            'composer_available' => $this->isComposerAvailable(),
            'storage_writable' => $this->isStorageWritable(),
            'vendor_writable' => $this->isVendorWritable(),
            'recommended_method' => $this->getRecommendedMethod(),
        ]);
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
        if (!$this->canExec()) {
            return false;
        }

        try {
            exec('composer --version 2>&1', $output, $exitCode);

            return $exitCode === 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function getRecommendedMethod(): string
    {
        $memory = $this->getMemoryLimitMB();
        $hasAdequateMemory = ($memory >= 512 || $memory === -1);

        if ($this->canProcOpen() && $hasAdequateMemory && $this->isComposerAvailable()) {
            return 'composer';
        }

        return 'direct';
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
