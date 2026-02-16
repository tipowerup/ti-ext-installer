<div class="tipowerup-installer">
    @if(!$isOnboarded)
        {{-- Show onboarding wizard if not completed --}}
        <livewire:tipowerup-installer::onboarding />
    @else
        {{-- Main Installer UI --}}
        <div class="container-fluid">
            {{-- Core Extensions Warning Banner --}}
            @if(count($missingCoreExtensions) > 0)
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="fa fa-exclamation-triangle me-3" style="font-size: 24px;"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-2">
                            <strong>Missing Required TI Core Extensions</strong>
                        </h6>
                        <p class="mb-2">
                            The following extensions must be installed for PowerUp Installer to work correctly:
                        </p>
                        <div class="d-flex flex-wrap gap-2 mb-2">
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">{{ lang('tipowerup.installer::default.text_title') }}</h4>
                    <p class="text-muted mb-0 small">
                        {{ lang('tipowerup.installer::default.text_description') }}
                    </p>
                </div>
                <button wire:click="openSettings" class="btn btn-outline-secondary">
                    <i class="fa fa-cog"></i>
                    <span class="ms-2 d-none d-md-inline">Settings</span>
                </button>
            </div>

            {{-- Tab Navigation --}}
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button wire:click="switchTab('installed')" type="button"
                            class="nav-link {{ $activeTab === 'installed' ? 'active' : '' }}"
                            role="tab">
                        <i class="fa fa-check-circle me-2"></i>
                        {{ lang('tipowerup.installer::default.tab_installed') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button wire:click="switchTab('marketplace')" type="button"
                            class="nav-link {{ $activeTab === 'marketplace' ? 'active' : '' }}"
                            role="tab">
                        <i class="fa fa-shopping-bag me-2"></i>
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
                <div class="offcanvas-header border-bottom">
                    <h5 class="offcanvas-title">{{ lang('tipowerup.installer::default.settings_title') }}</h5>
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
                        <div class="modal-header border-bottom">
                            <h5 class="modal-title">Package Details</h5>
                            <button wire:click="closePackageDetail" type="button" class="btn-close"></button>
                        </div>
                        <div class="modal-body">
                            <livewire:tipowerup-installer::package-detail :package-code="$selectedPackage" />
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
</div>

<style>
.tipowerup-installer {
    min-height: 500px;
}

.tipowerup-installer-onboarding {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.tipowerup-installer-onboarding .card {
    border: none;
    border-radius: 12px;
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
</style>
