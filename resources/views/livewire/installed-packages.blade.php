<div class="tipowerup-installer__installed-packages">
    @if($errorMessage)
        <div class="alert alert-danger d-flex align-items-start mb-3" role="alert">
            <i class="fa fa-exclamation-triangle me-2 mt-1 flex-shrink-0"></i>
            <div class="flex-grow-1">
                @if($isKeyError)
                    {{ lang('tipowerup.installer::default.error_powerup_key_invalid_alert') }}
                    <a href="#" wire:click.prevent="$dispatch('open-settings')" class="alert-link">
                        {{ lang('tipowerup.installer::default.settings_title') }}
                    </a>
                @else
                    {{ $errorMessage }}
                @endif
            </div>
            @if(!$isKeyError)
                <button wire:click="$set('errorMessage', null)" type="button" class="btn-close btn-close-sm flex-shrink-0 ms-2"></button>
            @endif
        </div>
    @endif

    <div class="d-flex justify-content-end align-items-center">
        <div class="d-flex gap-2 align-items-center">
            @if(count($installedPackages) > 0)
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
                        {{ lang('tipowerup.installer::default.marketplace_checking_updates') }}
                    </span>
                </button>
            @endif

            @if(count($installedPackages) > 0 || count($availablePackages) > 0)
                <button wire:click="toggleViewMode"
                        class="btn btn-outline-secondary btn-sm"
                        title="{{ $viewMode === 'grid' ? lang('tipowerup.installer::default.installed_view_list') : lang('tipowerup.installer::default.installed_view_grid') }}">
                    <i class="fa {{ $viewMode === 'grid' ? 'fa-list' : 'fa-th' }}"></i>
                </button>
            @endif
        </div>
    </div>

    @if($isLoading)
        <div class="tipowerup-installer__packages-grid">
            @for($i = 0; $i < 3; $i++)
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--icon-lg"></div>
                            <div class="ms-3 flex-grow-1">
                                <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--title mb-2"></div>
                                <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--subtitle"></div>
                            </div>
                        </div>
                        <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--body mb-2"></div>
                        <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--body-short mb-3"></div>
                        <div class="d-flex gap-2">
                            <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--action-btn"></div>
                            <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--action-btn"></div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>

    @elseif(count($installedPackages) === 0 && count($availablePackages) === 0)
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

    @else
        {{-- ====== Updates Available Section (collapsible) ====== --}}
        @php
            $packagesWithUpdates = collect($installedPackages)->where('has_update', true)->values()->all();
        @endphp
        @if(count($packagesWithUpdates) > 0)
            <div class="tipowerup-installer__section mb-3">
                <div wire:click="toggleUpdatesSection"
                     class="tipowerup-installer__section-header tipowerup-installer__section-header--clickable">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa {{ $updatesCollapsed ? 'fa-chevron-right' : 'fa-chevron-down' }} tipowerup-installer__chevron-icon"></i>
                        <h6 class="mb-0 fw-semibold tipowerup-installer__section-title">
                            {{ lang('tipowerup.installer::default.updates_title') }}
                        </h6>
                        <span class="badge text-white tipowerup-installer__section-badge--updates">{{ count($packagesWithUpdates) }}</span>
                    </div>
                </div>

                @if(!$updatesCollapsed)
                    <div class="mt-2">
                        @foreach($packagesWithUpdates as $package)
                            @php $icon = $package['icon'] ?? null; @endphp
                            <div wire:key="update-{{ $package['code'] }}" class="d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    @include('tipowerup.installer::livewire._partials.package-icon-small', ['icon' => $icon, 'name' => $package['name'], 'type' => $package['type']])
                                    <div>
                                        <strong class="tipowerup-installer__text-sm">{{ $package['name'] }}</strong>
                                        <div class="text-muted tipowerup-installer__text-xs">
                                            {{ $package['version'] }} &rarr; {{ $package['latest_version'] }}
                                        </div>
                                    </div>
                                </div>
                                <button wire:click="updatePackage('{{ $package['code'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="updatePackage('{{ $package['code'] }}')"
                                        class="btn btn-success btn-sm">
                                    <span wire:loading wire:target="updatePackage('{{ $package['code'] }}')">
                                        <span class="tipowerup-installer__spinner me-1"></span>
                                    </span>
                                    <span wire:loading.remove wire:target="updatePackage('{{ $package['code'] }}')">
                                        <i class="fa fa-arrow-up me-1"></i>
                                    </span>
                                    {{ lang('tipowerup.installer::default.action_update') }}
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- ====== Installed PowerUps Section (collapsible) ====== --}}
        @if(count($installedPackages) > 0)
            <div class="tipowerup-installer__section mb-3">
                <div wire:click="toggleInstalledSection"
                     class="tipowerup-installer__section-header tipowerup-installer__section-header--clickable">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa {{ $installedCollapsed ? 'fa-chevron-right' : 'fa-chevron-down' }} tipowerup-installer__chevron-icon"></i>
                        <h6 class="mb-0 fw-semibold tipowerup-installer__section-title">
                            {{ lang('tipowerup.installer::default.my_powerups_installed_title') }}
                        </h6>
                        <span class="badge text-white tipowerup-installer__section-badge--installed">{{ count($installedPackages) }}</span>
                    </div>
                </div>

                @if(!$installedCollapsed)
                    @if($viewMode === 'grid')
                        <div class="tipowerup-installer__packages-grid mt-2">
                            @foreach($installedPackages as $package)
                                @php $icon = $package['icon'] ?? null; @endphp
                                <div wire:key="installed-{{ $package['code'] }}" class="card tipowerup-installer__package-card">
                                    <div class="card-body">
                                        <div class="tipowerup-installer__package-header">
                                            @include('tipowerup.installer::livewire._partials.package-icon', ['icon' => $icon, 'name' => $package['name'], 'type' => $package['type'], 'size' => '44px'])

                                            <div class="tipowerup-installer__package-info">
                                                <h6 class="tipowerup-installer__package-name mb-0">
                                                    {{ $package['name'] }}
                                                    @if(!$package['is_owned'])
                                                        <i class="fa fa-exclamation-triangle text-warning ms-1 tipowerup-installer__icon-xxs"
                                                           title="{{ lang('tipowerup.installer::default.my_powerups_not_owned_tooltip') }}"></i>
                                                    @endif
                                                </h6>
                                                <div class="tipowerup-installer__package-version text-muted">
                                                    @if($package['has_update'])
                                                        <span class="text-success">
                                                            {{ $package['version'] }} &rarr; {{ $package['latest_version'] }}
                                                        </span>
                                                    @else
                                                        {{ lang('tipowerup.installer::default.installed_version', ['version' => $package['version']]) }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tipowerup-installer__package-meta">
                                            <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] }}">
                                                {{ ucfirst($package['type']) }}
                                            </span>
                                            @if($package['type'] === 'extension')
                                                <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['is_active'] ? 'active' : 'disabled' }}">
                                                    {{ lang('tipowerup.installer::default.installed_' . ($package['is_active'] ? 'active' : 'disabled')) }}
                                                </span>
                                            @else
                                                <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['is_active'] ? 'active' : 'disabled' }}">
                                                    {{ lang('tipowerup.installer::default.installed_' . ($package['is_active'] ? 'current' : 'inactive')) }}
                                                </span>
                                            @endif
                                        </div>

                                        @if($package['description'])
                                            <p class="tipowerup-installer__package-description">
                                                {{ \Illuminate\Support\Str::limit($package['description'], 120) }}
                                            </p>
                                        @endif

                                        @if($package['expires_at'])
                                            <div class="mb-3">
                                                <small class="text-muted d-block">
                                                    <i class="fa fa-clock me-1"></i>
                                                    {{ lang('tipowerup.installer::default.installed_expires', ['date' => $package['expires_at']]) }}
                                                </small>
                                            </div>
                                        @endif

                                        <div class="tipowerup-installer__package-footer">
                                            <div class="d-flex gap-2 flex-wrap align-items-center">
                                                <div class="d-flex gap-2">
                                                    @if($package['has_update'])
                                                        <button wire:click="updatePackage('{{ $package['code'] }}')"
                                                                wire:loading.attr="disabled"
                                                                wire:target="updatePackage('{{ $package['code'] }}')"
                                                                class="btn btn-success btn-sm">
                                                            <span wire:loading wire:target="updatePackage('{{ $package['code'] }}')">
                                                                <span class="tipowerup-installer__spinner me-1"></span>
                                                            </span>
                                                            <span wire:loading.remove wire:target="updatePackage('{{ $package['code'] }}')">
                                                                <i class="fa fa-arrow-up me-1"></i>
                                                            </span>
                                                            {{ lang('tipowerup.installer::default.action_update') }}
                                                        </button>
                                                    @endif

                                                    @if($package['type'] === 'extension')
                                                        @if($package['is_active'])
                                                            <button wire:click="disableExtension('{{ $package['code'] }}')"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="disableExtension('{{ $package['code'] }}')"
                                                                    class="btn btn-outline-warning btn-sm"
                                                                    title="{{ lang('tipowerup.installer::default.action_disable') }}">
                                                                <span wire:loading wire:target="disableExtension('{{ $package['code'] }}')">
                                                                    <span class="tipowerup-installer__spinner me-1"></span>
                                                                </span>
                                                                <span wire:loading.remove wire:target="disableExtension('{{ $package['code'] }}')">
                                                                    <i class="fa fa-pause-circle me-1"></i>
                                                                </span>
                                                                {{ lang('tipowerup.installer::default.action_disable') }}
                                                            </button>
                                                        @else
                                                            <button wire:click="enableExtension('{{ $package['code'] }}')"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="enableExtension('{{ $package['code'] }}')"
                                                                    class="btn btn-outline-success btn-sm"
                                                                    title="{{ lang('tipowerup.installer::default.action_enable') }}">
                                                                <span wire:loading wire:target="enableExtension('{{ $package['code'] }}')">
                                                                    <span class="tipowerup-installer__spinner me-1"></span>
                                                                </span>
                                                                <span wire:loading.remove wire:target="enableExtension('{{ $package['code'] }}')">
                                                                    <i class="fa fa-play-circle me-1"></i>
                                                                </span>
                                                                {{ lang('tipowerup.installer::default.action_enable') }}
                                                            </button>
                                                        @endif
                                                    @elseif($package['type'] === 'theme' && !$package['is_active'])
                                                        <button wire:click="activateTheme('{{ $package['code'] }}')"
                                                                wire:loading.attr="disabled"
                                                                wire:target="activateTheme('{{ $package['code'] }}')"
                                                                class="btn btn-outline-success btn-sm"
                                                                title="{{ lang('tipowerup.installer::default.action_activate') }}">
                                                            <span wire:loading wire:target="activateTheme('{{ $package['code'] }}')">
                                                                <span class="tipowerup-installer__spinner me-1"></span>
                                                            </span>
                                                            <span wire:loading.remove wire:target="activateTheme('{{ $package['code'] }}')">
                                                                <i class="fa fa-check-circle me-1"></i>
                                                            </span>
                                                            {{ lang('tipowerup.installer::default.action_activate') }}
                                                        </button>
                                                    @endif
                                                </div>

                                                <div class="d-flex gap-2 ms-auto">
                                                    @if($package['type'] === 'extension' && $package['is_active'] && $package['settings_url'])
                                                        <a href="{{ $package['settings_url'] }}"
                                                           class="btn btn-outline-secondary btn-sm"
                                                           title="{{ lang('tipowerup.installer::default.action_settings') }}">
                                                            <i class="fa fa-cog"></i>
                                                        </a>
                                                    @endif

                                                    @if($package['type'] === 'theme' && $package['is_active'])
                                                        <a href="{{ $package['edit_url'] }}"
                                                           class="btn btn-outline-secondary btn-sm"
                                                           title="{{ lang('tipowerup.installer::default.action_edit') }}">
                                                            <i class="fa fa-pencil"></i>
                                                        </a>
                                                        @if($package['customize_url'])
                                                            <a href="{{ $package['customize_url'] }}"
                                                               class="btn btn-outline-secondary btn-sm"
                                                               title="{{ lang('tipowerup.installer::default.action_customize') }}">
                                                                <i class="fa fa-sliders-h"></i>
                                                            </a>
                                                        @endif
                                                    @endif

                                                    <button wire:click="viewDetail('{{ $package['code'] }}')"
                                                            class="btn btn-outline-secondary btn-sm"
                                                            title="{{ lang('tipowerup.installer::default.detail_description') }}">
                                                        <i class="fa fa-eye"></i>
                                                    </button>

                                                    @if(!$package['is_active'])
                                                        <button wire:click="confirmUninstall('{{ $package['code'] }}')"
                                                                class="btn btn-outline-danger btn-sm"
                                                                title="{{ lang('tipowerup.installer::default.action_uninstall') }}">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="table-responsive mt-2">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th class="tipowerup-installer__table-col--icon-sm"></th>
                                        <th>{{ lang('tipowerup.installer::default.detail_version') }}</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th class="tipowerup-installer__table-col--actions-wide text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($installedPackages as $package)
                                        @php $icon = $package['icon'] ?? null; @endphp
                                        <tr wire:key="installed-list-{{ $package['code'] }}">
                                            <td>
                                                @include('tipowerup.installer::livewire._partials.package-icon-small', ['icon' => $icon, 'name' => $package['name'], 'type' => $package['type']])
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>
                                                        {{ $package['name'] }}
                                                        @if(!$package['is_owned'])
                                                            <i class="fa fa-exclamation-triangle text-warning ms-1 tipowerup-installer__icon-xxs"
                                                               title="{{ lang('tipowerup.installer::default.my_powerups_not_owned_tooltip') }}"></i>
                                                        @endif
                                                    </strong>
                                                    <div class="text-muted small">
                                                        @if($package['has_update'])
                                                            <span class="text-success fw-medium">
                                                                {{ $package['version'] }} &rarr; {{ $package['latest_version'] }}
                                                            </span>
                                                        @else
                                                            {{ lang('tipowerup.installer::default.installed_version', ['version' => $package['version']]) }}
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
                                                @if($package['type'] === 'extension')
                                                    <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['is_active'] ? 'active' : 'disabled' }}">
                                                        {{ lang('tipowerup.installer::default.installed_' . ($package['is_active'] ? 'active' : 'disabled')) }}
                                                    </span>
                                                @else
                                                    <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['is_active'] ? 'active' : 'disabled' }}">
                                                        {{ lang('tipowerup.installer::default.installed_' . ($package['is_active'] ? 'current' : 'inactive')) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    @if($package['has_update'])
                                                        <button wire:click="updatePackage('{{ $package['code'] }}')"
                                                                wire:loading.attr="disabled"
                                                                wire:target="updatePackage('{{ $package['code'] }}')"
                                                                class="btn btn-success">
                                                            <span wire:loading wire:target="updatePackage('{{ $package['code'] }}')">
                                                                <span class="tipowerup-installer__spinner tipowerup-installer__spinner--sm"></span>
                                                            </span>
                                                            <span wire:loading.remove wire:target="updatePackage('{{ $package['code'] }}')">
                                                                <i class="fa fa-arrow-up"></i>
                                                            </span>
                                                        </button>
                                                    @endif

                                                    @if($package['type'] === 'extension')
                                                        @if($package['is_active'])
                                                            <button wire:click="disableExtension('{{ $package['code'] }}')"
                                                                    wire:loading.attr="disabled"
                                                                    class="btn btn-outline-warning"
                                                                    title="{{ lang('tipowerup.installer::default.action_disable') }}">
                                                                <i class="fa fa-pause-circle"></i>
                                                            </button>
                                                        @else
                                                            <button wire:click="enableExtension('{{ $package['code'] }}')"
                                                                    wire:loading.attr="disabled"
                                                                    class="btn btn-outline-success"
                                                                    title="{{ lang('tipowerup.installer::default.action_enable') }}">
                                                                <i class="fa fa-play-circle"></i>
                                                            </button>
                                                        @endif
                                                    @elseif($package['type'] === 'theme' && !$package['is_active'])
                                                        <button wire:click="activateTheme('{{ $package['code'] }}')"
                                                                wire:loading.attr="disabled"
                                                                class="btn btn-outline-success"
                                                                title="{{ lang('tipowerup.installer::default.action_activate') }}">
                                                            <i class="fa fa-check-circle"></i>
                                                        </button>
                                                    @endif

                                                    @if($package['type'] === 'extension' && $package['is_active'] && $package['settings_url'])
                                                        <a href="{{ $package['settings_url'] }}"
                                                           class="btn btn-outline-secondary"
                                                           title="{{ lang('tipowerup.installer::default.action_settings') }}">
                                                            <i class="fa fa-cog"></i>
                                                        </a>
                                                    @endif

                                                    @if($package['type'] === 'theme' && $package['is_active'])
                                                        <a href="{{ $package['edit_url'] }}"
                                                           class="btn btn-outline-secondary"
                                                           title="{{ lang('tipowerup.installer::default.action_edit') }}">
                                                            <i class="fa fa-pencil"></i>
                                                        </a>
                                                        @if($package['customize_url'])
                                                            <a href="{{ $package['customize_url'] }}"
                                                               class="btn btn-outline-secondary"
                                                               title="{{ lang('tipowerup.installer::default.action_customize') }}">
                                                                <i class="fa fa-sliders-h"></i>
                                                            </a>
                                                        @endif
                                                    @endif

                                                    <button wire:click="viewDetail('{{ $package['code'] }}')"
                                                            class="btn btn-outline-secondary"
                                                            title="{{ lang('tipowerup.installer::default.detail_description') }}">
                                                        <i class="fa fa-eye"></i>
                                                    </button>

                                                    @if(!$package['is_active'])
                                                        <button wire:click="confirmUninstall('{{ $package['code'] }}')"
                                                                class="btn btn-outline-danger"
                                                                title="{{ lang('tipowerup.installer::default.action_uninstall') }}">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
            @endif
            </div>
        @endif

        @if(count($installedPackages) > 0 && count($availablePackages) > 0)
            <hr class="my-3 tipowerup-installer__divider">
        @endif

        {{-- ====== Available PowerUps Section (collapsible) ====== --}}
        @if(count($availablePackages) > 0)
            <div class="tipowerup-installer__section">
                <div wire:click="toggleAvailableSection"
                     class="tipowerup-installer__section-header tipowerup-installer__section-header--clickable">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa {{ $availableCollapsed ? 'fa-chevron-right' : 'fa-chevron-down' }} tipowerup-installer__chevron-icon"></i>
                        <h6 class="mb-0 fw-semibold tipowerup-installer__section-title">
                            {{ lang('tipowerup.installer::default.my_powerups_available_title') }}
                        </h6>
                        <span class="badge text-white tipowerup-installer__section-badge--available">{{ count($availablePackages) }}</span>
                    </div>
                </div>

                @if(!$availableCollapsed)
                    @if($viewMode === 'grid')
                        <div class="tipowerup-installer__packages-grid mt-2">
                            @foreach($availablePackages as $package)
                                @php $icon = $package['icon'] ?? null; @endphp
                                <div wire:key="available-{{ $package['code'] }}" class="card tipowerup-installer__package-card">
                                    <div class="card-body">
                                        <div class="tipowerup-installer__package-header">
                                            @include('tipowerup.installer::livewire._partials.package-icon', ['icon' => $icon, 'name' => $package['name'], 'type' => $package['type'], 'size' => '44px'])

                                            <div class="tipowerup-installer__package-info">
                                                <h6 class="tipowerup-installer__package-name mb-0">
                                                    {{ $package['name'] }}
                                                </h6>
                                                <div class="d-flex align-items-center gap-1 mt-1">
                                                    <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] }}">
                                                        {{ ucfirst($package['type']) }}
                                                    </span>
                                                    @if($package['version'])
                                                        <span class="text-muted tipowerup-installer__text-xs">v{{ $package['version'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        @if($package['description'])
                                            <p class="tipowerup-installer__package-description">
                                                {{ \Illuminate\Support\Str::limit($package['description'], 120) }}
                                            </p>
                                        @endif

                                        <div class="tipowerup-installer__package-footer">
                                            <div class="d-flex gap-2 flex-wrap align-items-center">
                                                <button wire:click="installPackage('{{ $package['code'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="installPackage('{{ $package['code'] }}')"
                                                        class="tipowerup-installer__btn-install btn btn-sm">
                                                    <span wire:loading wire:target="installPackage('{{ $package['code'] }}')">
                                                        <span class="tipowerup-installer__spinner me-1"></span>
                                                    </span>
                                                    <span wire:loading.remove wire:target="installPackage('{{ $package['code'] }}')">
                                                        <i class="fa fa-download me-1"></i>
                                                    </span>
                                                    {{ lang('tipowerup.installer::default.action_install') }}
                                                </button>

                                                <button wire:click="viewDetail('{{ $package['code'] }}')"
                                                        class="btn btn-outline-secondary btn-sm ms-auto"
                                                        title="{{ lang('tipowerup.installer::default.detail_description') }}">
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="table-responsive mt-2">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th class="tipowerup-installer__table-col--icon-sm"></th>
                                        <th>{{ lang('tipowerup.installer::default.marketplace_table_header_name') }}</th>
                                        <th>Type</th>
                                        <th class="tipowerup-installer__table-col--actions text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($availablePackages as $package)
                                        @php $icon = $package['icon'] ?? null; @endphp
                                        <tr wire:key="available-list-{{ $package['code'] }}">
                                            <td>
                                                @include('tipowerup.installer::livewire._partials.package-icon-small', ['icon' => $icon, 'name' => $package['name'], 'type' => $package['type']])
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>{{ $package['name'] }}</strong>
                                                    @if($package['description'])
                                                        <div class="text-muted small">
                                                            {{ \Illuminate\Support\Str::limit($package['description'], 80) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] }}">
                                                    {{ ucfirst($package['type']) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button wire:click="installPackage('{{ $package['code'] }}')"
                                                            wire:loading.attr="disabled"
                                                            wire:target="installPackage('{{ $package['code'] }}')"
                                                            class="btn btn-success">
                                                        <span wire:loading wire:target="installPackage('{{ $package['code'] }}')">
                                                            <span class="tipowerup-installer__spinner tipowerup-installer__spinner--sm me-1"></span>
                                                        </span>
                                                        <span wire:loading.remove wire:target="installPackage('{{ $package['code'] }}')">
                                                            <i class="fa fa-download me-1"></i>
                                                        </span>
                                                        {{ lang('tipowerup.installer::default.action_install') }}
                                                    </button>

                                                    <button wire:click="viewDetail('{{ $package['code'] }}')"
                                                            class="btn btn-outline-secondary"
                                                            title="{{ lang('tipowerup.installer::default.detail_description') }}">
                                                        <i class="fa fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        @endif

    @endif

    {{-- Uninstall Confirmation Modal --}}
    @if($confirmAction)
        <div class="modal fade show d-block tipowerup-installer__modal-backdrop" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header border-bottom py-3">
                        <h6 class="modal-title mb-0">
                            {{ lang('tipowerup.installer::default.action_uninstall') }}
                        </h6>
                        <button wire:click="cancelConfirm" type="button" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            {{ lang('tipowerup.installer::default.confirm_uninstall', ['package' => $confirmName]) }}
                        </p>
                    </div>
                    <div class="modal-footer border-top py-2">
                        <button wire:click="cancelConfirm" type="button" class="btn btn-secondary btn-sm">
                            {{ lang('tipowerup.installer::default.progress_close') }}
                        </button>
                        <button wire:click="executeConfirmedAction"
                                wire:loading.attr="disabled"
                                wire:target="executeConfirmedAction"
                                type="button"
                                class="btn btn-danger btn-sm">
                            <span wire:loading wire:target="executeConfirmedAction">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            </span>
                            {{ lang('tipowerup.installer::default.action_uninstall') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
