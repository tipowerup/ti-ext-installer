<?php

declare(strict_types=1);

use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\BaseExtension;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tipowerup\Installer\Exceptions\PackageInstallationException;
use Tipowerup\Installer\Services\DirectInstaller;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a ZIP archive at the given path, optionally wrapping all files under
 * a single root folder prefix (GitHub-style archive).
 *
 * @param  array<string, string>  $files  Map of filename => file content
 */
function createTestZip(string $path, array $files, ?string $rootPrefix = null): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $name => $content) {
        $entryName = $rootPrefix ? $rootPrefix.$name : $name;
        $zip->addFromString($entryName, $content);
    }

    $zip->close();
}

/**
 * Read a local file's binary contents and return them as a string, suitable
 * for use as the body of an Http::fake() response.
 */
function zipBinaryContent(string $filePath): string
{
    return file_get_contents($filePath);
}

/**
 * Return a DirectInstaller with the ExtensionManager and ThemeManager mocked
 * so that TI registration calls do not require a full TI context.
 */
function directInstallerWithMockedTI(?callable $extensionSetup = null, ?callable $themeSetup = null): DirectInstaller
{
    $extensionSetup ??= function ($mock): void {
        $fakeExtension = \Mockery::mock(BaseExtension::class);
        $mock->shouldReceive('loadExtension')->zeroOrMoreTimes()->andReturn($fakeExtension);
        $mock->shouldReceive('getIdentifier')->zeroOrMoreTimes()->andReturn('tipowerup.darkmode');
        $mock->shouldReceive('installExtension')->zeroOrMoreTimes()->andReturn(true);
        $mock->shouldReceive('uninstallExtension')->zeroOrMoreTimes()->andReturn(true);
    };

    $themeSetup ??= function ($mock): void {
        $mock->shouldReceive('loadTheme')->zeroOrMoreTimes()->andReturn(null);
        $mock->shouldReceive('installTheme')->zeroOrMoreTimes()->andReturn(true);
        $mock->shouldReceive('deleteTheme')->zeroOrMoreTimes()->andReturn(null);
    };

    /** @phpstan-ignore-next-line */
    app()->instance(ExtensionManager::class, \Mockery::mock(ExtensionManager::class, $extensionSetup));
    /** @phpstan-ignore-next-line */
    app()->instance(ThemeManager::class, \Mockery::mock(ThemeManager::class, $themeSetup));

    return new DirectInstaller;
}

/**
 * Return a minimal valid ZIP for an extension (Extension.php + composer.json).
 */
function extensionZipFiles(): array
{
    return [
        'Extension.php' => '<?php class Extension {}',
        'composer.json' => json_encode(['name' => 'tipowerup/ti-ext-darkmode', 'version' => '1.0.0']),
    ];
}

/**
 * Return a minimal valid ZIP for a theme (theme.json).
 */
function themeZipFiles(): array
{
    return [
        'theme.json' => json_encode(['code' => 'tipowerup-darktheme', 'name' => 'Dark Theme']),
    ];
}

// ---------------------------------------------------------------------------
// Clean up any test artifacts after every test
// ---------------------------------------------------------------------------

afterEach(function (): void {
    $localDisk = Storage::disk('local');

    foreach (['tipowerup/tmp', 'tipowerup/extensions/tipowerup', 'tipowerup/themes', 'tipowerup/backups'] as $dir) {
        $fullPath = $localDisk->path($dir);
        if (File::isDirectory($fullPath)) {
            File::deleteDirectory($fullPath);
        }
    }

    \Mockery::close();
});

// ===========================================================================
// describe: install
// ===========================================================================

