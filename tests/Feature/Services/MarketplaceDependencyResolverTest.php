<?php

declare(strict_types=1);

use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\MarketplaceDependencyResolver;

beforeEach(function (): void {
    $migrationPath = dirname(__DIR__, 3).'/database/migrations';
    $this->loadMigrationsFrom($migrationPath);

    $this->resolver = new MarketplaceDependencyResolver;
});

function makeLicense(
    string $code,
    string $version = '1.0.0',
    array $requires = [],
    bool $active = true,
): License {
    return License::create([
        'package_code' => $code,
        'package_name' => $code,
        'package_type' => 'extension',
        'version' => $version,
        'install_method' => 'direct',
        'requires_marketplace_packages' => $requires,
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => $active,
    ]);
}

// ---------------------------------------------------------------------------
// missing()
// ---------------------------------------------------------------------------

it('returns empty when dep list is empty', function (): void {
    expect($this->resolver->missing([]))->toBe([]);
});

it('reports a dep as missing when no license exists for it', function (): void {
    $deps = [['name' => 'Toolkit', 'code' => 'tipowerup/ti-theme-toolkit', 'min_version' => '0.4.0']];

    expect($this->resolver->missing($deps))->toHaveCount(1)
        ->and($this->resolver->missing($deps)[0]['code'])->toBe('tipowerup/ti-theme-toolkit');
});

it('treats an installed dep at sufficient version as satisfied', function (): void {
    makeLicense('tipowerup/ti-theme-toolkit', '0.4.2');

    $deps = [['name' => 'Toolkit', 'code' => 'tipowerup/ti-theme-toolkit', 'min_version' => '0.4.0']];

    expect($this->resolver->missing($deps))->toBe([]);
});

it('reports a dep as missing when installed version is too old', function (): void {
    makeLicense('tipowerup/ti-theme-toolkit', '0.3.9');

    $deps = [['name' => 'Toolkit', 'code' => 'tipowerup/ti-theme-toolkit', 'min_version' => '0.4.0']];

    expect($this->resolver->missing($deps))->toHaveCount(1);
});

it('treats an inactive license as not installed', function (): void {
    makeLicense('tipowerup/ti-theme-toolkit', '1.0.0', active: false);

    $deps = [['name' => 'Toolkit', 'code' => 'tipowerup/ti-theme-toolkit', 'min_version' => '0.4.0']];

    expect($this->resolver->missing($deps))->toHaveCount(1);
});

it('considers a dep with no min_version always satisfied if installed', function (): void {
    makeLicense('tipowerup/ti-theme-toolkit', '0.0.1');

    $deps = [['name' => 'Toolkit', 'code' => 'tipowerup/ti-theme-toolkit']];

    expect($this->resolver->missing($deps))->toBe([]);
});

it('skips dep entries that have no code', function (): void {
    $deps = [['name' => 'Mystery', 'min_version' => '1.0.0']];

    expect($this->resolver->missing($deps))->toBe([]);
});

// ---------------------------------------------------------------------------
// dependents()
// ---------------------------------------------------------------------------

it('returns no dependents when nothing requires the package', function (): void {
    makeLicense('tipowerup/ti-theme-toolkit');
    makeLicense('tipowerup.standalone');

    expect($this->resolver->dependents('tipowerup/ti-theme-toolkit'))->toBe([]);
});

it('lists every active license that requires the package', function (): void {
    makeLicense('tipowerup/ti-theme-toolkit');
    makeLicense('tipowerup.theme-a', requires: [
        ['name' => 'Toolkit', 'code' => 'tipowerup/ti-theme-toolkit', 'min_version' => '0.4.0'],
    ]);
    makeLicense('tipowerup.theme-b', requires: [
        ['name' => 'Toolkit', 'code' => 'tipowerup/ti-theme-toolkit', 'min_version' => '0.4.0'],
    ]);

    expect($this->resolver->dependents('tipowerup/ti-theme-toolkit'))
        ->toEqualCanonicalizing(['tipowerup.theme-a', 'tipowerup.theme-b']);
});

it('ignores inactive licenses when listing dependents', function (): void {
    makeLicense('tipowerup/ti-theme-toolkit');
    makeLicense('tipowerup.theme-a', requires: [
        ['code' => 'tipowerup/ti-theme-toolkit'],
    ], active: false);

    expect($this->resolver->dependents('tipowerup/ti-theme-toolkit'))->toBe([]);
});

it('does not list the package itself as its own dependent', function (): void {
    makeLicense('tipowerup/ti-theme-toolkit', requires: [
        ['code' => 'tipowerup/ti-theme-toolkit'],
    ]);

    expect($this->resolver->dependents('tipowerup/ti-theme-toolkit'))->toBe([]);
});
