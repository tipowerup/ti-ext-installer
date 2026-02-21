<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Igniter\System\Models\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Services\ComposerPharManager;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\PowerUpApiClient;

class SettingsPanel extends Component
{
    public string $apiKey = '';

    public string $newApiKey = '';

    public string $installMethod = 'auto';

    public array $environmentInfo = [];

    public bool $isSaving = false;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public bool $showApiKeyInput = false;

    public function mount(): void
    {
        $this->loadCurrentSettings();
        $this->loadEnvironmentInfo();
    }

    public function loadCurrentSettings(): void
    {
        // Load current PowerUp key (masked)
        $rawApiKey = params('tipowerup_api_key', '');
        if ($rawApiKey) {
            $this->apiKey = $this->maskApiKey($rawApiKey);
        }

        // Load install method preference
        $this->installMethod = params('tipowerup_install_method', 'auto');
    }

    public function loadEnvironmentInfo(): void
    {
        $hostingDetector = resolve(HostingDetector::class);
        $this->environmentInfo = $hostingDetector->analyze();
    }

    public function saveSettings(): void
    {
        $this->isSaving = true;
        $this->errorMessage = null;
        $this->successMessage = null;

        try {
            // Validate install method
            if (!in_array($this->installMethod, ['auto', 'direct', 'composer'], true)) {
                $this->errorMessage = lang('tipowerup.installer::default.error_invalid_install_method');

                return;
            }

            // Save install method preference
            Settings::setPref('tipowerup_install_method', $this->installMethod);

            // If new PowerUp key provided, verify and save it
            if ($this->newApiKey !== '' && $this->newApiKey !== '0') {
                $this->changeApiKey();

                return; // changeApiKey already handles success/error messaging
            }

            $this->successMessage = lang('tipowerup.installer::default.settings_saved');
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSaving = false;
        }
    }

    public function changeApiKey(): void
    {
        $this->isSaving = true;
        $this->errorMessage = null;
        $this->successMessage = null;

        try {
            if ($this->newApiKey === '' || $this->newApiKey === '0') {
                $this->errorMessage = lang('tipowerup.installer::default.error_api_key_required');

                return;
            }

            // Verify the new PowerUp key
            $apiClient = resolve(PowerUpApiClient::class);
            $apiClient->setApiKey($this->newApiKey);
            $apiClient->verifyKey();

            // Save the new PowerUp key
            Settings::setPref('tipowerup_api_key', $this->newApiKey);

            // Update masked display
            $this->apiKey = $this->maskApiKey($this->newApiKey);
            $this->newApiKey = '';
            $this->showApiKeyInput = false;

            // Clear hosting detector cache to refresh environment info
            $hostingDetector = resolve(HostingDetector::class);
            $hostingDetector->clearCache();
            $this->loadEnvironmentInfo();

            $this->successMessage = lang('tipowerup.installer::default.success_api_key_verified');
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSaving = false;
        }
    }

    public function toggleApiKeyInput(): void
    {
        $this->showApiKeyInput = !$this->showApiKeyInput;
        $this->newApiKey = '';
        $this->errorMessage = null;
    }

    public function downloadComposerPhar(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        try {
            $pharManager = resolve(ComposerPharManager::class);

            if ($pharManager->download()) {
                $hostingDetector = resolve(HostingDetector::class);
                $hostingDetector->clearCache();
                $this->loadEnvironmentInfo();

                $this->successMessage = lang('tipowerup.installer::default.success_composer_phar_downloaded');
            } else {
                $this->errorMessage = lang('tipowerup.installer::default.error_composer_phar_download_failed');
            }
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function refreshEnvironmentInfo(): void
    {
        $hostingDetector = resolve(HostingDetector::class);
        $this->environmentInfo = $hostingDetector->freshAnalyze();
    }

    public function closePanel(): void
    {
        $this->dispatch('settings-closed');
    }

    private function maskApiKey(string $key): string
    {
        if (strlen($key) <= 4) {
            return str_repeat('*', strlen($key));
        }

        return str_repeat('*', strlen($key) - 4).substr($key, -4);
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.settings-panel');
    }
}
