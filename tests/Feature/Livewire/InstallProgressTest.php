<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tipowerup\Installer\Livewire\InstallProgress;
use Tipowerup\Installer\Services\ProgressTracker;

afterEach(function (): void {
    Mockery::close();
});

it('renders the component', function (): void {
    Livewire::test(InstallProgress::class)
        ->assertStatus(200);
});

it('shows install title by default', function (): void {
    Livewire::test(InstallProgress::class)
        ->assertSet('isUpdate', false);
});

it('shows update mode when isUpdate is true', function (): void {
    Livewire::test(InstallProgress::class, ['isUpdate' => true])
        ->assertSet('isUpdate', true);
});

it('initializes with package code when provided', function (): void {
    Livewire::test(InstallProgress::class, [
        'packageCode' => 'tipowerup/ti-ext-darkmode',
        'packageName' => 'Dark Mode',
    ])
        ->assertSet('packageCode', 'tipowerup/ti-ext-darkmode')
        ->assertSet('packageName', 'Dark Mode')
        ->assertSet('isCompleted', false)
        ->assertSet('hasFailed', false);
});

it('cancel does nothing when not in a safe stage', function (): void {
    $component = Livewire::test(InstallProgress::class, [
        'packageCode' => 'tipowerup/ti-ext-darkmode',
        'packageName' => 'Dark Mode',
    ]);

    // Simulate being in installing stage (not safe for cancel)
    $component->set('currentStage', 'installing')
        ->call('cancelInstall')
        ->assertSet('isCancelled', false);
});

it('cancel works during safe stages', function (): void {
    $tracker = Mockery::mock(ProgressTracker::class);
    $tracker->shouldReceive('cancel')->once();
    app()->instance(ProgressTracker::class, $tracker);

    $component = Livewire::test(InstallProgress::class, [
        'packageCode' => 'tipowerup/ti-ext-darkmode',
        'packageName' => 'Dark Mode',
    ]);

    $component->set('currentStage', 'preparing')
        ->call('cancelInstall');
});

it('closeProgress dispatches install-completed event', function (): void {
    Livewire::test(InstallProgress::class)
        ->call('closeProgress')
        ->assertDispatched('install-completed');
});

it('displays error message on failure', function (): void {
    $batchId = 'test-batch-123';
    $packageCode = 'tipowerup/ti-ext-darkmode';

    $tracker = Mockery::mock(ProgressTracker::class);
    $tracker->shouldReceive('get')
        ->with($batchId, $packageCode)
        ->andReturn([
            'stage' => 'failed',
            'progress_percent' => 0,
            'message' => 'Installation failed',
            'error' => 'Download error',
            'error_code' => 'download_failed',
            'failed_stage' => 'installing',
        ]);
    app()->instance(ProgressTracker::class, $tracker);

    $component = Livewire::test(InstallProgress::class);
    $component->set('batchId', $batchId)
        ->set('packageCode', $packageCode)
        ->call('pollProgress')
        ->assertSet('hasFailed', true)
        ->assertSet('errorDetail', 'Download error');
});
