<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;
use Tipowerup\Installer\Models\InstallLog;
use Tipowerup\Installer\Services\PowerUpApiClient;
use Tipowerup\Installer\Services\ReportBuilder;

class InstallLogs extends Component
{
    public int $logLimit = 100;

    public string $filterPackage = '';

    public string $filterAction = '';

    public string $filterSuccess = '';

    public ?int $expandedLogId = null;

    /** @var array<int, array<string, mixed>> */
    public array $logs = [];

    public bool $isSubmitting = false;

    public ?string $reportSuccess = null;

    public ?string $reportError = null;

    public function mount(): void
    {
        $this->loadLogs();
    }

    public function loadLogs(): void
    {
        $query = InstallLog::query()->orderBy('created_at', 'desc');

        if ($this->filterPackage !== '') {
            $query->where('package_code', 'like', '%'.$this->filterPackage.'%');
        }

        if ($this->filterAction !== '') {
            $query->where('action', $this->filterAction);
        }

        if ($this->filterSuccess !== '') {
            $query->where('success', $this->filterSuccess === '1');
        }

        $this->logs = $query->limit($this->logLimit)->get()->toArray();
    }

    public function updatedFilterPackage(): void
    {
        $this->loadLogs();
    }

    public function updatedFilterAction(): void
    {
        $this->loadLogs();
    }

    public function updatedFilterSuccess(): void
    {
        $this->loadLogs();
    }

    public function updatedLogLimit(): void
    {
        $this->logLimit = max(1, min(1000, $this->logLimit));
        $this->loadLogs();
    }

    public function toggleExpand(int $logId): void
    {
        $this->expandedLogId = $this->expandedLogId === $logId ? null : $logId;
    }

    public function clearAllLogs(): void
    {
        InstallLog::query()->delete();
        $this->logs = [];
    }

    public function submitReport(): void
    {
        $this->isSubmitting = true;
        $this->reportSuccess = null;
        $this->reportError = null;

        try {
            $reportBuilder = resolve(ReportBuilder::class);
            $reportData = $reportBuilder->build($this->logs);

            $apiClient = resolve(PowerUpApiClient::class);
            $apiClient->submitReport($reportData);

            $this->reportSuccess = lang('tipowerup.installer::default.logs_report_success');
        } catch (Throwable $e) {
            $this->reportError = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function closeModal(): void
    {
        $this->dispatch('install-logs-closed');
    }

    public function render(): View
    {
        return view('tipowerup.installer::livewire.install-logs');
    }
}
