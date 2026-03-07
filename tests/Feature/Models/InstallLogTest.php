<?php

declare(strict_types=1);

use Tipowerup\Installer\Models\InstallLog;

beforeEach(function (): void {
    $migrationPath = dirname(__DIR__, 3).'/database/migrations';
    $this->loadMigrationsFrom($migrationPath);
});

it('creates a log entry with logAction', function (): void {
    $log = InstallLog::logAction(
        'tipowerup/ti-ext-darkmode',
        'install',
        'direct',
        ['success' => true, 'to_version' => '1.0.0']
    );

    expect($log->exists)->toBeTrue()
        ->and($log->package_code)->toBe('tipowerup/ti-ext-darkmode')
        ->and($log->action)->toBe('install')
        ->and($log->method)->toBe('direct')
        ->and($log->success)->toBeTrue()
        ->and($log->to_version)->toBe('1.0.0');
});

it('auto-captures PHP version and TI version', function (): void {
    $log = InstallLog::logAction(
        'tipowerup/ti-ext-darkmode',
        'install',
        'direct',
        ['success' => true]
    );

    expect($log->php_version)->toBe(PHP_VERSION)
        ->and($log->ti_version)->not->toBeNull()
        ->and($log->memory_limit_mb)->not->toBeNull();
});

it('scopeFailed returns only failed entries', function (): void {
    InstallLog::logAction('tipowerup/ti-ext-a', 'install', 'direct', ['success' => true]);
    InstallLog::logAction('tipowerup/ti-ext-b', 'install', 'direct', [
        'success' => false,
        'error_message' => 'Download failed',
    ]);

    $failed = InstallLog::failed()->get();

    expect($failed)->toHaveCount(1)
        ->and($failed->first()->package_code)->toBe('tipowerup/ti-ext-b');
});

it('scopeByPackage filters correctly', function (): void {
    InstallLog::logAction('tipowerup/ti-ext-a', 'install', 'direct', ['success' => true]);
    InstallLog::logAction('tipowerup/ti-ext-b', 'install', 'direct', ['success' => true]);
    InstallLog::logAction('tipowerup/ti-ext-a', 'update', 'direct', ['success' => true]);

    $logs = InstallLog::byPackage('tipowerup/ti-ext-a')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('package_code')->unique()->toArray())->toBe(['tipowerup/ti-ext-a']);
});

it('stores stack_trace on failure', function (): void {
    $log = InstallLog::logAction(
        'tipowerup/ti-ext-darkmode',
        'install',
        'direct',
        [
            'success' => false,
            'error_message' => 'Something went wrong',
            'stack_trace' => 'Error at line 42 in SomeFile.php',
        ]
    );

    expect($log->stack_trace)->toBe('Error at line 42 in SomeFile.php')
        ->and($log->error_message)->toBe('Something went wrong');
});

it('stores duration_seconds', function (): void {
    $log = InstallLog::logAction(
        'tipowerup/ti-ext-darkmode',
        'install',
        'direct',
        ['success' => true, 'duration_seconds' => 5]
    );

    expect($log->duration_seconds)->toBe(5);
});
