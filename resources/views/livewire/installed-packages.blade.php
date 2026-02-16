<div class="tipowerup-installer__installed-packages">
    {{-- Error Alert --}}
    @if($errorMessage)
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="fa fa-exclamation-triangle me-3"></i>
            <div class="flex-grow-1">
                <strong>{{ lang('tipowerup.installer::default.error_connection_failed') }}</strong>
                <p class="mb-0 mt-1 small">{{ $errorMessage }}</p>
            </div>
            <button wire:click="$set('errorMessage', null)" type="button" class="btn-close"></button>
        </div>
    @endif

    {{-- Success Message --}}
    @if(session()->has('success'))
        <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
            <i class="fa fa-check-circle me-3"></i>
            <div class="flex-grow-1">{{ session('success') }}</div>
            <button onclick="this.parentElement.remove()" type="button" class="btn-close"></button>
        </div>
    @endif

    {{-- Header with Actions --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-1">{{ lang('tipowerup.installer::default.installed_title') }}</h5>
            <p class="text-muted mb-0 small">
                @if(count($packages) > 0)
                    {{ count($packages) }} {{ count($packages) === 1 ? 'package' : 'packages' }} installed
                @endif
            </p>
        </div>

        <div class="d-flex gap-2 align-items-center">
            {{-- Check Updates Button --}}
            @if(count($packages) > 0)
                <button wire:click="checkUpdates"
                        wire:loading.attr="disabled"
                        wire:target="checkUpdates"
                        class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                    <span wire:loading.remove wire:target="checkUpdates">
                        <i class="fa fa-sync-alt me-2"></i>
                        {{ lang('tipowerup.installer::default.action_check_updates') }}
                    </span>
                    <span wire:loading wire:target="checkUpdates">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        {{ lang('tipowerup.installer::default.marketplace_detecting_purchase') }}
                    </span>
                </button>

                {{-- View Mode Toggle --}}
                <button wire:click="toggleViewMode"
                        class="btn btn-outline-secondary btn-sm"
                        title="{{ $viewMode === 'grid' ? lang('tipowerup.installer::default.installed_view_list') : lang('tipowerup.installer::default.installed_view_grid') }}">
                    <i class="fa {{ $viewMode === 'grid' ? 'fa-list' : 'fa-th' }}"></i>
                </button>
            @endif
        </div>
    </div>

    {{-- Loading State --}}
    @if($isLoading)
        <div class="tipowerup-installer__packages-grid">
            @for($i = 0; $i < 3; $i++)
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="tipowerup-installer__skeleton" style="width: 56px; height: 56px; border-radius: 0.75rem;"></div>
                            <div class="ms-3 flex-grow-1">
                                <div class="tipowerup-installer__skeleton mb-2" style="width: 60%; height: 20px;"></div>
                                <div class="tipowerup-installer__skeleton" style="width: 40%; height: 16px;"></div>
                            </div>
                        </div>
                        <div class="tipowerup-installer__skeleton mb-2" style="width: 100%; height: 16px;"></div>
                        <div class="tipowerup-installer__skeleton mb-3" style="width: 80%; height: 16px;"></div>
                        <div class="d-flex gap-2">
                            <div class="tipowerup-installer__skeleton" style="width: 80px; height: 32px;"></div>
                            <div class="tipowerup-installer__skeleton" style="width: 80px; height: 32px;"></div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>

    {{-- Empty State --}}
    @elseif(count($packages) === 0)
        <div class="tipowerup-installer__empty-state">
            <div class="tipowerup-installer__empty-icon">
                <i class="fa fa-box-open"></i>
            </div>
            <h6 class="tipowerup-installer__empty-title">
                {{ lang('tipowerup.installer::default.installed_empty') }}
            </h6>
            <p class="tipowerup-installer__empty-message">
                {{ lang('tipowerup.installer::default.installed_empty_message') }}
            </p>
            <button wire:click="$parent.switchTab('marketplace')" class="btn btn-primary">
                <i class="fa fa-shopping-bag me-2"></i>
                {{ lang('tipowerup.installer::default.installed_browse_marketplace') }}
            </button>
        </div>

    {{-- Packages List - Grid View --}}
    @elseif($viewMode === 'grid')
        <div class="tipowerup-installer__packages-grid">
            @foreach($packages as $package)
                <div wire:key="package-{{ $package['code'] }}" class="tipowerup-installer__package-card">
                    {{-- Package Header --}}
                    <div class="tipowerup-installer__package-header">
                        <div class="tipowerup-installer__package-icon">
                            <i class="fa {{ $package['icon'] }}"></i>
                        </div>
                        <div class="tipowerup-installer__package-info">
                            <h6 class="tipowerup-installer__package-name mb-0">
                                {{ $package['name'] }}
                            </h6>
                            <div class="tipowerup-installer__package-version text-muted">
                                {{ lang('tipowerup.installer::default.installed_version', ['version' => $package['version']]) }}
                            </div>
                        </div>
                    </div>

                    {{-- Package Meta (Badges) --}}
                    <div class="tipowerup-installer__package-meta">
                        <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] }}">
                            {{ ucfirst($package['type']) }}
                        </span>
                        <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['is_active'] ? 'active' : 'disabled' }}">
                            {{ lang('tipowerup.installer::default.installed_' . ($package['is_active'] ? 'active' : 'disabled')) }}
                        </span>
                    </div>

                    {{-- Description --}}
                    @if($package['description'])
                        <p class="tipowerup-installer__package-description">
                            {{ \Illuminate\Support\Str::limit($package['description'], 120) }}
                        </p>
                    @endif

                    {{-- Update Available Badge --}}
                    @if($package['has_update'])
                        <div class="alert alert-info d-flex align-items-center p-2 mb-3" style="font-size: 0.875rem;">
                            <i class="fa fa-arrow-circle-up me-2"></i>
                            <strong>
                                {{ lang('tipowerup.installer::default.installed_update_available', ['version' => $package['latest_version']]) }}
                            </strong>
                        </div>
                    @endif

                    {{-- License Info --}}
                    <div class="mb-3">
                        <small class="text-muted d-block">
                            <i class="fa fa-calendar me-1"></i>
                            {{ lang('tipowerup.installer::default.installed_installed', ['date' => $package['installed_at']]) }}
                        </small>
                        @if($package['expires_at'])
                            <small class="text-muted d-block">
                                <i class="fa fa-clock me-1"></i>
                                {{ lang('tipowerup.installer::default.installed_expires', ['date' => $package['expires_at']]) }}
                            </small>
                        @endif
                    </div>

                    {{-- Package Footer (Actions) --}}
                    <div class="tipowerup-installer__package-footer">
                        <div class="d-flex gap-2 flex-wrap">
                            @if($package['has_update'])
                                <button wire:click="updatePackage('{{ $package['code'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="updatePackage('{{ $package['code'] }}')"
                                        class="btn btn-success btn-sm flex-grow-1">
                                    <span wire:loading.remove wire:target="updatePackage('{{ $package['code'] }}')">
                                        <i class="fa fa-arrow-up me-1"></i>
                                        {{ lang('tipowerup.installer::default.action_update') }}
                                    </span>
                                    <span wire:loading wire:target="updatePackage('{{ $package['code'] }}')">
                                        <span class="tipowerup-installer__spinner"></span>
                                    </span>
                                </button>
                            @endif

                            <button wire:click="viewDetail('{{ $package['code'] }}')"
                                    class="btn btn-outline-secondary btn-sm {{ $package['has_update'] ? '' : 'flex-grow-1' }}">
                                <i class="fa fa-info-circle me-1"></i>
                                {{ lang('tipowerup.installer::default.detail_description') }}
                            </button>

                            <button wire:click="uninstallPackage('{{ $package['code'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="uninstallPackage('{{ $package['code'] }}')"
                                    wire:confirm="{{ lang('tipowerup.installer::default.confirm_uninstall', ['package' => $package['name']]) }}"
                                    class="btn btn-outline-danger btn-sm">
                                <span wire:loading.remove wire:target="uninstallPackage('{{ $package['code'] }}')">
                                    <i class="fa fa-trash me-1"></i>
                                    {{ lang('tipowerup.installer::default.action_uninstall') }}
                                </span>
                                <span wire:loading wire:target="uninstallPackage('{{ $package['code'] }}')">
                                    <span class="tipowerup-installer__spinner"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    {{-- Packages List - List View --}}
    @else
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>{{ lang('tipowerup.installer::default.detail_version') }}</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Installed</th>
                        <th style="width: 280px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($packages as $package)
                        <tr wire:key="package-list-{{ $package['code'] }}">
                            {{-- Icon & Name --}}
                            <td>
                                <div class="d-flex align-items-center">
                                    <div style="width: 40px; height: 40px; border-radius: 0.5rem; background: linear-gradient(135deg, var(--ti-primary), var(--ti-primary-hover)); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.25rem;">
                                        <i class="fa {{ $package['icon'] }}"></i>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong>{{ $package['name'] }}</strong>
                                    <div class="text-muted small">
                                        {{ lang('tipowerup.installer::default.installed_version', ['version' => $package['version']]) }}
                                        @if($package['has_update'])
                                            <span class="text-info ms-1">
                                                <i class="fa fa-arrow-circle-up"></i>
                                                {{ lang('tipowerup.installer::default.installed_update_available', ['version' => $package['latest_version']]) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] }}">
                                    {{ ucfirst($package['type']) }}
                                </span>
                            </td>
                            <td>
                                <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['is_active'] ? 'active' : 'disabled' }}">
                                    {{ lang('tipowerup.installer::default.installed_' . ($package['is_active'] ? 'active' : 'disabled')) }}
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">{{ $package['installed_at'] }}</small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    @if($package['has_update'])
                                        <button wire:click="updatePackage('{{ $package['code'] }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="updatePackage('{{ $package['code'] }}')"
                                                class="btn btn-success">
                                            <span wire:loading.remove wire:target="updatePackage('{{ $package['code'] }}')">
                                                <i class="fa fa-arrow-up"></i>
                                            </span>
                                            <span wire:loading wire:target="updatePackage('{{ $package['code'] }}')">
                                                <span class="tipowerup-installer__spinner" style="width: 14px; height: 14px; border-width: 2px;"></span>
                                            </span>
                                        </button>
                                    @endif

                                    <button wire:click="viewDetail('{{ $package['code'] }}')"
                                            class="btn btn-outline-secondary">
                                        <i class="fa fa-info-circle"></i>
                                    </button>

                                    <button wire:click="uninstallPackage('{{ $package['code'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="uninstallPackage('{{ $package['code'] }}')"
                                            wire:confirm="{{ lang('tipowerup.installer::default.confirm_uninstall', ['package' => $package['name']]) }}"
                                            class="btn btn-outline-danger">
                                        <span wire:loading.remove wire:target="uninstallPackage('{{ $package['code'] }}')">
                                            <i class="fa fa-trash"></i>
                                        </span>
                                        <span wire:loading wire:target="uninstallPackage('{{ $package['code'] }}')">
                                            <span class="tipowerup-installer__spinner" style="width: 14px; height: 14px; border-width: 2px;"></span>
                                        </span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
