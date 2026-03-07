<?php

declare(strict_types=1);

use Tipowerup\Installer\Http\Controllers\Installer;

// ---------------------------------------------------------------------------
// getSlug()
// ---------------------------------------------------------------------------

it('returns the correct URL slug', function (): void {
    expect(Installer::getSlug())->toBe('tipowerup/installer');
});

// ---------------------------------------------------------------------------
// $requiredPermissions
// ---------------------------------------------------------------------------

it('requires the Tipowerup.Installer.* permission', function (): void {
    $reflection = new ReflectionClass(Installer::class);
    $property = $reflection->getProperty('requiredPermissions');
    $property->setAccessible(true);

    // Instantiate without calling parent::__construct() to avoid TI bootstrap.
    $instance = $reflection->newInstanceWithoutConstructor();

    expect($property->getValue($instance))->toBe('Tipowerup.Installer.*');
});
