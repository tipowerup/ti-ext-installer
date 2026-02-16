<div class="tipowerup-installer__marketplace">
    {{-- Purchase Polling Banner --}}
    @if($isPollingPurchases && $pollingPackageCode)
        <div class="alert alert-info d-flex align-items-center mb-4" role="alert" wire:poll.5s="pollForPurchase">
            <div class="spinner-border spinner-border-sm me-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="flex-grow-1">
                <strong>{{ lang('tipowerup.installer::default.marketplace_detecting_purchase') }}</strong>
                <p class="mb-0 small mt-1">
                    Complete your purchase of "{{ $this->getPackageName($pollingPackageCode) }}" on tipowerup.com, then come back. We'll detect it automatically.
                </p>
            </div>
            <button wire:click="stopPolling" class="btn btn-sm btn-outline-secondary ms-3">
                Stop Waiting
            </button>
        </div>
    @endif

    {{-- Error Message --}}
    @if($errorMessage)
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="fa fa-exclamation-circle me-3" style="font-size: 20px;"></i>
            <div class="flex-grow-1">
                {{ $errorMessage }}
            </div>
        </div>
    @endif

    {{-- Search Bar --}}
    <div class="tipowerup-installer__search-bar">
        <i class="fa fa-search tipowerup-installer__search-icon"></i>
        <input
            type="text"
            wire:model.live.debounce.500ms="searchQuery"
            class="form-control tipowerup-installer__search-input"
            placeholder="{{ lang('tipowerup.installer::default.marketplace_search') }}"
        />
    </div>

    {{-- Filters and Batch Actions --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="tipowerup-installer__filters">
            <button
                wire:click="setFilter('all')"
                class="tipowerup-installer__filter-btn {{ $filterType === 'all' ? 'tipowerup-installer__filter-btn--active' : '' }}"
            >
                <i class="fa fa-th me-2"></i>
                {{ lang('tipowerup.installer::default.marketplace_filter_all') }}
            </button>
            <button
                wire:click="setFilter('extension')"
                class="tipowerup-installer__filter-btn {{ $filterType === 'extension' ? 'tipowerup-installer__filter-btn--active' : '' }}"
            >
                <i class="fa fa-puzzle-piece me-2"></i>
                {{ lang('tipowerup.installer::default.marketplace_filter_extensions') }}
            </button>
            <button
                wire:click="setFilter('theme')"
                class="tipowerup-installer__filter-btn {{ $filterType === 'theme' ? 'tipowerup-installer__filter-btn--active' : '' }}"
            >
                <i class="fa fa-paint-brush me-2"></i>
                {{ lang('tipowerup.installer::default.marketplace_filter_themes') }}
            </button>
        </div>

        @if(count($selectedForBatch) > 0)
            <button wire:click="batchInstall" class="btn btn-success">
                <i class="fa fa-download me-2"></i>
                {{ lang('tipowerup.installer::default.action_batch_install') }} ({{ count($selectedForBatch) }})
            </button>
        @endif
    </div>

    {{-- Loading State --}}
    @if($isLoading)
        <div class="tipowerup-installer__packages-grid">
            @for($i = 0; $i < 6; $i++)
                <div class="tipowerup-installer__package-card">
                    <div class="tipowerup-installer__package-header">
                        <div class="tipowerup-installer__package-icon tipowerup-installer__skeleton" style="width: 56px; height: 56px;"></div>
                        <div class="tipowerup-installer__package-info flex-grow-1">
                            <div class="tipowerup-installer__skeleton mb-2" style="height: 20px; width: 70%;"></div>
                            <div class="tipowerup-installer__skeleton" style="height: 16px; width: 40%;"></div>
                        </div>
                    </div>
                    <div class="tipowerup-installer__skeleton mb-3" style="height: 60px;"></div>
                    <div class="tipowerup-installer__skeleton" style="height: 40px;"></div>
                </div>
            @endfor
        </div>
    @else
        {{-- Empty State --}}
        @if(count($packages) === 0)
            <div class="tipowerup-installer__empty-state">
                <div class="tipowerup-installer__empty-icon">
                    <i class="fa fa-shopping-bag"></i>
                </div>
                <h5 class="tipowerup-installer__empty-title">
                    No packages found
                </h5>
                <p class="tipowerup-installer__empty-message">
                    @if($searchQuery !== '')
                        No packages match your search query "{{ $searchQuery }}". Try different keywords.
                    @else
                        The marketplace is currently empty or unavailable.
                    @endif
                </p>
            </div>
        @else
            {{-- Package Grid --}}
            <div class="tipowerup-installer__packages-grid">
                @foreach($packages as $package)
                    <div wire:key="package-{{ $package['code'] }}" class="tipowerup-installer__package-card tipowerup-installer__marketplace-card">
                        {{-- Price Badge --}}
                        @if(isset($package['price']) && $package['price'] > 0)
                            <div class="tipowerup-installer__marketplace-card-price">
                                ${{ number_format($package['price'], 2) }}
                            </div>
                        @else
                            <div class="tipowerup-installer__marketplace-card-price" style="background-color: var(--ti-success-light); color: var(--ti-success);">
                                {{ lang('tipowerup.installer::default.marketplace_free') }}
                            </div>
                        @endif

                        {{-- Package Header --}}
                        <div class="tipowerup-installer__package-header">
                            @if(isset($package['icon']) && $package['icon'])
                                <img src="{{ $package['icon'] }}" alt="{{ $package['name'] }}" class="tipowerup-installer__package-icon" style="object-fit: cover;">
                            @else
                                <div class="tipowerup-installer__package-icon">
                                    {{ strtoupper(substr($package['name'], 0, 2)) }}
                                </div>
                            @endif

                            <div class="tipowerup-installer__package-info">
                                <div class="tipowerup-installer__package-name">
                                    {{ $package['name'] }}
                                </div>
                                <div class="tipowerup-installer__package-version">
                                    <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] === 'theme' ? 'theme' : 'extension' }}">
                                        {{ ucfirst($package['type']) }}
                                    </span>
                                    @if(isset($package['author']))
                                        <span class="text-muted ms-1">by {{ $package['author'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Description --}}
                        @if(isset($package['description']))
                            <p class="tipowerup-installer__package-description">
                                {{ \Illuminate\Support\Str::limit($package['description'], 100) }}
                            </p>
                        @endif

                        {{-- Package Footer with Actions --}}
                        <div class="tipowerup-installer__package-footer">
                            {{-- Batch Select Checkbox --}}
                            @if($package['is_purchased'] ?? false)
                                <div class="form-check">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="select-{{ $package['code'] }}"
                                        wire:click="toggleBatchSelect('{{ $package['code'] }}')"
                                        {{ in_array($package['code'], $selectedForBatch) ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label small" for="select-{{ $package['code'] }}">
                                        Select
                                    </label>
                                </div>
                            @endif

                            {{-- Action Buttons --}}
                            <div class="tipowerup-installer__marketplace-actions ms-auto">
                                {{-- View Details Button --}}
                                <button
                                    wire:click="viewDetail('{{ $package['code'] }}')"
                                    class="btn btn-sm btn-outline-secondary"
                                    title="View Details"
                                >
                                    <i class="fa fa-eye"></i>
                                </button>

                                {{-- Install/Buy Buttons --}}
                                @if($package['is_installed'] ?? false)
                                    <button class="btn btn-sm btn-success" disabled>
                                        <i class="fa fa-check me-1"></i>
                                        Installed
                                    </button>
                                @elseif($package['is_purchased'] ?? false)
                                    <button
                                        wire:click="installPackage('{{ $package['code'] }}')"
                                        class="tipowerup-installer__btn-install btn btn-sm"
                                    >
                                        <i class="fa fa-download me-1"></i>
                                        {{ lang('tipowerup.installer::default.marketplace_install') }}
                                    </button>
                                @else
                                    <button
                                        wire:click="buyOnPowerUp('{{ $package['code'] }}')"
                                        @click="window.open('https://tipowerup.com/packages/{{ $package['code'] }}', '_blank')"
                                        class="tipowerup-installer__btn-buy btn btn-sm"
                                    >
                                        <i class="fa fa-shopping-cart me-1"></i>
                                        {{ lang('tipowerup.installer::default.marketplace_buy') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($totalPages > 1)
                <nav class="mt-4" aria-label="Marketplace pagination">
                    <ul class="pagination justify-content-center">
                        {{-- Previous Button --}}
                        <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                            <button
                                wire:click="goToPage({{ $currentPage - 1 }})"
                                class="page-link"
                                {{ $currentPage <= 1 ? 'disabled' : '' }}
                            >
                                <i class="fa fa-chevron-left me-1"></i>
                                Previous
                            </button>
                        </li>

                        {{-- Page Numbers --}}
                        @php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                        @endphp

                        @if($startPage > 1)
                            <li class="page-item">
                                <button wire:click="goToPage(1)" class="page-link">1</button>
                            </li>
                            @if($startPage > 2)
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            @endif
                        @endif

                        @for($i = $startPage; $i <= $endPage; $i++)
                            <li class="page-item {{ $currentPage === $i ? 'active' : '' }}">
                                <button wire:click="goToPage({{ $i }})" class="page-link">
                                    {{ $i }}
                                </button>
                            </li>
                        @endfor

                        @if($endPage < $totalPages)
                            @if($endPage < $totalPages - 1)
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            @endif
                            <li class="page-item">
                                <button wire:click="goToPage({{ $totalPages }})" class="page-link">{{ $totalPages }}</button>
                            </li>
                        @endif

                        {{-- Next Button --}}
                        <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                            <button
                                wire:click="goToPage({{ $currentPage + 1 }})"
                                class="page-link"
                                {{ $currentPage >= $totalPages ? 'disabled' : '' }}
                            >
                                Next
                                <i class="fa fa-chevron-right ms-1"></i>
                            </button>
                        </li>
                    </ul>
                </nav>
            @endif
        @endif
    @endif
</div>
