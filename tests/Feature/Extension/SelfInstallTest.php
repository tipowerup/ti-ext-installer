<?php

declare(strict_types=1);

use Igniter\Flame\Support\Facades\Igniter;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tipowerup\Installer\Extension;

beforeEach(function (): void {
    Cache::forget('tipowerup.installer.self_installed');

    // The Pest/PHPUnit runner is itself a console process, so force the
    // "in admin request" branch open the way boot() would see it in production.
    $prop = new ReflectionProperty($this->app, 'isRunningInConsole');
    $prop->setValue($this->app, false);
    $this->app->instance('request', Request::create(rtrim(Igniter::adminUri(), '/').'/foo'));

    if (Schema::hasTable('tip_licenses')) {
        Schema::drop('tip_licenses');
    }

    $this->extension = new Extension($this->app);
    $this->invoke = (new ReflectionClass(Extension::class))->getMethod('selfInstallIfNeeded');
    $this->invoke->setAccessible(true);
});

it('does nothing once the self-installed cache flag is already set', function (): void {
    Cache::forever('tipowerup.installer.self_installed', true);

    $manager = Mockery::mock(ExtensionManager::class);
    $manager->shouldNotReceive('installExtension');
    $this->app->instance(ExtensionManager::class, $manager);

    $this->invoke->invoke($this->extension);
});

it('sets the cache flag without installing when the tables already exist', function (): void {
    Schema::create('tip_licenses', function ($table): void {
        $table->id();
    });

    $manager = Mockery::mock(ExtensionManager::class);
    $manager->shouldNotReceive('installExtension');
    $this->app->instance(ExtensionManager::class, $manager);

    $this->invoke->invoke($this->extension);

    expect(Cache::get('tipowerup.installer.self_installed'))->toBeTrue();
});

it('installs the extension and sets the cache flag when the tables are missing', function (): void {
    $manager = Mockery::mock(ExtensionManager::class);
    $manager->shouldReceive('installExtension')->once()->with('tipowerup.installer');
    $this->app->instance(ExtensionManager::class, $manager);

    $this->invoke->invoke($this->extension);

    expect(Cache::get('tipowerup.installer.self_installed'))->toBeTrue();
});

it('logs a warning instead of bubbling when self-install fails', function (): void {
    $manager = Mockery::mock(ExtensionManager::class);
    $manager->shouldReceive('installExtension')->once()->andThrow(new RuntimeException('self-install exploded'));
    $this->app->instance(ExtensionManager::class, $manager);

    Log::spy();

    expect(fn () => $this->invoke->invoke($this->extension))->not->toThrow(Throwable::class);

    expect(Cache::get('tipowerup.installer.self_installed'))->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'self-install failed')
            && str_contains((string) ($context['error'] ?? ''), 'self-install exploded'))
        ->once();
});
