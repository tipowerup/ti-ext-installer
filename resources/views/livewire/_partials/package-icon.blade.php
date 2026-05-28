@php
    $variant = $variant ?? 'card';
    $icon = is_array($icon) ? $icon : [];
    $bg = $icon['background_color'] ?? null;
    $type = $type ?? 'extension';
    $baseClass = $variant === 'list' ? 'tipowerup-installer__list-icon' : 'tipowerup-installer__package-icon';
    $typeClass = $baseClass.'--'.$type;
    $bgStyle = $bg ? 'background: '.$bg.';' : '';
@endphp
@if(!empty($icon['url']))
    <div class="{{ $baseClass }} {{ $typeClass }}" @if($bgStyle) style="{{ $bgStyle }}" @endif>
        <img src="{{ $icon['url'] }}" alt="{{ $name }}" class="tipowerup-installer__package-icon-img" />
    </div>
@elseif(!empty($icon['class']))
    <div class="{{ $baseClass }} {{ $typeClass }}" style="{{ $bgStyle }}{{ isset($icon['color']) ? ' color: '.$icon['color'].';' : '' }}">
        <i class="{{ $icon['class'] }}"></i>
    </div>
@elseif($type === 'bundle')
    <div class="{{ $baseClass }} {{ $typeClass }}" @if($bgStyle) style="{{ $bgStyle }}" @endif>
        <i class="fa fa-box"></i>
    </div>
@elseif($type === 'theme')
    <div class="{{ $baseClass }} {{ $typeClass }}" @if($bgStyle) style="{{ $bgStyle }}" @endif>
        <i class="fa fa-paint-brush"></i>
    </div>
@elseif($type === 'extension')
    <div class="{{ $baseClass }} {{ $typeClass }}" @if($bgStyle) style="{{ $bgStyle }}" @endif>
        <i class="fa fa-puzzle-piece"></i>
    </div>
@else
    <div class="{{ $baseClass }} {{ $typeClass }}" @if($bgStyle) style="{{ $bgStyle }}" @endif>
        {{ strtoupper(substr($name, 0, 2)) }}
    </div>
@endif
