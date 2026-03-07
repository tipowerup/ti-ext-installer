<div>
    {{-- Header --}}
    <div class="modal-header border-bottom py-3">
        <h6 class="modal-title mb-0">
            <i class="fa fa-history me-1"></i>
            {{ lang('tipowerup.installer::default.logs_title') }}
        </h6>
        <button wire:click="closeModal" type="button" class="btn-close"></button>
    </div>

    {{-- Filters --}}
    <div class="modal-body border-bottom py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label tipowerup-installer__text-xxs mb-1">
                    {{ lang('tipowerup.installer::default.logs_filter_package') }}
                </label>
                <input type="text" wire:model.live.debounce.300ms="filterPackage"
                       class="form-control form-control-sm"
                       placeholder="{{ lang('tipowerup.installer::default.logs_filter_package_placeholder') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label tipowerup-installer__text-xxs mb-1">
                    {{ lang('tipowerup.installer::default.logs_filter_action') }}
                </label>
                <select wire:model.live="filterAction" class="form-select form-select-sm">
                    <option value="">{{ lang('tipowerup.installer::default.logs_filter_all') }}</option>
                    <option value="install">Install</option>
                    <option value="update">Update</option>
                    <option value="uninstall">Uninstall</option>
                    <option value="restore">Restore</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label tipowerup-installer__text-xxs mb-1">
                    {{ lang('tipowerup.installer::default.logs_filter_status') }}
                </label>
                <select wire:model.live="filterSuccess" class="form-select form-select-sm">
                    <option value="">{{ lang('tipowerup.installer::default.logs_filter_all') }}</option>
                    <option value="1">{{ lang('tipowerup.installer::default.logs_status_success') }}</option>
                    <option value="0">{{ lang('tipowerup.installer::default.logs_status_failed') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label tipowerup-installer__text-xxs mb-1">
                    {{ lang('tipowerup.installer::default.logs_limit') }}
                </label>
                <input type="number" wire:model.live.debounce.500ms="logLimit"
                       class="form-control form-control-sm" min="1" max="1000">
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="modal-body p-0" style="max-height: 400px; overflow-y: auto;">
        @if(count($logs) > 0)
            <table class="table table-sm table-hover mb-0 tipowerup-installer__logs-table">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_col_package') }}</th>
                        <th class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_col_action') }}</th>
                        <th class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_col_method') }}</th>
                        <th class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_col_status') }}</th>
                        <th class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_col_version') }}</th>
                        <th class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_col_duration') }}</th>
                        <th class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_col_date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr wire:key="log-{{ $log['id'] }}" wire:click="toggleExpand({{ $log['id'] }})"
                            class="cursor-pointer {{ !$log['success'] ? 'table-danger-subtle' : '' }}"
                            role="button">
                            <td class="tipowerup-installer__text-xxs">
                                @if(!$log['success'])
                                    <i class="fa fa-chevron-{{ $expandedLogId === $log['id'] ? 'down' : 'right' }} me-1 text-muted" style="font-size: 0.6rem;"></i>
                                @endif
                                {{ $log['package_code'] }}
                            </td>
                            <td class="tipowerup-installer__text-xxs">
                                <span class="badge bg-secondary bg-opacity-25 text-dark">{{ ucfirst($log['action']) }}</span>
                            </td>
                            <td class="tipowerup-installer__text-xxs">{{ $log['method'] }}</td>
                            <td class="tipowerup-installer__text-xxs">
                                @if($log['success'])
                                    <span class="badge bg-success">{{ lang('tipowerup.installer::default.logs_status_success') }}</span>
                                @else
                                    <span class="badge bg-danger">{{ lang('tipowerup.installer::default.logs_status_failed') }}</span>
                                @endif
                            </td>
                            <td class="tipowerup-installer__text-xxs text-nowrap">
                                @if($log['from_version'] && $log['to_version'])
                                    {{ $log['from_version'] }} &rarr; {{ $log['to_version'] }}
                                @elseif($log['to_version'])
                                    {{ $log['to_version'] }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="tipowerup-installer__text-xxs">
                                {{ $log['duration_seconds'] ? $log['duration_seconds'].'s' : '—' }}
                            </td>
                            <td class="tipowerup-installer__text-xxs text-nowrap">
                                {{ $log['created_at'] ? \Carbon\Carbon::parse($log['created_at'])->format('M d, H:i') : '—' }}
                            </td>
                        </tr>

                        {{-- Expanded error detail --}}
                        @if($expandedLogId === $log['id'] && !$log['success'])
                            <tr wire:key="log-detail-{{ $log['id'] }}">
                                <td colspan="7" class="bg-light px-3 py-2">
                                    @if($log['error_message'])
                                        <div class="mb-2">
                                            <strong class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_error_message') }}:</strong>
                                            <div class="tipowerup-installer__text-xxs text-danger">{{ $log['error_message'] }}</div>
                                        </div>
                                    @endif
                                    @if($log['stack_trace'])
                                        <div>
                                            <strong class="tipowerup-installer__text-xxs">{{ lang('tipowerup.installer::default.logs_stack_trace') }}:</strong>
                                            <pre class="tipowerup-installer__text-xxs bg-dark text-light p-2 rounded mt-1 mb-0" style="max-height: 200px; overflow-y: auto; font-size: 0.65rem;">{{ $log['stack_trace'] }}</pre>
                                        </div>
                                    @endif
                                    @if($log['php_version'] || $log['ti_version'] || $log['memory_limit_mb'])
                                        <div class="mt-2 d-flex gap-3">
                                            @if($log['php_version'])
                                                <span class="tipowerup-installer__text-xxs text-muted">PHP: {{ $log['php_version'] }}</span>
                                            @endif
                                            @if($log['ti_version'])
                                                <span class="tipowerup-installer__text-xxs text-muted">TI: {{ $log['ti_version'] }}</span>
                                            @endif
                                            @if($log['memory_limit_mb'])
                                                <span class="tipowerup-installer__text-xxs text-muted">Memory: {{ $log['memory_limit_mb'] }}MB</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center text-muted py-4">
                <i class="fa fa-inbox fa-2x mb-2 d-block"></i>
                <p class="tipowerup-installer__text-xxs mb-0">{{ lang('tipowerup.installer::default.logs_empty') }}</p>
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="modal-footer py-2">
        <div class="d-flex align-items-center gap-2 w-100">
            {{-- Clear logs --}}
            <button wire:click="clearAllLogs"
                    wire:confirm="{{ lang('tipowerup.installer::default.logs_confirm_clear') }}"
                    class="btn btn-outline-danger btn-sm"
                    {{ count($logs) === 0 ? 'disabled' : '' }}>
                <i class="fa fa-trash me-1"></i>
                {{ lang('tipowerup.installer::default.logs_clear_all') }}
            </button>

            <div class="vr"></div>

            {{-- Report section --}}
            <div class="d-flex align-items-center gap-2">
                <button wire:click="submitReport"
                        wire:loading.attr="disabled"
                        class="btn btn-outline-primary btn-sm text-nowrap"
                        {{ count($logs) === 0 ? 'disabled' : '' }}>
                    <span wire:loading.remove wire:target="submitReport">
                        <i class="fa fa-paper-plane me-1"></i>
                        {{ lang('tipowerup.installer::default.logs_report_submit') }}
                    </span>
                    <span wire:loading wire:target="submitReport">
                        <i class="fa fa-spinner fa-spin me-1"></i>
                        {{ lang('tipowerup.installer::default.logs_report_sending') }}
                    </span>
                </button>
            </div>

            @if($reportSuccess)
                <span class="tipowerup-installer__text-xxs text-success">
                    <i class="fa fa-check me-1"></i>{{ $reportSuccess }}
                </span>
            @endif

            @if($reportError)
                <span class="tipowerup-installer__text-xxs text-danger">
                    <i class="fa fa-times me-1"></i>{{ $reportError }}
                </span>
            @endif
        </div>
        <div class="w-100 mt-1">
            <small class="tipowerup-installer__text-xxs text-muted">
                <i class="fa fa-shield-alt me-1"></i>
                {{ lang('tipowerup.installer::default.logs_report_privacy') }}
            </small>
        </div>
    </div>
</div>
