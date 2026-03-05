<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Tipowerup\Installer\Exceptions\LicenseValidationException;

class PowerUpApiClient
{
    private const int TIMEOUT_SECONDS = 30;

    private const int MAX_RETRIES = 3;

    private int $timeout = self::TIMEOUT_SECONDS;

    private int $maxRetries = self::MAX_RETRIES;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = params('tipowerup_api_key', '');
    }

    public function setApiKey(string $key): void
    {
        $this->apiKey = $key;
    }

    public function setTimeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function setMaxRetries(int $retries): static
    {
        $this->maxRetries = $retries;

        return $this;
    }

    public function verifyKey(): array
    {
        $data = $this->makeRequest('POST', '/api/v1/powerup/validate', [
            'domain' => request()->getHost(),
        ]);

        if (!($data['success'] ?? false)) {
            throw new LicenseValidationException(
                $data['message'] ?? 'PowerUp key verification failed.'
            );
        }

        return $data['data'] ?? [];
    }

    public function verifyLicense(string $packageCode): array
    {
        $data = $this->makeRequest('POST', '/api/v1/powerup/verify-key', [
            'package_code' => $packageCode,
            'domain' => request()->getHost(),
            'ti_version' => app()->version(),
        ]);

        if (!($data['success'] ?? false)) {
            throw new LicenseValidationException(
                $data['message'] ?? 'License validation failed for this package.'
            );
        }

        return $data['data'] ?? [];
    }

    public function checkUpdates(array $installedPackages): array
    {
        $response = $this->makeRequest('POST', '/api/v1/powerup/check-updates', [
            'packages' => $installedPackages,
        ]);

        return $response['data'] ?? [];
    }

    public function getMyPackages(): array
    {
        return $this->makeRequest('GET', '/api/v1/powerup/products');
    }

    public function getMarketplace(array $filters = []): array
    {
        $queryParams = array_filter([
            'search' => $filters['search'] ?? null,
            'type' => $filters['type'] ?? null,
            'page' => $filters['page'] ?? null,
            'per_page' => $filters['per_page'] ?? null,
        ]);

        return $this->makeRequest('GET', '/api/v1/powerup/marketplace', $queryParams);
    }

    public function getPackageDetail(string $packageCode): array
    {
        $response = $this->makeRequest('GET', '/api/v1/powerup/products/'.$packageCode);

        return $response['data'] ?? [];
    }

    public function getProductVersion(string $slug): array
    {
        return $this->makeRequest('GET', '/api/v1/powerup/product/'.$slug.'/version');
    }

    public function downloadProduct(string $slug): array
    {
        return $this->makeRequest('GET', '/api/v1/powerup/product/'.$slug.'/download');
    }

    public function acquireFreeProduct(string $packageCode): array
    {
        $data = $this->makeRequest('POST', '/api/v1/powerup/acquire-free', [
            'package_code' => $packageCode,
        ]);

        if (!($data['success'] ?? false)) {
            throw new RuntimeException($data['error'] ?? 'Failed to acquire product.');
        }

        return $data['data'] ?? [];
    }

    private function makeRequest(string $method, string $uri, array $data = []): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                Log::debug('PowerUp API Request', [
                    'method' => $method,
                    'uri' => $uri,
                    'attempt' => $attempt,
                ]);

                $request = Http::baseUrl(config('tipowerup.installer.api_url'))
                    ->timeout($this->timeout)
                    ->acceptJson()
                    ->withHeaders($this->buildHeaders());

                if ($this->apiKey !== '' && $this->apiKey !== '0') {
                    $request = $request->withToken($this->apiKey);
                }

                $response = match (strtoupper($method)) {
                    'POST' => $request->post($uri, $data),
                    'GET' => $request->get($uri, $data),
                    default => throw new InvalidArgumentException('Unsupported HTTP method: '.$method),
                };

                if ($response->successful()) {
                    Log::debug('PowerUp API Response Success', [
                        'uri' => $uri,
                        'status' => $response->status(),
                    ]);

                    return $response->json();
                }

                // Log non-successful response
                Log::warning('PowerUp API Response Failed', [
                    'uri' => $uri,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // If it's a client error (4xx), don't retry
                if ($response->clientError()) {
                    $errorMessage = $response->json('message')
                        ?? $response->json('error')
                        ?? 'API request failed';

                    throw new RuntimeException($errorMessage, $response->status());
                }

                // Server error (5xx), retry
                throw new RuntimeException(
                    'Server error: '.$response->status(),
                    $response->status()
                );

            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning(sprintf('PowerUp API connection failed (attempt %d)', $attempt), [
                    'uri' => $uri,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    // Exponential backoff: 1s, 2s, 4s
                    sleep(2 ** ($attempt - 1));

                    continue;
                }
            } catch (RuntimeException $e) {
                $lastException = $e;
                Log::error('PowerUp API request failed', [
                    'uri' => $uri,
                    'error' => $e->getMessage(),
                ]);

                // Don't retry runtime exceptions (they're usually unrecoverable)
                throw $e;
            }
        }

        // If we've exhausted all retries
        throw new RuntimeException(
            'Failed to connect to PowerUp API after '.$this->maxRetries.' attempts: '.
            ($lastException ? $lastException->getMessage() : 'Unknown error'),
            0,
            $lastException
        );
    }

    private function buildHeaders(): array
    {
        return [
            'X-PowerUp-Platform' => sprintf(
                'php:%s;ti:%s;domain:%s',
                PHP_VERSION,
                app()->version(),
                request()->getHost()
            ),
            'User-Agent' => 'TastyIgniter-PowerUp-Installer/1.0',
        ];
    }
}
