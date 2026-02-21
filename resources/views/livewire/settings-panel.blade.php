<div class="tipowerup-installer__settings-body">
    {{-- Success/Error Messages --}}
    @if($successMessage)
        <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
            <i class="fa fa-check-circle me-3" style="font-size: 20px;"></i>
            <div>{{ $successMessage }}</div>
        </div>
    @endif

    @if($errorMessage)
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="fa fa-exclamation-circle me-3" style="font-size: 20px;"></i>
            <div>{{ $errorMessage }}</div>
        </div>
    @endif

    {{-- PowerUp Key Section --}}
    <div class="mb-4">
        <label class="form-label">{{ lang('tipowerup.installer::default.settings_api_key') }}</label>

        @if(!$showApiKeyInput)
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control" value="{{ $apiKey }}" disabled>
                <button wire:click="toggleApiKeyInput" type="button" class="btn btn-outline-primary">
                    <i class="fa fa-edit"></i>
                </button>
            </div>
            <small class="form-text text-muted">
                {{ lang('tipowerup.installer::default.onboarding_api_key_help') }}
            </small>
        @else
            <input wire:model.defer="newApiKey" type="text" class="form-control mb-2"
                   placeholder="{{ lang('tipowerup.installer::default.settings_api_key_placeholder') }}">
            <div class="d-flex gap-2">
                <button wire:click="changeApiKey" type="button" class="btn btn-primary btn-sm"
                        wire:loading.attr="disabled" wire:target="changeApiKey">
                    <span wire:loading.remove wire:target="changeApiKey">
                        <i class="fa fa-check me-1"></i>
                        {{ lang('tipowerup.installer::default.action_verify_key') }}
                    </span>
                    <span wire:loading wire:target="changeApiKey">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Verifying...
                    </span>
                </button>
                <button wire:click="toggleApiKeyInput" type="button" class="btn btn-secondary btn-sm">
                    {{ lang('tipowerup.installer::default.progress_cancel') }}
                </button>
            </div>
        @endif
    </div>

    {{-- Installation Method Section --}}
    <div class="mb-4">
        <label class="form-label">{{ lang('tipowerup.installer::default.settings_install_method') }}</label>
        <select wire:model.defer="installMethod" class="form-select">
            <option value="auto">{{ lang('tipowerup.installer::default.settings_method_auto') }}</option>
            <option value="direct">{{ lang('tipowerup.installer::default.settings_method_direct') }}</option>
            <option value="composer">{{ lang('tipowerup.installer::default.settings_method_composer') }}</option>
        </select>
        <small class="form-text text-muted">
            Based on your environment,
            @if($environmentInfo['recommended_method'] === 'composer')
                <strong>Composer installation</strong> is recommended.
            @else
                <strong>Direct extraction</strong> is recommended.
            @endif
        </small>
    </div>

    {{-- Save Button --}}
    <div class="mb-4">
        <button wire:click="saveSettings" type="button" class="btn btn-primary w-100"
                wire:loading.attr="disabled" wire:target="saveSettings">
            <span wire:loading.remove wire:target="saveSettings">
                <i class="fa fa-save me-2"></i>
                {{ lang('tipowerup.installer::default.settings_save') }}
            </span>
            <span wire:loading wire:target="saveSettings">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Saving...
            </span>
        </button>
    </div>

    {{-- Environment Information Section --}}
    <div class="border-top pt-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="mb-0">{{ lang('tipowerup.installer::default.settings_environment') }}</h6>
            <button wire:click="refreshEnvironmentInfo" type="button"
                    class="btn btn-outline-secondary btn-sm"
                    wire:loading.attr="disabled" wire:target="refreshEnvironmentInfo">
                <span wire:loading.remove wire:target="refreshEnvironmentInfo">
                    <i class="fa fa-sync-alt"></i>
                </span>
                <span wire:loading wire:target="refreshEnvironmentInfo">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </span>
            </button>
        </div>
        <ul class="list-unstyled tipowerup-installer__health-list">
            <li class="tipowerup-installer__health-item">
                <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--success">
                    <i class="fa fa-info"></i>
                </div>
                <div class="tipowerup-installer__health-content">
                    <div class="tipowerup-installer__health-title">PHP Version</div>
                    <p class="tipowerup-installer__health-description mb-0">{{ PHP_VERSION }}</p>
                </div>
            </li>

            <li class="tipowerup-installer__health-item">
                <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--{{ $environmentInfo['can_exec'] ? 'success' : 'error' }}">
                    <i class="fa fa-{{ $environmentInfo['can_exec'] ? 'check' : 'times' }}"></i>
                </div>
                <div class="tipowerup-installer__health-content">
                    <div class="tipowerup-installer__health-title">exec() Function</div>
                    <p class="tipowerup-installer__health-description mb-0">
                        {{ $environmentInfo['can_exec'] ? 'Available' : 'Disabled' }}
                    </p>
                </div>
            </li>

            <li class="tipowerup-installer__health-item">
                <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--success">
                    <i class="fa fa-memory"></i>
                </div>
                <div class="tipowerup-installer__health-content">
                    <div class="tipowerup-installer__health-title">Memory Limit</div>
                    <p class="tipowerup-installer__health-description mb-0">
                        @if($environmentInfo['memory_limit_mb'] === -1)
                            Unlimited
                        @else
                            {{ $environmentInfo['memory_limit_mb'] }} MB
                        @endif
                    </p>
                </div>
            </li>

            <li class="tipowerup-installer__health-item">
                @if(($environmentInfo['composer_source'] ?? null) === 'system')
                    <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--success">
                        <i class="fa fa-check"></i>
                    </div>
                    <div class="tipowerup-installer__health-content">
                        <div class="tipowerup-installer__health-title">Composer</div>
                        <p class="tipowerup-installer__health-description mb-0">
                            {{ lang('tipowerup.installer::default.composer_source_system') }}
                        </p>
                    </div>
                @elseif(($environmentInfo['composer_source'] ?? null) === 'downloaded')
                    <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--success">
                        <i class="fa fa-check"></i>
                    </div>
                    <div class="tipowerup-installer__health-content">
                        <div class="tipowerup-installer__health-title">Composer</div>
                        <p class="tipowerup-installer__health-description mb-0">
                            {{ lang('tipowerup.installer::default.composer_source_downloaded') }}
                        </p>
                    </div>
                @else
                    <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--warning">
                        <i class="fa fa-exclamation-triangle"></i>
                    </div>
                    <div class="tipowerup-installer__health-content">
                        <div class="tipowerup-installer__health-title">Composer</div>
                        <div class="d-flex align-items-center gap-2">
                            <p class="tipowerup-installer__health-description mb-0">
                                {{ lang('tipowerup.installer::default.composer_source_none') }}
                            </p>
                            @if($environmentInfo['can_proc_open'] ?? false)
                                <button wire:click="downloadComposerPhar" type="button"
                                        class="btn btn-outline-primary btn-sm"
                                        wire:loading.attr="disabled" wire:target="downloadComposerPhar">
                                    <span wire:loading.remove wire:target="downloadComposerPhar">
                                        <i class="fa fa-download me-1"></i>
                                    </span>
                                    <span wire:loading wire:target="downloadComposerPhar">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </li>

            <li class="tipowerup-installer__health-item">
                <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--{{ $environmentInfo['storage_writable'] ? 'success' : 'error' }}">
                    <i class="fa fa-{{ $environmentInfo['storage_writable'] ? 'check' : 'times' }}"></i>
                </div>
                <div class="tipowerup-installer__health-content">
                    <div class="tipowerup-installer__health-title">Storage Directory</div>
                    <p class="tipowerup-installer__health-description mb-0">
                        {{ $environmentInfo['storage_writable'] ? 'Writable' : 'Not writable' }}
                    </p>
                </div>
            </li>

            <li class="tipowerup-installer__health-item">
                <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--{{ $environmentInfo['has_zip_archive'] ? 'success' : 'error' }}">
                    <i class="fa fa-{{ $environmentInfo['has_zip_archive'] ? 'check' : 'times' }}"></i>
                </div>
                <div class="tipowerup-installer__health-content">
                    <div class="tipowerup-installer__health-title">ZipArchive Extension</div>
                    <p class="tipowerup-installer__health-description mb-0">
                        {{ $environmentInfo['has_zip_archive'] ? 'Available' : 'Not available' }}
                    </p>
                </div>
            </li>

            <li class="tipowerup-installer__health-item">
                <div class="tipowerup-installer__health-icon tipowerup-installer__health-icon--success">
                    <i class="fa fa-thumbs-up"></i>
                </div>
                <div class="tipowerup-installer__health-content">
                    <div class="tipowerup-installer__health-title">Recommended Method</div>
                    <p class="tipowerup-installer__health-description mb-0">
                        @if($environmentInfo['recommended_method'] === 'composer')
                            <strong class="text-primary">Composer Installation</strong>
                        @else
                            <strong class="text-primary">Direct Extraction</strong>
                        @endif
                    </p>
                </div>
            </li>
        </ul>
    </div>
</div>
