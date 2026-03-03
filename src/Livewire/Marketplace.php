<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Igniter\Flame\Support\Facades\File;
use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Livewire\Concerns\HandlesApiErrors;
use Tipowerup\Installer\Services\PowerUpApiClient;

class Marketplace extends Component
{
    use HandlesApiErrors;

    public array $packages = [];

    public string $searchQuery = '';

    public string $filterType = 'all';

    public int $currentPage = 1;

    public int $totalPages = 1;

    public int $perPage = 12;

    public bool $isLoading = true;

    public string $viewMode = 'grid';

    public function mount(): void
    {
        $this->loadMarketplace();
    }

    #[On('api-key-changed')]
    public function onApiKeyChanged(): void
    {
        $this->loadMarketplace();
    }

    public function loadMarketplace(): void
    {
        $this->isLoading = true;
        $this->resetApiError();

        try {
            $apiClient = resolve(PowerUpApiClient::class);

            $filters = [
                'search' => $this->searchQuery !== '' ? $this->searchQuery : null,
                'type' => $this->filterType !== 'all' ? $this->filterType : null,
                'page' => $this->currentPage,
                'per_page' => $this->perPage,
            ];

            $response = $apiClient->getMarketplace($filters);

            $installedCodes = $this->getInstalledPackageCodes();
            $this->packages = array_values(
                array_filter($response['data'] ?? [], fn (array $pkg): bool => !in_array($pkg['code'] ?? '', $installedCodes, true) && !($pkg['purchased'] ?? false))
            );
            $pagination = $response['pagination'] ?? [];
            $this->totalPages = $pagination['total_pages'] ?? 1;
            $this->currentPage = $pagination['current_page'] ?? 1;
        } catch (Throwable $e) {
            $this->handleApiError($e);
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

    public function acquireFreeProduct(string $packageCode, string $packageName): void
    {
        $this->resetApiError();

        try {
            $apiClient = resolve(PowerUpApiClient::class);
            $apiClient->acquireFreeProduct($packageCode);

            $this->showToast('success', lang('tipowerup.installer::default.success_free_acquired', [
                'package' => $packageName,
            ]));

            $this->loadMarketplace();
            $this->dispatch('api-key-changed');
        } catch (Throwable $e) {
            $this->handleApiError($e);
        }
    }

    public function refreshMarketplace(): void
    {
        $this->loadMarketplace();
    }

    /**
     * Get composer package names of all installed tipowerup packages.
     *
     * @return array<int, string>
     */
    private function getInstalledPackageCodes(): array
    {
        $codes = [];

        $extensionManager = resolve(ExtensionManager::class);
        foreach ($extensionManager->listExtensions() as $code) {
            if (!str_starts_with($code, 'tipowerup.') || $code === 'tipowerup.installer') {
                continue;
            }

            $extension = $extensionManager->findExtension($code);
            if ($extension === null) {
                continue;
            }

            $extensionRoot = dirname(dirname(File::fromClass(get_class($extension))));
            $composerPath = $extensionRoot.'/composer.json';
            $contents = file_get_contents($composerPath);
            $composerData = json_decode($contents, true);
            $codes[] = $composerData['name'];
        }

        $themeManager = resolve(ThemeManager::class);
        foreach ($themeManager->listThemes() as $code => $theme) {
            if (!str_starts_with($code, 'tipowerup-')) {
                continue;
            }

            $themePath = $themeManager->findPath($code);
            if ($themePath === null) {
                continue;
            }

            $contents = file_get_contents($themePath.'/composer.json');
            $composerData = json_decode($contents, true);
            $codes[] = $composerData['name'];
        }

        return $codes;
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.marketplace');
    }
}
