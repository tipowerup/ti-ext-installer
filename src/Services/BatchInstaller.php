<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Tipowerup\Installer\Models\License;

class BatchInstaller
{
    /** @var array<string, array<string>> */
    private array $dependencyGraph = [];

    public function __construct(
        private readonly InstallationPipeline $pipeline,
        private readonly PowerUpApiClient $apiClient,
        private readonly HostingDetector $hostingDetector,
        private readonly ProgressTracker $progressTracker,
    ) {}

    /**
     * Install multiple packages with dependency-aware ordering.
     * Independent packages install in parallel (downloads), then sequential extract/migrate.
     *
     * @param  array<string>  $packageCodes  List of package codes to install
     * @param  callable|null  $onProgress  Progress callback: fn(string $packageCode, string $stage, int $percent, string $message)
     * @return array<string, array{success: bool, error: ?string, version: ?string}>
     */
    public function batchInstall(array $packageCodes, ?callable $onProgress = null): array
    {
        $batchId = (string) Str::uuid();
        $onProgress ??= function (string $packageCode, string $stage, int $percent, string $message): void {};

        try {
            // Filter out already-installed packages
            $installed = $this->filterInstalled($packageCodes);
            $toInstall = array_diff($packageCodes, $installed);

            if ($toInstall === []) {
                return array_fill_keys($packageCodes, [
                    'success' => true,
                    'error' => 'Already installed',
                    'version' => null,
                ]);
            }

            // Build dependency groups
            $groups = $this->buildDependencyGroups(array_values($toInstall));

            // Get recommended install method
            $method = $this->hostingDetector->getRecommendedMethod();

            // Track results per package
            $results = [];
            $failedPackages = [];

            // Process each group sequentially
            foreach ($groups as $group) {
                foreach ($group as $packageCode) {
                    // Skip if any dependency failed
                    if ($this->hasDependencyFailure($packageCode, $failedPackages)) {
                        $results[$packageCode] = [
                            'success' => false,
                            'error' => 'Dependency installation failed',
                            'version' => null,
                        ];

                        $failedPackages[] = $packageCode;

                        continue;
                    }

                    try {
                        // Execute installation with progress callback
                        $result = $this->pipeline->execute(
                            $packageCode,
                            $method,
                            function (string $stage, int $percent, string $message) use ($packageCode, $onProgress): void {
                                $onProgress($packageCode, $stage, $percent, $message);
                            }
                        );

                        $results[$packageCode] = [
                            'success' => true,
                            'error' => null,
                            'version' => $result['version'] ?? null,
                        ];

                    } catch (Throwable $e) {
                        $results[$packageCode] = [
                            'success' => false,
                            'error' => $e->getMessage(),
                            'version' => null,
                        ];

                        $failedPackages[] = $packageCode;
                    }
                }
            }

            // Fill in results for already-installed packages
            foreach ($installed as $packageCode) {
                $license = License::byPackage($packageCode)->first();
                $results[$packageCode] = [
                    'success' => true,
                    'error' => null,
                    'version' => $license?->version ?? null,
                ];
            }

            return $results;

        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Build dependency graph for the given packages.
     * Queries API for each package's dependencies.
     * Returns ordered groups where each group can be processed in parallel.
     *
     * @param  array<string>  $packageCodes
     * @return array<int, array<string>> Ordered groups (group 0 first, then 1, etc.)
     */
    public function buildDependencyGroups(array $packageCodes): array
    {
        // Build adjacency list: package => [dependencies that are also in our install list]
        $graph = [];

        foreach ($packageCodes as $packageCode) {
            try {
                $detail = $this->apiClient->getPackageDetail($packageCode);
                $dependencies = $detail['dependencies'] ?? [];

                // Only include dependencies that are in our install list
                $relevantDeps = array_intersect($dependencies, $packageCodes);
                $graph[$packageCode] = $relevantDeps;

            } catch (Throwable $e) {
                Log::warning('BatchInstaller: Failed to fetch dependencies for '.$packageCode, [
                    'error' => $e->getMessage(),
                ]);

                // Assume no dependencies if API call fails
                $graph[$packageCode] = [];
            }
        }

        $this->dependencyGraph = $graph;

        // Perform topological sort
        $sorted = $this->topologicalSort($graph);

        // Group into levels based on dependencies
        return $this->groupByDependencyLevel($sorted, $graph);
    }

    /**
     * Topological sort of packages based on dependencies.
     *
     * @param  array<string, array<string>>  $graph  Adjacency list: package => [dependencies]
     * @return array<string> Sorted package codes
     */
    public function topologicalSort(array $graph): array
    {
        // Calculate in-degree (number of dependencies) for each package
        $inDegree = [];
        $allNodes = array_unique(array_merge(
            array_keys($graph),
            ...array_values($graph)
        ));

        foreach ($allNodes as $node) {
            $inDegree[$node] = 0;
        }

        foreach ($graph as $package => $dependencies) {
            foreach ($dependencies as $dependency) {
                $inDegree[$package] = ($inDegree[$package] ?? 0) + 1;
            }
        }

        // Start with packages that have no dependencies
        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        $sorted = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            $sorted[] = $current;

            // Find all packages that depend on the current package
            foreach ($graph as $package => $dependencies) {
                if (in_array($current, $dependencies, true)) {
                    $inDegree[$package]--;

                    if ($inDegree[$package] === 0) {
                        $queue[] = $package;
                    }
                }
            }
        }

        // Check for circular dependencies
        if (count($sorted) !== count($allNodes)) {
            throw new RuntimeException(
                'Circular dependency detected in packages: '.implode(', ', array_diff($allNodes, $sorted))
            );
        }

        return $sorted;
    }

    /**
     * Check which packages from the list are already installed.
     *
     * @param  array<string>  $packageCodes
     * @return array<string> Package codes that are already installed
     */
    public function filterInstalled(array $packageCodes): array
    {
        return License::whereIn('package_code', $packageCodes)
            ->where('is_active', true)
            ->pluck('package_code')
            ->toArray();
    }

    /**
     * Get the overall batch progress percentage.
     *
     * @param  array<string>  $packageCodes
     * @return array{overall_percent: int, packages: array<string, array{stage: string, percent: int}>}
     */
    public function getBatchProgress(string $batchId, array $packageCodes): array
    {
        return $this->progressTracker->getBatchProgress($batchId, $packageCodes);
    }

    /**
     * Group sorted packages into dependency levels.
     *
     * @param  array<string>  $sorted
     * @param  array<string, array<string>>  $graph
     * @return array<int, array<string>>
     */
    private function groupByDependencyLevel(array $sorted, array $graph): array
    {
        $levels = [];
        $levelAssignments = [];

        foreach ($sorted as $package) {
            $dependencies = $graph[$package] ?? [];

            if (empty($dependencies)) {
                // No dependencies, level 0
                $levelAssignments[$package] = 0;
            } else {
                // Level = max(dependency levels) + 1
                $maxDepLevel = -1;
                foreach ($dependencies as $dep) {
                    if (isset($levelAssignments[$dep])) {
                        $maxDepLevel = max($maxDepLevel, $levelAssignments[$dep]);
                    }
                }

                $levelAssignments[$package] = $maxDepLevel + 1;
            }
        }

        // Group by level
        foreach ($levelAssignments as $package => $level) {
            if (!isset($levels[$level])) {
                $levels[$level] = [];
            }

            $levels[$level][] = $package;
        }

        // Sort levels by key
        ksort($levels);

        return array_values($levels);
    }

    /**
     * Check if a package has a dependency that failed.
     *
     * @param  array<string>  $failedPackages
     */
    private function hasDependencyFailure(string $packageCode, array $failedPackages): bool
    {
        if ($failedPackages === []) {
            return false;
        }

        $dependencies = $this->dependencyGraph[$packageCode] ?? [];

        foreach ($dependencies as $dependency) {
            if (in_array($dependency, $failedPackages, true)) {
                return true;
            }
        }

        return false;
    }
}
