<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
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

    public function mount(string $packageCode, array $initialData = []): void
    {
        $this->packageCode = $packageCode;

        if ($initialData !== [] && (!empty($initialData['local']) || ($initialData['type'] ?? '') === 'bundle')) {
            $this->applyPackageData($initialData);
            $this->isLoading = false;
        } else {
            $this->loadPackageDetails($initialData);
        }
    }

    public function loadPackageDetails(array $fallbackData = []): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $apiClient = resolve(PowerUpApiClient::class);
            $response = $apiClient->getPackageDetail($this->packageCode);
            $this->applyPackageData($response);
        } catch (ConnectionException) {
            if ($fallbackData !== []) {
                $this->applyPackageData($fallbackData);
            } else {
                $this->errorMessage = lang('tipowerup.installer::default.error_connection_failed');
            }
        } catch (Throwable $e) {
            if ($fallbackData !== []) {
                $this->applyPackageData($fallbackData);
            } else {
                $this->errorMessage = $e->getMessage();
            }
        } finally {
            $this->isLoading = false;
        }
    }

    private function applyPackageData(array $data): void
    {
        $this->packageData = [
            'code' => $data['code'] ?? $this->packageCode,
            'name' => $data['name'] ?? lang('tipowerup.installer::default.detail_unknown_name'),
            'description' => $data['description'] ?? '',
            'description_html' => $this->sanitizeHtml(Str::markdown($data['description'] ?? '')),
            'version' => $data['version'] ?? '0.0.0',
            'author' => $data['author'] ?? 'Unknown',
            'type' => $data['type'] ?? 'extension',
            'url' => $data['url'] ?? null,
            'icon' => $data['icon'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'screenshots' => $data['screenshots'] ?? [],
            'changelog' => $data['changelog'] ?? [],
            'requirements' => $data['requirements'] ?? [],
            'updated_at' => $data['updated_at'] ?? null,
            'price' => $data['price'] ?? 0,
            'price_formatted' => $data['price_formatted'] ?? null,
            'purchased' => $data['purchased'] ?? false,
            'local' => $data['local'] ?? false,
        ];
    }

    /**
     * Strip dangerous HTML tags from rendered Markdown, keeping safe formatting tags.
     */
    private function sanitizeHtml(string $html): string
    {
        return strip_tags($html, [
            'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'a', 'strong', 'em', 'b', 'i',
            'code', 'pre', 'blockquote', 'br', 'hr',
            'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'dl', 'dt', 'dd', 'sub', 'sup',
        ]);
    }

    public function switchDetailTab(string $tab): void
    {
        if (in_array($tab, ['description', 'screenshots', 'changelog', 'compatibility'], true)) {
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
