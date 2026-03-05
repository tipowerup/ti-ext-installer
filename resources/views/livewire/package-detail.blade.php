<div>
    @if($isLoading)
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted">{{ lang('tipowerup.installer::default.detail_loading') }}</p>
        </div>
    @elseif($errorMessage)
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="fa fa-exclamation-circle me-3 tipowerup-installer__icon-lg"></i>
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
        @php
            $icon = $packageData['icon'] ?? null;
            $defaultColors = ['extension' => '#3B82F6', 'theme' => '#F97316', 'bundle' => '#8B5CF6'];
            $defaultColor = $defaultColors[$packageData['type'] ?? 'extension'] ?? '#3B82F6';
        @endphp

        <div class="d-flex align-items-start mb-4">
            @if(is_array($icon) && !empty($icon['url']))
                <img src="{{ $icon['url'] }}" alt="{{ $packageData['name'] }}"
                     class="tipowerup-installer__detail-icon-img me-3">
            @elseif(is_array($icon) && !empty($icon['class']))
                <div class="me-3 d-flex align-items-center justify-content-center tipowerup-installer__detail-icon"
                     style="background: {{ $icon['background_color'] ?? $defaultColor }}; color: {{ $icon['color'] ?? '#fff' }}; font-size: 1.5rem;">
                    <i class="{{ $icon['class'] }}"></i>
                </div>
            @else
                <div class="me-3 d-flex align-items-center justify-content-center tipowerup-installer__detail-icon tipowerup-installer__detail-icon-text"
                     style="background: {{ $defaultColor }};">
                    {{ strtoupper(substr($packageData['name'], 0, 2)) }}
                </div>
            @endif

            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                    <h4 class="mb-0">{{ $packageData['name'] }}</h4>
                    @if(!($packageData['local'] ?? false))
                        @if($packageData['purchased'] ?? false)
                            <button wire:click="installPackage" type="button" class="btn btn-success btn-sm"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="installPackage">
                                    <i class="fa fa-download me-1"></i>
                                    {{ lang('tipowerup.installer::default.action_install') }}
                                </span>
                                <span wire:loading wire:target="installPackage">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Installing...
                                </span>
                            </button>
                        @else
                            <a href="{{ $packageData['url'] ?? '#' }}"
                               target="_blank"
                               class="btn btn-primary btn-sm">
                                <i class="fa fa-shopping-cart me-1"></i>
                                {{ lang('tipowerup.installer::default.marketplace_buy') }}
                            </a>
                        @endif
                    @endif
                </div>
                <p class="text-muted mb-2 small">
                    <span>{{ lang('tipowerup.installer::default.detail_author') }}: {{ $packageData['author'] }}</span>
                    <span class="mx-2">&middot;</span>
                    <span>{{ lang('tipowerup.installer::default.detail_version') }}: {{ $packageData['version'] }}</span>
                    @if($packageData['updated_at'])
                        <span class="mx-2">&middot;</span>
                        <span>{{ lang('tipowerup.installer::default.detail_last_updated') }}: {{ \Carbon\Carbon::parse($packageData['updated_at'])->format('M j, Y') }}</span>
                    @endif
                </p>
                <div class="d-flex gap-2 align-items-center">
                    <span class="tipowerup-installer__badge tipowerup-installer__badge--{{ $packageData['type'] === 'theme' ? 'theme' : ($packageData['type'] === 'bundle' ? 'bundle' : 'extension') }}">
                        {{ ucfirst($packageData['type']) }}
                    </span>
                    @if(!($packageData['local'] ?? false) && !($packageData['purchased'] ?? false))
                        @if(($packageData['price'] ?? 0) > 0)
                            <span class="fw-semibold tipowerup-installer__price-highlight">
                                {{ $packageData['price_formatted'] ?? ('$' . number_format($packageData['price'], 2)) }}
                            </span>
                        @else
                            <span class="fw-semibold text-success">{{ lang('tipowerup.installer::default.marketplace_free') }}</span>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button wire:click="switchDetailTab('description')" type="button"
                        class="nav-link {{ $activeDetailTab === 'description' ? 'active' : '' }}" role="tab">
                    {{ lang('tipowerup.installer::default.detail_description') }}
                </button>
            </li>
            @if(!empty($packageData['screenshots']) || !empty($packageData['cover_image']))
                <li class="nav-item" role="presentation">
                    <button wire:click="switchDetailTab('screenshots')" type="button"
                            class="nav-link {{ $activeDetailTab === 'screenshots' ? 'active' : '' }}" role="tab">
                        {{ lang('tipowerup.installer::default.detail_screenshots') }}
                    </button>
                </li>
            @endif
            @if(!empty($packageData['changelog']))
                <li class="nav-item" role="presentation">
                    <button wire:click="switchDetailTab('changelog')" type="button"
                            class="nav-link {{ $activeDetailTab === 'changelog' ? 'active' : '' }}" role="tab">
                        {{ lang('tipowerup.installer::default.detail_changelog') }}
                    </button>
                </li>
            @endif
            @if(!empty($packageData['requirements']))
                <li class="nav-item" role="presentation">
                    <button wire:click="switchDetailTab('compatibility')" type="button"
                            class="nav-link {{ $activeDetailTab === 'compatibility' ? 'active' : '' }}" role="tab">
                        {{ lang('tipowerup.installer::default.detail_compatibility') }}
                    </button>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            @if($activeDetailTab === 'description')
                <div class="tab-pane fade show active">
                    @if(!empty($packageData['description_html']))
                        <div class="prose">
                            {!! $packageData['description_html'] !!}
                        </div>
                    @else
                        <p class="text-muted">{{ lang('tipowerup.installer::default.detail_no_description') }}</p>
                    @endif
                </div>
            @elseif($activeDetailTab === 'screenshots')
                @php
                    $allImages = collect();
                    if (!empty($packageData['cover_image'])) {
                        $allImages->push($packageData['cover_image']);
                    }
                    foreach ($packageData['screenshots'] ?? [] as $s) {
                        if ($s !== ($packageData['cover_image'] ?? null)) {
                            $allImages->push($s);
                        }
                    }
                @endphp
                <div class="tab-pane fade show active"
                     x-data="lightbox(@js($allImages->values()->all()))"
                     @keydown.escape.window="open && close()"
                     @keydown.left.window="open && prev()"
                     @keydown.right.window="open && next()">
                    @if($allImages->isNotEmpty())
                        <div class="tipowerup-installer__screenshot-grid">
                            @foreach($allImages as $index => $screenshot)
                                <img src="{{ $screenshot }}" alt="Screenshot {{ $index + 1 }}"
                                     class="tipowerup-installer__screenshot-grid-item"
                                     @click="show({{ $index }})"
                                     loading="lazy">
                            @endforeach
                        </div>

                        {{-- Lightbox Overlay --}}
                        <div x-show="open" x-cloak
                             x-transition:enter="tipowerup-installer__lightbox-enter"
                             x-transition:leave="tipowerup-installer__lightbox-leave"
                             class="tipowerup-installer__lightbox" @click.self="close()">

                            <button @click="close()" class="tipowerup-installer__lightbox-close" type="button" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </button>

                            <button x-show="images.length > 1" @click="prev()"
                                    class="tipowerup-installer__lightbox-nav tipowerup-installer__lightbox-nav--prev"
                                    type="button" aria-label="Previous">
                                <i class="fa fa-chevron-left"></i>
                            </button>

                            <img :src="images[current]" alt="Screenshot" class="tipowerup-installer__lightbox-image">

                            <button x-show="images.length > 1" @click="next()"
                                    class="tipowerup-installer__lightbox-nav tipowerup-installer__lightbox-nav--next"
                                    type="button" aria-label="Next">
                                <i class="fa fa-chevron-right"></i>
                            </button>

                            <div x-show="images.length > 1" class="tipowerup-installer__lightbox-counter">
                                <span x-text="(current + 1) + ' / ' + images.length"></span>
                            </div>
                        </div>
                    @else
                        <p class="text-muted">{{ lang('tipowerup.installer::default.detail_no_screenshots') }}</p>
                    @endif
                </div>
            @elseif($activeDetailTab === 'changelog')
                <div class="tab-pane fade show active">
                    @if(!empty($packageData['changelog']))
                        @foreach($packageData['changelog'] as $entry)
                            <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-primary">{{ $entry['version'] ?? '' }}</span>
                                    @if(!empty($entry['date']))
                                        <small class="text-muted">{{ $entry['date'] }}</small>
                                    @endif
                                </div>
                                @if(!empty($entry['notes']))
                                    <div class="prose">
                                        {!! strip_tags(\Illuminate\Support\Str::markdown($entry['notes']), '<p><h1><h2><h3><h4><h5><h6><ul><ol><li><a><strong><em><b><i><code><pre><blockquote><br><hr>') !!}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted">{{ lang('tipowerup.installer::default.detail_no_changelog') }}</p>
                    @endif
                </div>
            @elseif($activeDetailTab === 'compatibility')
                <div class="tab-pane fade show active">
                    @if(!empty($packageData['requirements']))
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ lang('tipowerup.installer::default.detail_table_header_package') }}</th>
                                    <th>{{ lang('tipowerup.installer::default.detail_table_header_version') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($packageData['requirements'] as $pkg => $version)
                                    <tr>
                                        <td><code>{{ $pkg }}</code></td>
                                        <td>{{ $version }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted">{{ lang('tipowerup.installer::default.detail_no_requirements') }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
