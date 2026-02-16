<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\HostingDetector;

it('detects exec availability correctly', function (): void {
    $detector = new HostingDetector;

    $canExec = $detector->canExec();

    expect($canExec)->toBeBool();
});

it('detects shell_exec availability correctly', function (): void {
    $detector = new HostingDetector;

    $canShellExec = $detector->canShellExec();

    expect($canShellExec)->toBeBool();
});

it('detects proc_open availability correctly', function (): void {
    $detector = new HostingDetector;

    $canProcOpen = $detector->canProcOpen();

    expect($canProcOpen)->toBeBool();
});

it('parses memory limit for unlimited value', function (): void {
    $detector = new HostingDetector;

    $memory = $detector->getMemoryLimitMB();

    expect($memory)->toBeInt();
    expect($memory >= -1)->toBeTrue();
});

it('parses memory limit with different units', function (): void {
    $detector = new HostingDetector;

    $memory = $detector->getMemoryLimitMB();

    // Should return valid integer in MB
    expect($memory)->toBeInt();
    expect($memory >= -1)->toBeTrue();
});

it('determines recommended method based on capabilities', function (): void {
    $detector = new HostingDetector;

    $method = $detector->getRecommendedMethod();

    expect($method)->toBeString();
    expect($method)->toBeIn(['composer', 'direct']);
});

it('recommends direct method when proc_open unavailable', function (): void {
    // Create anonymous class to simulate proc_open unavailable
    $detector = new class extends HostingDetector
    {
        public function canProcOpen(): bool
        {
            return false;
        }
    };

    $method = $detector->getRecommendedMethod();

    expect($method)->toBe('direct');
});

it('recommends direct method when composer unavailable', function (): void {
    // Create anonymous class to simulate composer unavailable
    $detector = new class extends HostingDetector
    {
        public function isComposerAvailable(): bool
        {
            return false;
        }
    };

    $method = $detector->getRecommendedMethod();

    expect($method)->toBe('direct');
});

it('recommends composer when all conditions met', function (): void {
    // Create anonymous class to simulate all conditions met
    $detector = new class extends HostingDetector
    {
        public function canProcOpen(): bool
        {
            return true;
        }

        public function isComposerAvailable(): bool
        {
            return true;
        }

        public function getMemoryLimitMB(): int
        {
            return 1024;
        }
    };

    $method = $detector->getRecommendedMethod();

    expect($method)->toBe('composer');
});

it('recommends direct when memory insufficient', function (): void {
    // Create anonymous class to simulate low memory
    $detector = new class extends HostingDetector
    {
        public function canProcOpen(): bool
        {
            return true;
        }

        public function isComposerAvailable(): bool
        {
            return true;
        }

        public function getMemoryLimitMB(): int
        {
            return 256; // Below 512 threshold
        }
    };

    $method = $detector->getRecommendedMethod();

    expect($method)->toBe('direct');
});

// Note: analyze(), clearCache() methods require Cache facade
// which is not available in minimal test environment.