describe('install', function (): void {

    it('installs extension package successfully', function (): void {
        $zipPath = sys_get_temp_dir().'/test-darkmode-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        $result = $installer->install('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
        ]);

        $targetPath = Storage::disk('local')->path('tipowerup/extensions/tipowerup/darkmode');

        expect($result['success'])->toBeTrue()
            ->and($result['method'])->toBe('direct')
            ->and($result['version'])->toBe('1.0.0')
            ->and(File::isDirectory($targetPath))->toBeTrue()
            ->and(File::exists($targetPath.'/Extension.php'))->toBeTrue()
            ->and(File::exists($targetPath.'/composer.json'))->toBeTrue();

        @unlink($zipPath);
    });

    it('installs theme package successfully', function (): void {
        $zipPath = sys_get_temp_dir().'/test-darktheme-'.uniqid().'.zip';
        createTestZip($zipPath, themeZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI(null, function ($mock): void {
            $mock->shouldReceive('loadTheme')->zeroOrMoreTimes()->andReturn(null);
            $mock->shouldReceive('installTheme')->zeroOrMoreTimes()->andReturn(true);
        });

        $result = $installer->install('tipowerup/ti-theme-darktheme', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-theme-darktheme/1.0.0',
            'package_type' => 'theme',
            'version' => '2.0.0',
        ]);

        $targetPath = Storage::disk('local')->path('tipowerup/themes/tipowerup-darktheme');

        expect($result['success'])->toBeTrue()
            ->and($result['path'])->toBe($targetPath)
            ->and(File::isDirectory($targetPath))->toBeTrue()
            ->and(File::exists($targetPath.'/theme.json'))->toBeTrue();

        @unlink($zipPath);
    });

    it('throws when download URL is missing from license data', function (): void {
        $installer = directInstallerWithMockedTI();

        expect(fn () => $installer->install('tipowerup/ti-ext-darkmode', [
            'package_type' => 'extension',
            'version' => '1.0.0',
            // download_url intentionally absent
        ]))->toThrow(
            PackageInstallationException::class,
            'Download URL not provided in license data'
        );
    });

    it('throws when download fails with an HTTP error', function (): void {
        Http::fake([
            'pkg.tipowerup.com/*' => Http::response('Internal Server Error', 500),
        ]);

        // TI managers are never reached when download fails - use a plain installer instance
        // and catch either PackageInstallationException or a wrapped runtime error from the
        // download layer (Http::fake() + sink() may surface the failure differently).
        $installer = new DirectInstaller;

        $threw = false;

        try {
            $installer->install('tipowerup/ti-ext-darkmode', [
                'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
                'package_type' => 'extension',
                'version' => '1.0.0',
            ]);
        } catch (PackageInstallationException $e) {
            $threw = true;
        } catch (Throwable $e) {
            // Any exception from the download layer is acceptable for this test
            $threw = true;
        }

        expect($threw)->toBeTrue();
    });

    it('throws when the checksum does not match', function (): void {
        $zipPath = sys_get_temp_dir().'/test-checksum-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        expect(fn () => $installer->install('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
            'checksum' => 'sha256:0000000000000000000000000000000000000000000000000000000000000000',
        ]))->toThrow(
            PackageInstallationException::class,
            "Package integrity check failed for 'tipowerup/ti-ext-darkmode'"
        );

        @unlink($zipPath);
    });

    it('succeeds when no checksum is provided in license data', function (): void {
        $zipPath = sys_get_temp_dir().'/test-nochecksum-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        $result = $installer->install('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
            // no checksum key
        ]);

        expect($result['success'])->toBeTrue();

        @unlink($zipPath);
    });

    it('succeeds when checksum uses the prefixed algorithm:hash format', function (): void {
        $zipPath = sys_get_temp_dir().'/test-sha256checksum-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        $correctChecksum = 'sha256:'.hash_file('sha256', $zipPath);

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        $result = $installer->install('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
            'checksum' => $correctChecksum,
        ]);

        expect($result['success'])->toBeTrue();

        @unlink($zipPath);
    });

    it('throws when extension ZIP is missing required files', function (): void {
        $zipPath = sys_get_temp_dir().'/test-badstruct-'.uniqid().'.zip';
        // ZIP without Extension.php or composer.json
        createTestZip($zipPath, [
            'README.md' => '# Missing required files',
            'src/SomeClass.php' => '<?php',
        ]);

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        expect(fn () => $installer->install('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
        ]))->toThrow(
            PackageInstallationException::class,
            'Invalid package structure - missing required files'
        );

        @unlink($zipPath);
    });

    it('throws when theme ZIP is missing theme.json', function (): void {
        $zipPath = sys_get_temp_dir().'/test-badtheme-'.uniqid().'.zip';
        createTestZip($zipPath, [
            'README.md' => '# Theme without theme.json',
        ]);

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        expect(fn () => $installer->install('tipowerup/ti-theme-darktheme', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-theme-darktheme/1.0.0',
            'package_type' => 'theme',
            'version' => '1.0.0',
        ]))->toThrow(
            PackageInstallationException::class,
            'Invalid package structure - missing required files'
        );

        @unlink($zipPath);
    });

    it('cleans up the temporary ZIP file after a successful install', function (): void {
        $zipPath = sys_get_temp_dir().'/test-cleanup-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        $capturedTmpFile = null;

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        // Intercept the tmp file path via a progress callback side effect — we
        // check by asserting no *.zip files linger in tipowerup/tmp after install.
        $installer = directInstallerWithMockedTI();

        $installer->install('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
        ]);

        $tmpDir = Storage::disk('local')->path('tipowerup/tmp');
        $remainingZips = File::glob($tmpDir.'/*.zip');

        expect($remainingZips)->toBeEmpty();

        @unlink($zipPath);
    });

    it('reports progress via the callback', function (): void {
        $zipPath = sys_get_temp_dir().'/test-progress-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        $progressCalls = [];
        $installer->install(
            'tipowerup/ti-ext-darkmode',
            [
                'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
                'package_type' => 'extension',
                'version' => '1.0.0',
            ],
            function (int $percent, string $message) use (&$progressCalls): void {
                $progressCalls[] = ['percent' => $percent, 'message' => $message];
            }
        );

        $percentages = array_column($progressCalls, 'percent');

        expect($progressCalls)->not->toBeEmpty()
            ->and(min($percentages))->toBeGreaterThanOrEqual(0)
            ->and(max($percentages))->toBe(100);

        @unlink($zipPath);
    });

    it('throws on an invalid package code format', function (): void {
        $installer = new DirectInstaller;

        expect(fn () => $installer->install('tipowerup.darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
        ]))->toThrow(InvalidArgumentException::class);
    });

    it('handles GitHub-style ZIPs with a single root prefix wrapper', function (): void {
        $zipPath = sys_get_temp_dir().'/test-rootprefix-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles(), 'tipowerup-ti-ext-darkmode-abc123/');

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        $result = $installer->install('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
        ]);

        $targetPath = Storage::disk('local')->path('tipowerup/extensions/tipowerup/darkmode');

        expect($result['success'])->toBeTrue()
            ->and(File::exists($targetPath.'/Extension.php'))->toBeTrue();

        @unlink($zipPath);
    });

    it('validates extension via src/Extension.php as an alternative to root Extension.php', function (): void {
        $zipPath = sys_get_temp_dir().'/test-srcext-'.uniqid().'.zip';
        createTestZip($zipPath, [
            'src/Extension.php' => '<?php class Extension {}',
            'composer.json' => json_encode(['name' => 'tipowerup/ti-ext-darkmode']),
        ]);

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        $result = $installer->install('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '1.0.0',
        ]);

        expect($result['success'])->toBeTrue();

        @unlink($zipPath);
    });
});

