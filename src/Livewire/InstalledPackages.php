<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Igniter\Flame\Support\Facades\File;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Models\Theme;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Exceptions\LicenseValidationException;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Services\PackageInstaller;
use Tipowerup\Installer\Services\PowerUpApiClient;

class InstalledPackages extends Component
{
    /**
     * @var array<int, array{code: string, name: string, description: string, version: string, latest_version: string, type: string, install_method: string, is_active: bool, expires_at: ?string, has_update: bool, icon: string, is_owned: bool, settings_url: ?string, edit_url: ?string, customize_url: ?string}>
     */
    public array $installedPackages = [];

    /**
     * @var array<int, array{code: string, name: string, description: string, type: string, icon: mixed, url: ?string, price: float, price_formatted: ?string, purchased: bool}>
     */
    public array $availablePackages = [];

    public string $viewMode = 'grid';

    public bool $isLoading = true;

    public bool $isCheckingUpdates = false;

    /**
     * @var array<string, array{current_version: string, latest_version: string}>
     */
    public array $availableUpdates = [];

    public ?string $errorMessage = null;

    public bool $isKeyError = false;

    public bool $installedCollapsed = false;

    public bool $availableCollapsed = false;

    public bool $updatesCollapsed = false;

    public ?string $confirmAction = null;

    public ?string $confirmCode = null;

    public ?string $confirmName = null;

    public function mount(): void
    {
        $this->loadPackages();
    }

    public function loadPackages(): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;
        $this->isKeyError = false;

