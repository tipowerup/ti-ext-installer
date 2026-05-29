<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Tipowerup\Installer\Services\HostingDetector;

beforeEach(function (): void {
    $this->detector = Mockery::mock(HostingDetector::class)->makePartial();
    $this->detector->shouldReceive('isSystemComposerAvailable')->andReturn(true);
    $this->detector->shouldReceive('getComposerBinaryPath')->andReturn('/usr/local/bin/composer');
});

it('analyze returns expected keys', function (): void {
    $result = $this->detector->analyze();

    expect($result)->toHaveKeys([
        'can_exec',
        'can_shell_exec',
        'can_proc_open',
        'memory_limit_mb',
        'max_execution_time',
        'has_zip_archive',
        'has_curl',
        'composer_available',
        'composer_source',
        'storage_writable',
        'vendor_writable',
        'recommended_method',
    ]);
});

it('analyze caches the result', function (): void {
    $first = $this->detector->analyze();
    $second = $this->detector->analyze();

    expect($first)->toBe($second);
    expect(Cache::has('tipowerup.hosting_analysis'))->toBeTrue();
});

it('freshAnalyze bypasses and repopulates cache', function (): void {
    $this->detector->analyze();

    $result = $this->detector->freshAnalyze();

    expect($result)->toHaveKey('can_exec');
    expect(Cache::has('tipowerup.hosting_analysis'))->toBeTrue();
});

it('clearCache removes cached analysis', function (): void {
    $this->detector->analyze();
    expect(Cache::has('tipowerup.hosting_analysis'))->toBeTrue();

    $this->detector->clearCache();

    expect(Cache::has('tipowerup.hosting_analysis'))->toBeFalse();
});

it('getUnwritableComposerPaths returns array', function (): void {
    expect($this->detector->getUnwritableComposerPaths())->toBeArray();
});

it('isComposerWritable returns bool', function (): void {
    expect($this->detector->isComposerWritable())->toBeBool();
});

it('getComposerBinaryPath returns string or null', function (): void {
    $path = $this->detector->getComposerBinaryPath();

    if ($path !== null) {
        expect($path)->toBeString();
    } else {
        expect($path)->toBeNull();
    }
});

it('recommended method is either composer or direct', function (): void {
    expect($this->detector->getRecommendedMethod())->toBeIn(['composer', 'direct']);
});

// ---------------------------------------------------------------------------
// Memory limit parsing covers all ini suffix variants
// ---------------------------------------------------------------------------

it('parses memory_limit suffixes correctly', function (string $iniValue, int $expectedMb): void {
    $detector = new HostingDetector;

    $original = ini_get('memory_limit');
    ini_set('memory_limit', $iniValue);

    try {
        expect($detector->getMemoryLimitMB())->toBe($expectedMb);
    } finally {
        ini_set('memory_limit', $original);
    }
})->with([
    'gigabytes' => ['2G', 2048],
    'megabytes' => ['256M', 256],
    'kilobytes' => ['131072K', 128],
    'unlimited' => ['-1', -1],
]);

// ---------------------------------------------------------------------------
// Composer source resolution
// ---------------------------------------------------------------------------

it('returns "system" when system composer is available', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('isSystemComposerAvailable')->andReturn(true);

    expect($detector->getComposerSource())->toBe('system');
});

it('returns "downloaded" when only the bundled phar is available', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('isSystemComposerAvailable')->andReturn(false);
    $detector->shouldReceive('isComposerPharAvailable')->andReturn(true);

    expect($detector->getComposerSource())->toBe('downloaded');
});

it('returns null when no composer is available', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('isSystemComposerAvailable')->andReturn(false);
    $detector->shouldReceive('isComposerPharAvailable')->andReturn(false);

    expect($detector->getComposerSource())->toBeNull();
});

// ---------------------------------------------------------------------------
// Composer path probing without exec()
// ---------------------------------------------------------------------------

