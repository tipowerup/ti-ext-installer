<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Services\HostingDetector;
use Tipowerup\Installer\Services\InstallationPipeline;

class InstallProgress extends Component
{
    public ?string $batchId = null;

    public ?string $packageCode = null;

    public string $packageName = '';

    public string $currentStage = 'preparing';

    public int $progressPercent = 0;

    public string $statusMessage = '';

    public bool $isCompleted = false;

    public bool $hasFailed = false;

    public ?string $errorMessage = null;

    public array $stages = [
        ['key' => 'preparing', 'label' => 'Preparing', 'status' => 'pending'],
        ['key' => 'downloading', 'label' => 'Downloading', 'status' => 'pending'],
        ['key' => 'verifying', 'label' => 'Verifying', 'status' => 'pending'],
        ['key' => 'extracting', 'label' => 'Extracting', 'status' => 'pending'],
        ['key' => 'migrating', 'label' => 'Migrating', 'status' => 'pending'],
        ['key' => 'finalizing', 'label' => 'Finalizing', 'status' => 'pending'],
    ];

    #[On('begin-install')]
    public function beginInstall(string $packageCode, string $packageName): void
    {
        $this->packageCode = $packageCode;
        $this->packageName = $packageName;
        $this->batchId = (string) Str::uuid();
        $this->isCompleted = false;
        $this->hasFailed = false;
        $this->errorMessage = null;

        // Reset stages
        $this->stages = array_map(fn ($stage): array => array_merge($stage, ['status' => 'pending']), $this->stages);

        // Determine installation method
        $hostingDetector = resolve(HostingDetector::class);
        $preferredMethod = params('tipowerup_install_method', 'auto');

        $method = $preferredMethod === 'auto' ? $hostingDetector->getRecommendedMethod() : $preferredMethod;

        // Start installation in background
        dispatch(function () use ($packageCode, $method): void {
            try {
                $pipeline = resolve(InstallationPipeline::class);
                $pipeline->execute($packageCode, $method);
            } catch (Throwable) {
                // Error will be captured in progress table
            }
        })->afterResponse();
    }

    public function pollProgress(): void
    {
        if ($this->isCompleted || $this->hasFailed || !$this->batchId) {
            return;
        }

        // Query progress from database
        $progress = DB::table('tipowerup_install_progress')
            ->where('batch_id', $this->batchId)
            ->where('package_code', $this->packageCode)
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$progress) {
            return;
        }

        $this->currentStage = $progress->stage;
        $this->progressPercent = $progress->progress_percent;
        $this->statusMessage = $progress->message ?? '';

        // Update stages based on current stage
        $this->updateStages($progress->stage);

        // Check for completion or failure
        if ($progress->stage === 'complete') {
            $this->isCompleted = true;
        } elseif ($progress->stage === 'failed') {
            $this->hasFailed = true;
            $this->errorMessage = $progress->error ?? 'Unknown error occurred';
        }
    }

    public function closeProgress(): void
    {
        $this->dispatch('install-completed');
    }

    public function retryInstall(): void
    {
        $this->beginInstall($this->packageCode, $this->packageName);
    }

    private function updateStages(string $currentStage): void
    {
        $stageOrder = ['preparing', 'downloading', 'verifying', 'extracting', 'migrating', 'finalizing'];
        $currentIndex = array_search($currentStage, $stageOrder, true);

        if ($currentIndex === false && $currentStage === 'complete') {
            // Mark all as completed
            $this->stages = array_map(fn ($stage): array => array_merge($stage, ['status' => 'completed']), $this->stages);

            return;
        }

        if ($currentIndex === false && $currentStage === 'failed') {
            // Mark current as error, previous as completed
            foreach ($this->stages as $index => &$stage) {
                $stage['status'] = $index < count($this->stages) - 1 ? 'completed' : 'error';
            }

            return;
        }

        // Mark stages before current as completed, current as in-progress, rest as pending
        foreach ($this->stages as $index => &$stage) {
            if ($stage['key'] === $currentStage) {
                $stage['status'] = 'current';
            } elseif ($currentIndex !== false && $index < $currentIndex) {
                $stage['status'] = 'completed';
            } else {
                $stage['status'] = 'pending';
            }
        }
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.install-progress');
    }
}
