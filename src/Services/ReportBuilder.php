<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

class ReportBuilder
{
    public function __construct(
        private readonly HostingDetector $hostingDetector,
    ) {}

    /**
     * Build a report payload for submission to the PowerUp API.
     *
     * @param  array<int, array<string, mixed>>  $logs
     * @return array{environment: array<string, mixed>, logs: array<int, array<string, mixed>>, installer_version: string}
     */
    public function build(array $logs): array
    {
        return [
            'environment' => $this->buildEnvironment(),
            'logs' => $logs,
            'installer_version' => $this->getInstallerVersion(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEnvironment(): array
    {
        $analysis = $this->hostingDetector->analyze();

        return [
            'php_version' => PHP_VERSION,
            'ti_version' => app()->version(),
            'os' => PHP_OS_FAMILY,
            'memory_limit_mb' => $analysis['memory_limit_mb'] ?? null,
            'can_exec' => $analysis['can_exec'] ?? false,
            'can_proc_open' => $analysis['can_proc_open'] ?? false,
            'composer_available' => $analysis['composer_available'] ?? false,
            'composer_source' => $analysis['composer_source'] ?? null,
            'storage_writable' => $analysis['storage_writable'] ?? false,
            'vendor_writable' => $analysis['vendor_writable'] ?? false,
            'has_zip_archive' => $analysis['has_zip_archive'] ?? false,
            'has_curl' => $analysis['has_curl'] ?? false,
            'recommended_method' => $analysis['recommended_method'] ?? 'direct',
        ];
    }

    private function getInstallerVersion(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                return \Composer\InstalledVersions::getPrettyVersion('tipowerup/installer') ?? 'unknown';
            } catch (\OutOfBoundsException) {
                return 'unknown';
            }
        }

        return 'unknown';
    }
}
