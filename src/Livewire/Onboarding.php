<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Igniter\System\Models\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Services\ComposerPharManager;
use Tipowerup\Installer\Services\HealthChecker;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\PowerUpApiClient;

class Onboarding extends Component
{
    public int $currentStep = 1;

    public string $apiKey = '';

    /**
     * @var array<int, array{key: string, label: string, passed: bool, message: string, fix: string|null, critical: bool}>
     */
    public array $healthChecks = [];

    public bool $apiKeyVerified = false;

    public ?array $userProfile = null;

    public bool $isVerifying = false;

    public ?string $errorMessage = null;

    public ?string $detectedMethod = null;

    #[Computed]
    public function logoDataUri(): string
    {
        $logoPath = dirname(__DIR__, 2).'/resources/images/logo.png';

        if (!file_exists($logoPath)) {
            return '';
        }

        $data = file_get_contents($logoPath);

        return 'data:image/png;base64,'.base64_encode($data);
    }

    public function mount(): void
    {
        // Check if already onboarded
        if (params('tipowerup_onboarded', false)) {
            $this->dispatch('onboarding-completed');

            return;
        }

        // Run health checks on mount
        $this->runHealthChecks();
    }

    public function runHealthChecks(): void
    {
        $healthChecker = resolve(HealthChecker::class);
        $this->healthChecks = $healthChecker->runAllChecks();

        $hostingDetector = resolve(HostingDetector::class);

        // Auto-download composer.phar if environment supports Composer but no system composer
        $memory = $hostingDetector->getMemoryLimitMB();
        $hasAdequateMemory = $memory >= 128 || $memory === -1;

        if ($hostingDetector->canProcOpen() && $hasAdequateMemory && $hostingDetector->getComposerSource() === null) {
            $pharManager = resolve(ComposerPharManager::class);
            $pharManager->download();
            $hostingDetector->clearCache();
        }

        $this->detectedMethod = $hostingDetector->getRecommendedMethod();
    }

    public function canProceedFromHealth(): bool
    {
        $healthChecker = resolve(HealthChecker::class);

        return !$healthChecker->hasCriticalFailures();
    }

    public function proceedToApiKey(): void
    {
        if (!$this->canProceedFromHealth()) {
            $this->errorMessage = 'Please fix all critical issues before proceeding.';

            return;
        }

        $this->currentStep = 2;
        $this->errorMessage = null;
    }

    public function verifyApiKey(): void
    {
        $this->errorMessage = null;

        // Validate input
        if ($this->apiKey === '' || $this->apiKey === '0') {
            $this->errorMessage = lang('tipowerup.installer::default.error_api_key_required');

            return;
        }

        $this->isVerifying = true;

        try {
            $apiClient = resolve(PowerUpApiClient::class);
            $apiClient->setApiKey($this->apiKey);

            $response = $apiClient->verifyKey();

            // Store the PowerUp key
            Settings::setPref('tipowerup_api_key', $this->apiKey);

            // Set user profile from response
            $this->userProfile = [
                'name' => $response['user']['name'] ?? 'User',
                'email' => $response['user']['email'] ?? '',
                'avatar' => $response['user']['avatar'] ?? null,
            ];

            $this->apiKeyVerified = true;
            $this->currentStep = 3;
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isVerifying = false;
        }
    }

    public function proceedToWelcome(): void
    {
        if (!$this->apiKeyVerified) {
            $this->errorMessage = lang('tipowerup.installer::default.error_powerup_key_not_verified');

            return;
        }

        $this->currentStep = 3;
        $this->errorMessage = null;
    }

    public function completeOnboarding(): void
    {
        // Mark onboarding as complete
        Settings::setPref('tipowerup_onboarded', true);

        // Dispatch event to parent component
        $this->dispatch('onboarding-completed');

        // Redirect to main installer page
        $this->redirect(admin_url('tipowerup/installer/installer'));
    }

    public function backToHealth(): void
    {
        $this->currentStep = 1;
        $this->errorMessage = null;
    }

    public function backToApiKey(): void
    {
        $this->currentStep = 2;
        $this->errorMessage = null;
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.onboarding', [
            'communityLinks' => resolve(HealthChecker::class)->getCommunityLinks(),
        ]);
    }
}
