<?php

declare(strict_types=1);

use Igniter\System\Models\Settings;
use Livewire\Livewire;
use Tipowerup\Installer\Exceptions\LicenseValidationException;
use Tipowerup\Installer\Livewire\SettingsPanel;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\PowerUpApiClient;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Bind a standard HostingDetector mock that returns a predictable environment array.
 */
function mockSettingsHostingDetector(string $method = 'direct', ?string $composerSource = null): void
{
    test()->mock(HostingDetector::class, function ($mock) use ($method, $composerSource): void {
        $mock->shouldReceive('analyze')->andReturn([
            'recommended_method' => $method,
            'can_exec' => false,
            'can_proc_open' => false,
            'memory_limit_mb' => 256,
            'composer_available' => $composerSource !== null,
            'composer_source' => $composerSource,
            'storage_writable' => true,
            'has_zip_archive' => true,
            'has_curl' => true,
        ]);
        $mock->shouldReceive('getRecommendedMethod')->andReturn($method);
        $mock->shouldReceive('clearCache');
    });
}

// ---------------------------------------------------------------------------
// Happy paths
// ---------------------------------------------------------------------------

it('mounts with current settings loaded and empty api key when none stored', function (): void {
    mockSettingsHostingDetector();

    Livewire::test(SettingsPanel::class)
        ->assertSet('apiKey', '')
        ->assertSet('installMethod', 'direct')
        ->assertSet('showApiKeyInput', false)
        ->assertSet('isSaving', false)
        ->assertSet('successMessage', null)
        ->assertSet('errorMessage', null);
});

it('mounts with masked api key when one is stored', function (): void {
    Settings::setPref('tipowerup_api_key', 'ABCDEFGHIJKLMNOP');
    mockSettingsHostingDetector();

    $component = Livewire::test(SettingsPanel::class);

    // Key must be masked: all chars except last 4 replaced with *
    $maskedKey = $component->get('apiKey');
    expect($maskedKey)
        ->toEndWith('MNOP')
        ->not->toBe('ABCDEFGHIJKLMNOP');

    expect(str_contains($maskedKey, '*'))->toBeTrue();
})->afterEach(function (): void {
    Settings::setPref('tipowerup_api_key', '');
});

it('mounts with install method from stored settings', function (): void {
    Settings::setPref('tipowerup_install_method', 'composer');
    mockSettingsHostingDetector('composer');

    Livewire::test(SettingsPanel::class)
        ->assertSet('installMethod', 'composer');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_install_method', 'direct');
});

it('saveSettings with valid install method saves to db and shows success message', function (): void {
    mockSettingsHostingDetector();

    Livewire::test(SettingsPanel::class)
        ->set('installMethod', 'direct')
        ->call('saveSettings')
        ->assertSet('isSaving', false)
        ->assertSet('errorMessage', null);

    // Regression: must use Settings::setPref(), not params()->set()
    expect(Settings::getPref('tipowerup_install_method'))->toBe('direct');

    $component = Livewire::test(SettingsPanel::class)
        ->set('installMethod', 'direct')
        ->call('saveSettings');

    expect($component->get('successMessage'))->not->toBeNull()->not->toBeEmpty();
})->afterEach(function (): void {
    Settings::setPref('tipowerup_install_method', 'direct');
});

it('changeApiKey with valid new key verifies, saves to db, masks display, hides input, and shows success', function (): void {
    Settings::setPref('tipowerup_api_key', 'old-key-1234');
    mockSettingsHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once()->with('new-valid-key-5678');
        $mock->shouldReceive('verifyKey')->once()->andReturn([]);
    });

    $component = Livewire::test(SettingsPanel::class)
        ->set('newApiKey', 'new-valid-key-5678')
        ->call('changeApiKey')
        ->assertSet('newApiKey', '')
        ->assertSet('showApiKeyInput', false)
        ->assertSet('isSaving', false)
        ->assertSet('errorMessage', null);

    expect($component->get('successMessage'))->not->toBeNull()->not->toBeEmpty();

    // Key must now be 'new-valid-key-5678' in the database
    expect(Settings::getPref('tipowerup_api_key'))->toBe('new-valid-key-5678');

    // Displayed key must be masked
    $displayedKey = $component->get('apiKey');
    expect($displayedKey)
        ->toEndWith('5678')
        ->not->toBe('new-valid-key-5678');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_api_key', '');
});

