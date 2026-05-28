<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->themesRoot = Storage::disk('local')->path('tipowerup/themes');
    $this->publicVendor = public_path('vendor');

    File::ensureDirectoryExists($this->themesRoot);
    File::ensureDirectoryExists($this->publicVendor);
});

afterEach(function (): void {
    if (File::isDirectory($this->themesRoot)) {
        File::deleteDirectory($this->themesRoot);
    }

    foreach (File::directories($this->publicVendor) as $dir) {
        if (str_starts_with(basename((string) $dir), 'test-')) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Seed a fake storage-installed theme at $themesRoot/$slug with the given
 * source directories populated by [$relativePath => $contents] file maps.
 */
function seedTheme(string $themesRoot, string $slug, array $sources): string
{
    $themePath = $themesRoot.'/'.$slug;
    File::ensureDirectoryExists($themePath);

    foreach ($sources as $sourceDir => $files) {
        $sourcePath = $themePath.'/'.$sourceDir;
        File::ensureDirectoryExists($sourcePath);

        foreach ($files as $relative => $contents) {
            $full = $sourcePath.'/'.$relative;
            File::ensureDirectoryExists(dirname($full));
            File::put($full, $contents);
        }
    }

    return $themePath;
}

it('publishes a theme that ships a public/ directory', function (): void {
    seedTheme($this->themesRoot, 'test-orange', [
        'public' => [
            'css/app.css' => 'body{color:red}',
            'js/app.js' => 'console.log(1)',
        ],
    ]);

    $this->artisan('tipowerup:republish-assets', ['theme' => 'test-orange'])
        ->assertExitCode(0)
        ->expectsOutputToContain('test-orange');

    expect(File::get(public_path('vendor/test-orange/css/app.css')))->toBe('body{color:red}');
    expect(File::get(public_path('vendor/test-orange/js/app.js')))->toBe('console.log(1)');
});

it('publishes a theme that ships an assets/ directory (TI legacy convention)', function (): void {
    seedTheme($this->themesRoot, 'test-legacy', [
        'assets' => [
            'css/legacy.css' => '/* legacy */',
        ],
    ]);

    $this->artisan('tipowerup:republish-assets', ['theme' => 'test-legacy'])
        ->assertExitCode(0);

    expect(File::exists(public_path('vendor/test-legacy/css/legacy.css')))->toBeTrue();
});

it('merges assets/ and public/ when both exist, with public/ winning on conflict', function (): void {
    seedTheme($this->themesRoot, 'test-merge', [
        'assets' => [
            'css/app.css' => 'from-assets',
            'js/only-assets.js' => 'asset-only',
        ],
        'public' => [
            'css/app.css' => 'from-public',
            'js/only-public.js' => 'public-only',
        ],
    ]);

    $this->artisan('tipowerup:republish-assets', ['theme' => 'test-merge'])
        ->assertExitCode(0);

    expect(File::get(public_path('vendor/test-merge/css/app.css')))->toBe('from-public');
    expect(File::exists(public_path('vendor/test-merge/js/only-assets.js')))->toBeTrue();
    expect(File::exists(public_path('vendor/test-merge/js/only-public.js')))->toBeTrue();
});

it('skips themes with no assets/ or public/ directory', function (): void {
    seedTheme($this->themesRoot, 'test-empty', []);

    $this->artisan('tipowerup:republish-assets', ['theme' => 'test-empty'])
        ->assertExitCode(0)
        ->expectsOutputToContain('skipped');

    expect(File::isDirectory(public_path('vendor/test-empty')))->toBeFalse();
});

it('publishes all themes when no slug is given', function (): void {
    seedTheme($this->themesRoot, 'test-a', ['public' => ['a.css' => 'A']]);
    seedTheme($this->themesRoot, 'test-b', ['public' => ['b.css' => 'B']]);

    $this->artisan('tipowerup:republish-assets')
        ->assertExitCode(0)
        ->expectsOutputToContain('2 published');

    expect(File::get(public_path('vendor/test-a/a.css')))->toBe('A');
    expect(File::get(public_path('vendor/test-b/b.css')))->toBe('B');
});

it('does not write to disk in --dry-run mode', function (): void {
    seedTheme($this->themesRoot, 'test-dry', ['public' => ['x.css' => 'never written']]);

    $this->artisan('tipowerup:republish-assets', ['theme' => 'test-dry', '--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('would copy');

    expect(File::isDirectory(public_path('vendor/test-dry')))->toBeFalse();
});

it('returns failure exit code when a named theme does not exist', function (): void {
    $this->artisan('tipowerup:republish-assets', ['theme' => 'test-missing'])
        ->assertExitCode(1)
        ->expectsOutputToContain('Theme not found');
});

it('reports success when no themes are installed at all', function (): void {
    File::deleteDirectory($this->themesRoot);

    $this->artisan('tipowerup:republish-assets')
        ->assertExitCode(0)
        ->expectsOutputToContain('No storage themes directory found');
});