// ===========================================================================
// describe: update
// ===========================================================================

describe('update', function (): void {

    it('creates a backup directory before extracting the new version', function (): void {
        $disk = Storage::disk('local');
        $existingPath = $disk->path('tipowerup/extensions/tipowerup/darkmode');
        File::makeDirectory($existingPath, 0755, true);
        File::put($existingPath.'/Extension.php', '<?php // old version');
        File::put($existingPath.'/composer.json', json_encode(['version' => '1.0.0']));

        $zipPath = sys_get_temp_dir().'/test-update-backup-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        // Track whether a backup existed when runMigrations is called (which happens after backup creation)
        $backupExistedDuringMigrations = false;

        $installer = new class($disk, $backupExistedDuringMigrations) extends DirectInstaller
        {
            public function __construct(
                private mixed $disk,
                private bool &$backupExistedDuringMigrations
            ) {}

            public function runMigrations(string $packageCode): void
            {
                $backupGlob = File::glob($this->disk->path('tipowerup/backups').'/tipowerup/*');
                $this->backupExistedDuringMigrations = !empty($backupGlob);
            }
        };

        app()->instance(ExtensionManager::class, \Mockery::mock(ExtensionManager::class));
        app()->instance(ThemeManager::class, \Mockery::mock(ThemeManager::class));

        $installer->update('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '2.0.0',
            'current_version' => '1.0.0',
        ]);

        expect($backupExistedDuringMigrations)->toBeTrue();

        @unlink($zipPath);
    });

    it('restores backup when package structure validation fails after extraction', function (): void {
        $disk = Storage::disk('local');
        $existingPath = $disk->path('tipowerup/extensions/tipowerup/darkmode');
        File::makeDirectory($existingPath, 0755, true);
        File::put($existingPath.'/Extension.php', '<?php // old version');
        File::put($existingPath.'/composer.json', json_encode(['version' => '1.0.0']));

        // ZIP that lacks required structure files
        $zipPath = sys_get_temp_dir().'/test-update-restore-'.uniqid().'.zip';
        createTestZip($zipPath, [
            'README.md' => '# Invalid ZIP',
        ]);

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        expect(fn () => $installer->update('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '2.0.0',
            'current_version' => '1.0.0',
        ]))->toThrow(PackageInstallationException::class, 'Invalid package structure');

        // Old files must have been restored
        expect(File::exists($existingPath.'/Extension.php'))->toBeTrue();

        @unlink($zipPath);
    });

    it('cleans up the backup directory after a successful update', function (): void {
        $disk = Storage::disk('local');
        $existingPath = $disk->path('tipowerup/extensions/tipowerup/darkmode');
        File::makeDirectory($existingPath, 0755, true);
        File::put($existingPath.'/Extension.php', '<?php // old');
        File::put($existingPath.'/composer.json', json_encode(['version' => '1.0.0']));

        $zipPath = sys_get_temp_dir().'/test-update-cleanbkp-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        // Capture the exact backup path by overriding runMigrations
        $capturedBackupPath = '';

        $installer = new class($capturedBackupPath) extends DirectInstaller
        {
            public function __construct(private string &$capturedBackupPath) {}

            public function runMigrations(string $packageCode): void
            {
                // Backup path follows the pattern: tipowerup/backups/{code}-{date}
                // The code contains a slash, so the path is nested
                $glob = File::glob(
                    Storage::disk('local')->path('tipowerup/backups/tipowerup/ti-ext-darkmode*')
                );
                $this->capturedBackupPath = $glob[0] ?? '';
            }
        };

        app()->instance(ExtensionManager::class, \Mockery::mock(ExtensionManager::class));
        app()->instance(ThemeManager::class, \Mockery::mock(ThemeManager::class));

        $installer->update('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '2.0.0',
            'current_version' => '1.0.0',
        ]);

        // The specific backup directory must have been deleted after successful update
        expect($capturedBackupPath)->not->toBeEmpty()
            ->and(File::isDirectory($capturedBackupPath))->toBeFalse();

        @unlink($zipPath);
    });

    it('proceeds without backup when no prior installation exists', function (): void {
        $zipPath = sys_get_temp_dir().'/test-update-noprev-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        $result = $installer->update('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '2.0.0',
        ]);

        expect($result['success'])->toBeTrue();

        @unlink($zipPath);
    });

    it('restores backup and rethrows when migration fails', function (): void {
        $disk = Storage::disk('local');
        $existingPath = $disk->path('tipowerup/extensions/tipowerup/darkmode');
        File::makeDirectory($existingPath, 0755, true);
        File::put($existingPath.'/Extension.php', '<?php // old');
        File::put($existingPath.'/composer.json', json_encode(['version' => '1.0.0']));

        $zipPath = sys_get_temp_dir().'/test-update-migfail-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        // Make the installer subclass so we can override runMigrations to throw
        $installer = new class extends DirectInstaller
        {
            public function runMigrations(string $packageCode): void
            {
                throw new RuntimeException('Simulated migration failure');
            }
        };

        app()->instance(ExtensionManager::class, \Mockery::mock(ExtensionManager::class, function ($mock): void {
            $fakeExtension = \Mockery::mock(BaseExtension::class);
            $mock->shouldReceive('loadExtension')->zeroOrMoreTimes()->andReturn($fakeExtension);
            $mock->shouldReceive('getIdentifier')->zeroOrMoreTimes()->andReturn('tipowerup.darkmode');
            $mock->shouldReceive('installExtension')->zeroOrMoreTimes()->andReturn(true);
        }));

        app()->instance(ThemeManager::class, \Mockery::mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('loadTheme')->zeroOrMoreTimes()->andReturn(null);
            $mock->shouldReceive('installTheme')->zeroOrMoreTimes()->andReturn(true);
        }));

        expect(fn () => $installer->update('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '2.0.0',
            'current_version' => '1.0.0',
        ]))->toThrow(PackageInstallationException::class, 'Migration failed');

        // Old files must be restored
        expect(File::exists($existingPath.'/Extension.php'))->toBeTrue();

        @unlink($zipPath);
    });

    it('returns from_version and to_version in the result', function (): void {
        $zipPath = sys_get_temp_dir().'/test-update-versions-'.uniqid().'.zip';
        createTestZip($zipPath, extensionZipFiles());

        Http::fake([
            'pkg.tipowerup.com/*' => Http::response(zipBinaryContent($zipPath)),
        ]);

        $installer = directInstallerWithMockedTI();

        $result = $installer->update('tipowerup/ti-ext-darkmode', [
            'download_url' => 'https://pkg.tipowerup.com/tipowerup/ti-ext-darkmode/1.0.0',
            'package_type' => 'extension',
            'version' => '2.0.0',
            'current_version' => '1.0.0',
        ]);

        expect($result['from_version'])->toBe('1.0.0')
            ->and($result['to_version'])->toBe('2.0.0')
            ->and($result['method'])->toBe('direct');

        @unlink($zipPath);
    });
});

