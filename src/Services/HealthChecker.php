<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Illuminate\Support\Facades\File;

class HealthChecker
{
    private const int MIN_PHP_VERSION = 80200; // PHP 8.2.0

    public function __construct(
        private readonly HostingDetector $hostingDetector,
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
            'label' => lang('tipowerup.installer::default.health_php_version', ['version' => '8.2+']),
            'passed' => $phpPassed,
            'message' => $phpPassed
                ? sprintf('PHP %s detected', $phpVersion)
                : sprintf('PHP %s detected, but 8.2+ is required', $phpVersion),
            'fix' => $phpPassed ? null : 'Contact your hosting provider to upgrade PHP to version 8.2 or higher.',
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

        // Public Vendor Directory Writable Check (for theme assets)
        $publicVendorPath = public_path('vendor');
        $publicVendorWritable = File::exists($publicVendorPath)
            ? is_writable($publicVendorPath)
            : is_writable(public_path());

        $checks[] = [
            'key' => 'public_vendor_writable',
            'label' => lang('tipowerup.installer::default.health_public_vendor_writable'),
            'passed' => $publicVendorWritable,
            'message' => $publicVendorWritable
                ? 'Public vendor directory is writable'
                : 'Public vendor directory is not writable',
            'fix' => $publicVendorWritable ? null : 'Set proper permissions on the public/vendor directory. Run: chmod -R 755 public/vendor',
            'critical' => false,
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
                'url' => 'https://tipowerup.com/support?ref=tipowerup-installer',
            ],
            [
                'label' => lang('tipowerup.installer::default.link_discord'),
                'url' => 'https://tipowerup.com/social/discord?ref=tipowerup-installer',
            ],
            [
                'label' => lang('tipowerup.installer::default.link_reddit'),
                'url' => 'https://tipowerup.com/social/reddit?ref=tipowerup-installer',
            ],
        ];
    }
}
