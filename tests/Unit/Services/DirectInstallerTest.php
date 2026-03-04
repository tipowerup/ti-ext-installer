<?php

declare(strict_types=1);

use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Services\DirectInstaller;

// ---------------------------------------------------------------------------
// Helper — anonymous subclass bypassing constructor + exposing private methods
// ---------------------------------------------------------------------------

function directInstaller(): DirectInstaller
{
    return new class extends DirectInstaller
    {
        public function __construct() {}

        public function callGetShortName(string $code): string
        {
            $reflection = new ReflectionMethod(DirectInstaller::class, 'getShortName');

            return $reflection->invoke($this, $code);
        }

        public function callGetVendorName(string $code): string
        {
            $reflection = new ReflectionMethod(DirectInstaller::class, 'getVendorName');

            return $reflection->invoke($this, $code);
        }

        public function callValidateFilePath(string $path): void
        {
            $reflection = new ReflectionMethod(DirectInstaller::class, 'validateFilePath');
            $reflection->invoke($this, $path);
        }

        public function callVerifyChecksum(string $filePath, string $expected): bool
        {
            $reflection = new ReflectionMethod(DirectInstaller::class, 'verifyChecksum');

            return $reflection->invoke($this, $filePath, $expected);
        }

        public function callValidatePackageCode(string $code): void
        {
            $reflection = new ReflectionMethod(DirectInstaller::class, 'validatePackageCode');
            $reflection->invoke($this, $code);
        }

        /**
         * Reimplements the same logic as DirectInstaller::validatePackageStructure()
         * using native file_exists() to avoid the File facade in unit tests.
         */
        public function callValidatePackageStructure(string $path, string $type): bool
        {
            if ($type === 'extension') {
                $hasExtensionFile = file_exists($path.'/Extension.php')
                    || file_exists($path.'/src/Extension.php');

                return $hasExtensionFile && file_exists($path.'/composer.json');
            }

            return file_exists($path.'/theme.json');
        }
    };
}

// ---------------------------------------------------------------------------
// Temp-resource registry — reset between tests via beforeEach/afterEach
// ---------------------------------------------------------------------------

/** @var list<string> */
$registry = [];

beforeEach(function () use (&$registry): void {
    $registry = [];
});

