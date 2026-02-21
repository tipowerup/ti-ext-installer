<div class="tipowerup-installer">
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
                    <p class="text-muted mb-0" style="font-size: 0.8125rem;">
                        {{ lang('tipowerup.installer::default.text_description') }}
                    </p>
                </div>
                <button wire:click="openSettings" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-cog"></i>
                    <span class="ms-1 d-none d-md-inline">Settings</span>
                </button>
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
                    <div class="tab-pane fade show active">
                        <livewire:tipowerup-installer::installed-packages />
                    </div>
                @else
                    <div class="tab-pane fade show active">
                        <livewire:tipowerup-installer::marketplace />
                    </div>
                @endif
            </div>
        </div>

        {{-- Settings Panel Slide-out --}}
        @if($showSettings)
            <div class="offcanvas offcanvas-end show" tabindex="-1" style="visibility: visible;">
                <div class="offcanvas-header border-bottom py-3">
                    <h6 class="offcanvas-title mb-0">{{ lang('tipowerup.installer::default.settings_title') }}</h6>
                    <button wire:click="closeSettings" type="button" class="btn-close"></button>
                </div>
                <div class="offcanvas-body">
                    <livewire:tipowerup-installer::settings-panel />
                </div>
            </div>
            <div wire:click="closeSettings" class="offcanvas-backdrop fade show"></div>
        @endif

        {{-- Package Detail Modal --}}
        @if($selectedPackage)
            <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header border-bottom py-3">
                            <h6 class="modal-title mb-0">PowerUp Details</h6>
                            <button wire:click="closePackageDetail" type="button" class="btn-close"></button>
                        </div>
                        <div class="modal-body" style="min-height: 400px;">
                            <livewire:tipowerup-installer::package-detail :package-code="$selectedPackage" :initial-data="$selectedPackageData" />
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Install Progress Modal --}}
        @if($showInstallProgress)
            <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);"
                 data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body">
                            <livewire:tipowerup-installer::install-progress />
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

<style>
.tipowerup-installer-onboarding {
    background: linear-gradient(135deg, #e0e7ff 0%, #ede9fe 40%, #fce7f3 100%);
    background-attachment: fixed;
    min-height: calc(100vh - 3.5rem);
}

.tipowerup-installer-onboarding .card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.07);
}

.tipowerup-installer .offcanvas {
    width: 400px !important;
}

.tipowerup-installer .offcanvas-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: 100vw;
    height: 100vh;
    background-color: #000;
}

.tipowerup-installer .nav-tabs {
    border-bottom-color: #dee2e6;
}

.tipowerup-installer .nav-tabs .nav-link {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    color: #1E293B;
    font-weight: 500;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
}

.tipowerup-installer .nav-tabs .nav-link:hover {
    color: #3B82F6;
    border-bottom-color: #93c5fd;
}

.tipowerup-installer .nav-tabs .nav-link.active {
    color: #3B82F6;
    border-bottom-color: #3B82F6;
    font-weight: 600;
}

.tipowerup-installer .btn-outline-secondary {
    color: #1E293B;
    border-color: #cbd5e1;
}

.tipowerup-installer .btn-outline-secondary:hover {
    color: #3B82F6;
    border-color: #3B82F6;
    background-color: #EFF6FF;
}
</style>
</div>
