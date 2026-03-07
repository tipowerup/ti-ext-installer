<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InstallLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'tip_install_logs';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'package_code',
        'action',
        'method',
        'from_version',
        'to_version',
        'success',
        'error_message',
        'stack_trace',
        'package_type',
        'php_version',
        'ti_version',
        'memory_limit_mb',
        'duration_seconds',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'success' => 'boolean',
    ];

    /**
     * Log an action for a package, auto-capturing environment info.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function logAction(string $packageCode, string $action, string $method, array $extra = []): self
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryMb = self::parseMemoryLimitMb($memoryLimit ?: '0');

        return self::create([
            'package_code' => $packageCode,
            'action' => $action,
            'method' => $method,
            'from_version' => $extra['from_version'] ?? null,
            'to_version' => $extra['to_version'] ?? null,
            'success' => $extra['success'] ?? false,
            'error_message' => $extra['error_message'] ?? null,
            'stack_trace' => $extra['stack_trace'] ?? null,
            'package_type' => $extra['package_type'] ?? null,
            'php_version' => PHP_VERSION,
            'ti_version' => app()->version(),
            'memory_limit_mb' => $memoryMb,
            'duration_seconds' => $extra['duration_seconds'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Scope to filter by package code.
     */
    public function scopeByPackage(Builder $query, string $packageCode): Builder
    {
        return $query->where('package_code', $packageCode);
    }

    /**
     * Scope to filter only failed entries.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('success', false);
    }

    /**
     * Parse a PHP memory_limit string into megabytes.
     */
    private static function parseMemoryLimitMb(string $limit): int
    {
        if ($limit === '-1') {
            return -1;
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
}