// ===========================================================================
// describe: uninstall
// ===========================================================================

describe('uninstall', function (): void {

    it('removes the extension directory', function (): void {
        $disk = Storage::disk('local');
        $extPath = $disk->path('tipowerup/extensions/tipowerup/darkmode');
        File::makeDirectory($extPath, 0755, true);
        File::put($extPath.'/Extension.php', '<?php');
        File::put($extPath.'/composer.json', '{}');

        app()->instance(ExtensionManager::class, \Mockery::mock(ExtensionManager::class, function ($mock): void {
            $mock->shouldReceive('uninstallExtension')->once()->andReturn(null);
        }));
        app()->instance(ThemeManager::class, \Mockery::mock(ThemeManager::class));

        $installer = new DirectInstaller;
        $installer->uninstall('tipowerup/ti-ext-darkmode');

        expect(File::isDirectory($extPath))->toBeFalse();
    });

    it('removes the theme directory', function (): void {
        $disk = Storage::disk('local');
        $themePath = $disk->path('tipowerup/themes/tipowerup-darktheme');
        File::makeDirectory($themePath, 0755, true);
        File::put($themePath.'/theme.json', '{}');

        app()->instance(ExtensionManager::class, \Mockery::mock(ExtensionManager::class));
        app()->instance(ThemeManager::class, \Mockery::mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('deleteTheme')->once()->andReturn(null);
        }));

        $installer = new DirectInstaller;
        $installer->uninstall('tipowerup/ti-theme-darktheme');

        expect(File::isDirectory($themePath))->toBeFalse();
    });

    it('removes published theme assets from the public directory', function (): void {
        $disk = Storage::disk('local');
        $themePath = $disk->path('tipowerup/themes/tipowerup-darktheme');
        File::makeDirectory($themePath, 0755, true);
        File::put($themePath.'/theme.json', '{}');

        $assetsPath = public_path('vendor/tipowerup-darktheme');
        File::makeDirectory($assetsPath, 0755, true);
        File::put($assetsPath.'/app.css', '.body{}');

        app()->instance(ExtensionManager::class, \Mockery::mock(ExtensionManager::class));
        app()->instance(ThemeManager::class, \Mockery::mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('deleteTheme')->once()->andReturn(null);
        }));

        $installer = new DirectInstaller;
        $installer->uninstall('tipowerup/ti-theme-darktheme');

        // Clean up public assets dir from real FS after assertion
        $assetsDirExists = File::isDirectory($assetsPath);

        if (File::isDirectory($assetsPath)) {
            File::deleteDirectory($assetsPath);
        }

        expect($assetsDirExists)->toBeFalse();
    });

    it('does nothing and logs a warning when the package is not found', function (): void {
        Log::spy();

        app()->instance(ExtensionManager::class, \Mockery::mock(ExtensionManager::class));
        app()->instance(ThemeManager::class, \Mockery::mock(ThemeManager::class));

        $installer = new DirectInstaller;

        // Should not throw
        $installer->uninstall('tipowerup/ti-ext-nonexistent');

        Log::shouldHaveReceived('warning')->with(
            'DirectInstaller: Package not found for uninstall',
            \Mockery::type('array')
        );
    });
});

