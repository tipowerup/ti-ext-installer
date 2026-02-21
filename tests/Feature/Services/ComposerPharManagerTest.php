<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tipowerup\Installer\Services\ComposerPharManager;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function pharManager(): ComposerPharManager
{
    return resolve(ComposerPharManager::class);
}

function cleanupPhar(): void
{
    $manager = pharManager();

    if (file_exists($manager->getPharPath())) {
        unlink($manager->getPharPath());
    }

    Cache::forget('tipowerup.composer_phar_version');
    Cache::forget('tipowerup.composer_phar_last_update_check');
}

function fakeSuccessfulDownload(string $version = '2.8.6'): string
{
    $pharContent = 'fake-phar-binary-content-for-testing';
    $hash = hash('sha256', $pharContent);

    Http::fake([
        'getcomposer.org/versions' => Http::response([
            'stable' => [['version' => $version]],
        ]),
        "getcomposer.org/download/{$version}/composer.phar.sha256sum" => Http::response(
            "{$hash}  composer.phar"
        ),
        "getcomposer.org/download/{$version}/composer.phar" => Http::response($pharContent),
    ]);

    return $pharContent;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

afterEach(function (): void {
    cleanupPhar();
});

it('getPharPath returns correct storage path', function (): void {
    $path = pharManager()->getPharPath();

    expect($path)->toEndWith('storage/app/tipowerup/bin/composer.phar');
});

it('isPharAvailable returns false when file missing', function (): void {
    expect(pharManager()->isPharAvailable())->toBeFalse();
});

it('isPharAvailable returns true when file exists', function (): void {
    $manager = pharManager();
    $dir = dirname($manager->getPharPath());

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($manager->getPharPath(), 'test');

    expect($manager->isPharAvailable())->toBeTrue();
});

it('getPharCommand returns PHP_BINARY and phar path', function (): void {
    $command = pharManager()->getPharCommand();

    expect($command)->toBe([PHP_BINARY, pharManager()->getPharPath()]);
});

it('download succeeds with valid HTTP responses and SHA-256 match', function (): void {
    fakeSuccessfulDownload('2.8.6');

    $result = pharManager()->download();

    expect($result)->toBeTrue();
    expect(pharManager()->isPharAvailable())->toBeTrue();
    expect(pharManager()->getInstalledVersion())->toBe('2.8.6');
});

it('download returns false on SHA-256 mismatch', function (): void {
    Http::fake([
        'getcomposer.org/versions' => Http::response([
            'stable' => [['version' => '2.8.6']],
        ]),
        'getcomposer.org/download/2.8.6/composer.phar.sha256sum' => Http::response(
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa  composer.phar'
        ),
        'getcomposer.org/download/2.8.6/composer.phar' => Http::response('phar-content'),
    ]);

    $result = pharManager()->download();

    expect($result)->toBeFalse();
    expect(pharManager()->isPharAvailable())->toBeFalse();
});

it('download returns false on network failure for versions endpoint', function (): void {
    Http::fake([
        'getcomposer.org/versions' => Http::response('', 500),
    ]);

    $result = pharManager()->download();

    expect($result)->toBeFalse();
});

it('download returns false on network failure for phar download', function (): void {
    Http::fake([
        'getcomposer.org/versions' => Http::response([
            'stable' => [['version' => '2.8.6']],
        ]),
        'getcomposer.org/download/2.8.6/composer.phar.sha256sum' => Http::response(
            hash('sha256', 'content').'  composer.phar'
        ),
        'getcomposer.org/download/2.8.6/composer.phar' => Http::response('', 500),
    ]);

    $result = pharManager()->download();

    expect($result)->toBeFalse();
});

it('remove deletes file and clears cache', function (): void {
    fakeSuccessfulDownload();
    pharManager()->download();

    expect(pharManager()->isPharAvailable())->toBeTrue();
    expect(pharManager()->getInstalledVersion())->not->toBeNull();

    pharManager()->remove();

    expect(pharManager()->isPharAvailable())->toBeFalse();
    expect(pharManager()->getInstalledVersion())->toBeNull();
});

it('ensureAvailable downloads when phar is missing', function (): void {
    fakeSuccessfulDownload();

    $result = pharManager()->ensureAvailable();

    expect($result)->toBeTrue();
    expect(pharManager()->isPharAvailable())->toBeTrue();
});

it('ensureAvailable returns true without downloading when phar exists', function (): void {
    // First download
    fakeSuccessfulDownload();
    pharManager()->download();

    // Mark update check as done so no HTTP calls are made
    Cache::put('tipowerup.composer_phar_last_update_check', now()->timestamp, 86400);

    Http::fake(); // No requests should be made

    $result = pharManager()->ensureAvailable();

    expect($result)->toBeTrue();

    Http::assertNothingSent();
});

it('parseSha256Sum handles "hash  filename" format', function (): void {
    fakeSuccessfulDownload();

    // The successful download test already validates this format
    $result = pharManager()->download();

    expect($result)->toBeTrue();
});

it('download returns false when sha256sum content is invalid', function (): void {
    Http::fake([
        'getcomposer.org/versions' => Http::response([
            'stable' => [['version' => '2.8.6']],
        ]),
        'getcomposer.org/download/2.8.6/composer.phar.sha256sum' => Http::response('not-a-valid-hash'),
        'getcomposer.org/download/2.8.6/composer.phar' => Http::response('content'),
    ]);

    $result = pharManager()->download();

    expect($result)->toBeFalse();
});
