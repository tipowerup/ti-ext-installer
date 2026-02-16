<div>
    @if($isLoading)
        {{-- Loading State --}}
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted">{{ lang('tipowerup.installer::default.marketplace_detecting_purchase') }}</p>
        </div>
    @elseif($errorMessage)
        {{-- Error State --}}
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="fa fa-exclamation-circle me-3" style="font-size: 24px;"></i>
            <div>
                <strong>{{ lang('tipowerup.installer::default.error_connection_failed') }}</strong>
                <p class="mb-0 small mt-1">{{ $errorMessage }}</p>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button wire:click="closeDetail" type="button" class="btn btn-secondary">
                {{ lang('tipowerup.installer::default.progress_close') }}
            </button>
            <button wire:click="loadPackageDetails" type="button" class="btn btn-primary">
                <i class="fa fa-redo me-2"></i>
                {{ lang('tipowerup.installer::default.progress_retry') }}
            </button>
        </div>
    @else
        {{-- Package Details --}}
        <div class="tipowerup-installer__package-header mb-4">
            <div class="d-flex align-items-start">
                @if($packageData['icon'])
                    <img src="{{ $packageData['icon'] }}" alt="{{ $packageData['name'] }}"
                         class="tipowerup-installer__package-icon me-3" style="width: 64px; height: 64px; border-radius: 8px; object-fit: cover;">
                @else
                    <div class="tipowerup-installer__package-icon me-3" style="width: 64px; height: 64px;">
                        {{ substr($packageData['name'], 0, 2) }}
                    </div>
                @endif

                <div class="flex-grow-1">
                    <h4 class="mb-1">{{ $packageData['name'] }}</h4>
                    <p class="text-muted mb-2 small">
                        <span>{{ lang('tipowerup.installer::default.detail_author') }}: {{ $packageData['author'] }}</span>
                        <span class="mx-2">·</span>
                        <span>{{ lang('tipowerup.installer::default.detail_last_updated') }}:
                            @if($packageData['last_updated'])
                                {{ \Carbon\Carbon::parse($packageData['last_updated'])->format('M Y') }}
                            @else
                                N/A
                            @endif
                        </span>
                    </p>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $packageData['type'] === 'theme' ? 'theme' : 'extension' }}">
                            {{ ucfirst($packageData['type']) }}
                        </span>
                        <span class="badge bg-secondary">
                            {{ lang('tipowerup.installer::default.detail_version') }}: {{ $packageData['version'] }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab Navigation --}}
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button wire:click="switchDetailTab('description')" type="button"
                        class="nav-link {{ $activeDetailTab === 'description' ? 'active' : '' }}"
                        role="tab">
                    <i class="fa fa-info-circle me-2"></i>
                    {{ lang('tipowerup.installer::default.detail_description') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button wire:click="switchDetailTab('changelog')" type="button"
                        class="nav-link {{ $activeDetailTab === 'changelog' ? 'active' : '' }}"
                        role="tab">
                    <i class="fa fa-list-ul me-2"></i>
                    {{ lang('tipowerup.installer::default.detail_changelog') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button wire:click="switchDetailTab('compatibility')" type="button"
                        class="nav-link {{ $activeDetailTab === 'compatibility' ? 'active' : '' }}"
                        role="tab">
                    <i class="fa fa-check-circle me-2"></i>
                    {{ lang('tipowerup.installer::default.detail_compatibility') }}
                </button>
            </li>
        </ul>

        {{-- Tab Content --}}
        <div class="tab-content mb-4" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
            @if($activeDetailTab === 'description')
                <div class="tab-pane fade show active">
                    @if(!empty($packageData['description']))
                        <div class="prose">
                            {!! nl2br(e($packageData['description'])) !!}
                        </div>
                    @else
                        <p class="text-muted">{{ lang('tipowerup.installer::default.installed_empty') }}</p>
                    @endif
                </div>
            @elseif($activeDetailTab === 'changelog')
                <div class="tab-pane fade show active">
                    @if(!empty($packageData['changelog']))
                        <div class="prose">
                            {!! nl2br(e($packageData['changelog'])) !!}
                        </div>
                    @else
                        <p class="text-muted">No changelog available.</p>
                    @endif
                </div>
            @elseif($activeDetailTab === 'compatibility')
                <div class="tab-pane fade show active">
                    @if(!empty($packageData['compatibility']))
                        <ul class="list-unstyled">
                            @foreach($packageData['compatibility'] as $requirement)
                                <li class="mb-2">
                                    <i class="fa fa-check-circle text-success me-2"></i>
                                    <strong>{{ $requirement['name'] ?? 'Requirement' }}:</strong> {{ $requirement['version'] ?? 'N/A' }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted">No specific requirements listed.</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Dependencies Section --}}
        @if(!empty($packageData['dependencies']))
            <div class="border-top pt-3 mb-4">
                <h6 class="mb-2">{{ lang('tipowerup.installer::default.detail_dependencies') }}:</h6>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($packageData['dependencies'] as $dependency)
                        <span class="badge bg-light text-dark border">{{ $dependency }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Action Buttons --}}
        <div class="d-flex justify-content-end gap-2 border-top pt-3">
            <button wire:click="closeDetail" type="button" class="btn btn-secondary">
                {{ lang('tipowerup.installer::default.progress_close') }}
            </button>

            @if($packageData['is_purchased'])
                <button wire:click="installPackage" type="button" class="btn btn-success"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="installPackage">
                        <i class="fa fa-download me-2"></i>
                        {{ lang('tipowerup.installer::default.action_install') }}
                    </span>
                    <span wire:loading wire:target="installPackage">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        Installing...
                    </span>
                </button>
            @else
                <a href="https://tipowerup.com/marketplace/{{ $packageData['code'] }}"
                   target="_blank"
                   class="btn btn-primary">
                    <i class="fa fa-shopping-cart me-2"></i>
                    {{ lang('tipowerup.installer::default.marketplace_buy') }}
                </a>
            @endif
        </div>
    @endif
</div>