// ===========================================================================
// describe: resolveTargetPath
// ===========================================================================

describe('resolveTargetPath', function (): void {

    /**
     * Expose the private resolveTargetPath method via a test subclass.
     */
    function makeInstallerWithAccessors(): DirectInstaller
    {
        return new class extends DirectInstaller
        {
            public function exposedResolveTargetPath(string $code, string $type): string
            {
                $method = new ReflectionMethod(DirectInstaller::class, 'resolveTargetPath');

                return $method->invoke($this, $code, $type);
            }
        };
    }

    it('resolves extension path to tipowerup/extensions/{vendor}/{shortname}', function (): void {
        /** @var object $installer */
        $installer = makeInstallerWithAccessors();
        $path = $installer->exposedResolveTargetPath('tipowerup/ti-ext-darkmode', 'extension');

        expect($path)->toEndWith('tipowerup/extensions/tipowerup/darkmode');
    });

    it('resolves theme path to tipowerup/themes/{vendor}-{shortname}', function (): void {
        /** @var object $installer */
        $installer = makeInstallerWithAccessors();
        $path = $installer->exposedResolveTargetPath('tipowerup/ti-theme-darktheme', 'theme');

        expect($path)->toEndWith('tipowerup/themes/tipowerup-darktheme');
    });

    it('strips the ti-ext- prefix from the short name', function (): void {
        /** @var object $installer */
        $installer = makeInstallerWithAccessors();
        $path = $installer->exposedResolveTargetPath('tipowerup/ti-ext-loyalty-points', 'extension');

        expect($path)->toEndWith('tipowerup/extensions/tipowerup/loyalty-points');
    });

    it('strips the ti-theme- prefix from the short name', function (): void {
        /** @var object $installer */
        $installer = makeInstallerWithAccessors();
        $path = $installer->exposedResolveTargetPath('tipowerup/ti-theme-responsive', 'theme');

        expect($path)->toEndWith('tipowerup/themes/tipowerup-responsive');
    });

    it('handles extension packages without the ti-ext- prefix', function (): void {
        /** @var object $installer */
        $installer = makeInstallerWithAccessors();
        $path = $installer->exposedResolveTargetPath('vendor/mypackage', 'extension');

        expect($path)->toEndWith('tipowerup/extensions/vendor/mypackage');
    });
});

