<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tipowerup\Installer\Livewire\InstallLogs;
use Tipowerup\Installer\Models\InstallLog;
use Tipowerup\Installer\Services\PowerUpApiClient;
use Tipowerup\Installer\Services\ReportBuilder;

beforeEach(function (): void {
    $migrationPath = dirname(__DIR__, 3).'/database/migrations';
    $this->loadMigrationsFrom($migrationPath);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function createTestLog(array $overrides = []): InstallLog
{
    return InstallLog::create(array_merge([
        'package_code' => 'tipowerup/ti-ext-test',
        'action' => 'install',
        'method' => 'direct',
        'success' => true,
        'php_version' => PHP_VERSION,
        'ti_version' => '4.0.0',
        'memory_limit_mb' => 256,
        'duration_seconds' => 5,
        'created_at' => now(),
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Happy paths
// ---------------------------------------------------------------------------

it('renders the install logs component', function (): void {
    Livewire::test(InstallLogs::class)
        ->assertSuccessful()
        ->assertSet('logLimit', 100)
        ->assertSet('filterPackage', '')
        ->assertSet('filterAction', '')
        ->assertSet('filterSuccess', '');
});

it('displays logs when present', function (): void {
    createTestLog();
    createTestLog(['package_code' => 'tipowerup/ti-ext-other', 'action' => 'update']);

    $component = Livewire::test(InstallLogs::class);

    expect($component->get('logs'))->toHaveCount(2);
});

it('filters logs by package code', function (): void {
    createTestLog(['package_code' => 'tipowerup/ti-ext-alpha']);
    createTestLog(['package_code' => 'tipowerup/ti-ext-beta']);

    $component = Livewire::test(InstallLogs::class)
        ->set('filterPackage', 'alpha');

    expect($component->get('logs'))->toHaveCount(1)
        ->and($component->get('logs')[0]['package_code'])->toBe('tipowerup/ti-ext-alpha');
});

it('filters logs by action', function (): void {
    createTestLog(['action' => 'install']);
    createTestLog(['action' => 'update']);
    createTestLog(['action' => 'uninstall']);

    $component = Livewire::test(InstallLogs::class)
        ->set('filterAction', 'update');

    expect($component->get('logs'))->toHaveCount(1)
        ->and($component->get('logs')[0]['action'])->toBe('update');
});

it('filters logs by success status', function (): void {
    createTestLog(['success' => true]);
    createTestLog(['success' => false, 'error_message' => 'Something broke']);

    $component = Livewire::test(InstallLogs::class)
        ->set('filterSuccess', '0');

    expect($component->get('logs'))->toHaveCount(1)
        ->and($component->get('logs')[0]['success'])->toBeFalse();
});

it('respects log limit', function (): void {
    for ($i = 0; $i < 5; $i++) {
        createTestLog();
    }

    $component = Livewire::test(InstallLogs::class)
        ->set('logLimit', 3);

    expect($component->get('logs'))->toHaveCount(3);
});

it('toggles log expansion', function (): void {
    $log = createTestLog(['success' => false, 'error_message' => 'Test error']);

    Livewire::test(InstallLogs::class)
        ->assertSet('expandedLogId', null)
        ->call('toggleExpand', $log->id)
        ->assertSet('expandedLogId', $log->id)
        ->call('toggleExpand', $log->id)
        ->assertSet('expandedLogId', null);
});

it('clears all logs', function (): void {
    createTestLog();
    createTestLog();

    expect(InstallLog::count())->toBe(2);

    Livewire::test(InstallLogs::class)
        ->call('clearAllLogs');

    expect(InstallLog::count())->toBe(0);
});

it('dispatches install-logs-closed when closing modal', function (): void {
    Livewire::test(InstallLogs::class)
        ->call('closeModal')
        ->assertDispatched('install-logs-closed');
});

// ---------------------------------------------------------------------------
// Report feature
// ---------------------------------------------------------------------------

it('submits a report successfully', function (): void {
    createTestLog();

    $this->mock(ReportBuilder::class, function ($mock): void {
        $mock->shouldReceive('build')->once()->andReturn([
            'environment' => ['php_version' => PHP_VERSION],
            'logs' => [],
            'installer_version' => 'dev',
        ]);
    });

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('submitReport')->once()->andReturn([]);
    });

    $component = Livewire::test(InstallLogs::class)
        ->call('submitReport')
        ->assertSet('isSubmitting', false);

    expect($component->get('reportSuccess'))->not->toBeNull()
        ->and($component->get('reportError'))->toBeNull();
});

it('handles report submission failure', function (): void {
    createTestLog();

    $this->mock(ReportBuilder::class, function ($mock): void {
        $mock->shouldReceive('build')->once()->andReturn([
            'environment' => [],
            'logs' => [],
            'installer_version' => 'dev',
        ]);
    });

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('submitReport')->once()->andThrow(
            new \RuntimeException('API is down')
        );
    });

    $component = Livewire::test(InstallLogs::class)
        ->call('submitReport')
        ->assertSet('isSubmitting', false);

    expect($component->get('reportError'))->toBe('API is down')
        ->and($component->get('reportSuccess'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Empty state
// ---------------------------------------------------------------------------

it('shows empty state when no logs', function (): void {
    Livewire::test(InstallLogs::class)
        ->assertSee(lang('tipowerup.installer::default.logs_empty'));
});
