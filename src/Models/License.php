<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'tipowerup_licenses';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'package_code',
        'package_name',
        'package_type',
        'version',
        'install_method',
        'license_hash',
        'installed_at',
        'updated_at',
        'expires_at',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'installed_at' => 'datetime',
        'updated_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active licenses.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by package code.
     */
    public function scopeByPackage(Builder $query, string $packageCode): Builder
    {
        return $query->where('package_code', $packageCode);
    }

    /**
     * Determine if the license is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