// ===========================================================================
// describe: validateDownloadUrl
// ===========================================================================

describe('validateDownloadUrl', function (): void {

    /**
     * Expose the private validateDownloadUrl method via a test subclass.
     */
    function makeInstallerWithUrlValidator(): DirectInstaller
    {
        return new class extends DirectInstaller
        {
            public function exposedValidateDownloadUrl(string $url): void
            {
                $method = new ReflectionMethod(DirectInstaller::class, 'validateDownloadUrl');
                $method->invoke($this, $url);
            }
        };
    }

    it('allows pkg.tipowerup.com', function (): void {
        $installer = makeInstallerWithUrlValidator();

        // Should not throw
        $installer->exposedValidateDownloadUrl('https://pkg.tipowerup.com/path/to/package.zip');

        expect(true)->toBeTrue();
    });

    it('allows packages.tipowerup.com', function (): void {
        $installer = makeInstallerWithUrlValidator();

        $installer->exposedValidateDownloadUrl('https://packages.tipowerup.com/path/to/package.zip');

        expect(true)->toBeTrue();
    });

    it('allows api.tipowerup.com', function (): void {
        $installer = makeInstallerWithUrlValidator();

        $installer->exposedValidateDownloadUrl('https://api.tipowerup.com/v1/download/package.zip');

        expect(true)->toBeTrue();
    });

    it('rejects plain HTTP URLs', function (): void {
        $installer = makeInstallerWithUrlValidator();

        expect(fn () => $installer->exposedValidateDownloadUrl('http://pkg.tipowerup.com/package.zip'))
            ->toThrow(PackageInstallationException::class, 'Only HTTPS download URLs are allowed');
    });

    it('rejects URLs from unknown hosts', function (): void {
        $installer = makeInstallerWithUrlValidator();

        expect(fn () => $installer->exposedValidateDownloadUrl('https://malicious.example.com/evil.zip'))
            ->toThrow(PackageInstallationException::class, 'Download URL host not in allowlist');
    });

    it('rejects malformed URLs that cannot be parsed', function (): void {
        $installer = makeInstallerWithUrlValidator();

        expect(fn () => $installer->exposedValidateDownloadUrl('not-a-url'))
            ->toThrow(PackageInstallationException::class);
    });

    it('rejects URLs missing a host component', function (): void {
        $installer = makeInstallerWithUrlValidator();

        expect(fn () => $installer->exposedValidateDownloadUrl('https://'))
            ->toThrow(PackageInstallationException::class);
    });

    it('respects allowed_download_hosts config override', function (): void {
        $original = config('tipowerup.installer.allowed_download_hosts');
        config()->set('tipowerup.installer.allowed_download_hosts', array_merge($original, ['my-custom-cdn.example.com']));

        try {
            $installer = makeInstallerWithUrlValidator();

            $installer->exposedValidateDownloadUrl('https://my-custom-cdn.example.com/package.zip');

            expect(true)->toBeTrue();
        } finally {
            config()->set('tipowerup.installer.allowed_download_hosts', $original);
        }
    });
});