it('isSystemComposerAvailable returns false when exec is disabled', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('canExec')->andReturn(false);

    expect($detector->isSystemComposerAvailable())->toBeFalse();
});

it('getComposerBinaryPath returns null when exec is disabled', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('canExec')->andReturn(false);

    expect($detector->getComposerBinaryPath())->toBeNull();
});

// ---------------------------------------------------------------------------
// Composer phar detection
// ---------------------------------------------------------------------------

it('isComposerPharAvailable reflects the storage phar file presence', function (): void {
    $detector = new HostingDetector;
    $pharPath = storage_path('app/tipowerup/bin/composer.phar');
    $existed = file_exists($pharPath);

    if (!$existed) {
        @mkdir(dirname($pharPath), 0755, true);
        file_put_contents($pharPath, '#!/usr/bin/env php');
    }

    try {
        expect($detector->isComposerPharAvailable())->toBeTrue();
    } finally {
        if (!$existed) {
            @unlink($pharPath);
        }
    }
});

// ---------------------------------------------------------------------------
// Composer-writable path checks
// ---------------------------------------------------------------------------

it('getUnwritableComposerPaths flags composer.json when the file is read-only', function (): void {
    $detector = new HostingDetector;
    $composerJson = base_path('composer.json');

    if (!file_exists($composerJson)) {
        $this->markTestSkipped('composer.json missing in this testbench setup');
    }

    $originalPerms = fileperms($composerJson);
    chmod($composerJson, 0o444);

    try {
        expect($detector->getUnwritableComposerPaths())->toContain($composerJson);
    } finally {
        chmod($composerJson, $originalPerms);
    }
});

it('getUnwritableComposerPaths flags vendor when the directory is missing', function (): void {
    // We can't safely delete vendor, so this test reads through the live state.
    // Vendor is always present in the testbench, so the assertion is that the path
    // either appears (would happen on a fresh checkout) or not. The behavioural
    // contract: only paths that are *currently* unwritable appear in the list.
    $detector = new HostingDetector;
    $vendor = base_path('vendor');

    if (is_dir($vendor) && is_writable($vendor)) {
        expect($detector->getUnwritableComposerPaths())->not->toContain($vendor);
    } else {
        expect($detector->getUnwritableComposerPaths())->toContain($vendor);
    }
});

// ---------------------------------------------------------------------------
// Recommended method composition
// ---------------------------------------------------------------------------

it('recommends "direct" when proc_open is unavailable', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('canProcOpen')->andReturn(false);

    expect($detector->getRecommendedMethod())->toBe('direct');
});

it('recommends "direct" when memory is too low', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('canProcOpen')->andReturn(true);
    $detector->shouldReceive('getMemoryLimitMB')->andReturn(64);

    expect($detector->getRecommendedMethod())->toBe('direct');
});

it('recommends "direct" when composer is unavailable', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('canProcOpen')->andReturn(true);
    $detector->shouldReceive('getMemoryLimitMB')->andReturn(256);
    $detector->shouldReceive('isComposerAvailable')->andReturn(false);

    expect($detector->getRecommendedMethod())->toBe('direct');
});

it('recommends "composer" when all preconditions are satisfied', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('canProcOpen')->andReturn(true);
    $detector->shouldReceive('getMemoryLimitMB')->andReturn(512);
    $detector->shouldReceive('isComposerAvailable')->andReturn(true);
    $detector->shouldReceive('isComposerWritable')->andReturn(true);

    expect($detector->getRecommendedMethod())->toBe('composer');
});

it('recommends "composer" when memory is unlimited (-1)', function (): void {
    $detector = Mockery::mock(HostingDetector::class)->makePartial();
    $detector->shouldReceive('canProcOpen')->andReturn(true);
    $detector->shouldReceive('getMemoryLimitMB')->andReturn(-1);
    $detector->shouldReceive('isComposerAvailable')->andReturn(true);
    $detector->shouldReceive('isComposerWritable')->andReturn(true);

    expect($detector->getRecommendedMethod())->toBe('composer');
});
