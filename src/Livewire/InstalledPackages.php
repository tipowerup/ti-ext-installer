<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\PackageInstaller;
use Tipowerup\Installer\Services\PowerUpApiClient;

class InstalledPackages extends Component
{
    /**
     * @var array<int, array{code: string, name: string, description: string, version: string, latest_version: string, type: string, install_method: string, is_active: bool, installed_at: string, expires_at: ?string, has_update: bool, icon: string}>
     */
    public array $packages = [];

    public string $viewMode = 'grid';

    public bool $isLoading = true;

    public bool $isCheckingUpdates = false;

    /**
     * @var array<string, array{current_version: string, latest_version: string}>
     */
    public array $availableUpdates = [];

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->loadPackages();
    }

    public function loadPackages(): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            // Get local license records
            $licenses = License::active()->get();

            // If no packages installed locally, return empty
            if ($licenses->isEmpty()) {
                $this->packages = [];
                $this->isLoading = false;

                return;
            }

            // Fetch remote package data from API
            $apiClient = resolve(PowerUpApiClient::class);
            $remoteData = $apiClient->getMyPackages();
            $remotePackages = $remoteData['packages'] ?? [];

            // Build indexed array of remote packages for quick lookup
            $remoteIndex = collect($remotePackages)->keyBy('code')->all();

            // Merge local + remote data
            $this->packages = $licenses->map(function (License $license) use ($remoteIndex): array {
                $remote = $remoteIndex[$license->package_code] ?? null;

                return [
                    'code' => $license->package_code,
                    'name' => $remote['name'] ?? $license->package_name,
                    'description' => $remote['description'] ?? '',
                    'version' => $license->version,
                    'latest_version' => $remote['latest_version'] ?? $license->version,
                    'type' => $license->package_type,
                    'install_method' => $license->install_method ?? 'direct',
                    'is_active' => $license->is_active,
                    'installed_at' => $license->installed_at->format('M j, Y'),
                    'expires_at' => $license->expires_at?->format('M j, Y'),
                    'has_update' => version_compare(
                        $remote['latest_version'] ?? $license->version,
                        $license->version,
                        '>'
                    ),
                    'icon' => $remote['icon'] ?? $this->getDefaultIcon($license->package_type),
                ];
            })->toArray();

        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->packages = [];
        } finally {
            $this->isLoading = false;
        }
    }

    public function checkUpdates(): void
    {
        $this->isCheckingUpdates = true;
        $this->errorMessage = null;

        try {
            $installer = resolve(PackageInstaller::class);
            $result = $installer->checkUpdates();

            $this->availableUpdates = [];

            foreach ($result['updates'] ?? [] as $update) {
                $this->availableUpdates[$update['package_code']] = [
                    'current_version' => $update['current_version'],
                    'latest_version' => $update['latest_version'],
                ];
            }

            // Refresh packages to show updated info
            $this->loadPackages();

        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isCheckingUpdates = false;
        }
    }

    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'grid' ? 'list' : 'grid';
    }

    public function installPackage(string $packageCode): void
    {
        // Dispatch event to parent (InstallerMain)
        $this->dispatch('install-started');

        // Dispatch event to InstallProgress component
        $this->dispatch('begin-install', packageCode: $packageCode);
    }

    public function updatePackage(string $packageCode): void
    {
        // Dispatch event to parent (InstallerMain)
        $this->dispatch('install-started');

        // Dispatch event to InstallProgress component
        $this->dispatch('begin-update', packageCode: $packageCode);
    }

    public function uninstallPackage(string $packageCode): void
    {
        try {
            $installer = resolve(PackageInstaller::class);
            $installer->uninstall($packageCode);

            // Reload packages
            $this->loadPackages();

            session()->flash('success', lang('tipowerup.installer::default.success_uninstalled', [
                'package' => $packageCode,
            ]));

        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function viewDetail(string $packageCode): void
    {
        // Dispatch event to parent to open PackageDetail modal
        $this->dispatch('view-package-detail', packageCode: $packageCode);
    }

    #[On('install-completed')]
    public function onInstallCompleted(): void
    {
        // Reload packages to reflect new state
        $this->loadPackages();
    }

    private function getDefaultIcon(string $packageType): string
    {
        return match ($packageType) {
            'extension' => 'fa-puzzle-piece',
            'theme' => 'fa-paint-brush',
            default => 'fa-cube',
        };
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.installed-packages');
    }
}
