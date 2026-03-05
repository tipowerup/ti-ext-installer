<div wire:poll.1s="pollProgress" class="tipowerup-installer__progress-modal">
    <div class="mb-4">
        <h5 class="mb-1">
            @if($isCompleted)
                <i class="fa fa-check-circle text-success me-2"></i>
                {{ lang('tipowerup.installer::default.progress_stage_completed') }}
            @elseif($isCancelled)
                <i class="fa fa-ban text-warning me-2"></i>
                {{ lang('tipowerup.installer::default.progress_stage_cancelled') }}
            @elseif($hasFailed)
                <i class="fa fa-times-circle text-danger me-2"></i>
                {{ lang('tipowerup.installer::default.progress_stage_failed') }}
            @else
                <i class="fa fa-spinner fa-spin text-primary me-2"></i>
                {{ lang('tipowerup.installer::default.progress_title', ['package' => $packageName]) }}
            @endif
        </h5>
    </div>

    @if(!$isCompleted && !$hasFailed)
        <div class="tipowerup-installer__progress-bar mb-4">
            <div class="tipowerup-installer__progress-fill" style="width: {{ $progressPercent }}%"></div>
        </div>
        <p class="text-center text-muted small mb-4">{{ $progressPercent }}%</p>
    @endif

    <ul class="tipowerup-installer__progress-steps">
        @foreach($stages as $stage)
            <li class="tipowerup-installer__progress-step tipowerup-installer__progress-step--{{ $stage['status'] }}"
                wire:key="stage-{{ $stage['key'] }}">
                <div class="tipowerup-installer__progress-step-icon">
                    @if($stage['status'] === 'completed')
                        <i class="fa fa-check"></i>
                    @elseif($stage['status'] === 'current')
                        <i class="fa fa-spinner fa-spin"></i>
                    @elseif($stage['status'] === 'error')
                        <i class="fa fa-times"></i>
                    @else
                        <i class="fa fa-circle tipowerup-installer__progress-dot"></i>
                    @endif
                </div>
                <div class="flex-grow-1">
                    <strong>{{ $stage['label'] }}</strong>
                    @if($stage['status'] === 'completed')
                        <span class="text-success ms-2 small">done</span>
                    @elseif($stage['status'] === 'current')
                        <span class="text-primary ms-2 small">in progress</span>
                    @elseif($stage['status'] === 'error')
                        <span class="text-danger ms-2 small">failed</span>
                    @else
                        <span class="text-muted ms-2 small">pending</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @if($statusMessage && !$isCompleted && !$hasFailed && !$isCancelled)
        <div class="alert alert-info mt-4 mb-0" role="alert">
            <i class="fa fa-info-circle me-2"></i>
            {{ $statusMessage }}
        </div>
    @endif

    @if($hasFailed && $errorMessage)
        <div class="alert alert-danger mt-4" role="alert">
            <i class="fa fa-exclamation-triangle me-2"></i>
            <strong>{{ lang('tipowerup.installer::default.progress_stage_failed') }}</strong>
            <p class="mb-0 mt-2 small">{{ $errorMessage }}</p>
            @if($errorDetail)
                <p class="mb-0 mt-1 small text-muted font-monospace">{{ $errorDetail }}</p>
            @endif
            @if(!$isCancelled)
                <p class="mb-0 mt-2 small text-muted">
                    {{ lang('tipowerup.installer::default.progress_error_help_logs') }}
                </p>
            @endif
        </div>
    @endif

    @if($isCompleted)
        <div class="alert alert-success mt-4" role="alert">
            <i class="fa fa-check-circle me-2"></i>
            <strong>{{ lang('tipowerup.installer::default.success_installed', ['package' => $packageName]) }}</strong>
        </div>
    @endif

    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
        @if($isCompleted)
            <button wire:click="closeProgress" wire:loading.attr="disabled" type="button" class="btn btn-primary">
                <span wire:loading wire:target="closeProgress"><i class="fa fa-spinner fa-spin me-1"></i></span>
                {{ lang('tipowerup.installer::default.progress_close') }}
            </button>
        @elseif($hasFailed)
            <button wire:click="closeProgress" wire:loading.attr="disabled" type="button" class="btn btn-secondary">
                <span wire:loading wire:target="closeProgress"><i class="fa fa-spinner fa-spin me-1"></i></span>
                {{ lang('tipowerup.installer::default.progress_close') }}
            </button>
            @if(!$isCancelled)
                <button wire:click="retryInstall" type="button" class="btn btn-primary">
                    <i class="fa fa-redo me-2"></i>
                    {{ lang('tipowerup.installer::default.progress_retry') }}
                </button>
            @endif
        @else
            <button wire:click="cancelInstall"
                wire:loading.attr="disabled"
                type="button"
                class="btn btn-outline-danger"
                @if(!$this->canCancel) disabled @endif
            >
                {{ lang('tipowerup.installer::default.progress_cancel') }}
            </button>
        @endif
    </div>
</div>