        try {
            // 1. Scan TI registries for on-system tipowerup extensions & themes
            $onSystem = $this->scanInstalledPowerUps();

            // Build set of on-system codes
            $onSystemCodes = array_column($onSystem, 'code');

            // 2. Get License records indexed by code
            $licenses = License::query()->get()->keyBy('package_code');

            // 3. Fetch API purchases
            $apiClient = resolve(PowerUpApiClient::class);
            $remoteData = $apiClient->getMyPackages();
            $remotePackages = $remoteData['data'] ?? [];
            $remoteIndex = collect($remotePackages)->keyBy('code')->all();
            $ownedCodes = array_keys($remoteIndex);

            // 4. Build Installed PowerUps from on-system scan
            $this->installedPackages = array_map(function (array $pkg) use ($licenses, $remoteIndex, $ownedCodes): array {
                $license = $licenses->get($pkg['code']);
                $remote = $remoteIndex[$pkg['code']] ?? null;
                $version = $license?->version ?? $pkg['version'] ?? '0.0.0';

                return [
                    'code' => $pkg['code'],
                    'name' => $remote['name'] ?? $license?->package_name ?? $pkg['name'],
                    'description' => $remote['description'] ?? $pkg['description'] ?? '',
                    'version' => $this->normalizeVersion($version),
                    'latest_version' => $this->normalizeVersion($remote['latest_version'] ?? $version),
                    'type' => $pkg['type'],
                    'install_method' => $license?->install_method ?? 'unknown',
                    'is_active' => $pkg['is_active'],
                    'expires_at' => $license?->expires_at?->format('M j, Y'),
                    'has_update' => version_compare(
                        $remote['latest_version'] ?? $version,
                        $version,
                        '>'
                    ),
                    'icon' => $remote['icon'] ?? $pkg['icon'] ?? $this->getDefaultIcon($pkg['type']),
                    'is_owned' => in_array($pkg['code'], $ownedCodes, true),
                    'settings_url' => $pkg['settings_url'] ?? null,
                    'edit_url' => $pkg['edit_url'] ?? null,
                    'customize_url' => $pkg['customize_url'] ?? null,
                ];
            }, $onSystem);

            // 5. Available PowerUps: API purchases NOT on system
            $this->availablePackages = collect($remotePackages)
                ->filter(fn (array $pkg): bool => !in_array($pkg['code'] ?? '', $onSystemCodes, true))
                ->map(fn (array $pkg): array => [
                    'code' => $pkg['code'],
                    'name' => $pkg['name'] ?? $pkg['code'],
                    'description' => $pkg['description'] ?? '',
                    'type' => $pkg['type'] ?? 'extension',
                    'icon' => $pkg['icon'] ?? $this->getDefaultIcon($pkg['type'] ?? 'extension'),
                    'url' => $pkg['url'] ?? null,
                    'price' => $pkg['price'] ?? 0,
                    'price_formatted' => $pkg['price_formatted'] ?? null,
                    'purchased' => true,
                ])
                ->values()
                ->toArray();

        } catch (LicenseValidationException $e) {
            $this->isKeyError = true;
            $this->errorMessage = $e->getMessage();
            $this->installedPackages = [];
            $this->availablePackages = [];
        } catch (ConnectionException) {
            $this->errorMessage = lang('tipowerup.installer::default.error_connection_failed');
            $this->installedPackages = [];
            $this->availablePackages = [];
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->installedPackages = [];
            $this->availablePackages = [];
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Scan TI's extension and theme registries for tipowerup packages.
     *
     * @return array<int, array{code: string, name: string, description: string, type: string, version: string, icon: mixed, is_active: bool}>
     */
    private function scanInstalledPowerUps(): array
    {
        $packages = [];

        // Scan extensions (codes like "tipowerup.darkmode")
        $extensionManager = resolve(ExtensionManager::class);
        foreach ($extensionManager->listExtensions() as $code) {
            if (!str_starts_with($code, 'tipowerup.') || $code === 'tipowerup.installer') {
                continue;
            }

            $extension = $extensionManager->findExtension($code);
            if ($extension === null) {
                continue;
            }

            $meta = $extension->extensionMeta();
            $extensionRoot = dirname(dirname(File::fromClass(get_class($extension))));

            $settingsUrl = null;
            $settings = $extension->registerSettings();
            if (!empty($settings)) {
                $firstKey = array_key_first($settings);
                $settingsUrl = admin_url('extensions/edit/'.str_replace('.', '/', $code).'/'.$firstKey);
            }

            $packages[] = [
                'code' => $code,
                'name' => $meta['name'] ?? $code,
                'description' => $meta['description'] ?? '',
                'type' => 'extension',
                'version' => $this->normalizeVersion($meta['version'] ?? '0.0.0'),
                'icon' => $this->normalizeIcon($meta['icon'] ?? null, $extensionRoot, 'extension'),
                'is_active' => !$extensionManager->isDisabled($code),
                'settings_url' => $settingsUrl,
            ];
        }

        // Scan themes (codes like "tipowerup-orange-tw")
        $composerVersions = $this->getComposerInstalledVersions();
        $themeManager = resolve(ThemeManager::class);
        foreach ($themeManager->listThemes() as $code => $theme) {
            if (!str_starts_with($code, 'tipowerup-')) {
                continue;
            }

            $themePath = $themeManager->findPath($code);
            $themeVersion = '0.0.0';
            $themeIcon = $this->getDefaultIcon('theme');

            if ($themePath !== null) {
                $themeComposerPath = $themePath.'/composer.json';
                if (file_exists($themeComposerPath)) {
                    $contents = file_get_contents($themeComposerPath);
                    $themeComposer = $contents !== false ? (json_decode($contents, true) ?: []) : [];
                    $composerName = $themeComposer['name'] ?? null;
                    if ($composerName !== null && isset($composerVersions[$composerName])) {
                        $themeVersion = $composerVersions[$composerName];
                    }
                }

                $themeIcon = $this->normalizeIcon($theme->icon ?? null, $themePath, 'theme');
            }

            $editUrl = admin_url('themes/source/'.$code);
            $customizeUrl = null;

            try {
                if (!empty($theme->getFormConfig())) {
                    $customizeUrl = admin_url('themes/edit/'.$code);
                }
            } catch (\Throwable) {
                // Theme may not have form config
            }

            $packages[] = [
                'code' => $code,
                'name' => $theme->label ?? $theme->name ?? $code,
                'description' => $theme->description ?? '',
                'type' => 'theme',
                'version' => $this->normalizeVersion($themeVersion),
                'icon' => $themeIcon,
                'is_active' => $themeManager->isActive($code),
                'edit_url' => $editUrl,
                'customize_url' => $customizeUrl,
            ];
        }

        return $packages;
    }

    /**
     * Read Composer installed.json and return a map of package name => version.
     *
     * @return array<string, string>
     */
    private function getComposerInstalledVersions(): array
    {
        $installedJsonPath = base_path('vendor/composer/installed.json');
        if (!file_exists($installedJsonPath)) {
            return [];
        }

        $contents = file_get_contents($installedJsonPath);
        if ($contents === false) {
            return [];
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return [];
        }

        $packages = $data['packages'] ?? $data;
        $versions = [];

        foreach ($packages as $pkg) {
            if (isset($pkg['name'], $pkg['version'])) {
                $versions[$pkg['name']] = $pkg['version'];
            }
        }

        return $versions;
    }

    public function checkUpdates(): void
    {
        $this->isCheckingUpdates = true;
        $this->errorMessage = null;
        $this->isKeyError = false;

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

        } catch (LicenseValidationException $e) {
            $this->isKeyError = true;
            $this->errorMessage = $e->getMessage();
        } catch (ConnectionException) {
            $this->errorMessage = lang('tipowerup.installer::default.error_connection_failed');
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

    public function toggleInstalledSection(): void
    {
        $this->installedCollapsed = !$this->installedCollapsed;
    }

    public function toggleAvailableSection(): void
    {
        $this->availableCollapsed = !$this->availableCollapsed;
    }

    public function toggleUpdatesSection(): void
    {
        $this->updatesCollapsed = !$this->updatesCollapsed;
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

    public function confirmUninstall(string $code): void
    {
        $package = collect($this->installedPackages)->firstWhere('code', $code);
        $this->confirmAction = 'uninstall';
        $this->confirmCode = $code;
        $this->confirmName = $package['name'] ?? $code;
    }

    public function cancelConfirm(): void
    {
        $this->confirmAction = null;
        $this->confirmCode = null;
        $this->confirmName = null;
    }

    public function executeConfirmedAction(): void
    {
        if ($this->confirmCode === null || $this->confirmAction === null) {
            return;
        }

        $code = $this->confirmCode;
        $action = $this->confirmAction;
        $this->cancelConfirm();

        if ($action === 'uninstall') {
            $this->uninstallPackage($code);
        }
    }

    public function uninstallPackage(string $packageCode): void
    {
        try {
            $name = $this->resolvePackageName($packageCode);
            $installer = resolve(PackageInstaller::class);
            $installer->uninstall($packageCode);

            $this->loadPackages();
            $this->showToast('success', lang('tipowerup.installer::default.success_uninstalled', [
                'package' => $name,
            ]));
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function enableExtension(string $code): void
    {
        try {
            $name = $this->resolvePackageName($code);
            $extensionManager = resolve(ExtensionManager::class);
            $extensionManager->updateInstalledExtensions($code, true);
            $this->loadPackages();

            $this->showToast('success', lang('tipowerup.installer::default.success_extension_enabled', [
                'package' => $name,
            ]));
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function disableExtension(string $code): void
    {
        try {
            $name = $this->resolvePackageName($code);
            $extensionManager = resolve(ExtensionManager::class);
            $extensionManager->updateInstalledExtensions($code, false);
            $this->loadPackages();

            $this->showToast('success', lang('tipowerup.installer::default.success_extension_disabled', [
                'package' => $name,
            ]));
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function activateTheme(string $code): void
    {
        try {
            $name = $this->resolvePackageName($code);
            Theme::activateTheme($code);
            Theme::clearDefaultModel();
            $this->loadPackages();

            $this->showToast('success', lang('tipowerup.installer::default.success_theme_activated', [
                'package' => $name,
            ]));
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function viewDetail(string $packageCode): void
    {
        $localData = $this->buildLocalPackageData($packageCode);
        $this->dispatch('view-package-detail', packageCode: $packageCode, packageData: $localData)
            ->to(InstallerMain::class);
    }

    /**
     * Build detail page data from local extension/theme files (no API needed).
     *
     * @return array<string, mixed>
     */
    private function buildLocalPackageData(string $packageCode): array
    {
        $package = collect($this->installedPackages)->firstWhere('code', $packageCode);
        if ($package === null) {
            return [];
        }

        $type = $package['type'] ?? 'extension';
        $rootPath = $this->resolvePackageRootPath($packageCode, $type);

        $author = 'Unknown';
        $composerData = [];
        if ($rootPath !== null) {
            $composerJsonPath = $rootPath.'/composer.json';
            if (file_exists($composerJsonPath)) {
                $contents = file_get_contents($composerJsonPath);
                $composerData = $contents !== false ? (json_decode($contents, true) ?: []) : [];
                $authors = $composerData['authors'] ?? [];
                if (!empty($authors[0]['name'])) {
                    $author = $authors[0]['name'];
                }
            }
        }

        // Read README.md for description
        $description = $package['description'] ?? '';
        if ($rootPath !== null) {
            $readmePath = $rootPath.'/README.md';
            if (file_exists($readmePath)) {
                $contents = file_get_contents($readmePath);
                if ($contents !== false) {
                    $description = $contents;
                }
            }
        }

        // Cover image: screenshot.png at root (limit to 512KB)
        $coverImage = null;
        if ($rootPath !== null) {
            $screenshotPath = $rootPath.'/screenshot.png';
            if (file_exists($screenshotPath) && filesize($screenshotPath) < 512 * 1024) {
                $contents = file_get_contents($screenshotPath);
                if ($contents !== false) {
                    $mime = mime_content_type($screenshotPath) ?: 'image/png';
                    $coverImage = 'data:'.$mime.';base64,'.base64_encode($contents);
                }
            }
        }

        // Screenshots: docs/screenshots/screenshot*.png (limit to 512KB each, max 5)
        $screenshots = [];
        if ($rootPath !== null) {
            $screenshotDir = $rootPath.'/docs/screenshots';
            if (is_dir($screenshotDir)) {
                $files = glob($screenshotDir.'/screenshot*.png') ?: [];
                foreach (array_slice($files, 0, 5) as $file) {
                    if (filesize($file) >= 512 * 1024) {
                        continue;
                    }
                    $contents = file_get_contents($file);
                    if ($contents !== false) {
                        $mime = mime_content_type($file) ?: 'image/png';
                        $screenshots[] = 'data:'.$mime.';base64,'.base64_encode($contents);
                    }
                }
            }
        }

        return [
            'code' => $packageCode,
            'name' => $package['name'],
            'description' => $description,
            'version' => $package['version'],
            'author' => $author,
            'type' => $type,
            'icon' => $package['icon'],
            'cover_image' => $coverImage,
            'screenshots' => $screenshots,
            'changelog' => [],
            'requirements' => $composerData['require'] ?? [],
            'updated_at' => null,
            'purchased' => false,
            'local' => true,
        ];
    }

    /**
     * Resolve the root filesystem path for a package.
     */
    private function resolvePackageRootPath(string $code, string $type): ?string
    {
        if ($type === 'theme') {
            $themeManager = resolve(ThemeManager::class);

            return $themeManager->findPath($code);
        }

        $extensionManager = resolve(ExtensionManager::class);
        $extension = $extensionManager->findExtension($code);
        if ($extension === null) {
            return null;
        }

        return dirname(dirname(File::fromClass(get_class($extension))));
    }

    #[On('install-completed')]
    public function onInstallCompleted(): void
    {
        // Reload packages to reflect new state
        $this->loadPackages();
    }

    /**
     * Normalize icon data from extension meta (camelCase keys, relative image paths)
     * into the format expected by blade partials (snake_case keys, data URIs).
     */
    private function normalizeIcon(mixed $icon, string $basePath, string $type): mixed
    {
        if (!is_array($icon)) {
            return $icon ?? $this->getDefaultIcon($type);
        }

        // Normalize camelCase -> snake_case for blade partials
        if (isset($icon['backgroundColor'])) {
            $icon['background_color'] = $icon['backgroundColor'];
            unset($icon['backgroundColor']);
        }

        // Resolve relative image path to data URI
        if (isset($icon['image']) && !isset($icon['url'])) {
            $imagePath = $basePath.'/'.$icon['image'];
            if (file_exists($imagePath)) {
                $mime = mime_content_type($imagePath) ?: 'image/svg+xml';
                $icon['url'] = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($imagePath));
            }
            unset($icon['image']);
        }

        return $icon;
    }

    /**
     * Strip 'v' prefix from semver versions (v1.2.3 -> 1.2.3), keep dev-* as-is.
     */
    private function normalizeVersion(string $version): string
    {
        if (preg_match('/^v(\d+\.\d+)/', $version)) {
            return substr($version, 1);
        }

        return $version;
    }

    /**
     * Resolve the human-readable name for a package code from current state.
     */
    private function resolvePackageName(string $code): string
    {
        $package = collect($this->installedPackages)->firstWhere('code', $code);

        return $package['name'] ?? $code;
    }

    /**
     * Show a TI admin toast notification via SweetAlert.
     */
    private function showToast(string $level, string $message): void
    {
        $escaped = addslashes($message);
        $this->js("$.ti.flashMessage({level: '{$level}', html: '{$escaped}'})");
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
