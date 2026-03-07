<?php

declare(strict_types=1);

use Tipowerup\Installer\Models\License;

beforeEach(function (): void {
    $migrationPath = dirname(__DIR__, 3).'/database/migrations';
    $this->loadMigrationsFrom($migrationPath);
});

it('creates a license record with required fields', function (): void {
    $license = License::create([
        'package_code' => 'tipowerup/ti-ext-darkmode',
        'package_name' => 'Dark Mode',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    expect($license->exists)->toBeTrue()
        ->and($license->package_code)->toBe('tipowerup/ti-ext-darkmode')
        ->and($license->is_active)->toBeTrue();
});

it('active scope returns only active licenses', function (): void {
    License::create([
        'package_code' => 'tipowerup/ti-ext-active',
        'package_name' => 'Active',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    License::create([
        'package_code' => 'tipowerup/ti-ext-inactive',
        'package_name' => 'Inactive',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => false,
    ]);

    $active = License::active()->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->package_code)->toBe('tipowerup/ti-ext-active');
});

it('byPackage scope filters by package code', function (): void {
    License::create([
        'package_code' => 'tipowerup/ti-ext-darkmode',
        'package_name' => 'Dark Mode',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    License::create([
        'package_code' => 'tipowerup/ti-ext-other',
        'package_name' => 'Other',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    $result = License::byPackage('tipowerup/ti-ext-darkmode')->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->package_code)->toBe('tipowerup/ti-ext-darkmode');
});

it('detects expired licenses', function (): void {
    $expired = License::create([
        'package_code' => 'tipowerup/ti-ext-expired',
        'package_name' => 'Expired',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $notExpired = License::create([
        'package_code' => 'tipowerup/ti-ext-valid',
        'package_name' => 'Valid',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addYear(),
        'is_active' => true,
    ]);

    $noExpiry = License::create([
        'package_code' => 'tipowerup/ti-ext-lifetime',
        'package_name' => 'Lifetime',
        'package_type' => 'extension',
        'version' => '1.0.0',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'expires_at' => null,
        'is_active' => true,
    ]);

    expect($expired->isExpired())->toBeTrue()
        ->and($notExpired->isExpired())->toBeFalse()
        ->and($noExpiry->isExpired())->toBeFalse();
});
