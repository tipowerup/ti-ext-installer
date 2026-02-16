<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\BatchInstaller;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\InstallationPipeline;
use Tipowerup\Installer\Services\PowerUpApiClient;

it('can be instantiated', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);
    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    expect($batchInstaller)->toBeInstanceOf(BatchInstaller::class);
});

it('performs topological sort correctly with no dependencies', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);
    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    $graph = [
        'PackageA' => [],
        'PackageB' => [],
        'PackageC' => [],
    ];

    $sorted = $batchInstaller->topologicalSort($graph);

    expect($sorted)->toHaveCount(3)
        ->and($sorted)->toContain('PackageA')
        ->and($sorted)->toContain('PackageB')
        ->and($sorted)->toContain('PackageC');
});

it('performs topological sort correctly with linear dependencies', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);
    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    // A depends on nothing, B depends on A, C depends on B
    $graph = [
        'PackageA' => [],
        'PackageB' => ['PackageA'],
        'PackageC' => ['PackageB'],
    ];

    $sorted = $batchInstaller->topologicalSort($graph);

    expect($sorted)->toBe(['PackageA', 'PackageB', 'PackageC']);
});

it('performs topological sort correctly with diamond dependency', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);
    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    // Diamond: A depends on nothing, B and C depend on A, D depends on B and C
    $graph = [
        'PackageA' => [],
        'PackageB' => ['PackageA'],
        'PackageC' => ['PackageA'],
        'PackageD' => ['PackageB', 'PackageC'],
    ];

    $sorted = $batchInstaller->topologicalSort($graph);

    // A must be first
    expect($sorted[0])->toBe('PackageA');

    // B and C must come before D
    $bIndex = array_search('PackageB', $sorted, true);
    $cIndex = array_search('PackageC', $sorted, true);
    $dIndex = array_search('PackageD', $sorted, true);

    expect($bIndex)->toBeLessThan($dIndex)
        ->and($cIndex)->toBeLessThan($dIndex);
});

it('detects circular dependencies', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);
    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    // Circular: A depends on B, B depends on A
    $graph = [
        'PackageA' => ['PackageB'],
        'PackageB' => ['PackageA'],
    ];

    expect(fn (): array => $batchInstaller->topologicalSort($graph))
        ->toThrow(RuntimeException::class, 'Circular dependency detected');
});

it('groups independent packages together', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);

    // Mock API client to return no dependencies for any package
    $apiClient->method('getPackageDetail')
        ->willReturn(['dependencies' => []]);

    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    $packages = ['PackageA', 'PackageB', 'PackageC'];
    $groups = $batchInstaller->buildDependencyGroups($packages);

    // All packages are independent, so they should all be in group 0
    expect($groups)->toHaveCount(1)
        ->and($groups[0])->toHaveCount(3)
        ->and($groups[0])->toContain('PackageA')
        ->and($groups[0])->toContain('PackageB')
        ->and($groups[0])->toContain('PackageC');
});

it('keeps dependent packages in later groups', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);

    // Mock API client to return dependencies
    $apiClient->method('getPackageDetail')
        ->willReturnCallback(fn ($packageCode): array => match ($packageCode) {
            'PackageA' => ['dependencies' => []],
            'PackageB' => ['dependencies' => ['PackageA']],
            'PackageC' => ['dependencies' => []],
            default => ['dependencies' => []],
        });

    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    $packages = ['PackageA', 'PackageB', 'PackageC'];
    $groups = $batchInstaller->buildDependencyGroups($packages);

    // PackageA and PackageC should be in group 0 (no dependencies)
    // PackageB should be in group 1 (depends on PackageA)
    expect($groups)->toHaveCount(2)
        ->and($groups[0])->toContain('PackageA')
        ->and($groups[0])->toContain('PackageC')
        ->and($groups[1])->toContain('PackageB');
});

it('handles complex dependency chains', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);

    // Mock API client to return complex dependencies
    // A -> no deps (level 0)
    // B -> depends on A (level 1)
    // C -> depends on A (level 1)
    // D -> depends on B and C (level 2)
    $apiClient->method('getPackageDetail')
        ->willReturnCallback(fn ($packageCode): array => match ($packageCode) {
            'PackageA' => ['dependencies' => []],
            'PackageB' => ['dependencies' => ['PackageA']],
            'PackageC' => ['dependencies' => ['PackageA']],
            'PackageD' => ['dependencies' => ['PackageB', 'PackageC']],
            default => ['dependencies' => []],
        });

    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    $packages = ['PackageA', 'PackageB', 'PackageC', 'PackageD'];
    $groups = $batchInstaller->buildDependencyGroups($packages);

    // Should have 3 levels
    expect($groups)->toHaveCount(3);

    // Level 0: PackageA
    expect($groups[0])->toHaveCount(1)
        ->and($groups[0])->toContain('PackageA');

    // Level 1: PackageB and PackageC
    expect($groups[1])->toHaveCount(2)
        ->and($groups[1])->toContain('PackageB')
        ->and($groups[1])->toContain('PackageC');

    // Level 2: PackageD
    expect($groups[2])->toHaveCount(1)
        ->and($groups[2])->toContain('PackageD');
});

it('handles packages with dependencies outside the install list', function (): void {
    $pipeline = $this->createMock(InstallationPipeline::class);
    $apiClient = $this->createMock(PowerUpApiClient::class);

    // PackageA depends on ExternalPackage (not in our install list)
    $apiClient->method('getPackageDetail')
        ->willReturnCallback(fn ($packageCode): array => match ($packageCode) {
            'PackageA' => ['dependencies' => ['ExternalPackage']],
            'PackageB' => ['dependencies' => []],
            default => ['dependencies' => []],
        });

    $hostingDetector = $this->createMock(HostingDetector::class);

    $batchInstaller = new BatchInstaller($pipeline, $apiClient, $hostingDetector);

    // Only installing PackageA and PackageB, not ExternalPackage
    $packages = ['PackageA', 'PackageB'];
    $groups = $batchInstaller->buildDependencyGroups($packages);

    // Both should be in level 0 since ExternalPackage is not in our install list
    expect($groups)->toHaveCount(1)
        ->and($groups[0])->toHaveCount(2)
        ->and($groups[0])->toContain('PackageA')
        ->and($groups[0])->toContain('PackageB');
});
