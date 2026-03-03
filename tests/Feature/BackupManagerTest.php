<?php

declare(strict_types=1);

use Tipowerup\Installer\Services\BackupManager;

it('can be instantiated', function (): void {
    $manager = new BackupManager;

    expect($manager)->toBeInstanceOf(BackupManager::class);
});

it('validates package code format', function (): void {
    $manager = new BackupManager;

    expect(fn () => $manager->hasBackup('invalid'))
        ->toThrow(InvalidArgumentException::class);
});

it('returns false when no backup exists', function (): void {
    $manager = new BackupManager;

    expect($manager->hasBackup('tipowerup/testpackage'))->toBeFalse();
});

it('returns null backup info when no backup exists', function (): void {
    $manager = new BackupManager;

    expect($manager->getBackupInfo('tipowerup/testpackage'))->toBeNull();
});
