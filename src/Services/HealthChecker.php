<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

class HealthChecker
{
    private const int MIN_PHP_VERSION = 80300; // PHP 8.3.0

    private const int MIN_MEMORY_MB = 128;

    private const int RECOMMENDED_MEMORY_MB = 512;

    public function __construct(
        private readonly HostingDetector $hostingDetector,
        private readonly CoreExtensionChecker $coreExtensionChecker,
    ) {}

    /**
     * Run all health checks.
     *
     * @return array<int, array{key: string, label: string, passed: bool, message: string, fix: string|null, critical: bool}>
     */
    public function runAllChecks(): array
    {
        $checks = [];

        // Analyze hosting environment once
        $hostingAnalysis = $this->hostingDetector->analyze();

        // PHP Version Check
        $phpVersion = PHP_VERSION;
        $phpVersionInt = PHP_VERSION_ID;
        $phpPassed = $phpVersionInt >= self::MIN_PHP_VERSION;

        $checks[] = [
            'key' => 'php_version',
            'label' => lang('tipowerup.installer::default.health_php_version', ['version' => '8.3+']),
            'passed' => $phpPassed,
            'message' => $phpPassed
                ? sprintf('PHP %s detected', $phpVersion)
                : sprintf('PHP %s detected, but 8.3+ is required', $phpVersion),
            'fix' => $phpPassed ? null : 'Contact your hosting provider to upgrade PHP to version 8.3 or higher.',
            'critical' => true,
        ];

        // ZipArchive Extension Check
        $hasZip = $hostingAnalysis['has_zip_archive'] ?? false;

        $checks[] = [
            'key' => 'zip_archive',
            'label' => lang('tipowerup.installer::default.health_zip_archive'),
            'passed' => $hasZip,
            'message' => $hasZip ? 'ZipArchive extension is available' : 'ZipArchive extension is missing',
            'fix' => $hasZip ? null : 'Enable the PHP ZipArchive extension. Contact your hosting provider or add "extension=zip.so" to your php.ini file.',
            'critical' => true,
        ];

        // cURL Extension Check
        $hasCurl = $hostingAnalysis['has_curl'] ?? false;

        $checks[] = [
            'key' => 'curl',
            'label' => lang('tipowerup.installer::default.health_curl'),
            'passed' => $hasCurl,
            'message' => $hasCurl ? 'cURL extension is available' : 'cURL extension is missing',
            'fix' => $hasCurl ? null : 'Enable the PHP cURL extension. Contact your hosting provider or add "extension=curl.so" to your php.ini file.',
            'critical' => true,
        ];

        // Storage Writable Check
        $storageWritable = $hostingAnalysis['storage_writable'] ?? false;

        $checks[] = [
            'key' => 'storage_writable',
            'label' => lang('tipowerup.installer::default.health_storage_writable'),
            'passed' => $storageWritable,
            'message' => $storageWritable
                ? 'Storage directory is writable'
                : 'Storage directory is not writable',
            'fix' => $storageWritable ? null : 'Set proper permissions on the storage/app/tipowerup directory. Run: chmod -R 755 storage/app/tipowerup',
            'critical' => true,
        ];

        // Vendor Writable Check (warning only for composer method)
        $vendorWritable = $hostingAnalysis['vendor_writable'] ?? false;

        $checks[] = [
            'key' => 'vendor_writable',
            'label' => lang('tipowerup.installer::default.health_vendor_writable'),
            'passed' => $vendorWritable,
            'message' => $vendorWritable
                ? 'Vendor directory is writable'
                : 'Vendor directory is not writable (only needed for Composer method)',
            'fix' => $vendorWritable ? null : 'Set proper permissions on the vendor directory. Run: chmod -R 755 vendor',
            'critical' => false, // Only warning since direct extraction doesn't need this
        ];

        // Memory Limit Check
        $memoryLimitMB = $this->hostingDetector->getMemoryLimitMB();
        $memoryPassed = $memoryLimitMB >= self::MIN_MEMORY_MB || $memoryLimitMB === -1;

        $memoryMessage = match (true) {
            $memoryLimitMB === -1 => 'Memory limit: Unlimited',
            $memoryLimitMB >= self::RECOMMENDED_MEMORY_MB => sprintf('Memory limit: %dMB (Good)', $memoryLimitMB),
            $memoryLimitMB >= self::MIN_MEMORY_MB => sprintf('Memory limit: %dMB (Adequate)', $memoryLimitMB),
            default => sprintf('Memory limit: %sMB (Low)', $memoryLimitMB),
        };

        $checks[] = [
            'key' => 'memory_limit',
            'label' => lang('tipowerup.installer::default.health_memory_limit', ['limit' => $memoryLimitMB === -1 ? 'Unlimited' : $memoryLimitMB]),
            'passed' => $memoryPassed,
            'message' => $memoryMessage,
            'fix' => $memoryPassed ? null : 'Increase PHP memory_limit to at least 128M. Contact your hosting provider or update your php.ini file.',
            'critical' => false, // Warning only
        ];

        // TI Core Extensions Check
        $missingExtensions = $this->coreExtensionChecker->getMissing();
        $allCoreExtensionsInstalled = $this->coreExtensionChecker->allInstalled();

        $extensionMessage = $allCoreExtensionsInstalled
            ? 'All required TI core extensions are installed'
            : 'Missing '.count($missingExtensions).' required TI core extension(s): '.
              implode(', ', array_column($missingExtensions, 'name'));

        $checks[] = [
            'key' => 'core_extensions',
            'label' => 'TI Core Extensions',
            'passed' => $allCoreExtensionsInstalled,
            'message' => $extensionMessage,
            'fix' => $allCoreExtensionsInstalled ? null : 'Install the missing TI core extensions from the Extensions page before using PowerUp Installer.',
            'critical' => true,
        ];

        return $checks;
    }

    /**
     * Check if there are any critical failures.
     */
    public function hasCriticalFailures(): bool
    {
        $checks = $this->runAllChecks();

        foreach ($checks as $check) {
            if ($check['critical'] && !$check['passed']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get community support links.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function getCommunityLinks(): array
    {
        return [
            [
                'label' => lang('tipowerup.installer::default.link_support'),
                'url' => 'https://tipowerup.com/support',
            ],
            [
                'label' => lang('tipowerup.installer::default.link_discord'),
                'url' => 'https://discord.gg/tipowerup',
            ],
            [
                'label' => lang('tipowerup.installer::default.link_reddit'),
                'url' => 'https://reddit.com/r/tipowerup',
            ],
        ];
    }
}
