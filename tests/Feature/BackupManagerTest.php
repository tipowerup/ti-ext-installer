<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tipowerup\Installer\Exceptions\BackupRestoreException;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\BackupManager;

beforeEach(function (): void {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');

    $this->manager = new BackupManager;
    $this->packageCode = 'tipowerup/ti-ext-testpkg';

    // Create a fake package in storage (where direct-installed packages live)
    $packageDir = storage_path('app/tipowerup/extensions/tipowerup/testpkg');
    File::ensureDirectoryExists($packageDir);
    File::put($packageDir.'/composer.json', json_encode(['name' => 'tipowerup/ti-ext-testpkg']));
    File::put($packageDir.'/Extension.php', '<?php // test extension');
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('app/tipowerup/extensions/tipowerup'));
    File::deleteDirectory(storage_path('app/tipowerup/backups'));
});

// --- hasBackup ---

it('returns false when no backup exists', function (): void {
    expect($this->manager->hasBackup($this->packageCode))->toBeFalse();
});

it('returns true after a backup has been created', function (): void {
    $this->manager->createBackup($this->packageCode);

    expect($this->manager->hasBackup($this->packageCode))->toBeTrue();
});

it('throws InvalidArgumentException from hasBackup when package code is invalid', function (): void {
    expect(fn () => $this->manager->hasBackup('invalid_code'))
        ->toThrow(InvalidArgumentException::class);
});

// --- createBackup ---

