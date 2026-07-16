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

it('moves a bundled ClassLoader behind the main app loader', function (): void {
    // Composer registers the bundled loader with prepend=true; the method must
    // re-register it with prepend=false so the host app's loader wins for any
    // package the storage bundle also ships (e.g. livewire/livewire).
    $marker = $this->tmpPackagePath.'/loader.flag';
    $autoloadCode = <<<PHP
        <?php

        return new class('$marker') extends \Composer\Autoload\ClassLoader
        {
            public function __construct(private string \$marker) {}

            public function register(\$prepend = false): void
            {
                file_put_contents(\$this->marker, 'registered:'.var_export(\$prepend, true), FILE_APPEND);
            }

            public function unregister(): void
            {
                file_put_contents(\$this->marker, 'unregistered|', FILE_APPEND);
            }
        };
        PHP;
    File::put($this->tmpPackagePath.'/vendor/autoload.php', $autoloadCode);
    File::put($marker, '');

    $this->invoke->invoke($this->extension, $this->tmpPackagePath);

    expect(File::get($marker))->toBe('unregistered|registered:false');
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