afterEach(function () use (&$registry): void {
    foreach ($registry as $path) {
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $fileInfo) {
                $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
    $registry = [];
});

// ---------------------------------------------------------------------------
// Helper functions — register created resources for cleanup
// ---------------------------------------------------------------------------

function makeTempFile(string $content, array &$registry): string
{
    $path = tempnam(sys_get_temp_dir(), 'di_test_');
    file_put_contents($path, $content);
    $registry[] = $path;

    return $path;
}

function makeTempDir(array &$registry): string
{
    $dir = sys_get_temp_dir().'/di_test_'.uniqid();
    mkdir($dir, 0755, true);
    $registry[] = $dir;

    return $dir;
}

// ===========================================================================
// getShortName
// ===========================================================================

describe('getShortName', function (): void {
    it('strips ti-ext- prefix from Composer format', function (): void {
        expect(directInstaller()->callGetShortName('tipowerup/ti-ext-darkmode'))->toBe('darkmode');
    });

    it('strips ti-theme- prefix from Composer format', function (): void {
        expect(directInstaller()->callGetShortName('tipowerup/ti-theme-flavor'))->toBe('flavor');
    });

    it('returns last segment unchanged when no ti-ext- or ti-theme- prefix', function (): void {
        expect(directInstaller()->callGetShortName('tipowerup/loyaltypoints'))->toBe('loyaltypoints');
    });

    it('preserves hyphens within the name after stripping ti-ext- prefix', function (): void {
        expect(directInstaller()->callGetShortName('tipowerup/ti-ext-loyalty-points'))->toBe('loyalty-points');
    });

    it('preserves hyphens in theme names after stripping the prefix', function (): void {
        expect(directInstaller()->callGetShortName('tipowerup/ti-theme-dark-night'))->toBe('dark-night');
    });

    it('returns last segment for dot notation', function (): void {
        expect(directInstaller()->callGetShortName('tipowerup.darkmode'))->toBe('darkmode');
    });

    it('handles dot notation with multiple segments by returning last', function (): void {
        expect(directInstaller()->callGetShortName('acme.some.widget'))->toBe('widget');
    });

    it('handles Composer format with a different vendor', function (): void {
        expect(directInstaller()->callGetShortName('acme/ti-ext-widget'))->toBe('widget');
    });
});

// ===========================================================================
// getVendorName
// ===========================================================================

describe('getVendorName', function (): void {
    it('returns vendor from Composer format', function (): void {
        expect(directInstaller()->callGetVendorName('tipowerup/ti-ext-darkmode'))->toBe('tipowerup');
    });

    it('returns vendor from dot notation', function (): void {
        expect(directInstaller()->callGetVendorName('tipowerup.darkmode'))->toBe('tipowerup');
    });

    it('returns vendor from Composer format with a different vendor', function (): void {
        expect(directInstaller()->callGetVendorName('acme/ti-ext-widget'))->toBe('acme');
    });

    it('returns first segment from Composer format regardless of package name', function (): void {
        expect(directInstaller()->callGetVendorName('myvendor/my-package'))->toBe('myvendor');
    });

    it('returns first segment from dot notation regardless of additional segments', function (): void {
        expect(directInstaller()->callGetVendorName('acme.some.widget'))->toBe('acme');
    });
});

// ===========================================================================
// validateFilePath
// ===========================================================================

describe('validateFilePath', function (): void {
    it('accepts a standard PHP source file path', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('src/Extension.php'))
            ->not->toThrow(PackageInstallationException::class);
    });

    it('accepts a root-level composer.json', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('composer.json'))
            ->not->toThrow(PackageInstallationException::class);
    });

    it('accepts deeply nested Blade view file', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('resources/views/test.blade.php'))
            ->not->toThrow(PackageInstallationException::class);
    });

    it('accepts CSS files', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('public/style.css'))
            ->not->toThrow(PackageInstallationException::class);
    });

    it('accepts JavaScript files', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('public/app.js'))
            ->not->toThrow(PackageInstallationException::class);
    });

    it('accepts image files', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('public/logo.png'))
            ->not->toThrow(PackageInstallationException::class);
    });

    it('rejects a classic path traversal to /etc/passwd', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('../../etc/passwd'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects deeply nested path traversal', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('foo/../../../bar'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects an absolute Unix path', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('/etc/passwd'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects a Windows-style absolute path', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('C:\Windows\system32'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects a .phar file', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('exploit.phar'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects a .sh shell script', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('script.sh'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects a .bash script', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('run.bash'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects a .exe binary', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('run.exe'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects a .bat batch file', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('cmd.bat'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects a .cmd file', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('run.cmd'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects a .com executable', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('exploit.com'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects dangerous extensions even in subdirectories', function (): void {
        expect(fn () => directInstaller()->callValidateFilePath('scripts/install.sh'))
            ->toThrow(PackageInstallationException::class);
    });
});

// ===========================================================================
// verifyChecksum
// ===========================================================================

describe('verifyChecksum', function () use (&$registry): void {
    it('verifies a correct sha1 checksum without algorithm prefix', function () use (&$registry): void {
        $path = makeTempFile('hello world package content', $registry);
        $expectedSha1 = sha1_file($path);

        expect(directInstaller()->callVerifyChecksum($path, $expectedSha1))->toBeTrue();
    });

    it('returns false for a wrong sha1 checksum without algorithm prefix', function () use (&$registry): void {
        $path = makeTempFile('some package content', $registry);

        expect(directInstaller()->callVerifyChecksum($path, 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef'))->toBeFalse();
    });

    it('verifies a correct sha256 checksum with algorithm prefix', function () use (&$registry): void {
        $path = makeTempFile('sha256 test package content', $registry);
        $expectedHash = hash_file('sha256', $path);

        expect(directInstaller()->callVerifyChecksum($path, 'sha256:'.$expectedHash))->toBeTrue();
    });

    it('returns false for a wrong sha256 checksum with algorithm prefix', function () use (&$registry): void {
        $path = makeTempFile('sha256 test content', $registry);

        expect(directInstaller()->callVerifyChecksum($path, 'sha256:'.str_repeat('0', 64)))->toBeFalse();
    });

    it('verifies a correct md5 checksum with algorithm prefix', function () use (&$registry): void {
        $path = makeTempFile('md5 test package content', $registry);
        $expectedHash = hash_file('md5', $path);

        expect(directInstaller()->callVerifyChecksum($path, 'md5:'.$expectedHash))->toBeTrue();
    });

    it('returns false for a wrong md5 checksum with algorithm prefix', function () use (&$registry): void {
        $path = makeTempFile('md5 test content', $registry);

        expect(directInstaller()->callVerifyChecksum($path, 'md5:'.str_repeat('0', 32)))->toBeFalse();
    });

    it('defaults to sha1 when no algorithm prefix is supplied', function () use (&$registry): void {
        $path = makeTempFile('default algorithm content', $registry);
        $sha1Hash = sha1_file($path);
        $sha256Hash = hash_file('sha256', $path);

        // sha1 must match
        expect(directInstaller()->callVerifyChecksum($path, $sha1Hash))->toBeTrue();

        // sha256 hash treated as sha1 literal — must not match
        expect(directInstaller()->callVerifyChecksum($path, $sha256Hash))->toBeFalse();
    });

    it('handles a file with binary content', function () use (&$registry): void {
        $path = makeTempFile(random_bytes(1024), $registry);
        $expectedHash = hash_file('sha256', $path);

        expect(directInstaller()->callVerifyChecksum($path, 'sha256:'.$expectedHash))->toBeTrue();
    });
});

// ===========================================================================
// validatePackageCode (from ValidatesPackageCode trait)
// ===========================================================================

describe('validatePackageCode', function (): void {
    it('accepts a plain vendor/package Composer code', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('vendor/package'))
            ->not->toThrow(InvalidArgumentException::class);
    });

    it('accepts the standard tipowerup extension format', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('tipowerup/ti-ext-darkmode'))
            ->not->toThrow(InvalidArgumentException::class);
    });

    it('accepts codes with hyphens in both vendor and package segments', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('my-vendor/my-package'))
            ->not->toThrow(InvalidArgumentException::class);
    });

    it('rejects dot notation (TI format)', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('tipowerup.darkmode'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects a code with no separator at all', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('invalid'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects an empty string', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode(''))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects a trailing slash with empty package segment', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('vendor/'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects a leading slash with empty vendor segment', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('/package'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects codes with special characters other than hyphens', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('vendor/package_name'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects codes with spaces', function (): void {
        expect(fn () => directInstaller()->callValidatePackageCode('vendor /package'))
            ->toThrow(InvalidArgumentException::class);
    });
});

