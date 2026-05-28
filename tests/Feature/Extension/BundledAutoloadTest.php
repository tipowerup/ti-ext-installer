<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tipowerup\Installer\Extension;

beforeEach(function (): void {
    $this->tmpPackagePath = sys_get_temp_dir().'/tipowerup-bundled-autoload-test-'.uniqid();
    File::makeDirectory($this->tmpPackagePath.'/vendor', 0755, true);

    $this->extension = new Extension($this->app);
    $this->invoke = (new ReflectionClass(Extension::class))->getMethod('bootStoragePackage');
    $this->invoke->setAccessible(true);
});

afterEach(function (): void {
    if (File::isDirectory($this->tmpPackagePath)) {
        File::deleteDirectory($this->tmpPackagePath);
    }
});

it('requires the bundled vendor autoload when present', function (): void {
    // Marker file written by the autoload script — proves require_once executed.
    $marker = $this->tmpPackagePath.'/loaded.flag';
    $autoloadCode = "<?php file_put_contents('".$marker."', 'loaded');\n";
    File::put($this->tmpPackagePath.'/vendor/autoload.php', $autoloadCode);

    $this->invoke->invoke($this->extension, $this->tmpPackagePath);

    expect(File::exists($marker))->toBeTrue();
});

it('does nothing when no bundled vendor autoload exists', function (): void {
    // No vendor/autoload.php in the package dir — call must be a no-op, not throw.
    expect(fn () => $this->invoke->invoke($this->extension, $this->tmpPackagePath))
        ->not->toThrow(Throwable::class);
});

it('logs a warning instead of bubbling when the autoload file throws', function (): void {
    $autoloadCode = "<?php throw new RuntimeException('bundled autoload exploded');\n";
    File::put($this->tmpPackagePath.'/vendor/autoload.php', $autoloadCode);

    Log::spy();

    // The method must swallow the throw so one bad package can't break boot.
    expect(fn () => $this->invoke->invoke($this->extension, $this->tmpPackagePath))
        ->not->toThrow(Throwable::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'bundled vendor autoload')
            && ($context['path'] ?? null) === $this->tmpPackagePath.'/vendor/autoload.php'
            && str_contains((string) ($context['error'] ?? ''), 'bundled autoload exploded'))
        ->once();
});
