<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Models;

use Illuminate\Database\Eloquent\Model;

class InstallLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'tipowerup_install_logs';

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
     * Log an action for a package.
     */
    public static function logAction(string $packageCode, string $action, string $method, array $extra = []): self
    {
        return self::create([
            'package_code' => $packageCode,
            'action' => $action,
            'method' => $method,
            'from_version' => $extra['from_version'] ?? null,
            'to_version' => $extra['to_version'] ?? null,
            'success' => $extra['success'] ?? false,
            'error_message' => $extra['error_message'] ?? null,
            'duration_seconds' => $extra['duration_seconds'] ?? null,
            'created_at' => now(),
        ]);
    }
}
