<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Tipowerup\Installer\Services\CoreExtensionChecker;

class InstallerMain extends Component
{
    public bool $isOnboarded = false;

    public string $activeTab = 'installed';

    public bool $showSettings = false;

    public ?string $selectedPackage = null;

    public array $selectedPackageData = [];

    public bool $showInstallProgress = false;

    public ?string $installPackageCode = null;

    public string $installPackageName = '';

    /**
     * @var array<int, array{code: string, name: string, installed: bool, manage_url: string}>
     */
    public array $missingCoreExtensions = [];

    public function mount(): void
    {
        $this->checkOnboardingStatus();
        $this->checkCoreExtensions();
    }

    public function checkOnboardingStatus(): void
    {
        $this->isOnboarded = (bool) params('tipowerup_onboarded', false);
    }

    public function checkCoreExtensions(): void
    {
        if (!$this->isOnboarded) {
            return;
        }

        $coreExtensionChecker = resolve(CoreExtensionChecker::class);
        $this->missingCoreExtensions = $coreExtensionChecker->getMissing();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openSettings(): void
    {
        $this->showSettings = true;
    }

    public function closeSettings(): void
    {
        $this->showSettings = false;
    }

    public function viewPackageDetail(string $packageCode, array $packageData = []): void
    {
        $this->selectedPackage = $packageCode;
        $this->selectedPackageData = $packageData;
    }

    public function closePackageDetail(): void
    {
        $this->selectedPackage = null;
        $this->selectedPackageData = [];
    }

    #[On('onboarding-completed')]
    public function onOnboardingCompleted(): void
    {
        $this->checkOnboardingStatus();
        $this->checkCoreExtensions();
    }

    #[On('open-settings')]
    public function onOpenSettings(): void
    {
        $this->openSettings();
    }

    #[On('settings-closed')]
    public function onSettingsClosed(): void
    {
        $this->closeSettings();
    }

    #[On('package-detail-closed')]
    public function onPackageDetailClosed(): void
    {
        $this->closePackageDetail();
    }

    #[On('begin-install')]
    public function onBeginInstall($packageCode, $packageName = ''): void
    {
        $this->installPackageCode = $packageCode;
        $this->installPackageName = $packageName;
        $this->showInstallProgress = true;
    }

    #[On('install-completed')]
    public function onInstallCompleted(): void
    {
        $this->showInstallProgress = false;
        $this->installPackageCode = null;
        $this->installPackageName = '';
        $this->checkCoreExtensions();
    }

    #[On('view-package-detail')]
    public function onViewPackageDetail(string $packageCode, array $packageData = []): void
    {
        $this->viewPackageDetail($packageCode, $packageData);
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.installer-main');
    }
}
