<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class HealthChecker
{
    private const int MIN_PHP_VERSION = 80200; // PHP 8.2.0

    private const int API_PING_TIMEOUT = 5;

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

        // Composer Writable Check
        $unwritablePaths = $this->hostingDetector->getUnwritableComposerPaths();
        $composerWritable = $unwritablePaths === [];
        $currentInstallMethod = params('tipowerup_install_method', 'auto');
        $composerWritableCritical = $currentInstallMethod === 'composer';

        $composerWritableFix = null;
        if (!$composerWritable) {
            $pathList = implode(', ', array_map(
                static fn (string $path): string => str_replace(base_path().'/', '', $path),
                $unwritablePaths,
            ));
            $composerWritableFix = lang('tipowerup.installer::default.health_composer_writable_fix', ['paths' => $pathList]);
        }

        $checks[] = [
            'key' => 'composer_writable',
            'label' => lang('tipowerup.installer::default.health_composer_writable'),
            'passed' => $composerWritable,
            'message' => $composerWritable
                ? lang('tipowerup.installer::default.health_composer_writable_passed')
                : lang('tipowerup.installer::default.health_composer_writable_failed'),
            'fix' => $composerWritableFix,
            'critical' => $composerWritableCritical,
        ];

        // API Connectivity Check
        $apiReachable = false;

        try {
            $response = Http::timeout(self::API_PING_TIMEOUT)->get(PowerUpApiClient::BASE_URL);
            $apiReachable = $response->successful() || $response->status() < 500;
        } catch (ConnectionException) {
            $apiReachable = false;
        }

        $checks[] = [
            'key' => 'api_connectivity',
            'label' => lang('tipowerup.installer::default.health_api_connectivity'),
            'passed' => $apiReachable,
            'message' => $apiReachable
                ? 'TI PowerUp API is reachable'
                : 'Unable to reach the TI PowerUp API',
            'fix' => $apiReachable ? null : 'Ensure your server has outbound internet access. Check that your firewall or hosting provider allows connections to tipowerup.test.',
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
