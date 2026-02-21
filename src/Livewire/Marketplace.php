<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Component;
use RuntimeException;
use Tipowerup\Installer\Exceptions\LicenseValidationException;
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

    public ?string $errorMessage = null;

    public bool $isKeyError = false;

    public string $viewMode = 'grid';

    public array $selectedForBatch = [];

    public function mount(): void
    {
        $this->loadMarketplace();
    }

    public function loadMarketplace(): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;
        $this->isKeyError = false;

        try {
            $apiClient = resolve(PowerUpApiClient::class);

            $filters = [
                'search' => $this->searchQuery !== '' ? $this->searchQuery : null,
                'type' => $this->filterType !== 'all' ? $this->filterType : null,
                'page' => $this->currentPage,
                'per_page' => $this->perPage,
            ];

            $response = $apiClient->getMarketplace($filters);

            $this->packages = $response['data'] ?? [];
            $pagination = $response['pagination'] ?? [];
            $this->totalPages = $pagination['total_pages'] ?? 1;
            $this->currentPage = $pagination['current_page'] ?? 1;
        } catch (LicenseValidationException $e) {
            $this->isKeyError = true;
            $this->errorMessage = $e->getMessage();
            $this->packages = [];
        } catch (ConnectionException) {
            $this->errorMessage = lang('tipowerup.installer::default.error_connection_failed');
            $this->packages = [];
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
            $this->packages = [];
        } catch (Exception) {
            $this->errorMessage = lang('tipowerup.installer::default.error_generic');
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

    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'grid' ? 'list' : 'grid';
    }

    public function viewDetail(string $packageCode): void
    {
        $packageData = collect($this->packages)->firstWhere('code', $packageCode) ?? [];
        $this->dispatch('view-package-detail', packageCode: $packageCode, packageData: $packageData)->to(InstallerMain::class);
    }

    public function installPackage(string $packageCode): void
    {
        $packageName = $this->getPackageName($packageCode);

        $this->dispatch('install-started');
        $this->dispatch('begin-install', packageCode: $packageCode, packageName: $packageName);
    }

    public function refreshMarketplace(): void
    {
        $this->loadMarketplace();
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

    public function getPackageName(string $packageCode): string
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
