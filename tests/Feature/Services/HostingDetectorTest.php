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
