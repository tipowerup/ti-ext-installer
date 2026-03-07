<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Tipowerup\Installer\Services\HealthChecker;
use Tipowerup\Installer\Services\HostingDetector;

beforeEach(function (): void {
    Http::fake([
        config('tipowerup.installer.api_url') => Http::response(['status' => 'ok'], 200),
    ]);

    // Mock HostingDetector to avoid real exec() and filesystem calls
    $this->hostingDetector = Mockery::mock(HostingDetector::class);
    $this->hostingDetector->shouldReceive('analyze')->andReturn([
        'has_zip_archive' => true,
        'has_curl' => true,
        'storage_writable' => true,
    ]);
    $this->hostingDetector->shouldReceive('getUnwritableComposerPaths')->andReturn([]);

    $this->checker = new HealthChecker($this->hostingDetector);
    $this->results = $this->checker->runAllChecks();
});

afterEach(function (): void {
    Mockery::close();
});

it('returns all health check results', function (): void {
    $keys = array_column($this->results, 'key');

    expect($keys)->toContain('php_version')
        ->and($keys)->toContain('zip_archive')
        ->and($keys)->toContain('curl')
        ->and($keys)->toContain('storage_writable')
        ->and($keys)->toContain('api_connectivity');

    foreach ($this->results as $result) {
        expect($result)->toHaveKeys(['key', 'label', 'passed', 'message', 'fix', 'critical']);
    }
});

it('passes PHP version check on current version', function (): void {
    $phpCheck = collect($this->results)->firstWhere('key', 'php_version');

    // We're running PHP 8.3+, which exceeds the 8.2 minimum
    expect($phpCheck['passed'])->toBeTrue()
        ->and($phpCheck['message'])->toContain(PHP_VERSION);
});

it('reflects ZipArchive extension availability', function (): void {
    $zipCheck = collect($this->results)->firstWhere('key', 'zip_archive');

    expect($zipCheck['passed'])->toBeTrue();
});

it('reflects cURL extension availability', function (): void {
    $curlCheck = collect($this->results)->firstWhere('key', 'curl');

    expect($curlCheck['passed'])->toBeTrue();
});

it('checks storage writable', function (): void {
    $storageCheck = collect($this->results)->firstWhere('key', 'storage_writable');

    expect($storageCheck)->not->toBeNull()
        ->and($storageCheck['passed'])->toBeTrue();
});

it('checks API connectivity', function (): void {
    $apiCheck = collect($this->results)->firstWhere('key', 'api_connectivity');

    // Http::fake returns 200, so this should pass
    expect($apiCheck['passed'])->toBeTrue();
});

it('detects critical failures', function (): void {
    $hostingDetector = Mockery::mock(HostingDetector::class);
    $hostingDetector->shouldReceive('analyze')->andReturn([
        'has_zip_archive' => false, // Critical!
        'has_curl' => true,
        'storage_writable' => true,
    ]);
    $hostingDetector->shouldReceive('getUnwritableComposerPaths')->andReturn([]);

    $checkerWithFailure = new HealthChecker($hostingDetector);
    $results = $checkerWithFailure->runAllChecks();

    expect($checkerWithFailure->hasCriticalFailures($results))->toBeTrue();
});

it('returns community links', function (): void {
    $links = $this->checker->getCommunityLinks();

    expect($links)->not->toBeEmpty();

    foreach ($links as $link) {
        expect($link)->toHaveKeys(['label', 'url']);
    }
});
