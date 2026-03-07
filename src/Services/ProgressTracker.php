<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Illuminate\Support\Facades\Cache;

class ProgressTracker
{
    private const string KEY_PREFIX = 'tipowerup_progress';

    private const int TTL_SECONDS = 3600; // 1 hour

    /**
     * Update progress for a package in a batch.
     */
    public function update(
        string $batchId,
        string $packageCode,
        string $stage,
        int $percent,
        string $message,
        ?string $error = null,
        ?string $errorCode = null,
        ?string $failedStage = null,
    ): void {
        $data = [
            'batch_id' => $batchId,
            'package_code' => $packageCode,
            'stage' => $stage,
            'progress_percent' => $percent,
            'message' => $message,
            'error' => $error,
            'error_code' => $errorCode,
            'failed_stage' => $failedStage,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->key($batchId, $packageCode), $data, self::TTL_SECONDS);
    }

    /**
     * Get progress for a specific package in a batch.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $batchId, string $packageCode): ?array
    {
        return Cache::get($this->key($batchId, $packageCode));
    }

    /**
     * Check if installation has been cancelled.
     */
    public function isCancelled(string $batchId, string $packageCode): bool
    {
        $data = $this->get($batchId, $packageCode);

        return $data !== null && ($data['stage'] ?? '') === 'cancelled';
    }

    /**
     * Mark an installation as cancelled.
     */
    public function cancel(string $batchId, string $packageCode, string $currentStage): void
    {
        $existing = $this->get($batchId, $packageCode) ?? [];

        $existing['stage'] = 'cancelled';
        $existing['error_code'] = 'cancelled';
        $existing['failed_stage'] = $currentStage;
        $existing['updated_at'] = now()->toIso8601String();

        Cache::put($this->key($batchId, $packageCode), $existing, self::TTL_SECONDS);
    }

    /**
     * Remove progress data for a package.
     */
    public function forget(string $batchId, string $packageCode): void
    {
        Cache::forget($this->key($batchId, $packageCode));
    }

    /**
     * Get overall batch progress across multiple packages.
     *
     * @param  array<string>  $packageCodes
     * @return array{overall_percent: int, packages: array<string, array{stage: string, percent: int}>}
     */
    public function getBatchProgress(string $batchId, array $packageCodes): array
    {
        $packages = [];
        $totalPercent = 0;
        $count = 0;

        foreach ($packageCodes as $packageCode) {
            $data = $this->get($batchId, $packageCode);

            if ($data !== null) {
                $packages[$packageCode] = [
                    'stage' => $data['stage'],
                    'percent' => $data['progress_percent'],
                ];
                $totalPercent += $data['progress_percent'];
                $count++;
            }
        }

        return [
            'overall_percent' => $count > 0 ? (int) ($totalPercent / $count) : 0,
            'packages' => $packages,
        ];
    }

    private function key(string $batchId, string $packageCode): string
    {
        return sprintf('%s:%s:%s', self::KEY_PREFIX, $batchId, $packageCode);
    }
}
