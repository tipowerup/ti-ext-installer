<?php

declare(strict_types=1);

use Igniter\System\Models\Settings;
use Livewire\Livewire;
use Tipowerup\Installer\Exceptions\LicenseValidationException;
use Tipowerup\Installer\Livewire\Onboarding;
use Tipowerup\Installer\Services\HealthChecker;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\PowerUpApiClient;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Return a mock HealthChecker that reports no critical failures and passes all checks.
 *
 * @param  array<int, array{key: string, label: string, passed: bool, message: string, fix: string|null, critical: bool}>  $checks
 */
function mockHealthChecker(bool $hasCriticalFailures = false, array $checks = []): void
{
    if ($checks === []) {
        $checks = [
            [
                'key' => 'php_version',
                'label' => 'PHP Version',
                'passed' => true,
                'message' => 'PHP 8.3 detected',
                'fix' => null,
                'critical' => true,
            ],
        ];
    }

    test()->mock(HealthChecker::class, function ($mock) use ($hasCriticalFailures, $checks): void {
        $mock->shouldReceive('runAllChecks')->andReturn($checks);
        $mock->shouldReceive('hasCriticalFailures')->andReturn($hasCriticalFailures);
        $mock->shouldReceive('getCommunityLinks')->andReturn([]);
    });
}

/**
 * Return a mock HostingDetector that returns 'direct' as the recommended method.
 */
function mockHostingDetector(string $method = 'direct', ?string $composerSource = null): void
{
    test()->mock(HostingDetector::class, function ($mock) use ($method, $composerSource): void {
        $mock->shouldReceive('analyze')->andReturn([
            'recommended_method' => $method,
            'can_exec' => false,
            'memory_limit_mb' => 256,
            'composer_available' => $composerSource !== null,
            'composer_source' => $composerSource,
            'storage_writable' => true,
            'has_zip_archive' => true,
            'has_curl' => true,
        ]);
        $mock->shouldReceive('getRecommendedMethod')->andReturn($method);
        $mock->shouldReceive('canProcOpen')->andReturn(false);
        $mock->shouldReceive('getMemoryLimitMB')->andReturn(256);
        $mock->shouldReceive('getComposerSource')->andReturn($composerSource);
        $mock->shouldReceive('clearCache');
    });
}

// ---------------------------------------------------------------------------
// Happy paths
// ---------------------------------------------------------------------------

it('mounts at step 1 with health checks loaded', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    Livewire::test(Onboarding::class)
        ->assertSet('currentStep', 1)
        ->assertSet('apiKeyVerified', false)
        ->assertSet('errorMessage', null)
        ->assertSet('isVerifying', false);
});

it('dispatches onboarding-completed when already onboarded', function (): void {
    Settings::setPref('tipowerup_onboarded', true);

    mockHealthChecker();
    mockHostingDetector();

    Livewire::test(Onboarding::class)
        ->assertDispatched('onboarding-completed');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_onboarded', false);
});

it('advances to step 2 when proceedToApiKey is called with no critical failures', function (): void {
    mockHealthChecker(hasCriticalFailures: false);
    mockHostingDetector();

    Livewire::test(Onboarding::class)
        ->call('proceedToApiKey')
        ->assertSet('currentStep', 2)
        ->assertSet('errorMessage', null);
});

it('verifies a valid api key, saves it to db, sets user profile, and advances to step 3', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once()->with('valid-key-123');
        $mock->shouldReceive('verifyKey')->once()->andReturn([
            'user' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'avatar' => null,
            ],
        ]);
    });

    Livewire::test(Onboarding::class)
        ->set('apiKey', 'valid-key-123')
        ->call('verifyApiKey')
        ->assertSet('apiKeyVerified', true)
        ->assertSet('currentStep', 3)
        ->assertSet('errorMessage', null)
        ->assertSet('isVerifying', false);

    // Regression: key must be persisted via Settings::setPref(), not params()->set()
    expect(Settings::getPref('tipowerup_api_key'))->toBe('valid-key-123');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_api_key', '');
});

it('completes onboarding, persists flag in db, dispatches event, and redirects', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    Livewire::test(Onboarding::class)
        ->call('completeOnboarding')
        ->assertDispatched('onboarding-completed')
        ->assertRedirect();

    // Regression: onboarded flag must be persisted via Settings::setPref(), not params()->set().
    // Settings stores booleans as '1' in SQLite, so cast before asserting.
    expect((bool) Settings::getPref('tipowerup_onboarded'))->toBeTrue();
})->afterEach(function (): void {
    Settings::setPref('tipowerup_onboarded', false);
});

