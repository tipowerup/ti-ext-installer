<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\Concerns\ClearsInstallerCaches;

class ClearsInstallerCachesTestHarness
{
    use ClearsInstallerCaches;

    public function callClearCaches(): void
    {
        $this->clearCaches();
    }
}

it('runs the four cache:clear / config:clear / route:clear / view:clear commands without throwing', function (): void {
    // Integration smoke: exercise the real artisan path so the trait body
    // (and the opcache_reset branch when the function is present) is covered.
    // We don't assert on side-effects here — the test infrastructure rebuilds
    // caches per test anyway. The contract being checked is "this never bubbles
    // an exception out of clearCaches()".
    expect(fn () => (new ClearsInstallerCachesTestHarness)->callClearCaches())
        ->not->toThrow(Throwable::class);
});
