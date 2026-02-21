<div class="tipowerup-installer__marketplace">
    {{-- Error Message --}}
    @if($errorMessage)
        <div class="alert alert-danger d-flex align-items-start mb-3" role="alert">
            <i class="fa fa-exclamation-circle me-2 mt-1 flex-shrink-0"></i>
            <div class="flex-grow-1">
                @if($isKeyError)
                    <strong>{{ lang('tipowerup.installer::default.error_powerup_key_invalid_alert') }}</strong>
                    <p class="mb-0 mt-1 small">{{ $errorMessage }}</p>
                @else
                    {{ $errorMessage }}
                @endif
            </div>
            <button wire:click="$set('errorMessage', null)" type="button" class="btn-close btn-close-sm flex-shrink-0 ms-2"></button>
        </div>
    @endif

    {{-- Toolbar: Search + Filters + View Toggle --}}
    <div class="tipowerup-installer__search-bar">
        <input
            type="text"
            wire:model.live.debounce.500ms="searchQuery"
            class="form-control tipowerup-installer__search-input"
            placeholder="{{ lang('tipowerup.installer::default.marketplace_search') }}"
        />
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        {{-- Filter Buttons --}}
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="tipowerup-installer__filters">
                <button
                    wire:click="setFilter('all')"
                    wire:loading.attr="disabled"
                    wire:loading.class="tipowerup-installer__filter-btn--loading"
                    class="tipowerup-installer__filter-btn {{ $filterType === 'all' ? 'tipowerup-installer__filter-btn--active' : '' }}"
                >
                    {{ lang('tipowerup.installer::default.marketplace_filter_all') }}
                </button>
                <button
                    wire:click="setFilter('extension')"
                    wire:loading.attr="disabled"
                    wire:loading.class="tipowerup-installer__filter-btn--loading"
                    class="tipowerup-installer__filter-btn {{ $filterType === 'extension' ? 'tipowerup-installer__filter-btn--active' : '' }}"
                >
                    <i class="fa fa-puzzle-piece me-1"></i>
                    {{ lang('tipowerup.installer::default.marketplace_filter_extensions') }}
                </button>
                <button
                    wire:click="setFilter('theme')"
                    wire:loading.attr="disabled"
                    wire:loading.class="tipowerup-installer__filter-btn--loading"
                    class="tipowerup-installer__filter-btn {{ $filterType === 'theme' ? 'tipowerup-installer__filter-btn--active' : '' }}"
                >
                    <i class="fa fa-paint-brush me-1"></i>
                    {{ lang('tipowerup.installer::default.marketplace_filter_themes') }}
                </button>
                <button
                    wire:click="setFilter('bundle')"
                    wire:loading.attr="disabled"
                    wire:loading.class="tipowerup-installer__filter-btn--loading"
                    class="tipowerup-installer__filter-btn {{ $filterType === 'bundle' ? 'tipowerup-installer__filter-btn--active' : '' }}"
                >
                    <i class="fa fa-box me-1"></i>
                    {{ lang('tipowerup.installer::default.marketplace_filter_bundles') }}
                </button>
            </div>

            @if(count($selectedForBatch) > 0)
                <button wire:click="batchInstall" class="btn btn-success btn-sm">
                    <i class="fa fa-download me-1"></i>
                    {{ lang('tipowerup.installer::default.action_batch_install') }} ({{ count($selectedForBatch) }})
                </button>
            @endif
        </div>

        {{-- Refresh + View Mode Toggle --}}
        <div class="d-flex align-items-center gap-2">
            <button
                wire:click="refreshMarketplace"
                wire:loading.attr="disabled"
                wire:target="refreshMarketplace"
                class="btn btn-outline-secondary btn-sm"
                title="{{ lang('tipowerup.installer::default.marketplace_refresh') }}"
            >
                <span wire:loading.remove wire:target="refreshMarketplace">
                    <i class="fa fa-sync-alt"></i>
                </span>
                <span wire:loading wire:target="refreshMarketplace">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </span>
            </button>

        @if(!$isLoading && count($packages) > 0)
            <div class="tipowerup-installer__view-toggle">
                <button
                    wire:click="toggleViewMode"
                    class="tipowerup-installer__view-toggle-btn {{ $viewMode === 'grid' ? 'tipowerup-installer__view-toggle-btn--active' : '' }}"
                    title="{{ lang('tipowerup.installer::default.installed_view_grid') }}"
                >
                    <i class="fa fa-th"></i>
                </button>
                <button
                    wire:click="toggleViewMode"
                    class="tipowerup-installer__view-toggle-btn {{ $viewMode === 'list' ? 'tipowerup-installer__view-toggle-btn--active' : '' }}"
                    title="{{ lang('tipowerup.installer::default.installed_view_list') }}"
                >
                    <i class="fa fa-list"></i>
                </button>
            </div>
        @endif
        </div>
    </div>

    {{-- Loading Overlay (shows immediately on filter/search/page change) --}}
    <div wire:loading wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage" class="tipowerup-installer__loading-overlay">
        <div class="d-flex flex-column align-items-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">{{ lang('tipowerup.installer::default.marketplace_loading') }}</p>
        </div>
    </div>

    <div wire:loading.remove wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage">

    {{-- Loading State (initial load only) --}}
    @if($isLoading)
        <div class="tipowerup-installer__packages-grid">
            @for($i = 0; $i < 6; $i++)
                <div class="card tipowerup-installer__package-card">
                    <div class="card-body">
                        <div class="tipowerup-installer__package-header mb-2">
                            <div class="tipowerup-installer__skeleton" style="width: 44px; height: 44px; border-radius: 0.5rem; flex-shrink: 0;"></div>
                            <div class="tipowerup-installer__package-info ms-2">
                                <div class="tipowerup-installer__skeleton mb-1" style="height: 16px; width: 70%;"></div>
                                <div class="tipowerup-installer__skeleton" style="height: 13px; width: 40%;"></div>
                            </div>
                        </div>
                        <div class="tipowerup-installer__skeleton mb-2" style="height: 40px;"></div>
                        <div class="tipowerup-installer__skeleton" style="height: 32px;"></div>
                    </div>
                </div>
            @endfor
        </div>

    {{-- Empty State --}}
    @elseif(count($packages) === 0)
        <div class="tipowerup-installer__empty-state">
            <div class="tipowerup-installer__empty-icon">
                <i class="fa fa-shopping-bag"></i>
            </div>
            <h6 class="tipowerup-installer__empty-title">{{ lang('tipowerup.installer::default.marketplace_empty_title') }}</h6>
            <p class="tipowerup-installer__empty-message">
                @if($searchQuery !== '')
                    {{ lang('tipowerup.installer::default.marketplace_empty_no_match', ['query' => $searchQuery]) }}
                @else
                    {{ lang('tipowerup.installer::default.marketplace_empty_generic') }}
                @endif
            </p>
        </div>

    {{-- Grid View --}}
    @elseif($viewMode === 'grid')
        <div class="tipowerup-installer__packages-grid tipowerup-installer__tab-content">
            @foreach($packages as $package)
                @php
                    $isBundle = ($package['type'] ?? '') === 'bundle';
                    $icon = $package['icon'] ?? null;
                    $cardClasses = 'card tipowerup-installer__package-card tipowerup-installer__marketplace-card';
                    if ($isBundle) {
                        $cardClasses .= ' tipowerup-installer__package-card--bundle';
                    }
                @endphp
                <div wire:key="package-{{ $package['code'] }}" class="{{ $cardClasses }}">
                    <div class="card-body">
                        {{-- Package Header: Icon + Name + Type --}}
                        <div class="tipowerup-installer__package-header mb-2">
                            {{-- Icon rendering --}}
                            @php
                                $defaultColors = ['extension' => '#3B82F6', 'theme' => '#F97316', 'bundle' => '#8B5CF6'];
                                $defaultColor = $defaultColors[$package['type'] ?? 'extension'] ?? '#3B82F6';
                            @endphp
                            @if($isBundle)
                                <div class="tipowerup-installer__package-icon" style="background: #8B5CF6;">
                                    <i class="fa fa-box"></i>
                                </div>
                            @elseif(is_array($icon) && !empty($icon['url']))
                                <img
                                    src="{{ $icon['url'] }}"
                                    alt="{{ $package['name'] }}"
                                    class="tipowerup-installer__package-icon"
                                    style="object-fit: cover; border-radius: 0.5rem;"
                                />
                            @elseif(is_array($icon) && !empty($icon['class']))
                                <div
                                    class="tipowerup-installer__package-icon"
                                    style="background: {{ $icon['background_color'] ?? $defaultColor }}; color: {{ $icon['color'] ?? '#fff' }};"
                                >
                                    <i class="{{ $icon['class'] }}"></i>
                                </div>
                            @else
                                <div class="tipowerup-installer__package-icon" style="background: {{ $defaultColor }};">
                                    {{ strtoupper(substr($package['name'], 0, 2)) }}
                                </div>
                            @endif

                            <div class="tipowerup-installer__package-info flex-grow-1">
                                <div class="tipowerup-installer__package-name" title="{{ $package['name'] }}">
                                    {{ $package['name'] }}
                                </div>
                                <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                    <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] === 'theme' ? 'theme' : ($isBundle ? 'bundle' : 'extension') }}">
                                        {{ ucfirst($package['type']) }}
                                    </span>
                                    @if(isset($package['version']))
                                        <span class="text-muted" style="font-size: 0.75rem;">v{{ $package['version'] }}</span>
                                    @endif
                                </div>
                            </div>

                            @if(($package['purchased'] ?? false) && !($package['is_installed'] ?? false))
                                <input
                                    type="checkbox"
                                    class="form-check-input mt-0 flex-shrink-0"
                                    id="select-{{ $package['code'] }}"
                                    wire:click="toggleBatchSelect('{{ $package['code'] }}')"
                                    {{ in_array($package['code'], $selectedForBatch) ? 'checked' : '' }}
                                >
                            @endif
                        </div>

                        {{-- Description --}}
                        <p class="tipowerup-installer__package-description">
                            {{ \Illuminate\Support\Str::limit($package['description'] ?? '', 90) }}
                        </p>

                        {{-- Bundle: products count --}}
                        @if($isBundle && !empty($package['products_count']) && $package['products_count'] > 0)
                            <p class="text-muted mb-2" style="font-size: 0.75rem;">
                                <i class="fa fa-layer-group me-1"></i>
                                {{ $package['products_count'] }} {{ $package['products_count'] === 1 ? 'product' : 'products' }} included
                            </p>
                        @endif

                        {{-- Package Footer with Price + Actions --}}
                        <div class="tipowerup-installer__package-footer">
                            {{-- Price display --}}
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                @if(isset($package['price']) && $package['price'] > 0)
                                    <span class="tipowerup-installer__price-current">
                                        {{ $package['price_formatted'] ?? ('$' . number_format($package['price'], 2)) }}
                                    </span>
                                    @if(!empty($package['original_price']) && $package['original_price'] > $package['price'])
                                        <span class="tipowerup-installer__price-original">
                                            {{ $package['original_price_formatted'] ?? ('$' . number_format($package['original_price'], 2)) }}
                                        </span>
                                    @endif
                                    @if(!empty($package['discount_percentage']))
                                        <span class="tipowerup-installer__bundle-discount-badge">
                                            {{ $package['discount_percentage'] }}% OFF
                                        </span>
                                    @endif
                                @else
                                    <span class="fw-semibold text-success" style="font-size: 0.875rem;">{{ lang('tipowerup.installer::default.marketplace_free') }}</span>
                                @endif
                            </div>

                            {{-- Action Buttons --}}
                            <div class="tipowerup-installer__marketplace-actions">
                                <button
                                    wire:click="viewDetail('{{ $package['code'] }}')"
                                    class="btn btn-sm btn-outline-secondary"
                                    title="View Details"
                                >
                                    <i class="fa fa-eye"></i>
                                </button>

                                @if($package['is_installed'] ?? false)
                                    <button class="btn btn-sm btn-success" disabled>
                                        <i class="fa fa-check me-1"></i>Installed
                                    </button>
                                @elseif($package['purchased'] ?? false)
                                    <button
                                        wire:click="installPackage('{{ $package['code'] }}')"
                                        class="tipowerup-installer__btn-install btn btn-sm"
                                    >
                                        <i class="fa fa-download me-1"></i>
                                        {{ lang('tipowerup.installer::default.marketplace_install') }}
                                    </button>
                                @else
                                    <a
                                        href="{{ $package['url'] ?? '#' }}"
                                        target="_blank"
                                        rel="noopener noreferrer"

                                        class="tipowerup-installer__btn-buy btn btn-sm"
                                    >
                                        <i class="fa fa-shopping-cart me-1"></i>
                                        {{ lang('tipowerup.installer::default.marketplace_buy') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    {{-- List View --}}
    @else
        <div class="tipowerup-installer__marketplace-list table-responsive tipowerup-installer__tab-content">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 48px;"></th>
                        <th>{{ lang('tipowerup.installer::default.marketplace_table_header_name') }}</th>
                        <th style="width: 90px;">Type</th>
                        <th style="width: 120px;">Price</th>
                        <th style="width: 80px;">Version</th>
                        <th style="width: 200px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($packages as $package)
                        @php
                            $isBundle = ($package['type'] ?? '') === 'bundle';
                            $icon = $package['icon'] ?? null;
                        @endphp
                        <tr wire:key="pkg-list-{{ $package['code'] }}">
                            {{-- Icon --}}
                            <td>
                                @php
                                    $defaultColors = ['extension' => '#3B82F6', 'theme' => '#F97316', 'bundle' => '#8B5CF6'];
                                    $defaultColor = $defaultColors[$package['type'] ?? 'extension'] ?? '#3B82F6';
                                @endphp
                                @if($isBundle)
                                    <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: #8B5CF6; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem;">
                                        <i class="fa fa-box"></i>
                                    </div>
                                @elseif(is_array($icon) && !empty($icon['url']))
                                    <img
                                        src="{{ $icon['url'] }}"
                                        alt="{{ $package['name'] }}"
                                        style="width: 36px; height: 36px; border-radius: 0.5rem; object-fit: cover;"
                                    />
                                @elseif(is_array($icon) && !empty($icon['class']))
                                    <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: {{ $icon['background_color'] ?? $defaultColor }}; color: {{ $icon['color'] ?? '#fff' }}; display: flex; align-items: center; justify-content: center; font-size: 0.875rem;">
                                        <i class="{{ $icon['class'] }}"></i>
                                    </div>
                                @else
                                    <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: {{ $defaultColor }}; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem; font-weight: 700;">
                                        {{ strtoupper(substr($package['name'], 0, 2)) }}
                                    </div>
                                @endif
                            </td>
                            {{-- Name & Description --}}
                            <td>
                                <div class="d-flex align-items-center gap-2" style="font-size: 0.875rem;">
                                    <span class="fw-semibold">{{ $package['name'] }}</span>
                                    @if(($package['purchased'] ?? false) && !($package['is_installed'] ?? false))
                                        <input
                                            type="checkbox"
                                            class="form-check-input mt-0 flex-shrink-0"
                                            id="list-select-{{ $package['code'] }}"
                                            wire:click="toggleBatchSelect('{{ $package['code'] }}')"
                                            {{ in_array($package['code'], $selectedForBatch) ? 'checked' : '' }}
                                        >
                                    @endif
                                </div>
                                @if(isset($package['description']))
                                    <div class="text-muted" style="font-size: 0.75rem; line-height: 1.3;">
                                        {{ \Illuminate\Support\Str::limit($package['description'], 70) }}
                                    </div>
                                @endif
                            </td>
                            {{-- Type --}}
                            <td>
                                <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] === 'theme' ? 'theme' : ($isBundle ? 'bundle' : 'extension') }}">
                                    {{ ucfirst($package['type']) }}
                                </span>
                            </td>
                            {{-- Price --}}
                            <td>
                                @if(isset($package['price']) && $package['price'] > 0)
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="fw-semibold" style="font-size: 0.875rem; color: var(--ti-primary);">
                                            {{ $package['price_formatted'] ?? ('$' . number_format($package['price'], 2)) }}
                                        </span>
                                        @if(!empty($package['original_price']) && $package['original_price'] > $package['price'])
                                            <span class="tipowerup-installer__price-original">
                                                {{ $package['original_price_formatted'] ?? ('$' . number_format($package['original_price'], 2)) }}
                                            </span>
                                        @endif
                                    </div>
                                    @if(!empty($package['discount_percentage']))
                                        <span class="tipowerup-installer__bundle-discount-badge mt-1 d-inline-flex">
                                            {{ $package['discount_percentage'] }}% OFF
                                        </span>
                                    @endif
                                @else
                                    <span class="fw-semibold text-success" style="font-size: 0.875rem;">Free</span>
                                @endif
                            </td>
                            {{-- Version --}}
                            <td>
                                <span class="text-muted small">{{ isset($package['version']) ? 'v'.$package['version'] : '—' }}</span>
                            </td>
                            {{-- Actions --}}
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1 align-items-center">
                                    <button
                                        wire:click="viewDetail('{{ $package['code'] }}')"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="View Details"
                                    >
                                        <i class="fa fa-eye"></i>
                                    </button>

                                    @if($package['is_installed'] ?? false)
                                        <button class="btn btn-sm btn-success" disabled>
                                            <i class="fa fa-check me-1"></i>Installed
                                        </button>
                                    @elseif($package['purchased'] ?? false)
                                        <button
                                            wire:click="installPackage('{{ $package['code'] }}')"
                                            class="tipowerup-installer__btn-install btn btn-sm"
                                        >
                                            <i class="fa fa-download me-1"></i>
                                            {{ lang('tipowerup.installer::default.marketplace_install') }}
                                        </button>
                                    @else
                                        <a
                                            href="{{ $package['url'] ?? '#' }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
    
                                            class="tipowerup-installer__btn-buy btn btn-sm"
                                        >
                                            <i class="fa fa-shopping-cart me-1"></i>
                                            {{ lang('tipowerup.installer::default.marketplace_buy') }}
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Pagination --}}
    @if(!$isLoading && $totalPages > 1)
        <nav class="mt-3" aria-label="Marketplace pagination">
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                    <button
                        wire:click="goToPage({{ $currentPage - 1 }})"
                        wire:loading.attr="disabled"
                        wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage"
                        class="page-link"
                        {{ $currentPage <= 1 ? 'disabled' : '' }}
                    >
                        <i class="fa fa-chevron-left"></i>
                    </button>
                </li>

                @php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                @endphp

                @if($startPage > 1)
                    <li class="page-item">
                        <button
                            wire:click="goToPage(1)"
                            wire:loading.attr="disabled"
                            wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage"
                            class="page-link"
                        >1</button>
                    </li>
                    @if($startPage > 2)
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    @endif
                @endif

                @for($i = $startPage; $i <= $endPage; $i++)
                    <li class="page-item {{ $currentPage === $i ? 'active' : '' }}">
                        <button
                            wire:click="goToPage({{ $i }})"
                            wire:loading.attr="disabled"
                            wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage"
                            class="page-link"
                        >{{ $i }}</button>
                    </li>
                @endfor

                @if($endPage < $totalPages)
                    @if($endPage < $totalPages - 1)
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    @endif
                    <li class="page-item">
                        <button
                            wire:click="goToPage({{ $totalPages }})"
                            wire:loading.attr="disabled"
                            wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage"
                            class="page-link"
                        >{{ $totalPages }}</button>
                    </li>
                @endif

                <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                    <button
                        wire:click="goToPage({{ $currentPage + 1 }})"
                        wire:loading.attr="disabled"
                        wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage"
                        class="page-link"
                        {{ $currentPage >= $totalPages ? 'disabled' : '' }}
                    >
                        <i class="fa fa-chevron-right"></i>
                    </button>
                </li>
            </ul>
        </nav>
    @endif

    </div>{{-- end wire:loading.remove wrapper --}}
</div>