it('toggleApiKeyInput toggles showApiKeyInput, clears newApiKey and error', function (): void {
    mockSettingsHostingDetector();

    // Toggle on
    Livewire::test(SettingsPanel::class)
        ->set('errorMessage', 'Some previous error')
        ->set('newApiKey', 'draft-key')
        ->call('toggleApiKeyInput')
        ->assertSet('showApiKeyInput', true)
        ->assertSet('newApiKey', '')
        ->assertSet('errorMessage', null);
});

it('toggleApiKeyInput called twice returns showApiKeyInput to false', function (): void {
    mockSettingsHostingDetector();

    Livewire::test(SettingsPanel::class)
        ->call('toggleApiKeyInput')
        ->assertSet('showApiKeyInput', true)
        ->call('toggleApiKeyInput')
        ->assertSet('showApiKeyInput', false);
});

it('closePanel dispatches settings-closed event', function (): void {
    mockSettingsHostingDetector();

    Livewire::test(SettingsPanel::class)
        ->call('closePanel')
        ->assertDispatched('settings-closed');
});

// ---------------------------------------------------------------------------
// Error paths
// ---------------------------------------------------------------------------

it('saveSettings with invalid install method shows error and does not persist', function (): void {
    mockSettingsHostingDetector();

    Settings::setPref('tipowerup_install_method', 'direct');

    $component = Livewire::test(SettingsPanel::class)
        ->set('installMethod', 'invalid-method')
        ->call('saveSettings')
        ->assertSet('isSaving', false)
        ->assertSet('successMessage', null);

    expect($component->get('errorMessage'))->not->toBeNull()->not->toBeEmpty();

    // Original value must not have been overwritten
    expect(Settings::getPref('tipowerup_install_method'))->not->toBe('invalid-method');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_install_method', 'direct');
});

it('changeApiKey with empty new key shows error and does not call the api', function (): void {
    mockSettingsHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->never();
        $mock->shouldReceive('verifyKey')->never();
    });

    $component = Livewire::test(SettingsPanel::class)
        ->set('newApiKey', '')
        ->call('changeApiKey')
        ->assertSet('isSaving', false);

    expect($component->get('errorMessage'))->not->toBeNull()->not->toBeEmpty();
});

it('changeApiKey with an api-rejected key shows error, keeps input visible, does not save', function (): void {
    Settings::setPref('tipowerup_api_key', 'original-key-5678');
    mockSettingsHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once()->with('rejected-key');
        $mock->shouldReceive('verifyKey')->once()->andThrow(
            new LicenseValidationException('Key is invalid or expired.')
        );
    });

    Livewire::test(SettingsPanel::class)
        ->set('newApiKey', 'rejected-key')
        ->call('changeApiKey')
        ->assertSet('isSaving', false)
        ->assertSet('successMessage', null)
        ->assertSet('errorMessage', 'Key is invalid or expired.');

    // Original key must remain unchanged in the database
    expect(Settings::getPref('tipowerup_api_key'))->toBe('original-key-5678');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_api_key', '');
});

// ---------------------------------------------------------------------------
// Critical regression tests
// ---------------------------------------------------------------------------

it('settings are persisted via Settings::setPref not params()->set — verifies db write', function (): void {
    mockSettingsHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once();
        $mock->shouldReceive('verifyKey')->once()->andReturn([]);
    });

    // Confirm both install method and api key land in the DB
    Livewire::test(SettingsPanel::class)
        ->set('installMethod', 'composer')
        ->set('newApiKey', 'regression-key-9999')
        ->call('saveSettings');

    // If params()->set() had been used, these would either throw or not persist
    expect(Settings::getPref('tipowerup_install_method'))->toBe('composer');
    expect(Settings::getPref('tipowerup_api_key'))->toBe('regression-key-9999');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_install_method', 'direct');
    Settings::setPref('tipowerup_api_key', '');
});

it('after successful key change showApiKeyInput is false and newApiKey is empty string', function (): void {
    mockSettingsHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once();
        $mock->shouldReceive('verifyKey')->once()->andReturn([]);
    });

    Livewire::test(SettingsPanel::class)
        ->set('showApiKeyInput', true)
        ->set('newApiKey', 'some-new-key-abcd')
        ->call('changeApiKey')
        ->assertSet('showApiKeyInput', false)
        ->assertSet('newApiKey', '');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_api_key', '');
});

it('isSaving is always false after saveSettings completes, even on exception', function (): void {
    mockSettingsHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once();
        $mock->shouldReceive('verifyKey')->once()->andThrow(new \RuntimeException('Unexpected error'));
    });

    Livewire::test(SettingsPanel::class)
        ->set('installMethod', 'direct')
        ->set('newApiKey', 'trigger-exception')
        ->call('saveSettings')
        ->assertSet('isSaving', false);
});
