<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tipowerup\Installer\Exceptions\LicenseValidationException;
use Tipowerup\Installer\Services\PowerUpApiClient;

beforeEach(function (): void {
    config()->set('tipowerup.installer.api_url', 'https://api.tipowerup.com');

    $this->client = new class extends PowerUpApiClient
    {
        public function __construct() {}
    };

    $this->client->setApiKey('test-key');
    $this->client->setMaxRetries(1);
});

// verifyKey

it('verifyKey returns data on success', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/validate' => Http::response([
            'success' => true,
            'data' => ['plan' => 'pro', 'expires_at' => '2027-01-01'],
        ], 200),
    ]);

    $result = $this->client->verifyKey();

    expect($result)->toBe(['plan' => 'pro', 'expires_at' => '2027-01-01']);
});

it('verifyKey throws LicenseValidationException on failure response', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/validate' => Http::response([
            'success' => false,
            'message' => 'Invalid API key.',
        ], 200),
    ]);

    expect(fn () => $this->client->verifyKey())
        ->toThrow(LicenseValidationException::class, 'Invalid API key.');
});

it('verifyKey throws LicenseValidationException with default message when none provided', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/validate' => Http::response([
            'success' => false,
        ], 200),
    ]);

    expect(fn () => $this->client->verifyKey())
        ->toThrow(LicenseValidationException::class, 'PowerUp key verification failed.');
});

// verifyLicense

it('verifyLicense returns data on success', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/verify-key' => Http::response([
            'success' => true,
            'data' => ['package_code' => 'tipowerup.darkmode', 'licensed' => true],
        ], 200),
    ]);

    $result = $this->client->verifyLicense('tipowerup.darkmode');

    expect($result)->toBe(['package_code' => 'tipowerup.darkmode', 'licensed' => true]);
});

it('verifyLicense throws LicenseValidationException on failure response', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/verify-key' => Http::response([
            'success' => false,
            'message' => 'License not found for this domain.',
        ], 200),
    ]);

    expect(fn () => $this->client->verifyLicense('tipowerup.darkmode'))
        ->toThrow(LicenseValidationException::class, 'License not found for this domain.');
});

it('verifyLicense throws LicenseValidationException with default message when none provided', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/verify-key' => Http::response([
            'success' => false,
        ], 200),
    ]);

    expect(fn () => $this->client->verifyLicense('tipowerup.darkmode'))
        ->toThrow(LicenseValidationException::class, 'License validation failed for this package.');
});

// checkUpdates

it('checkUpdates returns update data', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/check-updates' => Http::response([
            'success' => true,
            'data' => [
                ['package_code' => 'tipowerup.darkmode', 'latest_version' => '1.2.0'],
            ],
        ], 200),
    ]);

    $result = $this->client->checkUpdates(['tipowerup.darkmode' => '1.0.0']);

    expect($result)->toBe([
        ['package_code' => 'tipowerup.darkmode', 'latest_version' => '1.2.0'],
    ]);
});

it('checkUpdates returns empty array when data key is missing', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/check-updates' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $result = $this->client->checkUpdates([]);

    expect($result)->toBe([]);
});

// getMyPackages

it('getMyPackages returns packages', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products*' => Http::response([
            'success' => true,
            'data' => [
                ['code' => 'tipowerup.darkmode', 'name' => 'Dark Mode'],
                ['code' => 'tipowerup.loyalty', 'name' => 'Loyalty Points'],
            ],
        ], 200),
    ]);

    $result = $this->client->getMyPackages();

    expect($result)
        ->toHaveKey('success', true)
        ->toHaveKey('data')
        ->and($result['data'])->toHaveCount(2);
});

// getMarketplace

it('getMarketplace sends filters as query params', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/marketplace*' => Http::response([
            'success' => true,
            'data' => [],
        ], 200),
    ]);

    $this->client->getMarketplace([
        'search' => 'dark',
        'type' => 'extension',
        'page' => 1,
        'per_page' => 20,
    ]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'search=dark')
            && str_contains($request->url(), 'type=extension')
            && str_contains($request->url(), 'page=1')
            && str_contains($request->url(), 'per_page=20');
    });
});

it('getMarketplace filters out null values', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/marketplace*' => Http::response([
            'success' => true,
            'data' => [],
        ], 200),
    ]);

    $this->client->getMarketplace(['search' => 'dark', 'type' => null]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'search=dark')
            && !str_contains($request->url(), 'type=');
    });
});

// getPackageDetail

it('getPackageDetail returns package data', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products/tipowerup.darkmode' => Http::response([
            'success' => true,
            'data' => ['code' => 'tipowerup.darkmode', 'name' => 'Dark Mode', 'version' => '1.0.0'],
        ], 200),
    ]);

    $result = $this->client->getPackageDetail('tipowerup.darkmode');

    expect($result)->toBe(['code' => 'tipowerup.darkmode', 'name' => 'Dark Mode', 'version' => '1.0.0']);
});

it('getPackageDetail returns empty array when data key is missing', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products/tipowerup.darkmode' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $result = $this->client->getPackageDetail('tipowerup.darkmode');

    expect($result)->toBe([]);
});

// getProductVersion

