<?php

declare(strict_types=1);

use Igniter\System\Models\Settings;
use Tipowerup\Installer\Extension;

it('registers navigation under tools', function (): void {
    $extension = new Extension($this->app);
    $navigation = $extension->registerNavigation();

    expect($navigation)->toHaveKey('tools')
        ->and($navigation['tools']['child'])->toHaveKey('installer');
});

it('registers the manage permission', function (): void {
    $extension = new Extension($this->app);
    $permissions = $extension->registerPermissions();

    expect($permissions)->toHaveKey('Tipowerup.Installer.Manage');
});

it('can store and retrieve params', function (): void {
    $key = 'tipowerup_installer_test';
    $value = 'test_value';

    Settings::set($key, $value, 'prefs');
    $retrieved = Settings::get($key, null, 'prefs');

    expect($retrieved)->toBe($value);
});
