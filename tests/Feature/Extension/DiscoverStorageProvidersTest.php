<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tipowerup\Installer\Extension;
use Tipowerup\Installer\Tests\Fixtures\FakeStorageProvider;
use Tipowerup\Installer\Tests\Fixtures\ThrowingStorageProvider;

beforeEach(function (): void {
    $this->tmpPackagePath = sys_get_temp_dir().'/tipowerup-discover-providers-test-'.uniqid();
    File::makeDirectory($this->tmpPackagePath.'/vendor/composer', 0755, true);

    $this->extension = new Extension($this->app);

    $reflection = new ReflectionClass(Extension::class);
    $this->discover = $reflection->getMethod('discoverStorageProviders');
    $this->discover->setAccessible(true);
    $this->bootPackage = $reflection->getMethod('bootStoragePackage');
    $this->bootPackage->setAccessible(true);
});

afterEach(function (): void {
    if (File::isDirectory($this->tmpPackagePath)) {
        File::deleteDirectory($this->tmpPackagePath);
    }
});

it('reads providers from the bundled installed.json manifest', function (): void {
    File::put($this->tmpPackagePath.'/vendor/composer/installed.json', json_encode([
        'packages' => [
            ['extra' => ['laravel' => ['providers' => [FakeStorageProvider::class]]]],
        ],
    ]));

    $providers = $this->discover->invoke($this->extension, $this->tmpPackagePath);

    expect($providers)->toBe([FakeStorageProvider::class]);
});

it('reads providers from the package composer.json and dedupes against installed.json', function (): void {
    File::put($this->tmpPackagePath.'/vendor/composer/installed.json', json_encode([
        'packages' => [
            ['extra' => ['laravel' => ['providers' => [FakeStorageProvider::class]]]],
        ],
    ]));
    File::put($this->tmpPackagePath.'/composer.json', json_encode([
        'extra' => ['laravel' => ['providers' => [FakeStorageProvider::class, ThrowingStorageProvider::class]]],
    ]));

    $providers = $this->discover->invoke($this->extension, $this->tmpPackagePath);

    expect($providers)->toBe([FakeStorageProvider::class, ThrowingStorageProvider::class]);
});

it('logs a warning instead of bubbling when installed.json is malformed', function (): void {
    File::put($this->tmpPackagePath.'/vendor/composer/installed.json', 'not json');

    Log::spy();

    $providers = $this->discover->invoke($this->extension, $this->tmpPackagePath);

    expect($providers)->toBe([]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Failed to parse bundled installed.json'))
        ->once();
});

it('logs a warning instead of bubbling when composer.json is malformed', function (): void {
    File::put($this->tmpPackagePath.'/composer.json', 'not json');

    Log::spy();

    $providers = $this->discover->invoke($this->extension, $this->tmpPackagePath);

    expect($providers)->toBe([]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Failed to parse storage package composer.json'))
        ->once();
});

it('registers a discovered provider that is not already loaded', function (): void {
    File::put($this->tmpPackagePath.'/composer.json', json_encode([
        'extra' => ['laravel' => ['providers' => [FakeStorageProvider::class]]],
    ]));

    $this->bootPackage->invoke($this->extension, $this->tmpPackagePath);

    expect($this->app->getLoadedProviders())->toHaveKey(FakeStorageProvider::class);
});

it('skips a discovered provider that is already loaded', function (): void {
    $this->app->register(FakeStorageProvider::class);

    File::put($this->tmpPackagePath.'/composer.json', json_encode([
        'extra' => ['laravel' => ['providers' => [FakeStorageProvider::class]]],
    ]));

    expect(fn () => $this->bootPackage->invoke($this->extension, $this->tmpPackagePath))
        ->not->toThrow(Throwable::class);
});

it('logs a warning instead of bubbling when a discovered provider fails to register', function (): void {
    File::put($this->tmpPackagePath.'/composer.json', json_encode([
        'extra' => ['laravel' => ['providers' => [ThrowingStorageProvider::class]]],
    ]));

    Log::spy();

    expect(fn () => $this->bootPackage->invoke($this->extension, $this->tmpPackagePath))
        ->not->toThrow(Throwable::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Failed to register storage package provider')
            && ($context['provider'] ?? null) === ThrowingStorageProvider::class
            && str_contains((string) ($context['error'] ?? ''), 'provider registration exploded'))
        ->once();
});