it('throws InvalidArgumentException from createBackup when package code is invalid', function (): void {
    expect(fn () => $this->manager->createBackup('invalid_code'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws BackupRestoreException when package is not found on disk', function (): void {
    expect(fn () => $this->manager->createBackup('tipowerup/ti-ext-nonexistent'))
        ->toThrow(BackupRestoreException::class);
});

it('creates manifest.json and files.zip in the backup directory', function (): void {
    $this->manager->createBackup($this->packageCode);

    $backupDir = storage_path('app/tipowerup/backups/tipowerup.ti-ext-testpkg');

    expect(File::exists($backupDir.'/manifest.json'))->toBeTrue()
        ->and(File::exists($backupDir.'/files.zip'))->toBeTrue();
});

it('creates a manifest with expected keys and values', function (): void {
    $this->manager->createBackup($this->packageCode);

    $backupDir = storage_path('app/tipowerup/backups/tipowerup.ti-ext-testpkg');
    $manifest = json_decode(File::get($backupDir.'/manifest.json'), true);

    expect($manifest)->toBeArray()
        ->and($manifest)->toHaveKeys(['package_code', 'version', 'method', 'date', 'migration_state', 'files_count'])
        ->and($manifest['package_code'])->toBe($this->packageCode)
        ->and($manifest['files_count'])->toBeGreaterThan(0);
});

it('captures migration state from the database during backup', function (): void {
    DB::table('migrations')->insert([
        'migration' => 'tipowerup/ti-ext-testpkg_create_table',
        'batch' => 1,
    ]);

    $this->manager->createBackup($this->packageCode);

    $backupDir = storage_path('app/tipowerup/backups/tipowerup.ti-ext-testpkg');
    $manifest = json_decode(File::get($backupDir.'/manifest.json'), true);

    expect($manifest['migration_state'])->toBeArray()
        ->and($manifest['migration_state'])->toHaveCount(1)
        ->and($manifest['migration_state'][0]['migration'])->toBe('tipowerup/ti-ext-testpkg_create_table')
        ->and($manifest['migration_state'][0]['batch'])->toBe(1);
});

it('saves migration-state.json alongside the manifest', function (): void {
    DB::table('migrations')->insert([
        'migration' => 'tipowerup/ti-ext-testpkg_create_table',
        'batch' => 1,
    ]);

    $this->manager->createBackup($this->packageCode);

    $backupDir = storage_path('app/tipowerup/backups/tipowerup.ti-ext-testpkg');

    expect(File::exists($backupDir.'/migration-state.json'))->toBeTrue();

    $state = json_decode(File::get($backupDir.'/migration-state.json'), true);

    expect($state)->toBeArray()
        ->and($state[0]['migration'])->toBe('tipowerup/ti-ext-testpkg_create_table');
});

it('captures the package version from the License model when creating a backup', function (): void {
    License::create([
        'package_code' => $this->packageCode,
        'package_name' => 'Test Package',
        'package_type' => 'extension',
        'version' => '1.2.3',
        'install_method' => 'direct',
        'installed_at' => now(),
        'updated_at' => now(),
        'is_active' => true,
    ]);

    $this->manager->createBackup($this->packageCode);

    $backupDir = storage_path('app/tipowerup/backups/tipowerup.ti-ext-testpkg');
    $manifest = json_decode(File::get($backupDir.'/manifest.json'), true);

    expect($manifest['version'])->toBe('1.2.3')
        ->and($manifest['method'])->toBe('direct');
});

it('defaults to version 0.0.0 when no license record exists', function (): void {
    $this->manager->createBackup($this->packageCode);

    $backupDir = storage_path('app/tipowerup/backups/tipowerup.ti-ext-testpkg');
    $manifest = json_decode(File::get($backupDir.'/manifest.json'), true);

    expect($manifest['version'])->toBe('0.0.0');
});

// --- getBackupInfo ---

it('returns null when no backup exists', function (): void {
    expect($this->manager->getBackupInfo($this->packageCode))->toBeNull();
});

it('returns manifest data after a backup is created', function (): void {
    $this->manager->createBackup($this->packageCode);

    $info = $this->manager->getBackupInfo($this->packageCode);

    expect($info)->toBeArray()
        ->and($info['package_code'])->toBe($this->packageCode);
});

it('throws InvalidArgumentException from getBackupInfo when package code is invalid', function (): void {
    expect(fn () => $this->manager->getBackupInfo('bad/code/format'))
        ->toThrow(InvalidArgumentException::class);
});

// --- deleteBackup ---

it('removes the backup directory when a backup exists', function (): void {
    $this->manager->createBackup($this->packageCode);

    expect($this->manager->hasBackup($this->packageCode))->toBeTrue();

    $this->manager->deleteBackup($this->packageCode);

    expect($this->manager->hasBackup($this->packageCode))->toBeFalse();
});

it('does nothing when deleting a backup that does not exist', function (): void {
    // Should not throw
    $this->manager->deleteBackup($this->packageCode);

    expect($this->manager->hasBackup($this->packageCode))->toBeFalse();
});

it('throws InvalidArgumentException from deleteBackup when package code is invalid', function (): void {
    expect(fn () => $this->manager->deleteBackup('invalid'))
        ->toThrow(InvalidArgumentException::class);
});

// --- restore ---

it('throws BackupRestoreException when restoring a package with no backup', function (): void {
    expect(fn () => $this->manager->restore($this->packageCode))
        ->toThrow(BackupRestoreException::class);
});

it('throws InvalidArgumentException from restore when package code is invalid', function (): void {
    expect(fn () => $this->manager->restore('invalid_code'))
        ->toThrow(InvalidArgumentException::class);
});

it('extracts files back to disk after restoration', function (): void {
    $this->manager->createBackup($this->packageCode);

    // Remove the package directory to simulate an upgrade overwriting files
    $packageDir = storage_path('app/tipowerup/extensions/tipowerup/testpkg');
    File::deleteDirectory($packageDir);

    expect(is_dir($packageDir))->toBeFalse();

    $this->manager->restore($this->packageCode);

    expect(is_dir($packageDir))->toBeTrue()
        ->and(File::exists($packageDir.'/Extension.php'))->toBeTrue()
        ->and(File::exists($packageDir.'/composer.json'))->toBeTrue();
});

it('restores migration state in the database during restoration', function (): void {
    // Insert a migration row before backup
    DB::table('migrations')->insert([
        'migration' => 'tipowerup/ti-ext-testpkg_create_table',
        'batch' => 1,
    ]);

    $this->manager->createBackup($this->packageCode);

    // Simulate a new migration being added after backup, then clear state
    DB::table('migrations')
        ->where('migration', 'like', '%tipowerup/ti-ext-testpkg%')
        ->delete();

    DB::table('migrations')->insert([
        'migration' => 'tipowerup/ti-ext-testpkg_add_column',
        'batch' => 2,
    ]);

    // Restore should revert to the saved migration state
    $this->manager->restore($this->packageCode);

    $migrations = DB::table('migrations')
        ->where('migration', 'like', '%tipowerup/ti-ext-testpkg%')
        ->pluck('migration')
        ->all();

    expect($migrations)->toContain('tipowerup/ti-ext-testpkg_create_table')
        ->and($migrations)->not->toContain('tipowerup/ti-ext-testpkg_add_column');
});

// --- Full cycle ---

it('completes the full backup, verify, delete, and recreate cycle', function (): void {
    // No backup initially
    expect($this->manager->hasBackup($this->packageCode))->toBeFalse();
    expect($this->manager->getBackupInfo($this->packageCode))->toBeNull();

    // Create backup
    $this->manager->createBackup($this->packageCode);

    expect($this->manager->hasBackup($this->packageCode))->toBeTrue();

    $info = $this->manager->getBackupInfo($this->packageCode);
    expect($info)->toBeArray()
        ->and($info['package_code'])->toBe($this->packageCode)
        ->and($info['files_count'])->toBeGreaterThan(0);

    // Delete backup
    $this->manager->deleteBackup($this->packageCode);

    expect($this->manager->hasBackup($this->packageCode))->toBeFalse();

    // Recreate
    $this->manager->createBackup($this->packageCode);

    expect($this->manager->hasBackup($this->packageCode))->toBeTrue();
});

it('completes a full create, restore, and file verification cycle', function (): void {
    DB::table('migrations')->insert([
        'migration' => 'tipowerup/ti-ext-testpkg_create_table',
        'batch' => 1,
    ]);

    // Add an extra file to ensure all files are captured
    $packageDir = storage_path('app/tipowerup/extensions/tipowerup/testpkg');
    File::put($packageDir.'/README.md', '# Test Package');

    $this->manager->createBackup($this->packageCode);

    // Verify backup info
    $info = $this->manager->getBackupInfo($this->packageCode);
    expect($info['files_count'])->toBe(3); // composer.json, Extension.php, README.md

    // Wipe package directory
    File::deleteDirectory($packageDir);

    // Also wipe migration state
    DB::table('migrations')
        ->where('migration', 'like', '%tipowerup/ti-ext-testpkg%')
        ->delete();

    // Restore
    $this->manager->restore($this->packageCode);

    // Files restored
    expect(File::exists($packageDir.'/composer.json'))->toBeTrue()
        ->and(File::exists($packageDir.'/Extension.php'))->toBeTrue()
        ->and(File::exists($packageDir.'/README.md'))->toBeTrue();

    // Migration state restored
    $migrationCount = DB::table('migrations')
        ->where('migration', 'like', '%tipowerup/ti-ext-testpkg%')
        ->count();

    expect($migrationCount)->toBe(1);
});