// ===========================================================================
// describe: extractPackage (path traversal / security)
// ===========================================================================

describe('extractPackage security', function (): void {

    function makeInstallerWithExtractor(): DirectInstaller
    {
        return new class extends DirectInstaller
        {
            public function exposedExtractPackage(string $zipPath, string $targetPath, ?string $code = null): void
            {
                $method = new ReflectionMethod(DirectInstaller::class, 'extractPackage');
                $method->invoke($this, $zipPath, $targetPath, $code);
            }
        };
    }

    it('throws when a ZIP entry contains a path traversal sequence', function (): void {
        $zipPath = sys_get_temp_dir().'/test-traversal-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../outside/evil.php', '<?php evil();');
        $zip->close();

        $targetPath = Storage::disk('local')->path('tipowerup/tmp/traversal-test-'.uniqid());

        $installer = makeInstallerWithExtractor();

        expect(fn () => $installer->exposedExtractPackage($zipPath, $targetPath, 'test'))
            ->toThrow(PackageInstallationException::class);

        @unlink($zipPath);
        File::deleteDirectory($targetPath);
    });

    it('throws when a ZIP entry has a dangerous file extension', function (): void {
        $zipPath = sys_get_temp_dir().'/test-dangerous-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('install.sh', '#!/bin/bash\nrm -rf /');
        $zip->close();

        $targetPath = Storage::disk('local')->path('tipowerup/tmp/dangerous-test-'.uniqid());

        $installer = makeInstallerWithExtractor();

        expect(fn () => $installer->exposedExtractPackage($zipPath, $targetPath, 'test'))
            ->toThrow(PackageInstallationException::class, 'Dangerous file extension');

        @unlink($zipPath);
        File::deleteDirectory($targetPath);
    });

    it('throws when a ZIP entry uses an absolute path', function (): void {
        $zipPath = sys_get_temp_dir().'/test-abspath-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('/etc/evil.php', '<?php');
        $zip->close();

        $targetPath = Storage::disk('local')->path('tipowerup/tmp/abspath-test-'.uniqid());

        $installer = makeInstallerWithExtractor();

        expect(fn () => $installer->exposedExtractPackage($zipPath, $targetPath, 'test'))
            ->toThrow(PackageInstallationException::class);

        @unlink($zipPath);
        File::deleteDirectory($targetPath);
    });

    it('rejects .phar files inside the ZIP', function (): void {
        $zipPath = sys_get_temp_dir().'/test-phar-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('payload.phar', '<?php /* phar */');
        $zip->close();

        $targetPath = Storage::disk('local')->path('tipowerup/tmp/phar-test-'.uniqid());

        $installer = makeInstallerWithExtractor();

        expect(fn () => $installer->exposedExtractPackage($zipPath, $targetPath, 'test'))
            ->toThrow(PackageInstallationException::class, 'Dangerous file extension');

        @unlink($zipPath);
        File::deleteDirectory($targetPath);
    });
});
