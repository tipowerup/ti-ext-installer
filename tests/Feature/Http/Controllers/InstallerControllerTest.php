<?php

declare(strict_types=1);

use Igniter\Admin\Facades\AdminMenu;
use Igniter\Admin\Facades\Template;
use Tipowerup\Installer\Http\Controllers\Installer;

it('exposes the custom admin slug that avoids duplicating "installer" in the path', function (): void {
    expect(Installer::getSlug())->toBe('tipowerup/installer');
});

it('declares the installer manage permission scope', function (): void {
    $reflection = new ReflectionClass(Installer::class);
    $perm = $reflection->getProperty('requiredPermissions');
    $perm->setAccessible(true);

    // We instantiate without calling the AdminController parent constructor
    // (which requires a full admin runtime) — Reflection lets us read the
    // default property value without booting the controller.
    $defaults = $reflection->getDefaultProperties();

    expect($defaults['requiredPermissions'])->toBe('Tipowerup.Installer.*');
});

it('index() sets the page title/heading and registers the installer assets', function (): void {
    // Subclass to capture addCss / addJs calls without booting the AdminController
    // parent (which needs full admin runtime). Constructor bypassed so the
    // controller's protected asset state stays untouched.
    $stub = new class extends Installer
    {
        /** @var array<int, array{string, string}> */
        public array $cssCalls = [];

        /** @var array<int, array{string, string}> */
        public array $jsCalls = [];

        public function __construct() {}

        public function addCss($url, $name = ''): void
        {
            $this->cssCalls[] = [(string) $url, (string) $name];
        }

        public function addJs($url, $name = ''): void
        {
            $this->jsCalls[] = [(string) $url, (string) $name];
        }
    };

    $expectedTitle = lang('tipowerup.installer::default.text_title');

    Template::shouldReceive('setTitle')->once()->with($expectedTitle);
    Template::shouldReceive('setHeading')->once()->with($expectedTitle);
    AdminMenu::shouldReceive('setContext')->zeroOrMoreTimes();

    $stub->index();

    expect($stub->cssCalls)->toBe([['tipowerup.installer::css/installer.css', 'tipowerup-installer-css']])
        ->and($stub->jsCalls)->toBe([['tipowerup.installer::js/lightbox.js', 'tipowerup-installer-lightbox-js']]);
});
