<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Services\PowerUpApiClient;

class PackageDetail extends Component
{
    public string $packageCode = '';

    public array $packageData = [];

    public bool $isLoading = true;

    public ?string $errorMessage = null;

    public string $activeDetailTab = 'description';

    public function mount(string $packageCode): void
    {
        $this->packageCode = $packageCode;
        $this->loadPackageDetails();
    }

    public function loadPackageDetails(): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $apiClient = resolve(PowerUpApiClient::class);
            $response = $apiClient->getPackageDetail($this->packageCode);

            $this->packageData = [
                'code' => $response['code'] ?? $this->packageCode,
                'name' => $response['name'] ?? 'Unknown Package',
                'description' => $response['description'] ?? '',
                'version' => $response['version'] ?? '0.0.0',
                'author' => $response['author'] ?? 'Unknown',
                'type' => $response['type'] ?? 'extension',
                'icon' => $response['icon'] ?? null,
                'screenshots' => $response['screenshots'] ?? [],
                'changelog' => $response['changelog'] ?? '',
                'compatibility' => $response['compatibility'] ?? [],
                'dependencies' => $response['dependencies'] ?? [],
                'last_updated' => $response['last_updated'] ?? null,
                'price' => $response['price'] ?? 0,
                'is_purchased' => $response['is_purchased'] ?? false,
            ];
        } catch (Throwable) {
            $this->errorMessage = lang('tipowerup.installer::default.error_connection_failed');
        } finally {
            $this->isLoading = false;
        }
    }

    public function switchDetailTab(string $tab): void
    {
        if (in_array($tab, ['description', 'changelog', 'compatibility'], true)) {
            $this->activeDetailTab = $tab;
        }
    }

    public function closeDetail(): void
    {
        $this->dispatch('package-detail-closed');
    }

    public function installPackage(): void
    {
        $this->dispatch('install-started', packageCode: $this->packageCode, packageName: $this->packageData['name'] ?? $this->packageCode);
        $this->dispatch('package-detail-closed');
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.package-detail');
    }
}
