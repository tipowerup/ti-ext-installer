@php
    $bg = is_array($icon) ? ($icon['background_color'] ?? null) : null;
    $typeColors = ['extension' => '#3B82F6', 'theme' => '#F97316', 'bundle' => '#8B5CF6'];
    $typeBg = $typeColors[$type ?? 'extension'] ?? '#3B82F6';
@endphp
@if(is_array($icon) && !empty($icon['url']))
    <div class="tipowerup-installer__list-icon" @if($bg) style="background: {{ $bg }};" @endif>
        <img
            src="{{ $icon['url'] }}"
            alt="{{ $name }}"
            class="tipowerup-installer__package-icon-img"
        />
    </div>
@elseif(is_array($icon) && !empty($icon['class']))
    <div class="tipowerup-installer__list-icon tipowerup-installer__list-icon--custom" style="background: {{ $bg ?? $typeBg }}; color: {{ $icon['color'] ?? '#fff' }};">
        <i class="{{ $icon['class'] }}"></i>
    </div>
@elseif(is_string($icon) && $icon !== '')
    <div class="tipowerup-installer__list-icon" style="background: {{ $typeBg }}; color: white; font-size: 1rem;">
        <i class="fa fa-{{ ltrim($icon, 'fa-') }}"></i>
    </div>
@else
    <div class="tipowerup-installer__list-icon tipowerup-installer__list-icon--text" style="background: {{ $typeBg }};">
        {{ strtoupper(substr($name, 0, 2)) }}
    </div>
@endif
