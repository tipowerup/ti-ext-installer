<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Exception;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Tipowerup\Installer\Services\PowerUpApiClient;

class Marketplace extends Component
{
    public array $packages = [];

    public string $searchQuery = '';

    public string $filterType = 'all';

    public int $currentPage = 1;

    public int $totalPages = 1;

    public int $perPage = 12;

    public bool $isLoading = true;

    public bool $isPollingPurchases = false;

    public int $pollStartTime = 0;

    public ?string $pollingPackageCode = null;

    public ?string $errorMessage = null;

    public array $selectedForBatch = [];

    public function mount(): void
    {
        $this->loadMarketplace();
    }

    public function loadMarketplace(): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $apiClient = resolve(PowerUpApiClient::class);

            $filters = [
                'search' => $this->searchQuery !== '' ? $this->searchQuery : null,
                'type' => $this->filterType !== 'all' ? $this->filterType : null,
                'page' => $this->currentPage,
                'per_page' => $this->perPage,
            ];

            $response = $apiClient->getMarketplace($filters);

            $this->packages = $response['packages'] ?? [];
            $this->totalPages = $response['total_pages'] ?? 1;
            $this->currentPage = $response['current_page'] ?? 1;
        } catch (Exception) {
            $this->errorMessage = lang('tipowerup.installer::default.error_connection_failed');
            $this->packages = [];
        } finally {
            $this->isLoading = false;
        }
    }

    public function updatedSearchQuery(): void
    {
        $this->currentPage = 1;
        $this->loadMarketplace();
    }

    public function setFilter(string $type): void
    {
        $this->filterType = $type;
        $this->currentPage = 1;
        $this->loadMarketplace();
    }

    public function goToPage(int $page): void
    {
        if ($page < 1 || $page > $this->totalPages) {
            return;
        }

        $this->currentPage = $page;
        $this->loadMarketplace();
    }

    public function viewDetail(string $packageCode): void
    {
        $this->dispatch('view-package-detail', packageCode: $packageCode)->to(InstallerMain::class);
    }

    public function installPackage(string $packageCode): void
    {
        $packageName = $this->getPackageName($packageCode);

        $this->dispatch('install-started');
        $this->dispatch('begin-install', packageCode: $packageCode, packageName: $packageName);
    }

    public function buyOnPowerUp(string $packageCode): void
    {
        $this->startPurchasePolling($packageCode);
    }

    public function startPurchasePolling(string $packageCode): void
    {
        $this->isPollingPurchases = true;
        $this->pollingPackageCode = $packageCode;
        $this->pollStartTime = time();
    }

    public function pollForPurchase(): void
    {
        if (!$this->isPollingPurchases || $this->pollingPackageCode === null) {
            return;
        }

        // Check if 5 minutes have elapsed (300 seconds)
        if (time() - $this->pollStartTime > 300) {
            $this->stopPolling();

            return;
        }

        try {
            $apiClient = resolve(PowerUpApiClient::class);
            $myPackages = $apiClient->getMyPackages();

            $purchasedCodes = array_column($myPackages['packages'] ?? [], 'code');

            if (in_array($this->pollingPackageCode, $purchasedCodes, true)) {
                // Capture the package code before stopping polling
                $detectedCode = $this->pollingPackageCode;
                $this->stopPolling();
                $this->loadMarketplace();

                $this->dispatch('purchase-detected', packageCode: $detectedCode);
            }
        } catch (Exception) {
            // Silent fail - continue polling
        }
    }

    public function stopPolling(): void
    {
        $this->isPollingPurchases = false;
        $this->pollingPackageCode = null;
        $this->pollStartTime = 0;
    }

    public function toggleBatchSelect(string $packageCode): void
    {
        if (in_array($packageCode, $this->selectedForBatch, true)) {
            $this->selectedForBatch = array_values(
                array_filter($this->selectedForBatch, fn ($code): bool => $code !== $packageCode)
            );
        } else {
            $this->selectedForBatch[] = $packageCode;
        }
    }

    public function batchInstall(): void
    {
        if ($this->selectedForBatch === []) {
            return;
        }

        $this->dispatch('install-started');
        $this->dispatch('begin-batch-install', packageCodes: $this->selectedForBatch);

        $this->selectedForBatch = [];
    }

    private function getPackageName(string $packageCode): string
    {
        foreach ($this->packages as $package) {
            if ($package['code'] === $packageCode) {
                return $package['name'];
            }
        }

        return $packageCode;
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.marketplace');
    }
}
