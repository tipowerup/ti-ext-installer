@php
    $size = $size ?? '44px';
    $bg = is_array($icon) ? ($icon['background_color'] ?? null) : null;
    $typeColors = ['extension' => '#3B82F6', 'theme' => '#F97316', 'bundle' => '#8B5CF6'];
    $typeBg = $typeColors[$type ?? 'extension'] ?? '#3B82F6';
@endphp
@if(is_array($icon) && !empty($icon['url']))
    <div
        class="tipowerup-installer__package-icon"
        style="width: {{ $size }}; height: {{ $size }};{{ $bg ? ' background: '.$bg.';' : '' }}"
    >
        <img
            src="{{ $icon['url'] }}"
            alt="{{ $name }}"
            class="tipowerup-installer__package-icon-img"
        />
    </div>
@elseif(is_array($icon) && !empty($icon['class']))
    <div
        class="tipowerup-installer__package-icon"
        style="width: {{ $size }}; height: {{ $size }}; background: {{ $bg ?? $typeBg }}; color: {{ $icon['color'] ?? '#fff' }};"
    >
        <i class="{{ $icon['class'] }}"></i>
    </div>
@elseif(is_string($icon) && $icon !== '')
    <div class="tipowerup-installer__package-icon" style="width: {{ $size }}; height: {{ $size }}; background: {{ $typeBg }}; color: #fff;">
        <i class="fa fa-{{ ltrim($icon, 'fa-') }}"></i>
    </div>
@else
    <div class="tipowerup-installer__package-icon" style="width: {{ $size }}; height: {{ $size }}; background: {{ $typeBg }}; color: #fff;">
        {{ strtoupper(substr($name, 0, 2)) }}
    </div>
@endif
