{{-- Package icon (40px, used in list/table views) --}}
@php
    $bg = is_array($icon) ? ($icon['background_color'] ?? null) : null;
    $typeColors = ['extension' => '#3B82F6', 'theme' => '#F97316', 'bundle' => '#8B5CF6'];
    $typeBg = $typeColors[$type ?? 'extension'] ?? '#3B82F6';
@endphp
@if(is_array($icon) && !empty($icon['url']))
    <div style="width: 40px; height: 40px; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;{{ $bg ? ' background: '.$bg.';' : '' }}">
        <img
            src="{{ $icon['url'] }}"
            alt="{{ $name }}"
            style="width: 100%; height: 100%; object-fit: contain;"
        />
    </div>
@elseif(is_array($icon) && !empty($icon['class']))
    <div style="width: 40px; height: 40px; border-radius: 0.5rem; background: {{ $bg ?? $typeBg }}; color: {{ $icon['color'] ?? '#fff' }}; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
        <i class="{{ $icon['class'] }}"></i>
    </div>
@elseif(is_string($icon) && $icon !== '')
    <div style="width: 40px; height: 40px; border-radius: 0.5rem; background: {{ $typeBg }}; display: flex; align-items: center; justify-content: center; color: white; font-size: 1rem;">
        <i class="fa fa-{{ ltrim($icon, 'fa-') }}"></i>
    </div>
@else
    <div style="width: 40px; height: 40px; border-radius: 0.5rem; background: {{ $typeBg }}; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem; font-weight: 700;">
        {{ strtoupper(substr($name, 0, 2)) }}
    </div>
@endif
