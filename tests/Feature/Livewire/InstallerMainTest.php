<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tipowerup\Installer\Livewire\InstallerMain;
use Tipowerup\Installer\Services\CoreExtensionChecker;
use Tipowerup\Installer\Services\HealthChecker;
use Tipowerup\Installer\Services\HostingDetector;

beforeEach(function (): void {
    $this->mock(CoreExtensionChecker::class, function ($mock): void {
        $mock->shouldReceive('getMissing')->andReturn([]);
    });

    $this->mock(HealthChecker::class, function ($mock): void {
        $mock->shouldReceive('runAllChecks')->andReturn([]);
        $mock->shouldReceive('hasCriticalFailures')->andReturn(false);
        $mock->shouldReceive('getCommunityLinks')->andReturn([]);
    });

    $this->mock(HostingDetector::class, function ($mock): void {
        $mock->shouldReceive('analyze')->andReturn([
            'can_exec' => true,
            'can_proc_open' => true,
            'memory_limit_mb' => 256,
            'has_zip_archive' => true,
            'has_curl' => true,
            'storage_writable' => true,
            'composer_available' => true,
            'composer_source' => 'system',
        ]);
        $mock->shouldReceive('canProcOpen')->andReturn(true);
        $mock->shouldReceive('getMemoryLimitMB')->andReturn(256);
        $mock->shouldReceive('getComposerSource')->andReturn('system');
        $mock->shouldReceive('getUnwritableComposerPaths')->andReturn([]);
        $mock->shouldReceive('clearCache')->andReturn(null);
    });
});

it('renders the component', function (): void {
    Livewire::test(InstallerMain::class)
        ->assertStatus(200);
});

it('defaults to installed tab', function (): void {
    Livewire::test(InstallerMain::class)
        ->assertSet('activeTab', 'installed')
        ->assertSet('showSettings', false)
        ->assertSet('selectedPackage', null);
});

it('switchTab changes active tab', function (): void {
    Livewire::test(InstallerMain::class)
        ->call('switchTab', 'marketplace')
        ->assertSet('activeTab', 'marketplace');
});

it('openSettings and closeSettings toggle state', function (): void {
    Livewire::test(InstallerMain::class)
        ->call('openSettings')
        ->assertSet('showSettings', true)
        ->call('closeSettings')
        ->assertSet('showSettings', false);
});

it('viewPackageDetail sets selected package', function (): void {
    Livewire::test(InstallerMain::class)
        ->call('viewPackageDetail', 'tipowerup/ti-ext-darkmode', ['name' => 'Dark Mode'])
        ->assertSet('selectedPackage', 'tipowerup/ti-ext-darkmode')
        ->assertSet('selectedPackageData', ['name' => 'Dark Mode']);
});

it('closePackageDetail clears selected package', function (): void {
    Livewire::test(InstallerMain::class)
        ->call('viewPackageDetail', 'tipowerup/ti-ext-darkmode')
        ->call('closePackageDetail')
        ->assertSet('selectedPackage', null)
        ->assertSet('selectedPackageData', []);
});

it('onBeginInstall sets install state', function (): void {
    Livewire::test(InstallerMain::class)
        ->dispatch('begin-install', packageCode: 'tipowerup/ti-ext-test', packageName: 'Test Package')
        ->assertSet('installPackageCode', 'tipowerup/ti-ext-test')
        ->assertSet('installPackageName', 'Test Package')
        ->assertSet('installIsUpdate', false)
        ->assertSet('showInstallProgress', true);
});

it('onBeginUpdate sets update state', function (): void {
    Livewire::test(InstallerMain::class)
        ->dispatch('begin-update', packageCode: 'tipowerup/ti-ext-test')
        ->assertSet('installPackageCode', 'tipowerup/ti-ext-test')
        ->assertSet('installIsUpdate', true)
        ->assertSet('showInstallProgress', true);
});

it('onInstallCompleted resets install state', function (): void {
    Livewire::test(InstallerMain::class)
        ->dispatch('begin-install', packageCode: 'tipowerup/ti-ext-test', packageName: 'Test')
        ->dispatch('install-completed')
        ->assertSet('showInstallProgress', false)
        ->assertSet('installPackageCode', null)
        ->assertSet('installIsUpdate', false);
});

it('onOpenSettings opens settings via event', function (): void {
    Livewire::test(InstallerMain::class)
        ->dispatch('open-settings')
        ->assertSet('showSettings', true);
});

it('onSettingsClosed closes settings via event', function (): void {
    Livewire::test(InstallerMain::class)
        ->call('openSettings')
        ->dispatch('settings-closed')
        ->assertSet('showSettings', false);
});

it('onPackageDetailClosed clears package detail via event', function (): void {
    Livewire::test(InstallerMain::class)
        ->call('viewPackageDetail', 'tipowerup/ti-ext-test')
        ->dispatch('package-detail-closed')
        ->assertSet('selectedPackage', null);
});

it('openInstallLogs and closeInstallLogs toggle state', function (): void {
    Livewire::test(InstallerMain::class)
        ->call('openInstallLogs')
        ->assertSet('showInstallLogs', true)
        ->call('closeInstallLogs')
        ->assertSet('showInstallLogs', false);
});

it('onInstallLogsClosed closes logs via event', function (): void {
    Livewire::test(InstallerMain::class)
        ->call('openInstallLogs')
        ->dispatch('install-logs-closed')
        ->assertSet('showInstallLogs', false);
});

it('onViewPackageDetail sets package detail via event', function (): void {
    Livewire::test(InstallerMain::class)
        ->dispatch('view-package-detail', packageCode: 'tipowerup/ti-ext-darkmode', packageData: ['name' => 'Dark Mode'])
        ->assertSet('selectedPackage', 'tipowerup/ti-ext-darkmode');
});
