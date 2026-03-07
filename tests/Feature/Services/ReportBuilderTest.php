<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\ReportBuilder;

it('builds a report with correct structure', function (): void {
    $this->mock(HostingDetector::class, function ($mock): void {
        $mock->shouldReceive('analyze')->andReturn([
            'can_exec' => true,
            'can_proc_open' => true,
            'memory_limit_mb' => 256,
            'composer_available' => true,
            'composer_source' => 'system',
            'storage_writable' => true,
            'vendor_writable' => true,
            'has_zip_archive' => true,
            'has_curl' => true,
            'recommended_method' => 'composer',
        ]);
    });

    $builder = resolve(ReportBuilder::class);
    $report = $builder->build([['id' => 1, 'action' => 'install']]);

    expect($report)
        ->toHaveKeys(['environment', 'logs', 'installer_version'])
        ->and($report['environment'])->toHaveKeys([
            'php_version',
            'ti_version',
            'os',
            'memory_limit_mb',
            'can_exec',
            'can_proc_open',
            'composer_available',
            'storage_writable',
            'vendor_writable',
        ])
        ->and($report['environment']['php_version'])->toBe(PHP_VERSION)
        ->and($report['environment']['memory_limit_mb'])->toBe(256)
        ->and($report['environment']['can_exec'])->toBeTrue()
        ->and($report['logs'])->toBeArray();
});

it('includes os family in environment', function (): void {
    $this->mock(HostingDetector::class, function ($mock): void {
        $mock->shouldReceive('analyze')->andReturn([
            'can_exec' => false,
            'can_proc_open' => false,
            'memory_limit_mb' => 128,
            'composer_available' => false,
            'composer_source' => null,
            'storage_writable' => true,
            'vendor_writable' => true,
            'has_zip_archive' => true,
            'has_curl' => true,
            'recommended_method' => 'direct',
        ]);
    });

    $builder = resolve(ReportBuilder::class);
    $report = $builder->build([]);

    expect($report['environment']['os'])->toBe(PHP_OS_FAMILY);
});
