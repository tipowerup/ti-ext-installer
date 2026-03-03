<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;
use Tipowerup\Installer\Exceptions\PackageInstallationException;

class ComposerPharManager
{
    private const string VERSIONS_URL = 'https://getcomposer.org/versions';

    private const string DOWNLOAD_BASE_URL = 'https://getcomposer.org/download';

    private const string CACHE_KEY_VERSION = 'tipowerup.composer_phar_version';

    private const string CACHE_KEY_LAST_UPDATE_CHECK = 'tipowerup.composer_phar_last_update_check';

    private const int AUTO_UPDATE_INTERVAL_SECONDS = 86400; // 24 hours

    public function getPharPath(): string
    {
        return storage_path('app/tipowerup/bin/composer.phar');
    }

    public function isPharAvailable(): bool
    {
        return file_exists($this->getPharPath());
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function getPharCommand(): array
    {
        $phpFinder = new PhpExecutableFinder;
        $phpBinary = $phpFinder->find();

        if ($phpBinary === false) {
            throw new PackageInstallationException('Unable to find the PHP CLI binary.');
        }

        return [$phpBinary, $this->getPharPath()];
    }

    /**
     * Ensure phar is available: download if missing, auto-update if stale.
     */
    public function ensureAvailable(): bool
    {
        if ($this->isPharAvailable()) {
            $this->maybeAutoUpdate();

            return true;
        }

        return $this->download();
    }

    /**
     * Download the latest stable composer.phar and verify its SHA-256 hash.
     *
     * Optimized: sha256sum and phar are downloaded in parallel via Http::pool().
     */
    public function download(): bool
    {
        try {
            $version = $this->fetchLatestStableVersion();

            if ($version === null) {
                Log::error('ComposerPharManager: Failed to determine latest stable version');

                return false;
            }

            $pharUrl = sprintf('%s/%s/composer.phar', self::DOWNLOAD_BASE_URL, $version);
            $sha256Url = sprintf('%s/%s/composer.phar.sha256sum', self::DOWNLOAD_BASE_URL, $version);

            // Ensure target directory exists before downloading
            $dir = dirname($this->getPharPath());

            if (!is_dir($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            // Download sha256sum and phar in parallel
            $responses = Http::pool(fn (Pool $pool): array => [
                $pool->as('sha256')
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->get($sha256Url),
                $pool->as('phar')
                    ->connectTimeout(5)
                    ->timeout(30)
                    ->get($pharUrl),
            ]);

            if (!$responses['sha256']->successful()) {
                Log::error('ComposerPharManager: Failed to download SHA-256 checksum', [
                    'url' => $sha256Url,
                    'status' => $responses['sha256']->status(),
                ]);

                return false;
            }

            if (!$responses['phar']->successful()) {
                Log::error('ComposerPharManager: Failed to download composer.phar', [
                    'url' => $pharUrl,
                    'status' => $responses['phar']->status(),
                ]);

                return false;
            }

            $expectedHash = $this->parseSha256Sum($responses['sha256']->body());

            if ($expectedHash === null) {
                Log::error('ComposerPharManager: Failed to parse SHA-256 checksum', [
                    'content' => $responses['sha256']->body(),
                ]);

                return false;
            }

            $pharContent = $responses['phar']->body();

            // Verify SHA-256 hash
            $actualHash = hash('sha256', $pharContent);

            if (!hash_equals($expectedHash, $actualHash)) {
                Log::error('ComposerPharManager: SHA-256 hash mismatch', [
                    'expected' => $expectedHash,
                    'actual' => $actualHash,
                ]);

                return false;
            }

            // Write the phar file
            File::put($this->getPharPath(), $pharContent);
            chmod($this->getPharPath(), 0755);

            // Cache version info
            Cache::forever(self::CACHE_KEY_VERSION, $version);
            Cache::put(self::CACHE_KEY_LAST_UPDATE_CHECK, now()->timestamp, self::AUTO_UPDATE_INTERVAL_SECONDS);

            Log::info('ComposerPharManager: Successfully downloaded composer.phar', [
                'version' => $version,
                'path' => $this->getPharPath(),
            ]);

            return true;

        } catch (Throwable $e) {
            Log::error('ComposerPharManager: Download failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove the downloaded phar and clear cached version info.
     */
    public function remove(): void
    {
        if (file_exists($this->getPharPath())) {
            File::delete($this->getPharPath());
        }

        Cache::forget(self::CACHE_KEY_VERSION);
        Cache::forget(self::CACHE_KEY_LAST_UPDATE_CHECK);

        Log::info('ComposerPharManager: Removed composer.phar');
    }

    public function getInstalledVersion(): ?string
    {
        return Cache::get(self::CACHE_KEY_VERSION);
    }

    /**
     * Check for a newer version once per 24 hours. Non-blocking on failure.
     */
    private function maybeAutoUpdate(): void
    {
        if (Cache::has(self::CACHE_KEY_LAST_UPDATE_CHECK)) {
            return;
        }

        try {
            $latestVersion = $this->fetchLatestStableVersion();
            $currentVersion = $this->getInstalledVersion();

            if ($latestVersion !== null && $latestVersion !== $currentVersion) {
                Log::info('ComposerPharManager: Newer version available, updating', [
                    'current' => $currentVersion,
                    'latest' => $latestVersion,
                ]);

                $this->download();
            } else {
                // Just mark the check as done
                Cache::put(self::CACHE_KEY_LAST_UPDATE_CHECK, now()->timestamp, self::AUTO_UPDATE_INTERVAL_SECONDS);
            }
        } catch (Throwable $e) {
            Log::warning('ComposerPharManager: Auto-update check failed', [
                'error' => $e->getMessage(),
            ]);

            // Mark check as done even on failure to avoid retrying every request
            Cache::put(self::CACHE_KEY_LAST_UPDATE_CHECK, now()->timestamp, self::AUTO_UPDATE_INTERVAL_SECONDS);
        }
    }

    private function fetchLatestStableVersion(): ?string
    {
        try {
            $response = Http::connectTimeout(5)->timeout(10)->get(self::VERSIONS_URL);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (!is_array($data) || !isset($data['stable'][0]['version'])) {
                return null;
            }

            return $data['stable'][0]['version'];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse SHA-256 hash from sha256sum file format.
     * Handles both "hash  filename" and bare hash formats.
     */
    private function parseSha256Sum(string $content): ?string
    {
        $content = trim($content);

        // Format: "hash  filename" or "hash filename"
        if (preg_match('/^([a-f0-9]{64})\b/i', $content, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }
}
