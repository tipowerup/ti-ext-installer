<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tipowerup\Installer\Extension;

beforeEach(function (): void {
    $this->extension = new Extension($this->app);
    $this->invoke = (new ReflectionClass(Extension::class))->getMethod('defineRoutes');
    $this->invoke->setAccessible(true);
});

it('skips route registration when routes are cached', function (): void {
    // routesAreCached() memoizes into the 'routes.cached' container binding on
    // first call, so binding it directly is the only reliable way to force the
    // cached branch in tests (writing the cache file alone doesn't invalidate it).
    $this->app->instance('routes.cached', true);

    $countBefore = count(Route::getRoutes()->getRoutes());

    $this->invoke->invoke($this->extension);

    expect(count(Route::getRoutes()->getRoutes()))->toBe($countBefore);
});

it('registers the background update check route when routes are not cached', function (): void {
    $this->invoke->invoke($this->extension);

    expect(Route::has('tipowerup.installer.check-updates-bg'))->toBeTrue();
});
