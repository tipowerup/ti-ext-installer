<?php

declare(strict_types=1);

use Igniter\Admin\Facades\Template;
use Igniter\Flame\Support\Facades\Igniter;
use Igniter\System\Models\Settings;
use Illuminate\Http\Request;
use Tipowerup\Installer\Extension;

beforeEach(function (): void {
    $prop = new ReflectionProperty($this->app, 'isRunningInConsole');
    $prop->setValue($this->app, false);
    $this->app->instance('request', Request::create(rtrim(Igniter::adminUri(), '/').'/foo'));

    $this->extension = new Extension($this->app);
    $this->invoke = (new ReflectionClass(Extension::class))->getMethod('registerAutoUpdateCheck');
    $this->invoke->setAccessible(true);
});

it('renders nothing when no api key is configured', function (): void {
    Settings::set('tipowerup_api_key', '', 'prefs');

    $this->invoke->invoke($this->extension);

    expect(Template::renderHook('endScripts')->toHtml())->toBe('');
});

it('renders the auto update check partial when an api key is configured', function (): void {
    Settings::set('tipowerup_api_key', 'some-key', 'prefs');

    $this->invoke->invoke($this->extension);

    expect(Template::renderHook('endScripts')->toHtml())->not->toBe('');
});