it('getProductVersion returns version data', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/product/tipowerup-darkmode/version' => Http::response([
            'success' => true,
            'version' => '1.3.0',
            'changelog' => 'Bug fixes.',
        ], 200),
    ]);

    $result = $this->client->getProductVersion('tipowerup-darkmode');

    expect($result)->toBe([
        'success' => true,
        'version' => '1.3.0',
        'changelog' => 'Bug fixes.',
    ]);
});

// downloadProduct

it('downloadProduct returns download data', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/product/tipowerup-darkmode/download' => Http::response([
            'success' => true,
            'download_url' => 'https://pkg.tipowerup.com/tipowerup-darkmode-1.3.0.zip',
            'checksum' => 'abc123',
        ], 200),
    ]);

    $result = $this->client->downloadProduct('tipowerup-darkmode');

    expect($result)
        ->toHaveKey('success', true)
        ->toHaveKey('download_url')
        ->toHaveKey('checksum');
});

// submitReport

it('submitReport returns data on success', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/report-issue' => Http::response([
            'success' => true,
            'data' => ['ticket_id' => 'TKT-999'],
        ], 200),
    ]);

    $result = $this->client->submitReport(['environment' => [], 'logs' => []]);

    expect($result)->toBe(['ticket_id' => 'TKT-999']);
});

it('submitReport throws RuntimeException on failure', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/report-issue' => Http::response([
            'success' => false,
            'message' => 'Report submission failed.',
        ], 200),
    ]);

    expect(fn () => $this->client->submitReport(['environment' => [], 'logs' => []]))
        ->toThrow(RuntimeException::class, 'Report submission failed.');
});

it('submitReport throws RuntimeException with default message when none provided', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/report-issue' => Http::response([
            'success' => false,
        ], 200),
    ]);

    expect(fn () => $this->client->submitReport([]))
        ->toThrow(RuntimeException::class, 'Failed to submit report.');
});

// acquireFreeProduct

it('acquireFreeProduct returns data on success', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/acquire-free' => Http::response([
            'success' => true,
            'data' => ['license_key' => 'FREE-XXXX-YYYY'],
        ], 200),
    ]);

    $result = $this->client->acquireFreeProduct('tipowerup.darkmode');

    expect($result)->toBe(['license_key' => 'FREE-XXXX-YYYY']);
});

it('acquireFreeProduct throws RuntimeException on failure', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/acquire-free' => Http::response([
            'success' => false,
            'error' => 'Product not available for free acquisition.',
        ], 200),
    ]);

    expect(fn () => $this->client->acquireFreeProduct('tipowerup.darkmode'))
        ->toThrow(RuntimeException::class, 'Product not available for free acquisition.');
});

it('acquireFreeProduct throws RuntimeException with default message when no error provided', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/acquire-free' => Http::response([
            'success' => false,
        ], 200),
    ]);

    expect(fn () => $this->client->acquireFreeProduct('tipowerup.darkmode'))
        ->toThrow(RuntimeException::class, 'Failed to acquire product.');
});

// HTTP error handling

it('throws RuntimeException on 4xx client error without retrying', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products*' => Http::response([
            'message' => 'Unauthorized.',
        ], 401),
    ]);

    expect(fn () => $this->client->getMyPackages())
        ->toThrow(RuntimeException::class, 'Unauthorized.');
});

it('does not retry on 4xx client errors', function (): void {
    $this->client->setMaxRetries(3);

    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products*' => Http::response([
            'message' => 'Forbidden.',
        ], 403),
    ]);

    expect(fn () => $this->client->getMyPackages())
        ->toThrow(RuntimeException::class);

    Http::assertSentCount(1);
});

it('throws RuntimeException after max retries on 5xx server error', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products*' => Http::response([], 500),
    ]);

    expect(fn () => $this->client->getMyPackages())
        ->toThrow(RuntimeException::class, 'Server error: 500');
});

it('throws RuntimeException after exhausting retries on ConnectionException', function (): void {
    Http::fake(function (): never {
        throw new ConnectionException('Connection refused.');
    });

    expect(fn () => $this->client->getMyPackages())
        ->toThrow(RuntimeException::class);
});

// Auth headers

it('sends Authorization header when API key is set', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products*' => Http::response(['success' => true], 200),
    ]);

    $this->client->setApiKey('my-secret-key');
    $this->client->getMyPackages();

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Authorization', 'Bearer my-secret-key');
    });
});

it('does not send Authorization header when API key is empty', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products*' => Http::response(['success' => true], 200),
    ]);

    $this->client->setApiKey('');
    $this->client->getMyPackages();

    Http::assertSent(function ($request): bool {
        return !$request->hasHeader('Authorization');
    });
});

it('sends X-PowerUp-Platform and User-Agent headers', function (): void {
    Http::fake([
        'api.tipowerup.com/api/v1/powerup/products*' => Http::response(['success' => true], 200),
    ]);

    $this->client->getMyPackages();

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('X-PowerUp-Platform')
            && $request->hasHeader('User-Agent', 'TastyIgniter-PowerUp-Installer/1.0');
    });
});

// setTimeout chaining

it('setTimeout returns self for chaining', function (): void {
    $result = $this->client->setTimeout(60);

    expect($result)->toBe($this->client);
});

it('setMaxRetries returns self for chaining', function (): void {
    $result = $this->client->setMaxRetries(2);

    expect($result)->toBe($this->client);
});
