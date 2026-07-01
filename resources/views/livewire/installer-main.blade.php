<div class="tipowerup-installer"
     x-data="{ instantInstalling: false, instantPackageName: '' }"
     x-on:begin-install-instant.window="instantInstalling = true; instantPackageName = $event.detail?.packageName || ''"
     x-on:install-completed.window="instantInstalling = false"
     x-on:close-install-progress.window="instantInstalling = false">

    {{-- Instant install overlay: shows the moment an install button is clicked,
         bridges the wire roundtrip until the real progress modal renders.
         Uses <template x-if> so the DOM only exists while the overlay is active —
         avoids first-paint flash before Alpine hydrates. --}}
    <template x-if="instantInstalling && !@js((bool) $showInstallProgress)">
        <div class="modal fade show d-block tipowerup-installer__modal-backdrop" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5 class="mb-1">
                            <i class="fa fa-spinner fa-spin text-primary me-2"></i>
                            Starting installation
                            <span x-show="instantPackageName" x-text="': ' + instantPackageName"></span>
                        </h5>
                        <p class="text-muted small mb-0">Preparing the installer...</p>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @if(!$isOnboarded)
        {{-- Show onboarding wizard if not completed --}}
        <livewire:tipowerup-installer::onboarding />
    @else
        {{-- Main Installer UI --}}
        <div class="tipowerup-installer__page-card">
            {{-- Core Extensions Warning Banner --}}
            @if(count($missingCoreExtensions) > 0)
                <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                    <i class="fa fa-exclamation-triangle me-2"></i>
                    <div class="flex-grow-1">
                        <strong>Missing Required TI Core Extensions</strong>
                        <p class="mb-1 mt-1 small">
                            The following extensions must be installed for PowerUp Installer to work correctly:
                        </p>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach($missingCoreExtensions as $ext)
                                <span class="badge bg-danger">{{ $ext['name'] }}</span>
                            @endforeach
                        </div>
                        <a href="{{ $missingCoreExtensions[0]['manage_url'] ?? '#' }}"
                           class="btn btn-sm btn-danger">
                            <i class="fa fa-external-link-alt me-1"></i>
                            Manage Extensions
                        </a>
                    </div>
                </div>
            @endif

            {{-- Header with title + settings gear --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 fw-semibold">{{ lang('tipowerup.installer::default.text_title') }}</h5>
                    <p class="text-muted mb-0 tipowerup-installer__text-xxs">
                        {{ lang('tipowerup.installer::default.text_description') }}
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button wire:click="openInstallLogs" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-history"></i>
                        <span class="ms-1 d-none d-md-inline">{{ lang('tipowerup.installer::default.logs_title') }}</span>
                    </button>
                    <button wire:click="openSettings" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-cog"></i>
                        <span class="ms-1 d-none d-md-inline">Settings</span>
                    </button>
                </div>
            </div>

            {{-- Tab Navigation --}}
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button wire:click="switchTab('installed')" type="button"
                            class="nav-link {{ $activeTab === 'installed' ? 'active' : '' }}"
                            role="tab">
                        <i class="fa fa-check-circle me-1"></i>
                        {{ lang('tipowerup.installer::default.tab_installed') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button wire:click="switchTab('marketplace')" type="button"
                            class="nav-link {{ $activeTab === 'marketplace' ? 'active' : '' }}"
                            role="tab">
                        <i class="fa fa-shopping-bag me-1"></i>
                        {{ lang('tipowerup.installer::default.tab_marketplace') }}
                    </button>
                </li>
            </ul>

            {{-- Tab Content --}}
            <div class="tab-content">
                @if($activeTab === 'installed')
                    <div class="tab-pane fade show active" wire:key="tab-installed">
                        <livewire:tipowerup-installer::installed-packages lazy />
                    </div>
                @else
                    <div class="tab-pane fade show active" wire:key="tab-marketplace">
                        <livewire:tipowerup-installer::marketplace lazy />
                    </div>
                @endif
            </div>
        </div>

        {{-- Settings Panel Slide-out --}}
        @if($showSettings)
            <div x-data="{ open: true }" x-show="open" wire:key="settings-panel">
                <div class="offcanvas offcanvas-end show tipowerup-installer__offcanvas--visible" tabindex="-1">
                    <div class="offcanvas-header border-bottom py-3">
                        <h6 class="offcanvas-title mb-0">{{ lang('tipowerup.installer::default.settings_title') }}</h6>
                        <button @click="open = false" wire:click="closeSettings" type="button" class="btn-close"></button>
                    </div>
                    <div class="offcanvas-body">
                        <livewire:tipowerup-installer::settings-panel />
                    </div>
                </div>
                <div @click="open = false" wire:click="closeSettings" class="offcanvas-backdrop fade show"></div>
            </div>
        @endif

        {{-- Package Detail Modal --}}
        @if($selectedPackage)
            <div x-data="{ open: true }" x-show="open"
                 x-on:close-package-detail.window="open = false"
                 wire:key="package-detail-modal"
                 class="modal fade show d-block tipowerup-installer__modal-backdrop" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header border-bottom py-3">
                            <h6 class="modal-title mb-0">PowerUp Details</h6>
                            <button @click="open = false" wire:click="closePackageDetail" type="button" class="btn-close"></button>
                        </div>
                        <div class="modal-body tipowerup-installer__modal-body--detail">
                            <livewire:tipowerup-installer::package-detail :package-code="$selectedPackage" :initial-data="$selectedPackageData" lazy />
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Install Logs Modal --}}
        @if($showInstallLogs)
            <div x-data="{ open: true }" x-show="open"
                 x-on:close-install-logs.window="open = false"
                 wire:key="install-logs-modal"
                 class="modal fade show d-block tipowerup-installer__modal-backdrop" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <livewire:tipowerup-installer::install-logs />
                    </div>
                </div>
            </div>
        @endif

        {{-- Install Progress Modal --}}
        @if($showInstallProgress)
            <div x-data="{ open: true }" x-show="open"
                 x-on:close-install-progress.window="open = false"
                 wire:key="install-progress-modal"
                 class="modal fade show d-block tipowerup-installer__modal-backdrop" tabindex="-1"
                 data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body">
                            <livewire:tipowerup-installer::install-progress
                                :package-code="$installPackageCode"
                                :package-name="$installPackageName"
                                :is-update="$installIsUpdate" />
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

</div>
