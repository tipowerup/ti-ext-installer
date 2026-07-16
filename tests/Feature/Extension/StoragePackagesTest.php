<?php

declare(strict_types=1);

use Igniter\Main\Classes\Theme;
use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\BaseExtension;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tipowerup\Installer\Extension;

beforeEach(function (): void {
    $this->extensionsPath = Storage::disk('local')->path('tipowerup/extensions');
    $this->themesPath = Storage::disk('local')->path('tipowerup/themes');

    $this->extension = new Extension($this->app);
    $this->invoke = (new ReflectionClass(Extension::class))->getMethod('registerStoragePackages');
    $this->invoke->setAccessible(true);
});

afterEach(function (): void {
    if (File::isDirectory($this->extensionsPath)) {
        File::deleteDirectory($this->extensionsPath);
    }

    if (File::isDirectory($this->themesPath)) {
        File::deleteDirectory($this->themesPath);
    }
});

it('logs a warning instead of bubbling when a storage extension fails to load', function (): void {
    File::ensureDirectoryExists($this->extensionsPath.'/somevendor/somepkg');
    File::put($this->extensionsPath.'/somevendor/somepkg/composer.json', '{}');

    $manager = Mockery::mock(ExtensionManager::class);
    $manager->shouldReceive('addDirectory')->once()->with($this->extensionsPath);
    $manager->shouldReceive('loadExtension')->once()->andThrow(new RuntimeException('extension load exploded'));
    $this->app->instance(ExtensionManager::class, $manager);

    Log::spy();

    expect(fn () => $this->invoke->invoke($this->extension))->not->toThrow(Throwable::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Failed to load storage extension')
            && str_contains((string) ($context['error'] ?? ''), 'extension load exploded'))
        ->once();
});

it('registers a storage extension that loads successfully', function (): void {
    File::ensureDirectoryExists($this->extensionsPath.'/somevendor/somepkg');
    File::put($this->extensionsPath.'/somevendor/somepkg/composer.json', '{}');

    $fakeExtension = new class($this->app) extends BaseExtension {};

    $manager = Mockery::mock(ExtensionManager::class);
    $manager->shouldReceive('addDirectory')->once()->with($this->extensionsPath);
    $manager->shouldReceive('loadExtension')->once()->andReturn($fakeExtension);
    $this->app->instance(ExtensionManager::class, $manager);

    expect(fn () => $this->invoke->invoke($this->extension))->not->toThrow(Throwable::class);
});

it('logs a warning instead of bubbling when a storage theme fails to load', function (): void {
    File::ensureDirectoryExists($this->themesPath.'/sometheme');

    $manager = Mockery::mock(ThemeManager::class);
    $manager->shouldReceive('loadThemes')->once();
    $manager->shouldReceive('loadTheme')->once()->andThrow(new RuntimeException('theme load exploded'));
    $this->app->instance(ThemeManager::class, $manager);

    Log::spy();

    expect(fn () => $this->invoke->invoke($this->extension))->not->toThrow(Throwable::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Failed to load storage theme')
            && str_contains((string) ($context['error'] ?? ''), 'theme load exploded'))
        ->once();
});

it('boots a storage theme that loads successfully', function (): void {
    File::ensureDirectoryExists($this->themesPath.'/sometheme');

    $theme = Mockery::mock(Theme::class);

    $manager = Mockery::mock(ThemeManager::class);
    $manager->shouldReceive('loadThemes')->once();
    $manager->shouldReceive('loadTheme')->once()->andReturn($theme);
    $manager->shouldReceive('bootTheme')->once()->with($theme);
    $this->app->instance(ThemeManager::class, $manager);

    $this->invoke->invoke($this->extension);
});

it('does not boot a storage theme when loading returns null', function (): void {
    File::ensureDirectoryExists($this->themesPath.'/sometheme');

    $manager = Mockery::mock(ThemeManager::class);
    $manager->shouldReceive('loadThemes')->once();
    $manager->shouldReceive('loadTheme')->once()->andReturn(null);
    $manager->shouldNotReceive('bootTheme');
    $this->app->instance(ThemeManager::class, $manager);

    $this->invoke->invoke($this->extension);
});