it('backToHealth sets step to 1 and clears errors', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    Livewire::test(Onboarding::class)
        ->set('currentStep', 2)
        ->set('errorMessage', 'Some error')
        ->call('backToHealth')
        ->assertSet('currentStep', 1)
        ->assertSet('errorMessage', null);
});

it('backToApiKey sets step to 2 and clears errors', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    Livewire::test(Onboarding::class)
        ->set('currentStep', 3)
        ->set('errorMessage', 'Some error')
        ->call('backToApiKey')
        ->assertSet('currentStep', 2)
        ->assertSet('errorMessage', null);
});

// ---------------------------------------------------------------------------
// Error paths
// ---------------------------------------------------------------------------

it('blocks proceedToApiKey and shows error when critical failures exist', function (): void {
    mockHealthChecker(hasCriticalFailures: true);
    mockHostingDetector();

    Livewire::test(Onboarding::class)
        ->call('proceedToApiKey')
        ->assertSet('currentStep', 1)
        ->assertSet('errorMessage', 'Please fix all critical issues before proceeding.');
});

it('verifyApiKey with empty key shows error and stays on step 2', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    Livewire::test(Onboarding::class)
        ->set('currentStep', 2)
        ->set('apiKey', '')
        ->call('verifyApiKey')
        ->assertSet('currentStep', 2)
        ->assertSet('apiKeyVerified', false)
        ->assertSet('isVerifying', false);

    // errorMessage must be populated
    $component = Livewire::test(Onboarding::class)
        ->set('currentStep', 2)
        ->set('apiKey', '')
        ->call('verifyApiKey');

    expect($component->get('errorMessage'))->not->toBeNull()->not->toBeEmpty();
});

it('verifyApiKey with invalid key shows api error message and stays on step 2', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once()->with('bad-key');
        $mock->shouldReceive('verifyKey')->once()->andThrow(
            new LicenseValidationException('Invalid PowerUp key.')
        );
    });

    Livewire::test(Onboarding::class)
        ->set('currentStep', 2)
        ->set('apiKey', 'bad-key')
        ->call('verifyApiKey')
        ->assertSet('apiKeyVerified', false)
        ->assertSet('currentStep', 2)
        ->assertSet('errorMessage', 'Invalid PowerUp key.')
        ->assertSet('isVerifying', false);
});

it('proceedToWelcome without verified key shows error and stays on step 2', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    $component = Livewire::test(Onboarding::class)
        ->set('currentStep', 2)
        ->set('apiKeyVerified', false)
        ->call('proceedToWelcome')
        ->assertSet('currentStep', 2);

    expect($component->get('errorMessage'))->not->toBeNull()->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Critical regression tests
// ---------------------------------------------------------------------------

it('api key is persisted via Settings::setPref and is readable from db after verification', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once();
        $mock->shouldReceive('verifyKey')->once()->andReturn([
            'user' => ['name' => 'Regression User', 'email' => 'r@test.com', 'avatar' => null],
        ]);
    });

    Livewire::test(Onboarding::class)
        ->set('apiKey', 'regression-key-abc')
        ->call('verifyApiKey');

    // If code used params()->set() this would return '' because params() returns a scalar, not an object
    $storedKey = Settings::getPref('tipowerup_api_key');
    expect($storedKey)->toBe('regression-key-abc');
})->afterEach(function (): void {
    Settings::setPref('tipowerup_api_key', '');
});

it('step advances to 3 after successful key verification, not stuck on step 2', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once();
        $mock->shouldReceive('verifyKey')->once()->andReturn([
            'user' => ['name' => 'Step Regression', 'email' => 'step@test.com', 'avatar' => null],
        ]);
    });

    Livewire::test(Onboarding::class)
        ->set('currentStep', 2)
        ->set('apiKey', 'step-test-key')
        ->call('verifyApiKey')
        ->assertSet('currentStep', 3); // Must NOT remain at 2
})->afterEach(function (): void {
    Settings::setPref('tipowerup_api_key', '');
});

it('isVerifying is always false after verifyApiKey completes, even on exception', function (): void {
    mockHealthChecker();
    mockHostingDetector();

    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('setApiKey')->once();
        $mock->shouldReceive('verifyKey')->once()->andThrow(new \RuntimeException('Network error'));
    });

    Livewire::test(Onboarding::class)
        ->set('apiKey', 'any-key')
        ->call('verifyApiKey')
        ->assertSet('isVerifying', false);
});