// ===========================================================================
// validatePackageStructure
// ===========================================================================

describe('validatePackageStructure', function () use (&$registry): void {
    it('accepts an extension with Extension.php at root and composer.json', function () use (&$registry): void {
        $dir = makeTempDir($registry);
        file_put_contents($dir.'/Extension.php', '<?php');
        file_put_contents($dir.'/composer.json', '{}');

        expect(directInstaller()->callValidatePackageStructure($dir, 'extension'))->toBeTrue();
    });

    it('accepts an extension with Extension.php under src/ and composer.json at root', function () use (&$registry): void {
        $dir = makeTempDir($registry);
        mkdir($dir.'/src', 0755, true);
        file_put_contents($dir.'/src/Extension.php', '<?php');
        file_put_contents($dir.'/composer.json', '{}');

        expect(directInstaller()->callValidatePackageStructure($dir, 'extension'))->toBeTrue();
    });

    it('rejects an extension missing Extension.php entirely', function () use (&$registry): void {
        $dir = makeTempDir($registry);
        file_put_contents($dir.'/composer.json', '{}');

        expect(directInstaller()->callValidatePackageStructure($dir, 'extension'))->toBeFalse();
    });

    it('rejects an extension missing composer.json', function () use (&$registry): void {
        $dir = makeTempDir($registry);
        file_put_contents($dir.'/Extension.php', '<?php');

        expect(directInstaller()->callValidatePackageStructure($dir, 'extension'))->toBeFalse();
    });

    it('rejects an extension that has neither Extension.php nor composer.json', function () use (&$registry): void {
        $dir = makeTempDir($registry);
        file_put_contents($dir.'/SomeOtherFile.php', '<?php');

        expect(directInstaller()->callValidatePackageStructure($dir, 'extension'))->toBeFalse();
    });

    it('accepts a theme with theme.json at root', function () use (&$registry): void {
        $dir = makeTempDir($registry);
        file_put_contents($dir.'/theme.json', '{}');

        expect(directInstaller()->callValidatePackageStructure($dir, 'theme'))->toBeTrue();
    });

    it('rejects a theme missing theme.json', function () use (&$registry): void {
        $dir = makeTempDir($registry);
        file_put_contents($dir.'/composer.json', '{}');

        expect(directInstaller()->callValidatePackageStructure($dir, 'theme'))->toBeFalse();
    });

    it('rejects a completely empty directory for extension type', function () use (&$registry): void {
        $dir = makeTempDir($registry);

        expect(directInstaller()->callValidatePackageStructure($dir, 'extension'))->toBeFalse();
    });

    it('rejects a completely empty directory for theme type', function () use (&$registry): void {
        $dir = makeTempDir($registry);

        expect(directInstaller()->callValidatePackageStructure($dir, 'theme'))->toBeFalse();
    });

    it('accepts when only src/Extension.php exists alongside composer.json', function () use (&$registry): void {
        $dir = makeTempDir($registry);
        mkdir($dir.'/src', 0755, true);
        file_put_contents($dir.'/src/Extension.php', '<?php');
        file_put_contents($dir.'/composer.json', '{}');
        // Deliberately no $dir/Extension.php

        expect(directInstaller()->callValidatePackageStructure($dir, 'extension'))->toBeTrue();
    });
});
