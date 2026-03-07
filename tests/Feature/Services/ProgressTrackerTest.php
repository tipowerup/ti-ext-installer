<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Tipowerup\Installer\Services\ProgressTracker;

beforeEach(function (): void {
    Cache::flush();
    $this->tracker = resolve(ProgressTracker::class);
});

it('stores and retrieves progress', function (): void {
    $this->tracker->update('batch-1', 'vendor/package', 'installing', 50, 'Extracting files...');

    $progress = $this->tracker->get('batch-1', 'vendor/package');

    expect($progress)
        ->not->toBeNull()
        ->and($progress['stage'])->toBe('installing')
        ->and($progress['progress_percent'])->toBe(50)
        ->and($progress['message'])->toBe('Extracting files...');
});

it('returns null for unknown progress', function (): void {
    expect($this->tracker->get('nonexistent', 'vendor/package'))->toBeNull();
});

it('detects cancellation', function (): void {
    $this->tracker->update('batch-1', 'vendor/package', 'preparing', 5, 'Starting...');

    expect($this->tracker->isCancelled('batch-1', 'vendor/package'))->toBeFalse();

    $this->tracker->cancel('batch-1', 'vendor/package', 'preparing');

    expect($this->tracker->isCancelled('batch-1', 'vendor/package'))->toBeTrue();
});

it('forgets progress data', function (): void {
    $this->tracker->update('batch-1', 'vendor/package', 'complete', 100, 'Done!');
    $this->tracker->forget('batch-1', 'vendor/package');

    expect($this->tracker->get('batch-1', 'vendor/package'))->toBeNull();
});

it('calculates batch progress across multiple packages', function (): void {
    $this->tracker->update('batch-1', 'vendor/alpha', 'installing', 60, 'Installing...');
    $this->tracker->update('batch-1', 'vendor/beta', 'complete', 100, 'Done!');

    $batchProgress = $this->tracker->getBatchProgress('batch-1', ['vendor/alpha', 'vendor/beta']);

    expect($batchProgress['overall_percent'])->toBe(80)
        ->and($batchProgress['packages'])->toHaveKeys(['vendor/alpha', 'vendor/beta'])
        ->and($batchProgress['packages']['vendor/alpha']['percent'])->toBe(60)
        ->and($batchProgress['packages']['vendor/beta']['percent'])->toBe(100);
});

it('returns zero for empty batch progress', function (): void {
    $batchProgress = $this->tracker->getBatchProgress('no-such-batch', ['vendor/alpha']);

    expect($batchProgress['overall_percent'])->toBe(0)
        ->and($batchProgress['packages'])->toBeEmpty();
});

it('stores error data in progress', function (): void {
    $this->tracker->update(
        'batch-1',
        'vendor/package',
        'failed',
        0,
        'Installation failed',
        'Download error',
        'download_failed',
        'installing',
    );

    $progress = $this->tracker->get('batch-1', 'vendor/package');

    expect($progress['stage'])->toBe('failed')
        ->and($progress['error'])->toBe('Download error')
        ->and($progress['error_code'])->toBe('download_failed')
        ->and($progress['failed_stage'])->toBe('installing');
});
