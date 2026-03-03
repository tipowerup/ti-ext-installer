<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public bool $isCancelled = false;

    public ?string $errorMessage = null;

    public array $stages = [
        ['key' => 'preparing', 'label' => 'Preparing', 'status' => 'pending'],
        ['key' => 'compatibility', 'label' => 'Compatibility Check', 'status' => 'pending'],
        ['key' => 'backup', 'label' => 'Backup', 'status' => 'pending'],
        ['key' => 'installing', 'label' => 'Installing', 'status' => 'pending'],
        ['key' => 'migrating', 'label' => 'Migrating', 'status' => 'pending'],
        ['key' => 'finalizing', 'label' => 'Finalizing', 'status' => 'pending'],
    ];

    public function mount(?string $packageCode = null, string $packageName = ''): void
    {
        if ($packageCode !== null) {
            $this->startInstall($packageCode, $packageName);
        }
    }

    public function retryInstall(): void
    {
        if ($this->packageCode !== null) {
            $this->startInstall($this->packageCode, $this->packageName);
        }
    }

    private function startInstall(string $packageCode, string $packageName): void
    {
        $this->packageCode = $packageCode;
        $this->packageName = $packageName;
        $this->batchId = (string) Str::uuid();
        $this->isCompleted = false;
        $this->hasFailed = false;
        $this->isCancelled = false;
        $this->errorMessage = null;

        // Reset stages
        $this->stages = array_map(fn ($stage): array => array_merge($stage, ['status' => 'pending']), $this->stages);

        // Determine installation method
        $hostingDetector = resolve(HostingDetector::class);
        $preferredMethod = params('tipowerup_install_method', 'auto');

        $method = $preferredMethod === 'auto' ? $hostingDetector->getRecommendedMethod() : $preferredMethod;

        // Start installation in background
        $batchId = $this->batchId;
        dispatch(function () use ($packageCode, $method, $batchId): void {
            try {
                $pipeline = resolve(InstallationPipeline::class);
                $pipeline->execute($packageCode, $method, null, $batchId);
            } catch (Throwable $e) {
                Log::error('InstallProgress: Pipeline execution failed', [
                    'package_code' => $packageCode,
                    'error' => $e->getMessage(),
                ]);
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
        $this->updateStages($progress->stage, $progress->failed_stage ?? null);

        // Check for completion, cancellation, or failure
        if ($progress->stage === 'complete') {
            $this->isCompleted = true;
        } elseif ($progress->stage === 'cancelled') {
            $this->isCancelled = true;
            $this->hasFailed = true;
            $this->errorMessage = $this->getErrorMessage('cancelled');
        } elseif ($progress->stage === 'failed') {
            $this->hasFailed = true;
            $errorCode = $progress->error_code ?? 'unknown';
            $this->errorMessage = $this->getErrorMessage($errorCode);
        }
    }

    public function closeProgress(): void
    {
        $this->dispatch('install-completed');
    }

    private function updateStages(string $currentStage, ?string $failedStage = null): void
    {
        $stageOrder = ['preparing', 'compatibility', 'backup', 'installing', 'migrating', 'finalizing'];
        $currentIndex = array_search($currentStage, $stageOrder, true);

        if ($currentIndex === false && $currentStage === 'complete') {
            // Mark all as completed
            $this->stages = array_map(fn ($stage): array => array_merge($stage, ['status' => 'completed']), $this->stages);

            return;
        }

        if ($currentIndex === false && in_array($currentStage, ['failed', 'cancelled'], true)) {
            $failedIndex = null;
            if ($failedStage !== null) {
                $stageKeys = array_column($this->stages, 'key');
                $failedIndex = array_search($failedStage, $stageKeys, true);
            }

            foreach ($this->stages as $index => &$stage) {
                if ($failedIndex !== null && $failedIndex !== false) {
                    if ($index < $failedIndex) {
                        $stage['status'] = 'completed';
                    } elseif ($index === $failedIndex) {
                        $stage['status'] = 'error';
                    } else {
                        $stage['status'] = 'pending';
                    }
                } else {
                    // Fallback: mark all as completed except last
                    $stage['status'] = $index < count($this->stages) - 1 ? 'completed' : 'error';
                }
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

    private function getErrorMessage(string $errorCode): string
    {
        $langKey = 'tipowerup.installer::default.progress_error_'.$errorCode;
        $message = lang($langKey);

        // If lang returns the key itself, it means no translation exists — use fallback
        if ($message === $langKey) {
            return lang('tipowerup.installer::default.progress_error_unknown');
        }

        return $message;
    }

    public function cancelInstall(): void
    {
        if (!$this->batchId || $this->isCompleted || $this->hasFailed || $this->isCancelled) {
            return;
        }

        // Only allow cancel during safe stages
        $safeStages = ['preparing', 'compatibility', 'backup'];
        if (!in_array($this->currentStage, $safeStages, true)) {
            return;
        }

        DB::table('tipowerup_install_progress')->updateOrInsert(
            [
                'batch_id' => $this->batchId,
                'package_code' => $this->packageCode,
            ],
            [
                'stage' => 'cancelled',
                'error_code' => 'cancelled',
                'failed_stage' => $this->currentStage,
                'updated_at' => now(),
            ]
        );
    }

    public function getCanCancelProperty(): bool
    {
        if ($this->isCompleted || $this->hasFailed || $this->isCancelled) {
            return false;
        }

        $safeStages = ['preparing', 'compatibility', 'backup'];

        return in_array($this->currentStage, $safeStages, true);
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.install-progress');
    }
}
