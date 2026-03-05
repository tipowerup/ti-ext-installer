<div class="tipowerup-installer__marketplace">
    @if($errorMessage)
        <div class="alert alert-danger d-flex align-items-start mb-3" role="alert">
            <i class="fa fa-exclamation-circle me-2 mt-1 flex-shrink-0"></i>
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

    <div class="tipowerup-installer__search-bar">
        <input
            type="text"
            wire:model.live.debounce.500ms="searchQuery"
            class="form-control tipowerup-installer__search-input"
            placeholder="{{ lang('tipowerup.installer::default.marketplace_search') }}"
        />
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
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

        </div>

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

    <div wire:loading wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage" class="tipowerup-installer__loading-overlay">
        <div class="d-flex flex-column align-items-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mb-0 tipowerup-installer__text-sm">{{ lang('tipowerup.installer::default.marketplace_loading') }}</p>
        </div>
    </div>

    <div wire:loading.remove wire:target="setFilter, loadMarketplace, refreshMarketplace, goToPage">

    @if($isLoading)
        <div class="tipowerup-installer__packages-grid">
            @for($i = 0; $i < 6; $i++)
                <div class="card tipowerup-installer__package-card">
                    <div class="card-body">
                        <div class="tipowerup-installer__package-header mb-2">
                            <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--icon"></div>
                            <div class="tipowerup-installer__package-info ms-2">
                                <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--text-lg mb-1" style="width: 70%;"></div>
                                <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--text-sm" style="width: 40%;"></div>
                            </div>
                        </div>
                        <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--block mb-2"></div>
                        <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--btn"></div>
                    </div>
                </div>
            @endfor
        </div>

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
                        <div class="tipowerup-installer__package-header mb-2">
                            @php
                                $defaultColors = ['extension' => '#3B82F6', 'theme' => '#F97316', 'bundle' => '#8B5CF6'];
                                $defaultColor = $defaultColors[$package['type'] ?? 'extension'] ?? '#3B82F6';
                            @endphp
                            @if($isBundle)
                                <div class="tipowerup-installer__package-icon tipowerup-installer__package-icon--bundle">
                                    <i class="fa fa-box"></i>
                                </div>
                            @elseif(is_array($icon) && !empty($icon['url']))
                                <img
                                    src="{{ $icon['url'] }}"
                                    alt="{{ $package['name'] }}"
                                    class="tipowerup-installer__package-icon tipowerup-installer__package-icon--img"
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
                                        <span class="text-muted tipowerup-installer__text-xs">v{{ $package['version'] }}</span>
                                    @endif
                                </div>
                            </div>

                        </div>

                        <p class="tipowerup-installer__package-description">
                            {{ \Illuminate\Support\Str::limit($package['description'] ?? '', 90) }}
                        </p>

                        @if($isBundle && !empty($package['products_count']) && $package['products_count'] > 0)
                            <p class="text-muted mb-2 tipowerup-installer__text-xs">
                                <i class="fa fa-layer-group me-1"></i>
                                {{ $package['products_count'] }} {{ $package['products_count'] === 1 ? 'product' : 'products' }} included
                            </p>
                        @endif

                        <div class="tipowerup-installer__package-footer">
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
                                    <span class="fw-semibold text-success tipowerup-installer__text-sm">{{ lang('tipowerup.installer::default.marketplace_free') }}</span>
                                @endif
                            </div>

                            <div class="tipowerup-installer__marketplace-actions">
                                <button
                                    wire:click="viewDetail('{{ $package['code'] }}')"
                                    class="btn btn-sm btn-outline-secondary"
                                    title="View Details"
                                >
                                    <i class="fa fa-eye"></i>
                                </button>

                                @if(isset($package['price']) && $package['price'] > 0)
                                    <a
                                        href="{{ $package['url'] ?? '#' }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="tipowerup-installer__btn-buy btn btn-sm"
                                    >
                                        <i class="fa fa-shopping-cart me-1"></i>
                                        {{ lang('tipowerup.installer::default.marketplace_buy') }}
                                    </a>
                                @else
                                    <button
                                        wire:click="acquireFreeProduct('{{ $package['code'] }}', '{{ addslashes($package['name'] ?? '') }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="acquireFreeProduct"
                                        class="btn btn-sm btn-success"
                                    >
                                        <span wire:loading.remove wire:target="acquireFreeProduct">
                                            <i class="fa fa-plus me-1"></i>
                                            {{ lang('tipowerup.installer::default.marketplace_get') }}
                                        </span>
                                        <span wire:loading wire:target="acquireFreeProduct">
                                            <i class="fa fa-spinner fa-spin me-1"></i>
                                        </span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    @else
        <div class="tipowerup-installer__marketplace-list table-responsive tipowerup-installer__tab-content">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="tipowerup-installer__table-col--icon"></th>
                        <th>{{ lang('tipowerup.installer::default.marketplace_table_header_name') }}</th>
                        <th class="tipowerup-installer__table-col--type">Type</th>
                        <th class="tipowerup-installer__table-col--price">Price</th>
                        <th class="tipowerup-installer__table-col--version">Version</th>
                        <th class="tipowerup-installer__table-col--actions text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($packages as $package)
                        @php
                            $isBundle = ($package['type'] ?? '') === 'bundle';
                            $icon = $package['icon'] ?? null;
                        @endphp
                        <tr wire:key="pkg-list-{{ $package['code'] }}">
                            <td>
                                @php
                                    $defaultColors = ['extension' => '#3B82F6', 'theme' => '#F97316', 'bundle' => '#8B5CF6'];
                                    $defaultColor = $defaultColors[$package['type'] ?? 'extension'] ?? '#3B82F6';
                                @endphp
                                @if($isBundle)
                                    <div class="tipowerup-installer__list-icon tipowerup-installer__list-icon--bundle">
                                        <i class="fa fa-box"></i>
                                    </div>
                                @elseif(is_array($icon) && !empty($icon['url']))
                                    <img
                                        src="{{ $icon['url'] }}"
                                        alt="{{ $package['name'] }}"
                                        class="tipowerup-installer__list-icon tipowerup-installer__list-icon--img"
                                    />
                                @elseif(is_array($icon) && !empty($icon['class']))
                                    <div class="tipowerup-installer__list-icon tipowerup-installer__list-icon--custom" style="background: {{ $icon['background_color'] ?? $defaultColor }}; color: {{ $icon['color'] ?? '#fff' }};">
                                        <i class="{{ $icon['class'] }}"></i>
                                    </div>
                                @else
                                    <div class="tipowerup-installer__list-icon tipowerup-installer__list-icon--text" style="background: {{ $defaultColor }};">
                                        {{ strtoupper(substr($package['name'], 0, 2)) }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2 tipowerup-installer__text-sm">
                                    <span class="fw-semibold">{{ $package['name'] }}</span>
                                </div>
                                @if(isset($package['description']))
                                    <div class="text-muted tipowerup-installer__list-description">
                                        {{ \Illuminate\Support\Str::limit($package['description'], 70) }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $package['type'] === 'theme' ? 'theme' : ($isBundle ? 'bundle' : 'extension') }}">
                                    {{ ucfirst($package['type']) }}
                                </span>
                            </td>
                            <td>
                                @if(isset($package['price']) && $package['price'] > 0)
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="fw-semibold tipowerup-installer__list-price">
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
                                    <span class="fw-semibold text-success tipowerup-installer__text-sm">Free</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted small">{{ isset($package['version']) ? 'v'.$package['version'] : '—' }}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1 align-items-center">
                                    <button
                                        wire:click="viewDetail('{{ $package['code'] }}')"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="View Details"
                                    >
                                        <i class="fa fa-eye"></i>
                                    </button>

                                    @if(isset($package['price']) && $package['price'] > 0)
                                        <a
                                            href="{{ $package['url'] ?? '#' }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="tipowerup-installer__btn-buy btn btn-sm"
                                        >
                                            <i class="fa fa-shopping-cart me-1"></i>
                                            {{ lang('tipowerup.installer::default.marketplace_buy') }}
                                        </a>
                                    @else
                                        <button
                                            wire:click="acquireFreeProduct('{{ $package['code'] }}', '{{ addslashes($package['name'] ?? '') }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="acquireFreeProduct"
                                            class="btn btn-sm btn-success"
                                        >
                                            <span wire:loading.remove wire:target="acquireFreeProduct">
                                                <i class="fa fa-plus me-1"></i>
                                                {{ lang('tipowerup.installer::default.marketplace_get') }}
                                            </span>
                                            <span wire:loading wire:target="acquireFreeProduct">
                                                <i class="fa fa-spinner fa-spin me-1"></i>
                                            </span>
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
